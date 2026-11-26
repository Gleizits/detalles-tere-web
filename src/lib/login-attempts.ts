/**
 * Contadores de intentos fallidos de login.
 *
 * Vive en memoria del proceso, igual que el almacén de la versión PHP: en
 * serverless cada instancia tiene su propio contador, pero sigue frenando
 * los ataques dentro de una instancia.
 */

interface Attempt {
  count: number;
  windowStart: number;
  last: number;
}

const WINDOW_MS = 15 * 60 * 1000;
const MAX_ATTEMPTS = 5;
const MAX_PER_IP = 20;
const BACKOFF_AFTER = 3;
const INITIAL_BACKOFF_SECONDS = 2;
const MAX_BACKOFF_SECONDS = 60;

const byUser = new Map<string, Attempt>();
const byIp = new Map<string, Attempt>();

function fresh(attempt: Attempt | undefined, now: number): Attempt {
  if (!attempt || now - attempt.windowStart >= WINDOW_MS) {
    return { count: 0, windowStart: now, last: now };
  }
  return { ...attempt };
}

function backoffSeconds(failures: number): number {
  if (failures <= BACKOFF_AFTER) return 0;
  const exponent = Math.min(failures - BACKOFF_AFTER - 1, 5);
  return Math.min(INITIAL_BACKOFF_SECONDS * 2 ** exponent, MAX_BACKOFF_SECONDS);
}

export function blockingMessage(key: string, ip: string): string | null {
  const now = Date.now();

  const ipAttempt = byIp.get(ip);
  if (ipAttempt && now - ipAttempt.windowStart >= WINDOW_MS) byIp.delete(ip);
  if ((byIp.get(ip)?.count ?? 0) >= MAX_PER_IP) {
    return 'Demasiados intentos desde esta dirección. Vuelve a intentarlo en unos minutos.';
  }

  const attempt = byUser.get(key);
  if (!attempt) return null;
  if (now - attempt.windowStart >= WINDOW_MS) {
    byUser.delete(key);
    return null;
  }
  if (attempt.count >= MAX_ATTEMPTS) {
    return 'Demasiados intentos fallidos. Vuelve a intentarlo en unos minutos.';
  }

  const wait = backoffSeconds(attempt.count) * 1000 - (now - attempt.last);
  if (wait > 0) {
    const seconds = Math.ceil(wait / 1000);
    return `Espera ${seconds} segundo${seconds === 1 ? '' : 's'} antes de volver a intentar.`;
  }
  return null;
}

export function registerFailure(key: string, ip: string): void {
  const now = Date.now();
  const currentUser = fresh(byUser.get(key), now);
  byUser.set(key, {
    count: currentUser.count + 1,
    windowStart: currentUser.windowStart,
    last: now,
  });

  const currentIp = fresh(byIp.get(ip), now);
  byIp.set(ip, {
    count: currentIp.count + 1,
    windowStart: currentIp.windowStart,
    last: now,
  });
}

export function clearFailures(key: string): void {
  byUser.delete(key);
}
