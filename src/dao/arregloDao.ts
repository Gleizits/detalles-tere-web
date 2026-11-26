import { insertId, query, type DbClient } from '../db';
import { decimal, integer, str, type Row } from './util';

/** Equivale a Modelo\Arreglo (propiedades públicas en camelCase). */
export interface Arreglo {
  idDetalle: number;
  idCategoria: number | null;
  nombre: string;
  precioCompra: number | null;
  precioVenta: number | null;
  categoria: string;
}

const SQL_LISTAR =
  `SELECT a.ID_DETALLE, a.ID_CATEGORIA, a.NOMBRE, a.PRECIO_COMPRA, a.PRECIO_VENTA,
          c.NOMBRE AS CATEGORIA
   FROM dbo.ARREGLO a
   LEFT JOIN dbo.CATEGORIA c ON a.ID_CATEGORIA = c.ID_CATEGORIA
   ORDER BY a.NOMBRE`;

const SQL_INSERT =
  `INSERT INTO dbo.ARREGLO (ID_CATEGORIA, NOMBRE, PRECIO_COMPRA, PRECIO_VENTA)
   VALUES (?, ?, ?, ?)`;

function nullableInteger(row: Row, key: string): number | null {
  const value = row[key];
  return value === null || value === undefined ? null : integer(row, key);
}

function nullableDecimal(row: Row, key: string): number | null {
  const value = row[key];
  return value === null || value === undefined ? null : decimal(row, key);
}

function map(row: Row): Arreglo {
  return {
    idDetalle: integer(row, 'ID_DETALLE'),
    idCategoria: nullableInteger(row, 'ID_CATEGORIA'),
    nombre: str(row, 'NOMBRE'),
    precioCompra: nullableDecimal(row, 'PRECIO_COMPRA'),
    precioVenta: nullableDecimal(row, 'PRECIO_VENTA'),
    categoria: str(row, 'CATEGORIA'),
  };
}

export class ArregloDAO {
  constructor(private readonly client?: DbClient) {}

  /** @return Arreglo[] */
  async listar(): Promise<Arreglo[]> {
    const rows = await query<Row>(SQL_LISTAR, [], this.client);
    return rows.map(map);
  }

  async insertar(
    a: Pick<Arreglo, 'idCategoria' | 'nombre' | 'precioCompra' | 'precioVenta'>,
  ): Promise<number> {
    return insertId(
      SQL_INSERT,
      [a.idCategoria, a.nombre, a.precioCompra, a.precioVenta],
      this.client,
    );
  }
}
