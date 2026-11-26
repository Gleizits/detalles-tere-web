import type { Request, Response } from 'express';
import { UsuarioDAO } from '../dao/usuarioDao';
import { BusinessError, intParam, param, rawParam } from '../http';
import { Usuario } from '../models/usuario';
import { paginar } from '../pagination';
import { currentUser, flash, setUser } from '../session';
import { redirect, renderPage } from '../view';

interface CamposFormulario {
  correo: string | null;
  estado: boolean;
}

type CamposPorId = Record<number, CamposFormulario>;

/** mb_substr() / mb_strlen() cuentan caracteres, no unidades UTF-16. */
function recortar(valor: string, maximo: number): string {
  return Array.from(valor).slice(0, maximo).join('');
}

function longitud(valor: string): number {
  return Array.from(valor).length;
}

function obligatorio(valor: string, maximo: number, mensaje: string): string {
  const resultado = recortar(valor.trim(), maximo);
  if (resultado === '') throw new BusinessError(mensaje);
  return resultado;
}

function positivo(valor: string, mensaje: string): number {
  if (!/^\d+$/.test(valor) || Number(valor) <= 0) throw new BusinessError(mensaje);
  return Number.parseInt(valor, 10);
}

function enteroOpcional(valor: string): number | null {
  return /^-?\d+$/.test(valor.trim()) ? Number.parseInt(valor, 10) : null;
}

function camposPorFormulario(edicion: boolean, req: Request): CamposFormulario {
  const correo = param(req, 'correo').trim();
  return {
    correo: correo === '' ? null : recortar(correo, 75),
    estado: !edicion || param(req, 'estado') === 'on',
  };
}

function leerUsuario(
  edicion: boolean,
  req: Request,
  campos: CamposFormulario,
): Usuario {
  const id = edicion
    ? positivo(param(req, 'id'), 'Selecciona un usuario válido.')
    : 0;
  const username = obligatorio(param(req, 'username'), 50, 'Indica el usuario.');
  const nombre = obligatorio(param(req, 'nombre'), 50, 'Indica el nombre.');
  const apellido = obligatorio(param(req, 'apellido'), 50, 'Indica el apellido.');
  const correo = recortar(param(req, 'correo'), 75);
  const rol = obligatorio(param(req, 'rol'), 12, 'Selecciona un rol.');
  if (!['admin', 'empleado'].includes(rol)) {
    throw new BusinessError('El rol seleccionado no es válido.');
  }

  campos.correo = correo === '' ? null : correo;
  campos.estado = !edicion || param(req, 'estado') === 'on';

  return new Usuario({
    id,
    username,
    nombre,
    apellido,
    rol,
    correo: campos.correo,
    estado: campos.estado,
  });
}

function password(obligatorioClave: boolean, req: Request): string | null {
  const dato = rawParam(req, 'password'); // El PHP no recorta la contraseña.
  if (dato === '') {
    if (obligatorioClave) throw new BusinessError('Indica una contraseña.');
    return null;
  }

  const tamano = longitud(dato);
  if (tamano < 6 || tamano > 100) {
    throw new BusinessError('La contraseña debe tener entre 6 y 100 caracteres.');
  }
  return dato;
}

function protegerSesionPropia(
  req: Request,
  usuario: Usuario,
  campos: CamposFormulario,
): void {
  const actual = currentUser(req);
  if (actual && actual.id === usuario.id && !campos.estado) {
    throw new BusinessError('No puedes desactivar tu propio usuario.');
  }
}

function actualizarSesionSiCorresponde(req: Request, actualizado: Usuario): void {
  if (currentUser(req)?.id === actualizado.id) {
    setUser(req, actualizado);
  }
}

async function listarUsuarios(): Promise<{ usuarios: Usuario[]; campos: CamposPorId }> {
  const usuarios = await new UsuarioDAO().listar();
  const campos: CamposPorId = {};
  for (const usuario of usuarios) {
    campos[usuario.id] = {
      correo: usuario.correo ?? null,
      estado: Boolean(usuario.estado),
    };
  }
  return { usuarios, campos };
}

/** Repinta la vista con los datos capturados cuando hay error. */
async function mostrarConError(
  req: Request,
  res: Response,
  error: string,
): Promise<void> {
  const id = intParam(req, 'id');
  const editando = id > 0 || param(req, 'accion') === 'editar';
  const capturado = new Usuario({
    id,
    username: param(req, 'username'),
    nombre: param(req, 'nombre'),
    apellido: param(req, 'apellido'),
    rol: param(req, 'rol'),
  });
  const campos = camposPorFormulario(editando, req);
  capturado.correo = campos.correo;
  capturado.estado = campos.estado;

  const lista = await listarUsuarios();
  const pagina = paginar(lista.usuarios, req);
  await renderPage(req, res, 'usuarios', 'Usuarios', '/usuarios', {
    usuarios: pagina.items,
    campos: lista.campos,
    paginacion: pagina,
    usuarioEditar: capturado,
    camposEditar: campos,
    nuevo: !editando,
    error,
  });
}

/** GET /usuarios (?nuevo=1 | ?editar=id) */
export async function index(req: Request, res: Response): Promise<void> {
  const lista = await listarUsuarios();
  const todos = lista.usuarios;
  const campos = lista.campos;
  const pagina = paginar(todos, req);

  let editar: Usuario | null = null;
  let camposEditar: CamposFormulario = { correo: null, estado: true };
  let error: string | null = null;

  if (req.query.editar !== undefined) {
    const id = enteroOpcional(param(req, 'editar'));
    if (id === null || id <= 0) {
      error = 'El usuario solicitado no es válido.';
    } else {
      const encontrado = todos.find((usuario) => usuario.id === id);
      if (encontrado) {
        editar = encontrado;
        camposEditar = campos[id] ?? camposEditar;
      }
    }
  }

  await renderPage(req, res, 'usuarios', 'Usuarios', '/usuarios', {
    usuarios: pagina.items,
    campos,
    paginacion: pagina,
    usuarioEditar: editar,
    camposEditar,
    nuevo: req.query.nuevo !== undefined,
    error,
  });
}

/** POST /usuarios */
export async function procesar(req: Request, res: Response): Promise<void> {
  const accion = param(req, 'accion');

  try {
    if (accion === 'crear') {
      const campos = camposPorFormulario(false, req);
      const usuario = leerUsuario(false, req, campos);
      await new UsuarioDAO().crear(usuario, password(true, req) as string);
    } else if (accion === 'editar') {
      const campos = camposPorFormulario(true, req);
      const usuario = leerUsuario(true, req, campos);
      protegerSesionPropia(req, usuario, campos);
      await new UsuarioDAO().actualizar(usuario, password(false, req));
      actualizarSesionSiCorresponde(req, usuario);
    } else {
      throw new BusinessError('Acción no válida.');
    }
  } catch (fallo) {
    if (fallo instanceof BusinessError) {
      await mostrarConError(req, res, fallo.message);
      return;
    }
    console.error('Error en el módulo de usuarios:', fallo);
    await mostrarConError(req, res, 'No pudimos guardar el usuario. Intenta de nuevo.');
    return;
  }

  flash(req, 'aviso', 'Usuario guardado.');
  redirect(req, res, '/usuarios');
}
