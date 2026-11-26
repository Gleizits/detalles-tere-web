import { execute, insertId, query, queryOne, withTransaction, type DbClient } from '../db';
import { BusinessError } from '../http';
import { decimal, flag, integer, str, type Row } from './util';

export type ClaseInventario = 'PRODUCTO' | 'MATERIAL' | 'ARREGLO';

/** Equivalente a Modelo\Categoria (id/nombre usados en los selectores). */
export interface Categoria {
  idCategoria: number;
  nombre: string;
  descripcion: string | null;
}

/**
 * Equivalente a Modelo\InventarioItem. `stock` es un alias de `stockAct`
 * para controladores que usan ese nombre; se rellena siempre al mapear.
 */
export interface InventarioItem {
  idInventario: number;
  stockAct: number;
  stockMin: number;
  stock: number;
  tipoAlmacen: string;
  unidadMedida: string;
  nombre: string;
  tipo: string;
  categoria: string;
  clase: string;
  precioVenta: number | null;
  precioCompra: number | null;
  idProducto: number;
  idMaterial: number;
  idArreglo: number;
  idCategoria: number;
  idMaterialVinc: number;
  estado: boolean;
}

/** Acepta los mismos campos parciales que el controlador PHP iba asignando. */
export type InventarioItemData = Partial<InventarioItem>;

const BASE_SELECT =
  'SELECT i.ID_INVENTARIO, i.STOCK_ACT, i.STOCK_MIN, i.TIPO_ALMACEN, i.UNIDAD_MEDIDA, '
  + 'i.ID_PRODUCTO, i.ID_MATERIAL, i.ID_DETALLE, '
  + 'COALESCE(p.ID_CATEGORIA, m.ID_CATEGORIA, a.ID_CATEGORIA) AS ID_CATEGORIA, '
  + 'COALESCE(p.ESTADO, m.ESTADO, a.ESTADO) AS ESTADO, '
  + 'COALESCE(p.ID_MATERIAL, 0) AS ID_MATERIAL_VINC, '
  + 'COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) AS NOMBRE, '
  + 'COALESCE(p.TIPO, m.TIPO) AS TIPO, '
  + 'c.NOMBRE AS CATEGORIA, '
  + 'COALESCE(p.PRECIO_VENTA, m.PRECIO_VENTA, a.PRECIO_VENTA) AS PRECIO_VENTA, '
  + 'COALESCE(p.PRECIO_COMPRA, m.PRECIO_COMPRA, a.PRECIO_COMPRA) AS PRECIO_COMPRA, '
  + "CASE WHEN p.ID_PRODUCTO IS NOT NULL THEN 'PRODUCTO' "
  + "     WHEN m.ID_MATERIAL IS NOT NULL THEN 'MATERIAL' "
  + "     ELSE 'ARREGLO' END AS CLASE "
  + 'FROM dbo.INVENTARIO i '
  + 'LEFT JOIN dbo.PRODUCTO p ON i.ID_PRODUCTO = p.ID_PRODUCTO '
  + 'LEFT JOIN dbo.MATERIAL m ON i.ID_MATERIAL = m.ID_MATERIAL '
  + 'LEFT JOIN dbo.ARREGLO a ON i.ID_DETALLE = a.ID_DETALLE '
  + 'LEFT JOIN dbo.CATEGORIA c '
  + '  ON COALESCE(p.ID_CATEGORIA, m.ID_CATEGORIA, a.ID_CATEGORIA) = c.ID_CATEGORIA ';

const SQL_BUSQUEDA =
  'WHERE (COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) LIKE ? '
  + '   OR EXISTS (SELECT 1 FROM dbo.DETALLE_TERMINO dt '
  + '              JOIN dbo.TERMINOS t ON dt.ID_TERMINO = t.ID_TERMINO '
  + '              WHERE dt.ID_INVENTARIO = i.ID_INVENTARIO AND t.PALABRA LIKE ?)) ';

function optionalDecimal(row: Row, key: string): number | null {
  const value = row[key];
  return value === null || value === undefined ? null : decimal(row, key);
}

