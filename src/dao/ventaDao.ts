import { insertId, query, queryOne, withTransaction, type DbClient } from '../db';
import { BusinessError } from '../http';
import { CreditoClienteDAO } from './creditoClienteDao';
import { decimal, integer, str, type Row } from './util';

const SQL_INSERTAR_VENTA =
  'INSERT INTO dbo.VENTA (ID_CAJA, TOTAL, TIPO_PAGO, ESTADO, FECHA_VENTA) ' +
  'OUTPUT INSERTED.ID_VENTA VALUES (?, ?, ?, ?, SYSUTCDATETIME())';

const SQL_INSERTAR_DETALLE =
  'INSERT INTO dbo.DETALLE_VENTA ' +
  '(ID_INVENTARIO, CANTIDAD, COSTO_UNITARIO, SUBTOTAL, ID_CAJA, ID_VENTA, ' +
  ' FECHA_CREACION, ESTADO) ' +
  "VALUES (?, ?, ?, ?, ?, ?, SYSUTCDATETIME(), 'VENDIDO')";

const SQL_INSERTAR_DETALLE_COTIZACION =
  'INSERT INTO dbo.DETALLE_VENTA ' +
  '(ID_INVENTARIO, CANTIDAD, COSTO_UNITARIO, SUBTOTAL, ID_CAJA, ID_VENTA, ' +
  ' FECHA_CREACION, FECHA_VENCIMIENTO, ESTADO) ' +
  'VALUES (?, ?, ?, ?, NULL, ?, SYSUTCDATETIME(), ' +
  "        DATEADD(HOUR, 24, SYSUTCDATETIME()), 'COTIZACION')";

const SQL_DESCONTAR_STOCK =
  'UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT - ? ' +
  'WHERE ID_INVENTARIO = ? AND STOCK_ACT >= ?';

const SQL_MOVIMIENTO =
  'INSERT INTO dbo.MOVIMIENTO_INVENTARIO ' +
  '(ID_USUARIO, ID_INVENTARIO, FECHA_MOVIMIENTO, TIPO_MOVIMIENTO, CANTIDAD) ' +
  "VALUES (?, ?, SYSUTCDATETIME(), 'SALIDA', ?)";

const SQL_COTIZACIONES =
  'SELECT v.ID_VENTA, v.TOTAL, v.FECHA_VENTA, ' +
  'COUNT(d.ID_DETALLE_VENTA) AS NUM_LINEAS, ' +
  "STRING_AGG(CAST(COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) AS NVARCHAR(200)) " +
  "          + ' x' + CAST(d.CANTIDAD AS VARCHAR(10)), ', ') " +
  '    WITHIN GROUP (ORDER BY d.ID_DETALLE_VENTA) AS RESUMEN ' +
  'FROM dbo.VENTA v ' +
  'JOIN dbo.DETALLE_VENTA d ON d.ID_VENTA = v.ID_VENTA ' +
  'JOIN dbo.INVENTARIO i ON d.ID_INVENTARIO = i.ID_INVENTARIO ' +
  'LEFT JOIN dbo.PRODUCTO p ON i.ID_PRODUCTO = p.ID_PRODUCTO ' +
  'LEFT JOIN dbo.MATERIAL m ON i.ID_MATERIAL = m.ID_MATERIAL ' +
  'LEFT JOIN dbo.ARREGLO a ON i.ID_DETALLE = a.ID_DETALLE ' +
  "WHERE v.ESTADO = 'COTIZACION' " +
  '  AND v.FECHA_VENTA >= DATEADD(HOUR, -24, SYSUTCDATETIME()) ' +
  'GROUP BY v.ID_VENTA, v.TOTAL, v.FECHA_VENTA ' +
  'ORDER BY v.FECHA_VENTA DESC';

const SQL_LINEAS_COTIZACION =
  'SELECT d.ID_DETALLE_VENTA, d.ID_INVENTARIO, d.CANTIDAD, d.COSTO_UNITARIO, ' +
  'd.SUBTOTAL, d.ESTADO, i.STOCK_ACT, ' +
  'COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) AS NOMBRE ' +
  'FROM dbo.DETALLE_VENTA d ' +
  'JOIN dbo.INVENTARIO i ON d.ID_INVENTARIO = i.ID_INVENTARIO ' +
  "JOIN dbo.VENTA v ON v.ID_VENTA = d.ID_VENTA AND v.ESTADO = 'COTIZACION' " +
  'LEFT JOIN dbo.PRODUCTO p ON i.ID_PRODUCTO = p.ID_PRODUCTO ' +
  'LEFT JOIN dbo.MATERIAL m ON i.ID_MATERIAL = m.ID_MATERIAL ' +
  'LEFT JOIN dbo.ARREGLO a ON i.ID_DETALLE = a.ID_DETALLE ' +
  'WHERE d.ID_VENTA = ? ORDER BY d.ID_DETALLE_VENTA';

