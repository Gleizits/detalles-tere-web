import type { Request, Response } from 'express';
import { config } from '../config';
import { BusinessError, param } from '../http';
import { renderStandalone } from '../view';

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function whatsappUrl(mensaje: string): string {
  const texto = encodeURIComponent(mensaje);
  const numero = config.contacto.whatsapp.replace(/[^0-9]/g, '');
  return numero === ''
    ? `https://wa.me/?text=${texto}`
    : `https://wa.me/${numero}?text=${texto}`;
}

const VISITAS_COOKIE = 'dt_visitas';
const UN_ANIO_MS = 365 * 24 * 60 * 60 * 1000;

/** GET / — portada pública con contador de visitas persistente por navegador. */
export async function index(req: Request, res: Response): Promise<void> {
  const anterior = Number(req.cookies?.[VISITAS_COOKIE] ?? 0);
  const visitas = Number.isFinite(anterior) && anterior > 0 ? Math.floor(anterior) + 1 : 1;

  res.cookie(VISITAS_COOKIE, String(visitas), {
    httpOnly: true,
    sameSite: 'lax',
    secure: config.session.cookieSecure,
    path: '/',
    maxAge: UN_ANIO_MS,
  });

  await renderStandalone(req, res, 'landing', 'Inicio', {
    visitas,
    whatsapp: whatsappUrl('Hola, quiero información sobre DetallesTere.'),
    mapaSrc: config.contacto.mapa,
    correo: config.contacto.destino,
    redes: config.contacto.redes,
  });
}

function mailtoUrl(nombre: string, correo: string, mensaje: string): string {
  const asunto = encodeURIComponent(`Consulta desde la web — ${nombre}`);
  const cuerpo = encodeURIComponent(`${mensaje}\n\nDe: ${nombre}\nCorreo: ${correo}`);
  return `mailto:${config.contacto.destino}?subject=${asunto}&body=${cuerpo}`;
}

/** POST /contacto — valida CSRF y abre el cliente de correo del visitante. */
export async function contacto(req: Request, res: Response): Promise<void> {
  const nombre = param(req, 'nombre');
  const correo = param(req, 'correo');
  const mensaje = param(req, 'mensaje');

  if (nombre.length < 2 || nombre.length > 90) {
    throw new BusinessError('Escribe tu nombre completo.');
  }
  if (!EMAIL_RE.test(correo) || correo.length > 120) {
    throw new BusinessError('Escribe un correo válido para poder responderte.');
  }
  if (mensaje.length < 10 || mensaje.length > 2000) {
    throw new BusinessError('Escribe un mensaje de al menos 10 caracteres.');
  }

  await renderStandalone(req, res, 'contacto', 'Mensaje listo para enviar', {
    nombre,
    correo,
    nota: 'Tu cliente de correo se abrirá con el mensaje listo para enviar.',
    mailto: mailtoUrl(nombre, correo, mensaje),
    destino: config.contacto.destino,
  });
}
