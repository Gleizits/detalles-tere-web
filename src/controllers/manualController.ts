import type { Request, Response } from 'express';
import { config } from '../config';
import { renderPage } from '../view';

/** Manual del usuario, accesible desde el menú lateral. */
export async function usuario(req: Request, res: Response): Promise<void> {
  await renderPage(req, res, 'manual_usuario', 'Manual del usuario', '/panel', {
    minutosSesion: config.session.minutes,
  });
}

/** Manual técnico, disponible para todos desde la portada pública. */
export async function programador(req: Request, res: Response): Promise<void> {
  await renderPage(req, res, 'manual_programador', 'Manual del programador', '/panel');
}
