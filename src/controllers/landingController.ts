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

async function enviarConResend(
  nombre: string,
  correo: string,
  mensaje: string,
): Promise<boolean> {
  const { resendKey, destino, remitente } = config.contacto;
  if (resendKey === '') return false;

  const respuesta = await fetch('https://api.resend.com/emails', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${resendKey}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      from: remitente,
      to: [destino],
      reply_to: correo,
      subject: `Consulta desde la web — ${nombre}`,
      text: `${mensaje}\n\nDe: ${nombre}\nCorreo: ${correo}`,
    }),
  });

  if (!respuesta.ok) {
    throw new Error(`Resend respondió ${respuesta.status}`);
  }
  return true;
}

/** POST /contacto — valida CSRF global y envía por Resend si está configurado. */
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

  let enviado = false;
  let nota = 'Tu cliente de correo se abrirá para enviar el mensaje.';
  try {
    enviado = await enviarConResend(nombre, correo, mensaje);
    if (enviado) nota = 'Recibimos tu mensaje. Te responderemos al correo indicado.';
  } catch (error) {
    console.error('[detallesterets] No se pudo enviar el correo', error);
    nota = 'No pudimos enviarlo desde el servidor. Puedes usar tu cliente de correo.';
  }

  await renderStandalone(req, res, 'contacto', 'Mensaje enviado', {
    nombre,
    correo,
    nota,
    enviado,
    mailto: mailtoUrl(nombre, correo, mensaje),
  });
}
