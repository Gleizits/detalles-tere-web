import type { NextFunction, Request, Response } from 'express';
import { HttpError } from './http';
import { currentUser } from './session';
import { redirect } from './view';

export function requireAuth(req: Request, res: Response, next: NextFunction): void {
  if (currentUser(req)) {
    next();
    return;
  }
  const query = req.url.includes('?') ? req.url.slice(req.url.indexOf('?')) : '';
  redirect(req, res, `/login?destino=${encodeURIComponent(req.path + query)}`);
}

export function requireAdmin(req: Request, _res: Response, next: NextFunction): void {
  const user = currentUser(req);
  if (!user || !user.esAdmin()) {
    next(new HttpError(403, 'Esta sección requiere permisos de administrador.'));
    return;
  }
  next();
}
