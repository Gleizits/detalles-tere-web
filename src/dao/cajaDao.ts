import { insertId, query, queryOne, type DbClient } from '../db';
import { BusinessError } from '../http';
import { decimal, integer, nullableStr, type Row } from './util';

/** Equivalente a Modelo\Caja (propiedades en camelCase, sin métodos). */
export interface Caja {
  idCaja: number;
  idUsuario: number;
  montoInicial: number | null;
  montoFinal: number | null;
  montoReal: number | null;
  diferencia: number | null;
  fechaApertura: string | null;
  fechaCierre: string | null;
  nombreUsuario: string | null;
  esAbierta(): boolean;
}

/** Forma flexible usada al cerrar (PHP mutaba el objeto recibido). */
export type CajaData = Partial<Omit<Caja, 'esAbierta'>>;

const SQL_ABIERTA =
  'SELECT ID_CAJA, ID_USUARIO, MONTO_INICIAL, MONTO_FINAL, MONTO_REAL, '
  + 'DIFERENCIA, FECHA_APERTURA, FECHA_CIERRE '
  + 'FROM dbo.CAJA WHERE ID_USUARIO = ? AND FECHA_CIERRE IS NULL';

const SQL_ABRIR =
  'INSERT INTO dbo.CAJA (ID_USUARIO, MONTO_INICIAL, FECHA_APERTURA) '
  + 'VALUES (?, ?, SYSUTCDATETIME())';

const SQL_VENTAS_EFECTIVO =
  "SELECT ISNULL(SUM(TOTAL), 0) FROM dbo.VENTA "
  + "WHERE ID_CAJA = ? AND TIPO_PAGO = 'EFECTIVO' AND ESTADO = 'VENDIDO'";

// Ventas de productos (sin pagos de reserva): ID_PEDIDO IS NULL
const SQL_VENTAS_PROD_EFECTIVO =
  "SELECT ISNULL(SUM(TOTAL), 0) FROM dbo.VENTA "
  + "WHERE ID_CAJA = ? AND TIPO_PAGO = 'EFECTIVO' AND ESTADO = 'VENDIDO' "
  + 'AND ID_PEDIDO IS NULL';

// Pagos de reservas (adelantos/abonos) en efectivo: ID_PEDIDO IS NOT NULL
const SQL_PAGOS_RESERVA_EFECTIVO =
  "SELECT ISNULL(SUM(TOTAL), 0) FROM dbo.VENTA "
  + "WHERE ID_CAJA = ? AND TIPO_PAGO = 'EFECTIVO' AND ESTADO = 'VENDIDO' "
  + 'AND ID_PEDIDO IS NOT NULL';

const SQL_CERRAR =
  'UPDATE dbo.CAJA SET MONTO_FINAL = ?, MONTO_REAL = ?, DIFERENCIA = ?, '
  + 'FECHA_CIERRE = SYSUTCDATETIME() WHERE ID_CAJA = ?';

// TOP con parámetro no se puede preparar en SQL Server: el límite
// se inyecta como entero validado en historial(), igual que en PHP.
const SQL_HISTORIAL =
  "SELECT c.ID_CAJA, c.ID_USUARIO, u.NOMBRE + ' ' + u.APELLIDO AS NOMBRE_USUARIO, "
  + 'c.MONTO_INICIAL, c.MONTO_FINAL, c.MONTO_REAL, c.DIFERENCIA, '
  + 'c.FECHA_APERTURA, c.FECHA_CIERRE '
  + 'FROM dbo.CAJA c JOIN dbo.USUARIO u ON c.ID_USUARIO = u.ID_USUARIO '
  + 'WHERE c.ID_USUARIO = ? '
  + 'ORDER BY c.FECHA_APERTURA DESC';

function optionalDecimal(row: Row, key: string): number | null {
  const value = row[key];
  return value === null || value === undefined ? null : decimal(row, key);
}

