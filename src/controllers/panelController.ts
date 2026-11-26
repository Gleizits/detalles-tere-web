import type { Request, Response } from 'express';
import { saludoPorHorario } from '../helpers';
import { currentUser } from '../session';
import { renderPage } from '../view';

export async function index(req: Request, res: Response): Promise<void> {
  const usuario = currentUser(req);
  if (!usuario) {
    res.redirect('/login');
    return;
  }
  await renderPage(req, res, 'panel', 'Inicio', '/panel', {
    saludo: saludoPorHorario(),
  });
}
