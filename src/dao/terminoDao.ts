import { query, type DbClient } from '../db';
import { BusinessError } from '../http';
import { decimal, integer, nullableStr, str, type Row } from './util';

const SQL_LISTAR =
  'SELECT t.ID_TERMINO, t.PALABRA, ' +
  "COALESCE(STRING_AGG(CAST(COALESCE(pr.NOMBRE, m.NOMBRE, a.NOMBRE) " +
  "AS VARCHAR(MAX)), ', '), 'Sin artículos') AS ASOCIACIONES, " +
  'COUNT(dt.ID_INVENTARIO) AS NUM_PRODUCTOS ' +
  'FROM dbo.TERMINOS t ' +
  'LEFT JOIN dbo.DETALLE_TERMINO dt ON dt.ID_TERMINO = t.ID_TERMINO ' +
  'LEFT JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = dt.ID_INVENTARIO ' +
  'LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO ' +
  'LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL ' +
  'LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE ' +
  'GROUP BY t.ID_TERMINO, t.PALABRA ORDER BY t.PALABRA';

// Equivale a InventarioDAO::listarActivos() del Java. Se reproduce aquí
// para no modificar el DAO de inventario, que es responsabilidad otro agente.
const SQL_INVENTARIO_ACTIVO =
  'SELECT i.ID_INVENTARIO, i.STOCK_ACT, i.STOCK_MIN, i.TIPO_ALMACEN, i.UNIDAD_MEDIDA, ' +
  'COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) AS NOMBRE, ' +
  'COALESCE(p.TIPO, m.TIPO) AS TIPO, ' +
  'c.NOMBRE AS CATEGORIA, ' +
  'COALESCE(p.PRECIO_VENTA, m.PRECIO_VENTA, a.PRECIO_VENTA) AS PRECIO_VENTA, ' +
  'COALESCE(p.PRECIO_COMPRA, m.PRECIO_COMPRA, a.PRECIO_COMPRA) AS PRECIO_COMPRA, ' +
  "CASE WHEN p.ID_PRODUCTO IS NOT NULL THEN 'PRODUCTO' " +
  "     WHEN m.ID_MATERIAL IS NOT NULL THEN 'MATERIAL' " +
  "     ELSE 'ARREGLO' END AS CLASE " +
  'FROM dbo.INVENTARIO i ' +
  'LEFT JOIN dbo.PRODUCTO p ON i.ID_PRODUCTO = p.ID_PRODUCTO ' +
  'LEFT JOIN dbo.MATERIAL m ON i.ID_MATERIAL = m.ID_MATERIAL ' +
  'LEFT JOIN dbo.ARREGLO a ON i.ID_DETALLE = a.ID_DETALLE ' +
  'LEFT JOIN dbo.CATEGORIA c ' +
  '  ON COALESCE(p.ID_CATEGORIA, m.ID_CATEGORIA, a.ID_CATEGORIA) = c.ID_CATEGORIA ' +
  'WHERE COALESCE(p.ESTADO, m.ESTADO, a.ESTADO) = 1 ORDER BY NOMBRE';

export interface Termino {
  idTermino: number;
  idUsuario: number;
  palabra: string;
  asociaciones: string;
  numProductos: number;
}

export interface InventarioItem {
  idInventario: number;
  stockAct: number;
  stockMin: number;
  tipoAlmacen: string;
  unidadMedida: string;
  nombre: string;
  tipo: string;
  categoria: string;
  clase: string;
  /** Alias de stockAct usado por los controladores TS. */
  stock: number;
  precioVenta: number | null;
  precioCompra: number | null;
  // Valores por defecto del modelo PHP (la consulta no los selecciona).
  idProducto: number;
  idMaterial: number;
  idArreglo: number;
  idCategoria: number;
  idMaterialVinc: number;
  estado: boolean;
}

function nullableDecimal(row: Row, key: string): number | null {
  const value = row[key];
  return value === null || value === undefined ? null : decimal(row, key);
}

/** Acceso a términos de búsqueda y sus asociaciones con inventario. */
export class TerminoDAO {
  constructor(private readonly client?: DbClient) {}

  async listar(): Promise<Termino[]> {
    const rows = await query<Row>(SQL_LISTAR, [], this.client);
    return rows.map((row) => ({
      idTermino: integer(row, 'ID_TERMINO'),
      idUsuario: 0,
      palabra: str(row, 'PALABRA'),
      asociaciones: str(row, 'ASOCIACIONES'),
      numProductos: integer(row, 'NUM_PRODUCTOS'),
    }));
  }

  /** Artículos activos disponibles para asociar. */
  async listarInventarioActivo(): Promise<InventarioItem[]> {
    const rows = await query<Row>(SQL_INVENTARIO_ACTIVO, [], this.client);
    return rows.map((row) => ({
      idInventario: integer(row, 'ID_INVENTARIO'),
      stockAct: integer(row, 'STOCK_ACT'),
      stockMin: integer(row, 'STOCK_MIN'),
      tipoAlmacen: str(row, 'TIPO_ALMACEN'),
      unidadMedida: str(row, 'UNIDAD_MEDIDA'),
      nombre: str(row, 'NOMBRE'),
      tipo: str(row, 'TIPO'),
      categoria: str(row, 'CATEGORIA'),
      precioVenta: nullableDecimal(row, 'PRECIO_VENTA'),
      precioCompra: nullableDecimal(row, 'PRECIO_COMPRA'),
      clase: str(row, 'CLASE'),
      stock: integer(row, 'STOCK_ACT'),
      idProducto: 0,
      idMaterial: 0,
      idArreglo: 0,
      idCategoria: 0,
      idMaterialVinc: 0,
      estado: true,
    }));
  }

  async crear(palabra: string, idUsuario: number): Promise<void> {
    try {
      await query(
        'INSERT INTO dbo.TERMINOS (ID_USUARIO, PALABRA) VALUES (?, ?)',
        [idUsuario, palabra],
        this.client,
      );
    } catch (error) {
      throw new BusinessError('No se pudo crear el término. Puede que ya exista.', {
        cause: error,
      });
    }
  }

  async asociar(idTermino: number, idInventario: number): Promise<void> {
    try {
      await query(
        'INSERT INTO dbo.DETALLE_TERMINO (ID_TERMINO, ID_INVENTARIO) VALUES (?, ?)',
        [idTermino, idInventario],
        this.client,
      );
    } catch (error) {
      throw new BusinessError(
        'No se pudo asociar el término. Puede que la asociación ya exista.',
        { cause: error },
      );
    }
  }

  async desasociar(idTermino: number, idInventario: number): Promise<void> {
    try {
      await query(
        'DELETE FROM dbo.DETALLE_TERMINO WHERE ID_TERMINO = ? AND ID_INVENTARIO = ?',
        [idTermino, idInventario],
        this.client,
      );
    } catch (error) {
      throw new BusinessError('No se pudo quitar la asociación', { cause: error });
    }
  }
}
