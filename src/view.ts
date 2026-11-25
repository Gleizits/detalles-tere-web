import path from 'node:path';
import ejs from 'ejs';
import type { Request, Response } from 'express';
import { viewHelpers } from './helpers';
import { HttpError } from './http';
import { currentUser, csrfToken, takeFlash, commitSession } from './session';

const VIEWS_DIR = process.env.VERCEL
  ? path.join(process.cwd(), 'src', 'views')
  : path.join(__dirname, 'views');

function safeView(name: string): string {
  if (!/^[a-z0-9_]+$/i.test(name)) throw new HttpError(404, 'Vista no válida.');
  return path.join(VIEWS_DIR, `${name}.ejs`);
}

function locals(req: Request, extra: Record<string, unknown>): Record<string, unknown> {
  return {
    ...viewHelpers,
    ...extra,
    usuario: currentUser(req),
    csrfToken: csrfToken(req),
  };
}

export async function renderPage(
  req: Request,
  res: Response,
  view: string,
  titulo: string,
  seccion: string,
  data: Record<string, unknown> = {},
): Promise<void> {
  const file = safeView(view);
  const mensajesFlash = takeFlash(req);
  const shared = locals(req, { ...data, titulo, seccion, mensajesFlash });

  const body = await ejs.renderFile(file, shared, { filename: file });
  const layoutFile = path.join(VIEWS_DIR, 'comunes', 'layout.ejs');
  const html = await ejs.renderFile(
    layoutFile,
    { ...shared, body },
    { filename: layoutFile },
  );

  commitSession(req, res);
  res.status(200).type('html').send(html);
}

export async function renderStandalone(
  req: Request,
  res: Response,
  view: string,
  titulo: string,
  data: Record<string, unknown> = {},
  status = 200,
): Promise<void> {
  const file = safeView(view);
  const html = await ejs.renderFile(file, locals(req, { ...data, titulo }), { filename: file });
  commitSession(req, res);
  res.status(status).type('html').send(html);
}

export function redirect(req: Request, res: Response, destination: string): void {
  commitSession(req, res);
  res.redirect(urlFor(destination));
}

export function urlFor(route: string): string {
  const { url } = viewHelpers;
  return url(route);
}