function mapItem(row: Row): InventarioItem {
  const stockAct = integer(row, 'STOCK_ACT');
  const estadoRaw = row['ESTADO'];
  return {
    idInventario: integer(row, 'ID_INVENTARIO'),
    stockAct,
    stock: stockAct,
    stockMin: integer(row, 'STOCK_MIN'),
    tipoAlmacen: str(row, 'TIPO_ALMACEN'),
    unidadMedida: str(row, 'UNIDAD_MEDIDA'),
    nombre: str(row, 'NOMBRE'),
    tipo: str(row, 'TIPO'),
    categoria: str(row, 'CATEGORIA'),
    clase: str(row, 'CLASE'),
    precioVenta: optionalDecimal(row, 'PRECIO_VENTA'),
    precioCompra: optionalDecimal(row, 'PRECIO_COMPRA'),
    idProducto: integer(row, 'ID_PRODUCTO'),
    idMaterial: integer(row, 'ID_MATERIAL'),
    idArreglo: integer(row, 'ID_DETALLE'),
    idCategoria: integer(row, 'ID_CATEGORIA'),
    idMaterialVinc: integer(row, 'ID_MATERIAL_VINC'),
    // (bool) ($fila['ESTADO'] ?? true) de PHP
    estado: estadoRaw === null || estadoRaw === undefined ? true : flag(row, 'ESTADO'),
  };
}

/** Aplica los valores por defecto del modelo PHP antes de insertar/actualizar. */
function normalizar(item: InventarioItemData): InventarioItem {
  const stockAct = item.stockAct ?? item.stock ?? 0;
  return {
    idInventario: item.idInventario ?? 0,
    stockAct,
    stock: stockAct,
    stockMin: item.stockMin ?? 0,
    tipoAlmacen: item.tipoAlmacen ?? '',
    unidadMedida: item.unidadMedida ?? '',
    nombre: item.nombre ?? '',
    tipo: item.tipo ?? '',
    categoria: item.categoria ?? '',
    clase: item.clase ?? '',
    precioVenta: item.precioVenta ?? null,
    precioCompra: item.precioCompra ?? null,
    idProducto: item.idProducto ?? 0,
    idMaterial: item.idMaterial ?? 0,
    idArreglo: item.idArreglo ?? 0,
    idCategoria: item.idCategoria ?? 0,
    idMaterialVinc: item.idMaterialVinc ?? 0,
    estado: item.estado ?? true,
  };
}

/**
 * Acceso a datos de inventario: listado, búsqueda, alta, edición y
 * ajuste manual de stock. Une PRODUCTO/MATERIAL/ARREGLO en una vista.
 */
export class InventarioDAO {
  constructor(private readonly client?: DbClient) {}

  /**
   * Lista el inventario. Si q no está vacío, filtra por nombre o término.
   */
  async listar(q?: string | null, client?: DbClient): Promise<InventarioItem[]> {
    const consulta = (q ?? '').trim();
    let sql = BASE_SELECT;
    const valores: unknown[] = [];

    if (consulta !== '') {
      sql += SQL_BUSQUEDA;
      const like = `%${consulta}%`;
      valores.push(like, like);
    }
    sql += 'ORDER BY NOMBRE';

    const rows = await query<Row>(sql, valores, client ?? this.client);
    return rows.map(mapItem);
  }

  /** Solo activos (para operaciones de stock). */
  async listarActivos(client?: DbClient): Promise<InventarioItem[]> {
    const sql = BASE_SELECT
      + 'WHERE COALESCE(p.ESTADO, m.ESTADO, a.ESTADO) = 1 ORDER BY NOMBRE';
    const rows = await query<Row>(sql, [], client ?? this.client);
    return rows.map(mapItem);
  }

  /** Busca un ítem por ID. Devuelve null si no existe. */
  async buscarPorId(idInventario: number, client?: DbClient): Promise<InventarioItem | null> {
    const row = await queryOne<Row>(
      BASE_SELECT + 'WHERE i.ID_INVENTARIO = ?',
      [idInventario],
      client ?? this.client,
    );
    return row ? mapItem(row) : null;
  }

  /** Categorías para el selector del formulario. */
  async listarCategorias(client?: DbClient): Promise<Categoria[]> {
    const rows = await query<Row>(
      'SELECT ID_CATEGORIA, NOMBRE FROM dbo.CATEGORIA ORDER BY NOMBRE',
      [],
      client ?? this.client,
    );
    return rows.map((row) => ({
      idCategoria: integer(row, 'ID_CATEGORIA'),
      nombre: str(row, 'NOMBRE'),
      descripcion: null,
    }));
  }

  /** Materiales activos (id + nombre) para el selector, con forma de Categoria. */
  async listarMaterialesActivos(client?: DbClient): Promise<Categoria[]> {
    const rows = await query<Row>(
      'SELECT ID_MATERIAL, NOMBRE FROM dbo.MATERIAL WHERE ESTADO = 1 ORDER BY NOMBRE',
      [],
      client ?? this.client,
    );
    return rows.map((row) => ({
      idCategoria: integer(row, 'ID_MATERIAL'),
      nombre: str(row, 'NOMBRE'),
      descripcion: null,
    }));
  }