const SQL_BORRAR_LINEAS_EXPIRADAS =
  'DELETE FROM dbo.DETALLE_VENTA ' +
  "WHERE ESTADO = 'COTIZACION' AND FECHA_VENCIMIENTO IS NOT NULL " +
  '  AND FECHA_VENCIMIENTO < SYSUTCDATETIME()';

const SQL_BORRAR_CABECERAS_EXPIRADAS =
  "DELETE FROM dbo.VENTA WHERE ESTADO = 'COTIZACION' AND NOT EXISTS (" +
  '  SELECT 1 FROM dbo.DETALLE_VENTA d WHERE d.ID_VENTA = VENTA.ID_VENTA)';

const SQL_MARCAR_VENTA =
  "UPDATE dbo.VENTA SET ID_CAJA = ?, TIPO_PAGO = ?, ESTADO = 'VENDIDO' " +
  "WHERE ID_VENTA = ? AND ESTADO = 'COTIZACION'";

const SQL_MARCAR_LINEAS =
  "UPDATE dbo.DETALLE_VENTA SET ID_CAJA = ?, ESTADO = 'VENDIDO' " +
  "WHERE ID_VENTA = ? AND ESTADO = 'COTIZACION'";

const SQL_LEER_LINEAS_COTIZACION =
  "SELECT ID_INVENTARIO, CANTIDAD FROM dbo.DETALLE_VENTA " +
  "WHERE ID_VENTA = ? AND ESTADO = 'COTIZACION'";

function round2(value: number): number {
  return Math.round((value + Number.EPSILON) * 100) / 100;
}

function fechaTexto(row: Row, key: string): string | null {
  const value = row[key];
  if (value === null || value === undefined) return null;
  return value instanceof Date ? value.toISOString() : String(value);
}

function nullableDecimal(row: Row, key: string): number | null {
  const value = row[key];
  return value === null || value === undefined ? null : decimal(row, key);
}

/**
 * Ejecuta una sentencia DML y devuelve @@ROWCOUNT. `query` no expone
 * rowsAffected, por eso se añade una consulta al mismo lote.
 */
async function executeCount(
  sqlText: string,
  values: readonly unknown[] = [],
  client?: DbClient,
): Promise<number> {
  const row = await queryOne<Row>(`${sqlText}; SELECT @@ROWCOUNT AS AFECTADOS`, values, client);
  return row ? Number(decimal(row, 'AFECTADOS')) : 0;
}

/** Cabecera de VENTA (venta confirmada o cotización). */
export interface VentaData {
  idVenta?: number;
  idCaja?: number | null;
  total?: number | null;
  tipoPago?: string | null;
  estado?: string;
  fechaVenta?: string | null;
  numLineas?: number;
  resumen?: string;
}

export class Venta implements VentaData {
  idVenta: number;
  idCaja: number | null;
  total: number | null;
  tipoPago: string | null;
  estado: string;
  fechaVenta: string | null;
  numLineas: number;
  resumen: string;

  constructor(data: VentaData = {}) {
    this.idVenta = data.idVenta ?? 0;
    this.idCaja = data.idCaja ?? null;
    this.total = data.total ?? null;
    this.tipoPago = data.tipoPago ?? null;
    this.estado = data.estado ?? '';
    this.fechaVenta = data.fechaVenta ?? null;
    this.numLineas = data.numLineas ?? 0;
    this.resumen = data.resumen ?? '';
  }
}

/** Línea de venta / carrito / cotización. */
export interface DetalleVentaData {
  idDetalleVenta?: number;
  idInventario?: number;
  nombre?: string;
  cantidad?: number;
  costoUnitario?: number | null;
  subtotal?: number | null;
  idCaja?: number | null;
  idVenta?: number | null;
  fechaCreacion?: string | null;
  fechaVencimiento?: string | null;
  estado?: string;
  stockDisponible?: number;
}

export class DetalleVenta implements DetalleVentaData {
  idDetalleVenta: number;
  idInventario: number;
  nombre: string;
  cantidad: number;
  costoUnitario: number | null;
  subtotal: number | null;
  idCaja: number | null;
  idVenta: number | null;
  fechaCreacion: string | null;
  fechaVencimiento: string | null;
  estado: string;
  stockDisponible: number;

