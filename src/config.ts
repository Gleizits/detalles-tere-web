import dotenv from 'dotenv';
import fs from 'node:fs';
import path from 'node:path';

export const PROJECT_ROOT = process.env.VERCEL
  ? process.cwd()
  : path.resolve(__dirname, '..');

dotenv.config({ path: path.join(PROJECT_ROOT, '.env') });

// Compatibilidad con el .env de la versión PHP durante el desarrollo local.
const legacyEnv = path.join(PROJECT_ROOT, 'DetallesTerePHP', '.env');
if (!process.env.DB_PASSWORD && fs.existsSync(legacyEnv)) {
  for (const line of fs.readFileSync(legacyEnv, 'utf8').split(/\r?\n/)) {
    const clean = line.trim();
    if (!clean || clean.startsWith('#')) continue;
    const eq = clean.indexOf('=');
    if (eq <= 0) continue;
    const key = clean.slice(0, eq).trim();
    if (!process.env[key]) {
      process.env[key] = clean.slice(eq + 1).trim().replace(/^(['"])(.*)\1$/, '$2');
    }
  }
}

function text(name: string, fallback = ''): string {
  return process.env[name] ?? fallback;
}

function bool(name: string, fallback = false): boolean {
  const value = process.env[name];
  if (value === undefined || value === '') return fallback;
  return ['1', 'true', 'yes', 'on', 'sí', 'si'].includes(value.toLowerCase());
}

function int(name: string, fallback: number): number {
  const value = Number(process.env[name]);
  return Number.isFinite(value) && value > 0 ? Math.trunc(value) : fallback;
}

const baseUrl = text('BASE_URL').replace(/\/+$/, '');

export const config = {
  db: {
    server: text('DB_SERVER', 'localhost'),
    port: int('DB_PORT', 1433),
    database: text('DB_NAME', 'DetallesTereDB'),
    user: text('DB_USER', 'sa'),
    password: text('DB_PASSWORD'),
    windowsAuth: bool('DB_WINDOWS_AUTH'),
    domain: text('DB_DOMAIN'),
    trustCertificate: bool('DB_TRUST_CERT', true),
    encrypt: bool('DB_ENCRYPT', false),
    timeoutSeconds: int('DB_TIMEOUT', 10),
  },
  session: {
    secret: text('SESSION_SECRET', 'detallestere-dev-secret-cambiar-en-produccion'),
    minutes: int('SESSION_MINUTES', 30),
    cookieSecure: bool('COOKIE_SECURE'),
  },
  baseUrl,
  timezone: text('APP_TIMEZONE', 'America/Lima'),
  production: bool('APP_PRODUCTION'),
};

if (config.production && (process.env.SESSION_SECRET ?? '') === '') {
  throw new Error('SESSION_SECRET es obligatorio cuando APP_PRODUCTION=true');
}