  /**
   * Devuelve el ID de la categoría con ese nombre; si no existe, la crea.
   * CATEGORIA.NOMBRE no es UNIQUE: se busca primero.
   */
  async obOCrearCategoria(nombre: string, client?: DbClient): Promise<number> {
    const nombreLimpio = nombre.trim();
    const cn = client ?? this.client;
    const row = await queryOne<Row>(
      'SELECT ID_CATEGORIA FROM dbo.CATEGORIA WHERE NOMBRE = ?',
      [nombreLimpio],
      cn,
    );
    const encontrado = row ? Object.values(row)[0] : undefined;
    if (encontrado !== undefined && encontrado !== null) {
      return Number(encontrado) || 0;
    }

    return insertId(
      'INSERT INTO dbo.CATEGORIA (NOMBRE) VALUES (?)',
      [nombreLimpio],
      cn,
    );
  }

  /**
   * Alta de artículo: inserta PRODUCTO/MATERIAL/ARREGLO y su fila en
   * INVENTARIO en una sola transacción.
   */
  async crear(item: InventarioItemData, client?: DbClient): Promise<void> {
    const completo = normalizar(item);
    const [sqlEnt, colInventario] = this.sqlEntidadInsert(completo.clase);

    await this.enTransaccion(async (cn) => {
      const idEnt = await insertId(sqlEnt, this.paramsEntidad(completo, true), cn);
      await query(
        `INSERT INTO dbo.INVENTARIO (${colInventario}, STOCK_ACT, STOCK_MIN, TIPO_ALMACEN, UNIDAD_MEDIDA) `
        + 'VALUES (?,?,?,?,?)',
        [idEnt, completo.stockAct, completo.stockMin, completo.tipoAlmacen, completo.unidadMedida],
        cn,
      );
    }, client);
  }

  /** Edición de artículo. Precios y estado SOLO se aplican si `admin` es true. */
  async actualizar(item: InventarioItemData, admin: boolean, client?: DbClient): Promise<void> {
    const completo = normalizar(item);
    const [sqlEnt, idEntidad] = this.sqlEntidadUpdate(completo, admin);

    await this.enTransaccion(async (cn) => {
      await query(sqlEnt, this.paramsEntidadUpdate(completo, admin, idEntidad), cn);
      await query(
        'UPDATE dbo.INVENTARIO SET STOCK_MIN=?, UNIDAD_MEDIDA=?, TIPO_ALMACEN=? WHERE ID_INVENTARIO=?',
        [completo.stockMin, completo.unidadMedida, completo.tipoAlmacen, completo.idInventario],
        cn,
      );
    }, client);
  }

  /**
   * Ajuste manual de stock (entrada o salida). Registra ajuste, detalle,
   * movimiento y actualiza STOCK_ACT en una transacción. Rechaza stock negativo.
   */
  async ajustarStock(
    idInventario: number,
    delta: number,
    motivo: string,
    idUsuario: number,
    client?: DbClient,
  ): Promise<void> {
    if (delta === 0) {
      throw new BusinessError('La cantidad del ajuste no puede ser 0');
    }
    const tipoMov = delta > 0 ? 'ENTRADA' : 'SALIDA';
    const cantidad = Math.abs(delta);

    await this.enTransaccion(async (cn) => {
      const idAjuste = await insertId(
        'INSERT INTO dbo.AJUSTE_INVENTARIO (ID_USUARIO, FECHA) VALUES (?, dbo.utcnow())',
        [idUsuario],
        cn,
      );

      await query(
        'INSERT INTO dbo.DETALLE_AJUSTE (ID_AJUSTE, ID_INVENTARIO, CANTIDAD, TIPO_AJUSTE, MOTIVO) '
        + 'VALUES (?,?,?,?,?)',
        [idAjuste, idInventario, delta, 'MANUAL', motivo],
        cn,
      );

      const actualizados = await execute(
        'UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT + ? '
        + 'WHERE ID_INVENTARIO = ? AND STOCK_ACT + ? >= 0',
        [delta, idInventario, delta],
        cn,
      );
      if (actualizados === 0) {
        throw new BusinessError('No hay stock suficiente para esa salida');
      }

      await query(
        'INSERT INTO dbo.MOVIMIENTO_INVENTARIO '
        + '(ID_USUARIO, ID_INVENTARIO, FECHA_MOVIMIENTO, TIPO_MOVIMIENTO, CANTIDAD) '
        + 'VALUES (?, ?, dbo.utcnow(), ?, ?)',
        [idUsuario, idInventario, tipoMov, cantidad],
        cn,
      );
    }, client);
  }

