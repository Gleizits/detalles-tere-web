import type { Request, Response } from 'express';
import { TerminoDAO } from '../dao/terminoDao';
import { BusinessError, param } from '../http';
import { currentUser, flash } from '../session';
import { redirect, renderPage } from '../view';

function longitud(valor: string): number {
  return Array.from(valor).length;
}

function positivo(valor: string): number {
  if (!/^\d+$/.test(valor) || Number(valor) <= 0) {
    throw new BusinessError('Selecciona valores válidos.');
  }
  return Number.parseInt(valor, 10);
}

async function crear(req: Request): Promise<void> {
  const palabra = param(req, 'palabra');
  if (palabra === '' || longitud(palabra) > 75) {
    throw new BusinessError(
      'El término es obligatorio y puede tener máximo 75 caracteres.',
    );
  }
  await new TerminoDAO().crear(palabra, currentUser(req)?.id ?? 0);
}

async function cargarVista(
  req: Request,
  res: Response,
  error: string | null = null,
): Promise<void> {
  const dao = new TerminoDAO();
  const [terminos, inventario] = await Promise.all([
    dao.listar(),
    dao.listarInventarioActivo(),
  ]);

  await renderPage(req, res, 'terminos', 'Términos de búsqueda', '/terminos', {
    terminos,
    inventario,
    error,
  });
}

/** GET /terminos */
export async function index(req: Request, res: Response): Promise<void> {
  await cargarVista(req, res);
}

/** POST /terminos */
export async function procesar(req: Request, res: Response): Promise<void> {
  try {
    switch (param(req, 'accion')) {
      case 'crear':
        await crear(req);
        break;

      case 'asociar':
        await new TerminoDAO().asociar(
          positivo(param(req, 'idTermino')),
          positivo(param(req, 'idInventario')),
        );
        break;

      case 'desasociar':
        await new TerminoDAO().desasociar(
          positivo(param(req, 'idTermino')),
          positivo(param(req, 'idInventario')),
        );
        break;

      default:
        throw new BusinessError('Acción no válida.');
    }
  } catch (error) {
    if (error instanceof BusinessError) {
      await cargarVista(req, res, error.message);
      return;
    }
    console.error('Error en el módulo de términos:', error);
    await cargarVista(req, res, 'No pudimos guardar el cambio. Intenta de nuevo.');
    return;
  }

  flash(req, 'aviso', 'Cambios guardados.');
  redirect(req, res, '/terminos');
}
