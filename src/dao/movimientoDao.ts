import { query, type DbClient } from '../db';
import { integer, str, type Row } from './util';

/** Equivale a Modelo\Movimiento (trazabilidad de inventario). */
export interface Movimiento {
  idMovimiento: number;
  idUsuario: number;
  idInventario: number;
  nombreInventario: string;
  fechaMovimiento: string | null;
  tipoMovimiento: string;
  cantidad: number;
}

const SQL_LISTAR =
  `SELECT m.ID_MOVIMIENTO, m.ID_USUARIO, m.ID_INVENTARIO, m.FECHA_MOVIMIENTO,
          m.TIPO_MOVIMIENTO, m.CANTIDAD,
          COALESCE(p.NOMBRE, m2.NOMBRE, a.NOMBRE) AS NOMBRE_INVENTARIO
   FROM dbo.MOVIMIENTO_INVENTARIO m
   LEFT JOIN dbo.PRODUCTO p ON m.ID_INVENTARIO = p.ID_PRODUCTO
   LEFT JOIN dbo.MATERIAL m2 ON m.ID_INVENTARIO = m2.ID_MATERIAL
   LEFT JOIN dbo.ARREGLO a ON m.ID_INVENTARIO = a.ID_DETALLE
   ORDER BY m.FECHA_MOVIMIENTO DESC`;

const SQL_INSERT =
  `INSERT INTO dbo.MOVIMIENTO_INVENTARIO (
     ID_USUARIO, ID_INVENTARIO, FECHA_MOVIMIENTO, TIPO_MOVIMIENTO, CANTIDAD)
   VALUES (?, ?, SYSUTCDATETIME(), ?, ?)`;

function fechaTexto(row: Row, key: string): string | null {
  const value = row[key];
  if (value === null || value === undefined) return null;
  return value instanceof Date ? value.toISOString() : String(value);
}

function map(row: Row): Movimiento {
  return {
    idMovimiento: integer(row, 'ID_MOVIMIENTO'),
    idUsuario: integer(row, 'ID_USUARIO'),
    idInventario: integer(row, 'ID_INVENTARIO'),
    fechaMovimiento: fechaTexto(row, 'FECHA_MOVIMIENTO'),
    tipoMovimiento: str(row, 'TIPO_MOVIMIENTO'),
    cantidad: integer(row, 'CANTIDAD'),
    nombreInventario: str(row, 'NOMBRE_INVENTARIO'),
  };
}

export class MovimientoDAO {
  constructor(private readonly client?: DbClient) {}

  /** @return Movimiento[] */
  async listar(): Promise<Movimiento[]> {
    const rows = await query<Row>(SQL_LISTAR, [], this.client);
    return rows.map(map);
  }

  async insertar(
    m: Pick<Movimiento, 'idUsuario' | 'idInventario' | 'tipoMovimiento' | 'cantidad'>,
  ): Promise<void> {
    await query(
      SQL_INSERT,
      [m.idUsuario, m.idInventario, m.tipoMovimiento, m.cantidad],
      this.client,
    );
  }
}