  /**
   * Las operaciones PHP envueltas en beginTransaction usan su propia
   * transacción, salvo que ya llegue un cliente (VentaDAO/PedidoDAO).
   */
  private async enTransaccion<T>(
    work: (client: DbClient) => Promise<T>,
    client?: DbClient,
  ): Promise<T> {
    const activo = client ?? this.client;
    return activo ? work(activo) : withTransaction(work);
  }

  private sqlEntidadInsert(clase: string): [string, string] {
    switch (clase) {
      case 'PRODUCTO':
        return [
          'INSERT INTO dbo.PRODUCTO (ID_CATEGORIA, NOMBRE, TIPO, PRECIO_COMPRA, PRECIO_VENTA, ESTADO, ID_MATERIAL) VALUES (?,?,?,?,?,?,?)',
          'ID_PRODUCTO',
        ];
      case 'MATERIAL':
        return [
          'INSERT INTO dbo.MATERIAL (ID_CATEGORIA, NOMBRE, TIPO, PRECIO_COMPRA, PRECIO_VENTA, ESTADO) VALUES (?,?,?,?,?,?)',
          'ID_MATERIAL',
        ];
      case 'ARREGLO':
        return [
          'INSERT INTO dbo.ARREGLO (ID_CATEGORIA, NOMBRE, PRECIO_COMPRA, PRECIO_VENTA, ESTADO) VALUES (?,?,?,?,?)',
          'ID_DETALLE',
        ];
      default:
        throw new BusinessError('Clase no válida: ' + clase);
    }
  }

  private sqlEntidadUpdate(item: InventarioItem, admin: boolean): [string, number] {
    const id = this.idEntidad(item);
    let sql: string;
    switch (item.clase) {
      case 'PRODUCTO':
        sql = admin
          ? 'UPDATE dbo.PRODUCTO SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, PRECIO_COMPRA=?, PRECIO_VENTA=?, ESTADO=?, ID_MATERIAL=? WHERE ID_PRODUCTO=?'
          : 'UPDATE dbo.PRODUCTO SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, ID_MATERIAL=? WHERE ID_PRODUCTO=?';
        break;
      case 'MATERIAL':
        sql = admin
          ? 'UPDATE dbo.MATERIAL SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, PRECIO_COMPRA=?, PRECIO_VENTA=?, ESTADO=? WHERE ID_MATERIAL=?'
          : 'UPDATE dbo.MATERIAL SET ID_CATEGORIA=?, NOMBRE=?, TIPO=? WHERE ID_MATERIAL=?';
        break;
      case 'ARREGLO':
        sql = admin
          ? 'UPDATE dbo.ARREGLO SET ID_CATEGORIA=?, NOMBRE=?, PRECIO_COMPRA=?, PRECIO_VENTA=?, ESTADO=? WHERE ID_DETALLE=?'
          : 'UPDATE dbo.ARREGLO SET ID_CATEGORIA=?, NOMBRE=? WHERE ID_DETALLE=?';
        break;
      default:
        throw new BusinessError('Clase no válida: ' + item.clase);
    }
    return [sql, id];
  }

  private paramsEntidad(item: InventarioItem, _esCrear: boolean): unknown[] {
    const params: unknown[] = [];
    params.push(item.idCategoria > 0 ? item.idCategoria : null);
    params.push(item.nombre);
    if (item.clase !== 'ARREGLO') {
      params.push(item.tipo);
    }
    params.push(item.precioCompra);
    params.push(item.precioVenta);
    params.push(item.estado ? 1 : 0);
    if (item.clase === 'PRODUCTO') {
      params.push(item.idMaterialVinc > 0 ? item.idMaterialVinc : null);
    }
    return params;
  }

  private paramsEntidadUpdate(
    item: InventarioItem,
    admin: boolean,
    idEntidad: number,
  ): unknown[] {
    const params: unknown[] = [];
    params.push(item.idCategoria > 0 ? item.idCategoria : null);
    params.push(item.nombre);
    if (item.clase !== 'ARREGLO') {
      params.push(item.tipo);
    }
    if (admin) {
      params.push(item.precioCompra);
      params.push(item.precioVenta);
      params.push(item.estado ? 1 : 0);
    }
    if (item.clase === 'PRODUCTO') {
      params.push(item.idMaterialVinc > 0 ? item.idMaterialVinc : null);
    }
    params.push(idEntidad);
    return params;
  }

  private idEntidad(item: InventarioItem): number {
    switch (item.clase) {
      case 'PRODUCTO':
        return item.idProducto;
      case 'MATERIAL':
        return item.idMaterial;
      case 'ARREGLO':
        return item.idArreglo;
      default:
        throw new BusinessError('Clase no válida: ' + item.clase);
    }
  }
}