  constructor(
    idInventario = 0,
    nombre = '',
    cantidad = 0,
    costoUnitario: number | null = null,
    stockDisponible = 0,
  ) {
    this.idDetalleVenta = 0;
    this.idInventario = idInventario;
    this.nombre = nombre;
    this.cantidad = cantidad;
    this.costoUnitario = costoUnitario;
    this.subtotal = costoUnitario !== null ? round2(costoUnitario * cantidad) : null;
    this.idCaja = null;
    this.idVenta = null;
    this.fechaCreacion = null;
    this.fechaVencimiento = null;
    this.estado = '';
    this.stockDisponible = stockDisponible;
  }

  recalcularSubtotal(): void {
    if (this.costoUnitario !== null) {
      this.subtotal = round2(this.costoUnitario * this.cantidad);
    }
  }
}

function mapVenta(row: Row): Venta {
  return new Venta({
    idVenta: integer(row, 'ID_VENTA'),
    total: nullableDecimal(row, 'TOTAL'),
    estado: 'COTIZACION',
    numLineas: integer(row, 'NUM_LINEAS'),
    resumen: str(row, 'RESUMEN'),
    fechaVenta: fechaTexto(row, 'FECHA_VENTA'),
  });
}

function mapLineaCotizacion(row: Row, idVenta: number): DetalleVenta {
  const linea = new DetalleVenta();
  linea.idDetalleVenta = integer(row, 'ID_DETALLE_VENTA');
  linea.idInventario = integer(row, 'ID_INVENTARIO');
  linea.cantidad = integer(row, 'CANTIDAD');
  linea.costoUnitario = nullableDecimal(row, 'COSTO_UNITARIO');
  linea.subtotal = nullableDecimal(row, 'SUBTOTAL');
  linea.estado = str(row, 'ESTADO');
  linea.nombre = str(row, 'NOMBRE');
  linea.stockDisponible = integer(row, 'STOCK_ACT');
  linea.idVenta = idVenta;
  return linea;
}

/**
 * Acceso a datos de VENTA y DETALLE_VENTA (ventas y cotizaciones).
 * El stock solo se descuenta al confirmar una venta o convertir una cotización.
 */
export class VentaDAO {
  constructor(private readonly client?: DbClient) {}

  /** Confirma una venta: cabecera, líneas, stock y movimientos. */
  async confirmarVenta(
    carrito: readonly DetalleVentaData[],
    idCaja: number,
    idUsuario: number,
    tipoPago: string,
  ): Promise<number> {
    const total = this.sumar(carrito);
    try {
      return await withTransaction(async (client) => {
        const idVenta = await this.insertarVenta(client, idCaja, total, tipoPago, 'VENDIDO');
        for (const linea of carrito) {
          await this.insertarDetalle(client, linea, idCaja, idVenta, false);
          await this.descontarStock(client, linea.idInventario ?? 0, linea.cantidad ?? 0);
          await this.insertarMovimiento(client, idUsuario, linea.idInventario ?? 0, linea.cantidad ?? 0);
        }
        return idVenta;
      });
    } catch (error) {
      if (error instanceof BusinessError) throw error;
      throw new BusinessError('No se pudo registrar la venta', { cause: error });
    }
  }

  /** Confirma una venta usando todo el saldo a favor vigente del cliente. */
  async confirmarVentaConCredito(
    carrito: readonly DetalleVentaData[],
    idCaja: number,
    idUsuario: number,
    idCliente: number,
  ): Promise<number> {
    const total = this.sumar(carrito);
    try {
      return await withTransaction(async (client) => {
        const idVenta = await this.insertarVenta(client, idCaja, total, 'CREDITO', 'VENDIDO');
        for (const linea of carrito) {
          await this.insertarDetalle(client, linea, idCaja, idVenta, false);
          await this.descontarStock(client, linea.idInventario ?? 0, linea.cantidad ?? 0);
          await this.insertarMovimiento(client, idUsuario, linea.idInventario ?? 0, linea.cantidad ?? 0);
        }
        await new CreditoClienteDAO(client).aplicar(client, idCliente, total, idVenta);
        return idVenta;
      });
    } catch (error) {
      if (error instanceof BusinessError) throw error;
      throw new BusinessError('No se pudo registrar el canje', { cause: error });
    }
  }

  /** Guarda el carrito como cotización de 24 h SIN descontar stock. */
  async guardarCotizacion(carrito: readonly DetalleVentaData[]): Promise<number> {
    const total = this.sumar(carrito);
    try {
      return await withTransaction(async (client) => {
        const idVenta = await this.insertarVenta(client, null, total, null, 'COTIZACION');
        for (const linea of carrito) {
          await this.insertarDetalle(client, linea, null, idVenta, true);
        }
        return idVenta;
      });
    } catch (error) {
      if (error instanceof BusinessError) throw error;
      throw new BusinessError('No se pudo guardar la cotización', { cause: error });
    }
  }

