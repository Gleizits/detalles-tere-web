import { createHmac, randomBytes, timingSafeEqual } from 'node:crypto';
import type { Request, Response } from 'express';
import { config } from './config';
import { HttpError } from './http';
import { Usuario, type UsuarioData } from './models/usuario';

const COOKIE_NAME = 'dt_session';
const SESSION_MINUTES = config.session.minutes;

export interface FlashMessage {
  clave: string;
  mensaje: string;
  error: boolean;
  titulo: string;
}

export interface SessionState {
  user: UsuarioData | null;
  csrfToken: string;
  flash: FlashMessage[];
  expiresAt: number;
  /** Estado propio de cada módulo (carritos, cotización cargada, etc.). */
  [key: string]: unknown;
}

interface SignedPayload {
  u: UsuarioData | null;
  c: string;
  f: FlashMessage[];
  e: number;
  x: Record<string, unknown>;
}

function sign(payload: string): string {
  return createHmac('sha256', config.session.secret).update(payload).digest('base64url');
}

function newState(): SessionState {
  return {
    user: null,
    csrfToken: randomBytes(32).toString('hex'),
    flash: [],
    expiresAt: Date.now() + SESSION_MINUTES * 60_000,
  };
}

function encode(state: SessionState): string {
  const { user, csrfToken, flash, expiresAt, ...extra } = state;
  const payload: SignedPayload = {
    u: user,
    c: csrfToken,
    f: flash,
    e: expiresAt,
    x: extra,
  };
  const body = Buffer.from(JSON.stringify(payload)).toString('base64url');
  return `${body}.${sign(body)}`;
}

function decode(raw: string | undefined): SessionState | null {
  if (!raw) return null;
  const [body, signature, extra] = raw.split('.');
  if (!body || !signature || extra) return null;

  const expected = sign(body);
  const a = Buffer.from(signature);
  const b = Buffer.from(expected);
  if (a.length !== b.length || !timingSafeEqual(a, b)) return null;

  try {
    const parsed = JSON.parse(Buffer.from(body, 'base64url').toString('utf8')) as SignedPayload;
    if (typeof parsed.c !== 'string' || typeof parsed.e !== 'number') return null;
    if (parsed.e < Date.now()) return null;
    return {
      ...(parsed.x ?? {}),
      user: parsed.u ?? null,
      csrfToken: parsed.c,
      flash: Array.isArray(parsed.f) ? parsed.f : [],
      expiresAt: parsed.e,
    };
  } catch {
    return null;
  }
}

export function sessionMiddleware(req: Request, _res: Response, next: (error?: unknown) => void): void {
  const state = decode(req.cookies?.[COOKIE_NAME]) ?? newState();
  state.expiresAt = Date.now() + SESSION_MINUTES * 60_000;
  req.sessionState = state;
  next();
}

export function commitSession(req: Request, res: Response): void {
  res.cookie(COOKIE_NAME, encode(req.sessionState), {
    httpOnly: true,
    sameSite: 'lax',
    secure: config.session.cookieSecure,
    path: '/',
    maxAge: SESSION_MINUTES * 60_000,
  });
}

export function clearSession(req: Request, res: Response): void {
  req.sessionState = { ...newState(), flash: [] };
  res.clearCookie(COOKIE_NAME, { path: '/' });
}

export function csrfToken(req: Request): string {
  return req.sessionState.csrfToken;
}

export function rotateCsrf(req: Request): string {
  req.sessionState.csrfToken = randomBytes(32).toString('hex');
  return req.sessionState.csrfToken;
}

export function verifyCsrf(req: Request): void {
  if (req.method === 'GET' || req.method === 'HEAD' || req.method === 'OPTIONS') return;
  const received = req.body?.[ 'csrfToken' ];
  const expected = req.sessionState.csrfToken;
  if (
    typeof received !== 'string' ||
    received === '' ||
    received.length !== expected.length ||
    !timingSafeEqual(Buffer.from(received), Buffer.from(expected))
  ) {
    throw new HttpError(403, 'Token de seguridad inválido o ausente.');
  }
}

export function currentUser(req: Request): Usuario | null {
  return req.sessionState.user ? Usuario.fromJSON(req.sessionState.user) : null;
}

export function setUser(req: Request, user: Usuario): void {
  req.sessionState.user = user.toJSON();
  rotateCsrf(req);
}

export function flash(
  req: Request,
  clave: string,
  mensaje: string,
  error = false,
  titulo = '',
): void {
  req.sessionState.flash.push({ clave, mensaje, error, titulo });
}

export function takeFlash(req: Request): FlashMessage[] {
  const messages = req.sessionState.flash;
  req.sessionState.flash = [];
  return messages;
}
