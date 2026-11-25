import { insertId, query, type DbClient } from '../db';
import { integer, nullableStr, str, type Row } from './util';

/** Equivale a Modelo\Categoria. */
export interface Categoria {
  idCategoria: number;
  nombre: string;
  descripcion: string | null;
}

const SQL_LISTAR =
  'SELECT ID_CATEGORIA, NOMBRE, DESCRIPCION FROM dbo.CATEGORIA ORDER BY NOMBRE';

function map(row: Row): Categoria {
  return {
    idCategoria: integer(row, 'ID_CATEGORIA'),
    nombre: str(row, 'NOMBRE'),
    descripcion: nullableStr(row, 'DESCRIPCION'),
  };
}

export class CategoriaDAO {
  constructor(private readonly client?: DbClient) {}

  /** @return Categoria[] */
  async listar(): Promise<Categoria[]> {
    const rows = await query<Row>(SQL_LISTAR, [], this.client);
    return rows.map(map);
  }

  async insertar(c: Pick<Categoria, 'nombre' | 'descripcion'>): Promise<number> {
    return insertId(
      'INSERT INTO dbo.CATEGORIA (NOMBRE, DESCRIPCION) VALUES (?, ?)',
      [c.nombre, c.descripcion],
      this.client,
    );
  }

  async actualizar(c: Categoria): Promise<void> {
    await query(
      'UPDATE dbo.CATEGORIA SET NOMBRE=?, DESCRIPCION=? WHERE ID_CATEGORIA=?',
      [c.nombre, c.descripcion, c.idCategoria],
      this.client,
    );
  }

  async eliminar(id: number): Promise<void> {
    await query('DELETE FROM dbo.CATEGORIA WHERE ID_CATEGORIA=?', [id], this.client);
  }
}