  /** Cotizaciones activas (no vencidas). */
  async listarCotizaciones(): Promise<Venta[]> {
    const rows = await query<Row>(SQL_COTIZACIONES, [], this.client);
    return rows.map(mapVenta);
  }

  /** Líneas de una cotización (nombre y stock actual). Otras ventas quedan fuera. */
  async obtenerLineas(idVenta: number): Promise<DetalleVenta[]> {
    const rows = await query<Row>(SQL_LINEAS_COTIZACION, [idVenta], this.client);
    return rows.map((row) => mapLineaCotizacion(row, idVenta));
  }

  /** Convierte una cotización en venta, descontando stock y registrando movimientos. */
  async convertirCotizacion(
    idVenta: number,
    idCaja: number,
    idUsuario: number,
    tipoPago: string,
  ): Promise<void> {
    try {
      await withTransaction(async (client) => {
        // 0) Leer las líneas ANTES de cambiar su estado.
        const lineas = await this.obtenerLineasCn(client, idVenta);

        // 1) Marcar cabecera como vendida (solo si aún es cotización).
        const afectadas = await executeCount(SQL_MARCAR_VENTA, [idCaja, tipoPago, idVenta], client);
        if (afectadas === 0) {
          throw new BusinessError('La cotización ya fue cobrada o ya no existe.');
        }

        // 2) Marcar líneas como vendidas y fijar la caja.
        await query(SQL_MARCAR_LINEAS, [idCaja, idVenta], client);

        // 3) Descontar stock por cada línea leída antes del UPDATE.
        for (const linea of lineas) {
          await this.descontarStock(client, linea.idInventario, linea.cantidad);
          await this.insertarMovimiento(client, idUsuario, linea.idInventario, linea.cantidad);
        }
      });
    } catch (error) {
      if (error instanceof BusinessError) throw error;
      throw new BusinessError('No se pudo cobrar la cotización', { cause: error });
    }
  }

  /** Elimina cotizaciones vencidas (>24 h). Devuelve cuántas líneas borró. */
  async eliminarExpiradas(): Promise<number> {
    try {
      return await withTransaction(async (client) => {
        const borradas = await executeCount(SQL_BORRAR_LINEAS_EXPIRADAS, [], client);
        await query(SQL_BORRAR_CABECERAS_EXPIRADAS, [], client);
        return borradas;
      });
    } catch (error) {
      if (error instanceof BusinessError) throw error;
      throw new BusinessError('Error al limpiar cotizaciones vencidas', { cause: error });
    }
  }

  private sumar(carrito: readonly DetalleVentaData[]): number {
    let total = 0;
    for (const linea of carrito) {
      total += linea.subtotal ?? 0;
    }
    return round2(total);
  }

  private async insertarVenta(
    client: DbClient,
    idCaja: number | null,
    total: number,
    tipoPago: string | null,
    estado: string,
  ): Promise<number> {
    return insertId(SQL_INSERTAR_VENTA, [idCaja, total, tipoPago, estado], client);
  }

  private async insertarDetalle(
    client: DbClient,
    linea: DetalleVentaData,
    idCaja: number | null,
    idVenta: number,
    cotizacion: boolean,
  ): Promise<void> {
    if (cotizacion) {
      await query(
        SQL_INSERTAR_DETALLE_COTIZACION,
        [linea.idInventario ?? 0, linea.cantidad ?? 0, linea.costoUnitario, linea.subtotal, idVenta],
        client,
      );
      return;
    }
    await query(
      SQL_INSERTAR_DETALLE,
      [
        linea.idInventario ?? 0,
        linea.cantidad ?? 0,
        linea.costoUnitario,
        linea.subtotal,
        idCaja,
        idVenta,
      ],
      client,
    );
  }

  private async descontarStock(client: DbClient, idInventario: number, cantidad: number): Promise<void> {
    const afectadas = await executeCount(SQL_DESCONTAR_STOCK, [cantidad, idInventario, cantidad], client);
    if (afectadas === 0) {
      throw new BusinessError('Stock insuficiente para uno de los productos. Revisa el carrito.');
    }
  }

  private async insertarMovimiento(
    client: DbClient,
    idUsuario: number,
    idInventario: number,
    cantidad: number,
  ): Promise<void> {
    await query(SQL_MOVIMIENTO, [idUsuario, idInventario, cantidad], client);
  }

  private async obtenerLineasCn(client: DbClient, idVenta: number): Promise<DetalleVenta[]> {
    const rows = await query<Row>(SQL_LEER_LINEAS_COTIZACION, [idVenta], client);
    return rows.map((row) => {
      const linea = new DetalleVenta();
      linea.idInventario = integer(row, 'ID_INVENTARIO');
      linea.cantidad = integer(row, 'CANTIDAD');
      return linea;
    });
  }
}
