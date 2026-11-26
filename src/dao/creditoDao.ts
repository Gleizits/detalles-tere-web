import { insertId, query, type DbClient } from '../db';
import { decimal, integer, str, type Row } from './util';

/** Equivale a Modelo\CreditoCliente (íd. CREDITO_CLIENTE). */
export interface CreditoCliente {
  idCredito: number;
  idCliente: number;
  idInventario: number | null;
  monto: number | null;
  estado: string;
  fecha: string | null;
  fechaVencimiento: string | null;
  idUsuario: number;
  // Datos de apoyo para listar (no son columnas de la tabla).
  nombreCliente: string;
  numWsp: string;
}

const SQL_LISTAR =
  `SELECT c.ID_CREDITO_CLIENTE, c.ID_CLIENTE, c.ID_INVENTARIO, c.MONTO, c.ESTADO,
          c.FECHA, c.FECHA_VENCIMIENTO, c.ID_USUARIO,
          cl.NOMBRE AS NOMBRE_CLIENTE, cl.NUM_WSP
   FROM dbo.CREDITO_CLIENTE c
   JOIN dbo.CLIENTE cl ON c.ID_CLIENTE = cl.ID_CLIENTE
   ORDER BY c.FECHA DESC`;

function nullableInteger(row: Row, key: string): number | null {
  const value = row[key];
  return value === null || value === undefined ? null : integer(row, key);
}

function nullableDecimal(row: Row, key: string): number | null {
  const value = row[key];
  return value === null || value === undefined ? null : decimal(row, key);
}

function fechaTexto(row: Row, key: string): string | null {
  const value = row[key];
  if (value === null || value === undefined) return null;
  return value instanceof Date ? value.toISOString() : String(value);
}

function map(row: Row): CreditoCliente {
  return {
    idCredito: integer(row, 'ID_CREDITO_CLIENTE'),
    idCliente: integer(row, 'ID_CLIENTE'),
    idInventario: nullableInteger(row, 'ID_INVENTARIO'),
    monto: nullableDecimal(row, 'MONTO'),
    estado: str(row, 'ESTADO'),
    fecha: fechaTexto(row, 'FECHA'),
    fechaVencimiento: fechaTexto(row, 'FECHA_VENCIMIENTO'),
    idUsuario: integer(row, 'ID_USUARIO'),
    nombreCliente: str(row, 'NOMBRE_CLIENTE'),
    numWsp: str(row, 'NUM_WSP'),
  };
}

export class CreditoDAO {
  constructor(private readonly client?: DbClient) {}

  /** @return CreditoCliente[] */
  async listar(): Promise<CreditoCliente[]> {
    const rows = await query<Row>(SQL_LISTAR, [], this.client);
    return rows.map(map);
  }

  async insertar(
    c: Pick<
      CreditoCliente,
      | 'idCliente'
      | 'idInventario'
      | 'monto'
      | 'estado'
      | 'fecha'
      | 'fechaVencimiento'
      | 'idUsuario'
    >,
  ): Promise<number> {
    return insertId(
      `INSERT INTO dbo.CREDITO_CLIENTE (
         ID_CLIENTE, ID_INVENTARIO, MONTO, ESTADO, FECHA, FECHA_VENCIMIENTO, ID_USUARIO)
       VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [
        c.idCliente,
        c.idInventario,
        c.monto,
        c.estado,
        c.fecha,
        c.fechaVencimiento,
        c.idUsuario,
      ],
      this.client,
    );
  }
}
