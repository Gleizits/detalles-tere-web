import { query, type DbClient } from '../db';
import { integer, str, type Row } from './util';

/** Equivale a Modelo\Proveedor. */
export interface Proveedor {
  idProveedor: number;
  nombre: string;
  numero: string;
}

const SQL_LISTAR =
  'SELECT ID_PROVEEDOR, NOMBRE, NUMERO FROM dbo.PROVEEDOR ORDER BY NOMBRE';

const SQL_CREAR =
  'INSERT INTO dbo.PROVEEDOR (NOMBRE, NUMERO) VALUES (?, ?)';

function map(row: Row): Proveedor {
  return {
    idProveedor: integer(row, 'ID_PROVEEDOR'),
    nombre: str(row, 'NOMBRE'),
    numero: str(row, 'NUMERO'),
  };
}

export class ProveedorDAO {
  constructor(private readonly client?: DbClient) {}

  /** @return Proveedor[] */
  async listar(): Promise<Proveedor[]> {
    const rows = await query<Row>(SQL_LISTAR, [], this.client);
    return rows.map(map);
  }

  async crear(nombre: string, numero: string): Promise<void> {
    await query(SQL_CREAR, [nombre, numero], this.client);
  }
}
