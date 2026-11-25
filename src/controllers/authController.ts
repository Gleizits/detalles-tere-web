import type { Request, Response } from 'express';
import { UsuarioDAO } from '../dao/usuarioDao';
import { param } from '../http';
import { clearFailures, blockingMessage, registerFailure } from '../lib/login-attempts';
import { Usuario } from '../models/usuario';
import { clearSession, currentUser, setUser } from '../session';
import { redirect, renderStandalone } from '../view';

const RUTAS_PERMITIDAS = [
  '/panel',
  '/caja',
  '/venta',
  '/pedidos',
  '/inventario',
  '/reportes',
  '/usuarios',
  '/ingresos',
  '/terminos',
  '/manual-usuario',
  '/manual-programador',
];

function destinoValido(destino: string): boolean {
  if (!destino.startsWith('/')) return false;
  if (destino.startsWith('//') || /[:\\]|\.\./.test(destino)) return false;
  const ruta = destino.split('?')[0];
  return RUTAS_PERMITIDAS.some((permitida) => ruta === permitida || ruta.startsWith(`${permitida}/`));
}

function ipCliente(req: Request): string {
  return req.ip || req.socket.remoteAddress || '0.0.0.0';
}

export async function mostrarLogin(req: Request, res: Response): Promise<void> {
  if (currentUser(req)) {
    redirect(req, res, '/panel');
    return;
  }
  await renderStandalone(req, res, 'login', 'Iniciar sesión', {
    error: null,
    username: '',
    destino: param(req, 'destino'),
  });
}

export async function procesarLogin(req: Request, res: Response): Promise<void> {
  if (currentUser(req)) {
    redirect(req, res, '/panel');
    return;
  }

  const username = param(req, 'username');
  const password = typeof req.body?.password === 'string' ? req.body.password : '';
  const destino = param(req, 'destino');
  const ip = ipCliente(req);
  const clave = `${username.toLowerCase()}|${ip}`;

  const bloqueo = blockingMessage(clave, ip);
  if (bloqueo) {
    await renderStandalone(req, res, 'login', 'Iniciar sesión', {
      error: bloqueo,
      username,
      destino,
    });
    return;
  }

  const usuario = await new UsuarioDAO().login(username, password);
  if (!usuario) {
    registerFailure(clave, ip);
    await renderStandalone(req, res, 'login', 'Iniciar sesión', {
      error: 'Usuario o contraseña incorrectos.',
      username,
      destino,
    });
    return;
  }

  clearFailures(clave);
  setUser(req, usuario as Usuario);
  redirect(req, res, destinoValido(destino) ? destino : '/panel');
}

export function procesarLogout(req: Request, res: Response): void {
  clearSession(req, res);
  redirect(req, res, '/login');
}
