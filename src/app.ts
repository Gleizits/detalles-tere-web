import path from 'node:path';
import cookieParser from 'cookie-parser';
import express, { type ErrorRequestHandler } from 'express';
import { PROJECT_ROOT } from './config';
import { BusinessError, HttpError } from './http';
import { sessionMiddleware, verifyCsrf } from './session';
import { registerRoutes } from './routes';
import { renderStandalone } from './view';

const app = express();

app.disable('x-powered-by');
app.set('trust proxy', 1);

app.use((req, res, next) => {
  const csp = [
    "default-src 'self'",
    "script-src 'self'",
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: https:",
    "font-src 'self' data:",
    "connect-src 'self'",
    "frame-src https://www.google.com https://www.google.com.pe https://www.openstreetmap.org",
    "frame-ancestors 'none'",
    "base-uri 'self'",
    "form-action 'self'",
  ].join('; ');

  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('X-Frame-Options', 'DENY');
  res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
  res.setHeader(
    'Permissions-Policy',
    'geolocation=(), microphone=(), camera=(), payment=()',
  );
  res.setHeader('Content-Security-Policy', csp);
  if (req.path.startsWith('/assets/')) {
    res.setHeader('Cache-Control', 'no-cache');
  } else {
    res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
    res.setHeader('Pragma', 'no-cache');
  }
  next();
});

app.use(express.urlencoded({ extended: true, limit: '2mb' }));
app.use(express.json({ limit: '2mb' }));
app.use(cookieParser());
app.use(sessionMiddleware);
app.use((req, _res, next) => {
  try {
    verifyCsrf(req);
    next();
  } catch (error) {
    next(error);
  }
});

app.use(
  '/assets',
  express.static(path.join(PROJECT_ROOT, 'public', 'assets'), {
    fallthrough: true,
    index: false,
    maxAge: 0,
  }),
);

registerRoutes(app);

const RUTAS_CONOCIDAS = [
  '/contacto',
  '/manual-usuario',
  '/manual-programador',
  '/login',
  '/logout',
  '/panel',
  '/caja',
  '/venta',
  '/pedidos',
  '/inventario',
  '/ingresos',
  '/terminos',
  '/reportes',
  '/usuarios',
];

for (const ruta of RUTAS_CONOCIDAS) {
  app.all(ruta, (_req, _res, next) => {
    next(new HttpError(405, 'Método no permitido para esta ruta.'));
  });
}

app.use((req, _res, next) => {
  next(new HttpError(404, 'No encontramos la página que buscas.'));
});

const errorHandler: ErrorRequestHandler = (error, req, res, _next) => {
  if (res.headersSent) return;

  const status =
    error instanceof HttpError ? error.status : error instanceof BusinessError ? 400 : 500;
  const detail = error instanceof Error ? error.message : String(error ?? 'Error');
  const databaseFailure =
    status === 500 && /connect|login failed|timeout|econnreset|esocket|ettenv/i.test(detail);
  const message =
    status === 500
      ? databaseFailure
        ? 'No se pudo contactar con la base de datos. Revisa la configuración del .env.'
        : 'Ocurrió un error inesperado. Inténtalo de nuevo en unos minutos.'
      : error instanceof Error
        ? error.message
        : String(error ?? 'Error');

  const titulo =
    status === 403
      ? 'Sin permiso'
      : status === 404
        ? 'Página no encontrada'
        : status === 405
          ? 'Método no permitido'
          : 'Error';

  if (status === 500) console.error('[detallestere]', error);

  void renderStandalone(req, res, 'error', titulo, { status, mensaje: message }, status).catch(
    (renderError: unknown) => {
      console.error('[detallestere] No se pudo renderizar el error', renderError);
      res.status(status).type('text').send(message);
    },
  );
};

app.use(errorHandler);

export default app;
