import { Pool, type PoolClient } from 'pg';
import { config } from './config';

let pool: Pool | undefined;

export type DbClient = PoolClient;

export function getPool(): Pool {
  if (!pool) {
    if (!config.db.url) {
      throw new Error('DATABASE_URL no configurada; copia .env.example a .env');
    }
    pool = new Pool({
      connectionString: config.db.url,
      max: 10,
      min: 0,
      idleTimeoutMillis: 5_000,
      connectionTimeoutMillis: config.db.timeoutSeconds * 1000,
      query_timeout: config.db.timeoutSeconds * 1000,
    });
    pool.on('error', () => {
      // Evita que un error de conexión derribe el proceso serverless.
    });
  }
  return pool;
}

/**
 * PostgreSQL devuelve las columnas en minúsculas. Los DAO leen las claves en
 * MAYÚSCULAS (heredado de SQL Server/PHP), así que se normalizan aquí.
 */
function normalizeRow<T extends Record<string, unknown>>(row: Record<string, unknown>): T {
  const normalized: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(row)) {
    normalized[key.toUpperCase()] = value;
  }
  return normalized as T;
}

/** Convierte los marcadores `?` de los DAO en `$1, $2, ...`. */
function toPostgres(sqlText: string, values: readonly unknown[]): string {
  let index = 0;
  const converted = sqlText.replace(/\?/g, () => `$${++index}`);
  if (index !== values.length) {
    throw new Error(
      `Número de parámetros SQL incorrecto: ${values.length} valores para ${index} marcadores`,
    );
  }
  return converted;
}

export async function query<T extends Record<string, unknown> = Record<string, unknown>>(
  sqlText: string,
  values: readonly unknown[] = [],
  client?: DbClient,
): Promise<T[]> {
  const result = await (client ?? getPool()).query(toPostgres(sqlText, values), values as unknown[]);
  return result.rows.map((row) => normalizeRow<T>(row as Record<string, unknown>));
}

export async function queryOne<T extends Record<string, unknown> = Record<string, unknown>>(
  sqlText: string,
  values: readonly unknown[] = [],
  client?: DbClient,
): Promise<T | undefined> {
  const rows = await query<T>(sqlText, values, client);
  return rows[0];
}

export async function scalar<T = number>(
  sqlText: string,
  values: readonly unknown[] = [],
  client?: DbClient,
): Promise<T> {
  const row = await queryOne<Record<string, unknown>>(sqlText, values, client);
  if (!row) throw new Error('La consulta no devolvió filas');
  return Object.values(row)[0] as T;
}

/** Ejecuta DML y devuelve rowCount (reemplaza a `@@ROWCOUNT`). */
export async function execute(
  sqlText: string,
  values: readonly unknown[] = [],
  client?: DbClient,
): Promise<number> {
  const result = await (client ?? getPool()).query(toPostgres(sqlText, values), values as unknown[]);
  return result.rowCount ?? 0;
}

/** Equivale a SCOPE_IDENTITY(): la primera columna del RETURNING es el ID. */
export async function insertId(
  sqlText: string,
  values: readonly unknown[] = [],
  client?: DbClient,
): Promise<number> {
  const limpio = sqlText.trim().replace(/;+$/, '');
  const statement = /\bRETURNING\b/i.test(limpio) ? limpio : `${limpio} RETURNING *`;
  const rows = await query<Record<string, unknown>>(statement, values, client);
  const first = rows[0];
  if (!first) throw new Error('No se pudo insertar la fila');
  return Number(Object.values(first)[0]);
}

export async function withTransaction<T>(
  work: (client: PoolClient) => Promise<T>,
): Promise<T> {
  const client = await getPool().connect();
  try {
    await client.query('BEGIN');
    const result = await work(client);
    await client.query('COMMIT');
    return result;
  } catch (error) {
    await client.query('ROLLBACK').catch(() => undefined);
    throw error;
  } finally {
    client.release();
  }
}
