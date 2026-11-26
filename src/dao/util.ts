export type Row = Record<string, unknown>;

export function str(row: Row, key: string): string {
  const value = row[key];
  return value === null || value === undefined ? '' : String(value);
}

export function nullableStr(row: Row, key: string): string | null {
  const value = row[key];
  return value === null || value === undefined ? null : String(value);
}

export function integer(row: Row, key: string): number {
  const value = row[key];
  const parsed = Number(value);
  return Number.isFinite(parsed) ? Math.trunc(parsed) : 0;
}

export function decimal(row: Row, key: string): number {
  const value = row[key];
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

export function flag(row: Row, key: string): boolean {
  const value = row[key];
  if (typeof value === 'boolean') return value;
  if (typeof value === 'number') return value !== 0;
  return String(value ?? '').toUpperCase() === 'S' || String(value ?? '') === '1' || String(value ?? '').toLowerCase() === 'true';
}
