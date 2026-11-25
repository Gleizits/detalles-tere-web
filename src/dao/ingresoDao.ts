import { query, queryOne, withTransaction, type DbClient } from '../db';
import { BusinessError } from '../http';
import { decimal, integer, str, type Row } from './util';

const SQL_INSERTAR =
  'INSERT INTO dbo.INGRESO (ID_PROVEEDOR, ID_USUARIO, FECHA_INGRESO) ' +
  'OUTPUT INSERTED.ID_INGRESO VALUES (?, ?, ?)';

// TOP con parámetro no se puede preparar en SQL Server: el límite se inyecta
// como entero validado en listarRecientes(), como en CajaDAO.
const SQL_LISTAR_RECIENTES =
  'SELECT i.FECHA_INGRESO, p.NOMBRE AS PROVEEDOR, ' +
  'COALESCE(pr.NOMBRE, m.NOMBRE, a.NOMBRE) AS ARTICULO, ' +
  'd.CANTIDAD, d.PRECIO_COMPRA_UNITARIO, d.SUBTOTAL ' +
  'FROM dbo.DETALLE_INGRESO d ' +
  'JOIN dbo.INGRESO i ON i.ID_INGRESO = d.ID_INGRESO ' +
  'JOIN dbo.PROVEEDOR p ON p.ID_PROVEEDOR = i.ID_PROVEEDOR ' +
  'JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = d.ID_INVENTARIO ' +
  'LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO ' +
  'LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL ' +
  'LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE ' +
  'ORDER BY i.FECHA_INGRESO DESC, d.ID_DETALLE_INGRESO DESC';

const SQL_LINEA_ACTIVA =
  'SELECT 1 FROM dbo.INVENTARIO inv ' +
  'LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO ' +
  'LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL ' +
  'LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE ' +
  'WHERE inv.ID_INVENTARIO = ? ' +
  'AND COALESCE(pr.ESTADO, m.ESTADO, a.ESTADO) = 1';

const SQL_INSERTAR_DETALLE =
  'INSERT INTO dbo.DETALLE_INGRESO ' +
  '(ID_INGRESO, ID_INVENTARIO, CANTIDAD, PRECIO_COMPRA_UNITARIO, SUBTOTAL, OBSERVACIONES) ' +
  'VALUES (?, ?, ?, ?, ?, ?)';

const SQL_ACTUALIZAR_STOCK =
  'UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT + ? WHERE ID_INVENTARIO = ?';

// %1$s = tabla entidad, %2$s = clave (valores fijos del propio DAO).
const SQL_ACTUALIZAR_COSTO =
  'UPDATE entidad SET PRECIO_COMPRA = ? ' +
  'FROM dbo.%1$s entidad ' +
  'JOIN dbo.INVENTARIO inv ON inv.%2$s = entidad.%2$s ' +
  'WHERE inv.ID_INVENTARIO = ?';

const SQL_MOVIMIENTO =
  "INSERT INTO dbo.MOVIMIENTO_INVENTARIO " +
  "(ID_USUARIO, ID_INVENTARIO, FECHA_MOVIMIENTO, TIPO_MOVIMIENTO, CANTIDAD) " +
  "VALUES (?, ?, SYSUTCDATETIME(), 'ENTRADA', ?)";

/** Línea de captura de un ingreso (IngresoLinea / DetalleIngreso del PHP). */
export interface IngresoLinea {
  idInventario: number;
  cantidad: number;
  precioCompraUnitario: number | null;
  observaciones?: string | null;
  idDetalleIngreso?: number;
  idIngreso?: number;
  nombre?: string;
  nombreInventario?: string;
  subtotal?: number | null;
}

/** Fila devuelta por listarRecientes(). */
export interface IngresoReciente {
  nombre: string;
  cantidad: number;
  precioCompraUnitario: number | null;
  subtotal: number | null;
}

function round2(value: number): number {
  return Math.round((value + Number.EPSILON) * 100) / 100;
}

function nullableDecimal(row: Row, key: string): number | null {
  const value = row[key];
  return value === null || value === undefined ? null : decimal(row, key);
}

/** Registra ingresos completos en una transacción: cabecera, detalle, stock,
 * costo vigente y movimiento de entrada. */
export class IngresoDAO {
  constructor(private readonly client?: DbClient) {}

  async registrar(
    idProveedor: number,
    idUsuario: number,
    fecha: string,
    lineas: readonly IngresoLinea[],
  ): Promise<void> {
    if (lineas.length === 0) {
      throw new BusinessError('Agrega al menos un artículo al ingreso.');
    }

    await this.enTransaccion(async (cn) => {
      const idIngreso = await this.insertarCabecera(cn, idProveedor, idUsuario, fecha);
      for (const linea of lineas) {
        await this.validarLinea(cn, linea);
        await this.insertarDetalle(cn, idIngreso, linea);
        await this.actualizarInventario(cn, linea);
        await this.registrarMovimiento(cn, idUsuario, linea);
      }
    });
  }

