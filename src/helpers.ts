import { config } from './config';

export function e(value: unknown): string {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

export function url(route = ''): string {
  return `${config.baseUrl}${route.startsWith('/') ? route : `/${route}`}`;
}

export function num(value: unknown, decimals = 2, group = true): string {
  if (value === null || value === undefined || value === '') return '';
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) return '';
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
    useGrouping: group,
  }).format(numeric);
}

export function numJs(value: unknown): string {
  return num(value, 2, false);
}

function dateParts(value: Date): Record<string, string> {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: config.timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(value);
  const out: Record<string, string> = {};
  for (const part of parts) out[part.type] = part.value;
  return out;
}

/** Formatos PHP compatibles: d/m/Y, H:i, d/m H:i, etc. */
export function fecha(value: unknown, format = 'd/m/Y'): string {
  if (value === null || value === undefined || String(value).trim() === '') return '';
  const cleaned = String(value).trim().replace(/\.\d+$/, '');
  const date = new Date(cleaned);
  if (Number.isNaN(date.getTime())) return '';
  const p = dateParts(date);
  const tokens: Record<string, string> = {
    d: p.day,
    m: p.month,
    Y: p.year,
    y: p.year.slice(-2),
    H: p.hour,
    i: p.minute,
    s: p.second,
  };
  return format.replace(/d|m|Y|y|H|i|s/g, (token) => tokens[token] ?? token);
}

export function saludoPorHorario(): string {
  const hour = Number(dateParts(new Date()).hour);
  if (hour < 12) return 'Buenos días';
  if (hour < 19) return 'Buenas tardes';
  return 'Buenas noches';
}

export function mensajeDe(error: unknown): string {
  const message = error instanceof Error ? error.message : String(error ?? '');
  if (/stock/i.test(message)) {
    return 'No hay stock suficiente para apartar ese ítem. Quita o reduce la cantidad.';
  }
  if (message.includes('pedido (revísalo')) {
    return 'No se pudo actualizar el pedido. Puede que ya no esté en ese estado.';
  }
  return 'Ocurrió un error. Revisa los datos e inténtalo de nuevo.';
}

export const viewHelpers = { e, url, num, numJs, fecha, saludoPorHorario, mensajeDe };
