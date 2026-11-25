import { insertId, query, queryOne, type DbClient } from '../db';
import { decimal, integer, str, type Row } from './util';

/** Equivale a Modelo\Producto. */
export interface Producto {
  idProducto: number;
  idCategoria: number | null;
  nombre: string;
  tipo: string | null;
  precioCompra: number | null;
  precioVenta: number | null;
  categoria: string;
}

const SQL_LISTAR =
  `SELECT p.ID_PRODUCTO, p.ID_CATEGORIA, p.NOMBRE, p.TIPO, p.PRECIO_COMPRA, p.PRECIO_VENTA,
          c.NOMBRE AS CATEGORIA
   FROM dbo.PRODUCTO p
   LEFT JOIN dbo.CATEGORIA c ON p.ID_CATEGORIA = c.ID_CATEGORIA
   ORDER BY p.NOMBRE`;

// En el PHP original se concatenaba " WHERE ..." después de ORDER BY (T-SQL
// inválido). Aquí el filtro va en su cláusula correcta; proyección idéntica.
const SQL_OBTENER =
  `SELECT p.ID_PRODUCTO, p.ID_CATEGORIA, p.NOMBRE, p.TIPO, p.PRECIO_COMPRA, p.PRECIO_VENTA,
          c.NOMBRE AS CATEGORIA
   FROM dbo.PRODUCTO p
   LEFT JOIN dbo.CATEGORIA c ON p.ID_CATEGORIA = c.ID_CATEGORIA
   WHERE p.ID_PRODUCTO = ?
   ORDER BY p.NOMBRE`;

function nullableInteger(row: Row, key: string): number | null {
  const value = row[key];
  return value === null || value === undefined ? null : integer(row, key);
}

function nullableDecimal(row: Row, key: string): number | null {
  const value = row[key];
  return value === null || value === undefined ? null : decimal(row, key);
}

function map(row: Row): Producto {
  const value = row['TIPO'];
  return {
    idProducto: integer(row, 'ID_PRODUCTO'),
    idCategoria: nullableInteger(row, 'ID_CATEGORIA'),
    nombre: str(row, 'NOMBRE'),
    tipo: value === null || value === undefined ? null : String(value),
    precioCompra: nullableDecimal(row, 'PRECIO_COMPRA'),
    precioVenta: nullableDecimal(row, 'PRECIO_VENTA'),
    categoria: str(row, 'CATEGORIA'),
  };
}

export class ProductoDAO {
  constructor(private readonly client?: DbClient) {}

  /** @return Producto[] */
  async listar(): Promise<Producto[]> {
    const rows = await query<Row>(SQL_LISTAR, [], this.client);
    return rows.map(map);
  }

  async obtener(id: number): Promise<Producto | null> {
    const row = await queryOne<Row>(SQL_OBTENER, [id], this.client);
    return row ? map(row) : null;
  }

  async insertar(
    p: Pick<Producto, 'idCategoria' | 'nombre' | 'tipo' | 'precioCompra' | 'precioVenta'>,
  ): Promise<number> {
    return insertId(
      `INSERT INTO dbo.PRODUCTO (ID_CATEGORIA, NOMBRE, TIPO, PRECIO_COMPRA, PRECIO_VENTA)
       VALUES (?, ?, ?, ?, ?)`,
      [p.idCategoria, p.nombre, p.tipo, p.precioCompra, p.precioVenta],
      this.client,
    );
  }

  async actualizar(p: Producto): Promise<void> {
    await query(
      `UPDATE dbo.PRODUCTO SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, PRECIO_COMPRA=?, PRECIO_VENTA=?
       WHERE ID_PRODUCTO=?`,
      [p.idCategoria, p.nombre, p.tipo, p.precioCompra, p.precioVenta, p.idProducto],
      this.client,
    );
  }

  async eliminar(id: number): Promise<void> {
    await query('DELETE FROM dbo.PRODUCTO WHERE ID_PRODUCTO=?', [id], this.client);
  }
}