  async listarRecientes(limite: number): Promise<IngresoReciente[]> {
    const tope = Math.max(1, Math.min(100, limite));
    const sql = SQL_LISTAR_RECIENTES.replace(
      'SELECT i.FECHA_INGRESO',
      `SELECT TOP ${tope} i.FECHA_INGRESO`,
    );

    const rows = await query<Row>(sql, [], this.client);
    return rows.map((row) => ({
      nombre: `${str(row, 'PROVEEDOR')} · ${str(row, 'ARTICULO')}`,
      cantidad: integer(row, 'CANTIDAD'),
      precioCompraUnitario: nullableDecimal(row, 'PRECIO_COMPRA_UNITARIO'),
      subtotal: nullableDecimal(row, 'SUBTOTAL'),
    }));
  }

  // ------------------------------------------------------------------
  // Helpers internos
  // ------------------------------------------------------------------

  private async enTransaccion<T>(work: (cn: DbClient) => Promise<T>): Promise<T> {
    // Si el DAO se construyó con un cliente (por ejemplo una transacción
    // superior), se reutiliza; si no, se abre la transacción del PHP.
    return this.client ? work(this.client) : withTransaction(work);
  }

  private async insertarCabecera(
    cn: DbClient,
    idProveedor: number,
    idUsuario: number,
    fecha: string,
  ): Promise<number> {
    const row = await queryOne<Row>(SQL_INSERTAR, [idProveedor, idUsuario, fecha], cn);
    const id = row ? Number(row.ID_INGRESO) : Number.NaN;
    if (!Number.isFinite(id)) {
      throw new Error('No se pudo crear la cabecera del ingreso.');
    }
    return id;
  }

  private async validarLinea(cn: DbClient, linea: IngresoLinea): Promise<void> {
    const precio = linea.precioCompraUnitario;
    if (
      linea.cantidad <= 0 ||
      precio === null ||
      precio === undefined ||
      precio < 0 ||
      precio > 1_000_000
    ) {
      throw new BusinessError('Cantidad o precio de compra inválido.');
    }

    const row = await queryOne<Row>(SQL_LINEA_ACTIVA, [linea.idInventario], cn);
    if (!row) {
      throw new BusinessError('El artículo seleccionado no está activo.');
    }
  }

  private async insertarDetalle(
    cn: DbClient,
    idIngreso: number,
    linea: IngresoLinea,
  ): Promise<void> {
    const precio = linea.precioCompraUnitario ?? 0;
    await query(
      SQL_INSERTAR_DETALLE,
      [
        idIngreso,
        linea.idInventario,
        linea.cantidad,
        precio,
        round2(precio * linea.cantidad),
        linea.observaciones ?? null,
      ],
      cn,
    );
  }

  private async actualizarInventario(cn: DbClient, linea: IngresoLinea): Promise<void> {
    await query(SQL_ACTUALIZAR_STOCK, [linea.cantidad, linea.idInventario], cn);
    // El helper `query` no expone rowCount: comprobamos que la fila existe
    // para reproducir el control `rowCount() !== 1` del DAO de PHP.
    const fila = await queryOne<Row>(
      'SELECT 1 AS OK FROM dbo.INVENTARIO WHERE ID_INVENTARIO = ?',
      [linea.idInventario],
      cn,
    );
    if (!fila) {
      throw new Error('No se pudo actualizar el stock.');
    }

    await this.actualizarCosto(cn, 'PRODUCTO', 'ID_PRODUCTO', linea);
    await this.actualizarCosto(cn, 'MATERIAL', 'ID_MATERIAL', linea);
    await this.actualizarCosto(cn, 'ARREGLO', 'ID_DETALLE', linea);
  }

  private async actualizarCosto(
    cn: DbClient,
    tabla: string,
    clave: string,
    linea: IngresoLinea,
  ): Promise<void> {
    const sql = SQL_ACTUALIZAR_COSTO.replace('%1$s', tabla).replace(/%2\$s/g, clave);
    await query(sql, [linea.precioCompraUnitario ?? 0, linea.idInventario], cn);
  }

  private async registrarMovimiento(
    cn: DbClient,
    idUsuario: number,
    linea: IngresoLinea,
  ): Promise<void> {
    await query(SQL_MOVIMIENTO, [idUsuario, linea.idInventario, linea.cantidad], cn);
  }
}
