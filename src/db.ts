import sql from 'mssql';
import { config } from './config';

let poolPromise: Promise<sql.ConnectionPool> | undefined;

export type DbClient = sql.ConnectionPool | sql.Transaction;

function buildConfig(): sql.config {
  const { db } = config;
  const base: sql.config = {
    server: db.server,
    port: db.port,
    database: db.database,
    options: {
      encrypt: db.encrypt,
      trustServerCertificate: db.trustCertificate,
      enableArithAbort: true,
      connectTimeout: db.timeoutSeconds * 1000,
      requestTimeout: db.timeoutSeconds * 1000,
    },
    pool: { max: 10, min: 0, idleTimeoutMillis: 30_000 },
  };

  if (db.windowsAuth) {
    base.authentication = {
      type: 'ntlm',
      options: {
        domain: db.domain,
        userName: db.user,
        password: db.password,
      },
    };
  } else {
    base.user = db.user;
    base.password = db.password;
  }

  return base;
}

export function getPool(): Promise<sql.ConnectionPool> {
  if (poolPromise) return poolPromise;

  const pool = new sql.ConnectionPool(buildConfig());
  const pending = pool.connect().catch((error: unknown) => {
    poolPromise = undefined;
    throw error;
  });
  poolPromise = pending;
  return pending;
}

function bindInputs(request: sql.Request, values: readonly unknown[]): void {
  values.forEach((value, index) => {
    const name = `p${index}`;
    if (value === null || value === undefined) {
      request.input(name, sql.NVarChar, null);
    } else if (typeof value === 'number') {
      request.input(name, Number.isInteger(value) ? sql.Int : sql.Decimal(18, 2), value);
    } else if (typeof value === 'boolean') {
      request.input(name, sql.Bit, value ? 1 : 0);
    } else if (value instanceof Date) {
      request.input(name, sql.DateTime2, value);
    } else if (Buffer.isBuffer(value)) {
      request.input(name, sql.VarBinary, value);
    } else {
      request.input(name, sql.NVarChar, String(value));
    }
  });
}

/** Ejecuta SQL con marcadores `?`, exactamente como los DAO de la versión PHP. */
export async function query<T extends Record<string, unknown> = Record<string, unknown>>(
  sqlText: string,
  values: readonly unknown[] = [],
  client?: DbClient,
): Promise<T[]> {
  const request = (client ?? (await getPool())).request();
  bindInputs(request, values);
  let index = 0;
  const converted = sqlText.replace(/\?/g, () => `@p${index++}`);
  if (index !== values.length) {
    throw new Error(
      `Número de parámetros SQL incorrecto: ${values.length} valores para ${index} marcadores`,
    );
  }
  const result = await request.query<T>(converted);
  return result.recordset ?? [];
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

export async function insertId(
  sqlText: string,
  values: readonly unknown[] = [],
  client?: DbClient,
): Promise<number> {
  const statement = `${sqlText.trim().replace(/;+$/, '')}; SELECT CAST(SCOPE_IDENTITY() AS INT)`;
  return Number(await scalar(statement, values, client));
}

export async function withTransaction<T>(
  work: (client: sql.Transaction) => Promise<T>,
): Promise<T> {
  const transaction = new sql.Transaction(await getPool());
  await transaction.begin();
  try {
    const result = await work(transaction);
    await transaction.commit();
    return result;
  } catch (error) {
    await transaction.rollback().catch(() => undefined);
    throw error;
  }
}
