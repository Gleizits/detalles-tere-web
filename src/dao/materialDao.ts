import { insertId, query, type DbClient } from '../db';
import { decimal, integer, str, type Row } from './util';

/** Equivale a Modelo\Material. */
export interface Material {
  idMaterial: number;
  idCategoria: number | null;
  nombre: string;
  tipo: string | null;
  precioCompra: number | null;
  precioVenta: number | null;
  categoria: string;
}

const SQL_LISTAR =
  `SELECT m.ID_MATERIAL, m.ID_CATEGORIA, m.NOMBRE, m.TIPO, m.PRECIO_COMPRA, m.PRECIO_VENTA,
          c.NOMBRE AS CATEGORIA
   FROM dbo.MATERIAL m
   LEFT JOIN dbo.CATEGORIA c ON m.ID_CATEGORIA = c.ID_CATEGORIA
   ORDER BY m.NOMBRE`;

function nullableInteger(row: Row, key: string): number | null {
  const value = row[key];
  return value === null || value === undefined ? null : integer(row, key);
}

function nullableDecimal(row: Row, key: string): number | null {
  const value = row[key];
  return value === null || value === undefined ? null : decimal(row, key);
}

function map(row: Row): Material {
  const value = row['TIPO'];
  return {
    idMaterial: integer(row, 'ID_MATERIAL'),
    idCategoria: nullableInteger(row, 'ID_CATEGORIA'),
    nombre: str(row, 'NOMBRE'),
    tipo: value === null || value === undefined ? null : String(value),
    precioCompra: nullableDecimal(row, 'PRECIO_COMPRA'),
    precioVenta: nullableDecimal(row, 'PRECIO_VENTA'),
    categoria: str(row, 'CATEGORIA'),
  };
}

export class MaterialDAO {
  constructor(private readonly client?: DbClient) {}

  /** @return Material[] */
  async listar(): Promise<Material[]> {
    const rows = await query<Row>(SQL_LISTAR, [], this.client);
    return rows.map(map);
  }

  async insertar(
    m: Pick<Material, 'idCategoria' | 'nombre' | 'tipo' | 'precioCompra' | 'precioVenta'>,
  ): Promise<number> {
    return insertId(
      `INSERT INTO dbo.MATERIAL (ID_CATEGORIA, NOMBRE, TIPO, PRECIO_COMPRA, PRECIO_VENTA)
       VALUES (?, ?, ?, ?, ?)`,
      [m.idCategoria, m.nombre, m.tipo, m.precioCompra, m.precioVenta],
      this.client,
    );
  }

  async actualizar(m: Material): Promise<void> {
    await query(
      `UPDATE dbo.MATERIAL SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, PRECIO_COMPRA=?, PRECIO_VENTA=?
       WHERE ID_MATERIAL=?`,
      [m.idCategoria, m.nombre, m.tipo, m.precioCompra, m.precioVenta, m.idMaterial],
      this.client,
    );
  }

  async eliminar(id: number): Promise<void> {
    await query('DELETE FROM dbo.MATERIAL WHERE ID_MATERIAL=?', [id], this.client);
  }
}
