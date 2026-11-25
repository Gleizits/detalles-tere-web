import type { NextFunction, Request, RequestHandler, Response } from 'express';

export class HttpError extends Error {
  constructor(
    public readonly status: number,
    message = status === 403 ? 'Acceso denegado.' : `Error ${status}`,
  ) {
    super(message);
    this.name = 'HttpError';
  }
}

export class BusinessError extends Error {
  constructor(message: string, options?: { cause?: unknown }) {
    super(message, options);
    this.name = 'BusinessError';
  }
}

/** Envuelve controladores async para que Express 4 propague los rechazos. */
export function asyncRoute(
  handler: (req: Request, res: Response, next: NextFunction) => Promise<unknown> | unknown,
): RequestHandler {
  return (req, res, next) => {
    void Promise.resolve(handler(req, res, next)).catch(next);
  };
}

export function param(req: Request, key: string, fallback = ''): string {
  const source = req.body?.[key] ?? req.query?.[key] ?? fallback;
  return typeof source === 'string' ? source.trim() : fallback;
}

export function rawParam(req: Request, key: string, fallback = ''): string {
  const source = req.body?.[key] ?? req.query?.[key] ?? fallback;
  return typeof source === 'string' ? source : fallback;
}

export function intParam(req: Request, key: string, fallback = 0): number {
  const value = param(req, key);
  return /^-?\d+$/.test(value) ? Number.parseInt(value, 10) : fallback;
}

export function decimalParam(req: Request, key: string): number | undefined {
  const value = param(req, key).replace(',', '.');
  if (value === '' || !Number.isFinite(Number(value))) return undefined;
  return Number(value);
}

export function stringsParam(req: Request, key: string): string[] {
  const value = req.body?.[key];
  if (Array.isArray(value)) return value.filter((item): item is string => typeof item === 'string');
  return typeof value === 'string' ? [value] : [];
}