/** mssql entrega fechas como Date; PHP entregaba 'Y-m-d H:i:s'. */
function fechaTexto(row: Row, key: string): string | null {
  const value = row[key];
  if (value === null || value === undefined) return null;
  if (!(value instanceof Date)) return String(value);
  const pad = (n: number): string => String(n).padStart(2, '0');
  return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())} `
    + `${pad(value.getHours())}:${pad(value.getMinutes())}:${pad(value.getSeconds())}`;
}

function mapCaja(row: Row): Caja {
  const caja: Caja = {
    idCaja: integer(row, 'ID_CAJA'),
    idUsuario: integer(row, 'ID_USUARIO'),
    montoInicial: optionalDecimal(row, 'MONTO_INICIAL'),
    montoFinal: optionalDecimal(row, 'MONTO_FINAL'),
    montoReal: optionalDecimal(row, 'MONTO_REAL'),
    diferencia: optionalDecimal(row, 'DIFERENCIA'),
    fechaApertura: fechaTexto(row, 'FECHA_APERTURA'),
    fechaCierre: fechaTexto(row, 'FECHA_CIERRE'),
    // la columna NOMBRE_USUARIO no está en todas las consultas
    nombreUsuario: nullableStr(row, 'NOMBRE_USUARIO'),
    esAbierta: () => false,
  };
  caja.esAbierta = () => caja.fechaCierre === null;
  return caja;
}

/** round() de PHP: mitad hacia afuera de cero, a 2 decimales. */
function round2(value: number): number {
  const sign = value < 0 ? -1 : 1;
  return (sign * Math.round(Math.abs(value) * 100 + Number.EPSILON)) / 100;
}

/** Acceso a datos de CAJA. 1 caja abierta por usuario; cierre manual con conteo. */
export class CajaDAO {
  constructor(private readonly client?: DbClient) {}

  /** Devuelve la caja abierta del usuario, o null si no tiene. */
  async cajaAbierta(idUsuario: number, client?: DbClient): Promise<Caja | null> {
    const row = await queryOne<Row>(SQL_ABIERTA, [idUsuario], client ?? this.client);
    return row ? mapCaja(row) : null;
  }

  /** Abre una caja nueva y devuelve su id. Lanza excepción si ya hay una abierta. */
  async abrir(idUsuario: number, montoInicial: number, client?: DbClient): Promise<number> {
    const id = await insertId(SQL_ABRIR, [idUsuario, montoInicial], client ?? this.client);
    if (!Number.isFinite(id) || id <= 0) {
      throw new BusinessError('No se pudo abrir la caja');
    }
    return id;
  }

  /**
   * Suma de ventas en efectivo de una caja (para monto esperado).
   * Incluye ventas de productos Y pagos de reserva (todo el efectivo que entra).
   */
  async ventasEfectivo(idCaja: number, client?: DbClient): Promise<number> {
    return this.sumar(idCaja, SQL_VENTAS_EFECTIVO, client);
  }

  /** Efectivo que entra por ventas de productos (sin reservas). */
  async ventasProductosEfectivo(idCaja: number, client?: DbClient): Promise<number> {
    return this.sumar(idCaja, SQL_VENTAS_PROD_EFECTIVO, client);
  }

  /** Efectivo que entra por pagos de reservas (adelantos/abonos). */
  async pagosReservasEfectivo(idCaja: number, client?: DbClient): Promise<number> {
    return this.sumar(idCaja, SQL_PAGOS_RESERVA_EFECTIVO, client);
  }

  private async sumar(idCaja: number, sqlText: string, client?: DbClient): Promise<number> {
    const row = await queryOne<Row>(sqlText, [idCaja], client ?? this.client);
    const value = row ? Object.values(row)[0] : undefined;
    const parsed = Number(value);
    return value === undefined || value === null || !Number.isFinite(parsed) ? 0 : parsed;
  }

  /**
   * Cierra la caja: guarda montoFinal (esperado), montoReal (conteo) y diferencia.
   * Como en PHP, muta `diferencia` en el objeto recibido.
   */
  async cerrar(caja: CajaData, client?: DbClient): Promise<void> {
    const montoFinal = caja.montoFinal ?? 0;
    const montoReal = caja.montoReal ?? 0;
    const diferencia = round2(montoReal - montoFinal);

    await query(
      SQL_CERRAR,
      [montoFinal, caja.montoReal ?? null, diferencia, caja.idCaja ?? 0],
      client ?? this.client,
    );
    caja.diferencia = diferencia;
  }

  /** Historial reciente de cajas del usuario. */
  async historial(idUsuario: number, limite: number, client?: DbClient): Promise<Caja[]> {
    const tope = Math.max(1, Math.min(100, Math.trunc(limite) || 1));
    const sql = SQL_HISTORIAL.replace('SELECT c.ID_CAJA', `SELECT TOP ${tope} c.ID_CAJA`);
    const rows = await query<Row>(sql, [idUsuario], client ?? this.client);
    return rows.map(mapCaja);
  }
}
