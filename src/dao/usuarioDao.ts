import bcrypt from 'bcryptjs';
import { query, queryOne, scalar, type DbClient } from '../db';
import { BusinessError } from '../http';
import { Usuario, type UsuarioData } from '../models/usuario';
import { flag, integer, nullableStr, str, type Row } from './util';

const dummyHash = bcrypt.hashSync('tiempo-de-respuesta-dummy', 10);

function map(row: Row): Usuario {
  return new Usuario({
    id: integer(row, 'ID_USUARIO'),
    username: str(row, 'USERNAME'),
    nombre: str(row, 'NOMBRE'),
    apellido: str(row, 'APELLIDO'),
    rol: str(row, 'ROL'),
    correo: nullableStr(row, 'CORREO'),
    estado: flag(row, 'ESTADO'),
  });
}

export class UsuarioDAO {
  constructor(private readonly client?: DbClient) {}

  async login(username: string, password: string): Promise<Usuario | null> {
    const row = await queryOne<Row>(
      'SELECT ID_USUARIO, USERNAME, NOMBRE, APELLIDO, ROL, CONTRASENA FROM dbo.USUARIO WHERE USERNAME = ? AND ESTADO = 1',
      [username.trim()],
      this.client,
    );

    if (row) {
      const matches = await bcrypt.compare(password, str(row, 'CONTRASENA'));
      return matches ? map(row) : null;
    }

    await bcrypt.compare(password, dummyHash);
    return null;
  }

  async listar(): Promise<Usuario[]> {
    const rows = await query<Row>(
      'SELECT ID_USUARIO, USERNAME, NOMBRE, APELLIDO, CORREO, ROL, ESTADO FROM dbo.USUARIO ORDER BY APELLIDO, NOMBRE',
      [],
      this.client,
    );
    return rows.map(map);
  }

  async crear(usuario: Usuario, password: string): Promise<void> {
    try {
      await query(
        'INSERT INTO dbo.USUARIO (USERNAME, NOMBRE, APELLIDO, CORREO, CONTRASENA, ROL, ESTADO) VALUES (?, ?, ?, ?, ?, ?, 1)',
        [
          usuario.username,
          usuario.nombre,
          usuario.apellido,
          usuario.correo || null,
          await bcrypt.hash(password, 10),
          usuario.rol,
        ],
        this.client,
      );
    } catch (error) {
      throw new BusinessError(
        'No se pudo crear el usuario. Verifica que usuario y correo no estén repetidos.',
        { cause: error },
      );
    }
  }

  async actualizar(usuario: Usuario, password?: string | null): Promise<void> {
    const actual = await this.buscarPorId(usuario.id);
    const quedaAdmin = usuario.rol.toLowerCase() === 'admin' && usuario.estado;
    const eraAdminActivo = actual?.esAdmin() && actual.estado;
    if (eraAdminActivo && !quedaAdmin && (await this.contarAdministradoresActivos()) <= 1) {
      throw new BusinessError('Debe permanecer al menos un administrador activo.');
    }

    try {
      if (password) {
        await query(
          'UPDATE dbo.USUARIO SET USERNAME=?, NOMBRE=?, APELLIDO=?, CORREO=?, ROL=?, ESTADO=?, CONTRASENA=? WHERE ID_USUARIO=?',
          [
            usuario.username,
            usuario.nombre,
            usuario.apellido,
            usuario.correo || null,
            usuario.rol,
            usuario.estado ? 1 : 0,
            await bcrypt.hash(password, 10),
            usuario.id,
          ],
          this.client,
        );
      } else {
        await query(
          'UPDATE dbo.USUARIO SET USERNAME=?, NOMBRE=?, APELLIDO=?, CORREO=?, ROL=?, ESTADO=? WHERE ID_USUARIO=?',
          [
            usuario.username,
            usuario.nombre,
            usuario.apellido,
            usuario.correo || null,
            usuario.rol,
            usuario.estado ? 1 : 0,
            usuario.id,
          ],
          this.client,
        );
      }
    } catch (error) {
      throw new BusinessError(
        'No se pudo actualizar el usuario. Verifica que usuario y correo no estén repetidos.',
        { cause: error },
      );
    }
  }

  async buscarPorId(id: number): Promise<Usuario | null> {
    const row = await queryOne<Row>(
      'SELECT ID_USUARIO, USERNAME, NOMBRE, APELLIDO, CORREO, ROL, ESTADO FROM dbo.USUARIO WHERE ID_USUARIO = ?',
      [id],
      this.client,
    );
    return row ? map(row) : null;
  }

  private async contarAdministradoresActivos(): Promise<number> {
    return Number(
      await scalar(
        "SELECT COUNT(*) FROM dbo.USUARIO WHERE ROL = 'admin' AND ESTADO = 1",
        [],
        this.client,
      ),
    );
  }
}

export type { UsuarioData };
