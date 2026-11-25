import type { Request, Response } from 'express';
import { CajaDAO, type CajaData } from '../dao/cajaDao';
import { decimalParam, HttpError, intParam, param } from '../http';
import { currentUser } from '../session';
import { redirect, renderPage } from '../view';

/** Límite superior del monto, igual que en el servlet. */
const MONTO_MAXIMO = 1_000_000;

/** round() de PHP: mitad hacia afuera de cero, a 2 decimales. */
function round2(value: number): number {
  const sign = value < 0 ? -1 : 1;
  return (sign * Math.round(Math.abs(value) * 100 + Number.EPSILON)) / 100;
}

function idUsuarioActual(req: Request): number {
  const usuario = currentUser(req);
  if (!usuario) throw new HttpError(403, 'Acceso denegado.');
  return usuario.id;
}

/**
 * Pinta la vista con el estado actual (reenviarEstado del servlet).
 * Los errores y el resumen se pasan como datos de la vista, no como flash.
 */
async function estado(
  req: Request,
  res: Response,
  error: string | null = null,
  resumen: CajaData | null = null,
): Promise<void> {
  const dao = new CajaDAO();
  const idUsuario = idUsuarioActual(req);
  const abierta = await dao.cajaAbierta(idUsuario);

  // El DAO entrega objetos planos; la vista usaba el método esAbierta().
  const historial = (await dao.historial(idUsuario, 7)).map((caja) => ({
    ...caja,
    esAbierta: () => caja.fechaCierre === null,
  }));

  const datos: Record<string, unknown> = {
    caja: abierta,
    historial,
  };

  if (abierta !== null) {
    const ventasEfectivo = await dao.ventasEfectivo(abierta.idCaja);
    datos.ventasEfectivo = ventasEfectivo;
    datos.esperado = round2((abierta.montoInicial ?? 0) + ventasEfectivo);
    datos.ventasProductos = await dao.ventasProductosEfectivo(abierta.idCaja);
    datos.pagosReservas = await dao.pagosReservasEfectivo(abierta.idCaja);
  }

  if (error !== null) datos.error = error;
  if (resumen !== null) datos.resumen = resumen;

  await renderPage(req, res, 'caja', 'Caja', '/caja', datos);
}

/** Abre una caja con el monto inicial indicado. Devuelve si debe redirigir. */
async function abrir(req: Request, res: Response): Promise<boolean> {
  const monto = decimalParam(req, 'montoInicial');

  if (monto === undefined) {
    await estado(req, res, 'Ingresa un monto válido.');
    return false;
  }
  if (monto < 0 || monto > MONTO_MAXIMO) {
    await estado(req, res, 'El monto inicial debe estar entre 0 y 1,000,000.00.');
    return false;
  }

  const dao = new CajaDAO();
  const idUsuario = idUsuarioActual(req);

  if ((await dao.cajaAbierta(idUsuario)) !== null) {
    await estado(req, res, 'Ya tienes una caja abierta.');
    return false;
  }

  await dao.abrir(idUsuario, monto);
  return true;
}

/** Cierra la caja abierta, guardando esperado, contado y diferencia. */
async function cerrar(req: Request, res: Response): Promise<boolean> {
  const idCajaTxt = param(req, 'idCaja');
  const idCaja = intParam(req, 'idCaja', -1);
  const montoReal = decimalParam(req, 'montoReal');

  if (!/^-?\d+$/.test(idCajaTxt) || montoReal === undefined) {
    await estado(req, res, 'Ingresa un monto válido.');
    return false;
  }
  if (montoReal < 0 || montoReal > MONTO_MAXIMO) {
    await estado(req, res, 'El monto contado debe estar entre 0 y 1,000,000.00.');
    return false;
  }

  const dao = new CajaDAO();
  const abierta = await dao.cajaAbierta(idUsuarioActual(req));

  // El servlet redirige sin mensaje si la caja ya no es la abierta.
  if (abierta === null || abierta.idCaja !== idCaja) {
    return true;
  }

  const ventasEfectivo = await dao.ventasEfectivo(idCaja);
  abierta.montoFinal = round2((abierta.montoInicial ?? 0) + ventasEfectivo);
  abierta.montoReal = montoReal;
  await dao.cerrar(abierta);

  // Equivale al reenvío del servlet con el atributo "resumen".
  await estado(req, res, null, abierta);
  return false;
}

/** GET /caja */
export async function index(req: Request, res: Response): Promise<void> {
  await estado(req, res);
}

/** POST /caja (abrir o cerrar) */
export async function procesar(req: Request, res: Response): Promise<void> {
  const accion = param(req, 'accion');

  if (accion === 'abrir') {
    if (!(await abrir(req, res))) return;
  } else if (accion === 'cerrar') {
    if (!(await cerrar(req, res))) return;
  }

  redirect(req, res, '/caja');
}
