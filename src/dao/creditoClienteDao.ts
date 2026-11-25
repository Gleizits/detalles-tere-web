import { query, scalar, type DbClient } from '../db';
import { BusinessError } from '../http';

export type { CreditoCliente } from './creditoDao';

const SQL_CREAR =
  'INSERT INTO dbo.CREDITO_CLIENTE ' +
  '(ID_CLIENTE, MONTO, ESTADO, FECHA, FECHA_VENCIMIENTO, ID_USUARIO) ' +
  "VALUES (?, ?, 'ACTIVO', SYSUTCDATETIME(), " +
  'DATEADD(MONTH, 3, SYSUTCDATETIME()), ?)';

const SQL_SALDO =
  'SELECT COALESCE(SUM(MONTO), 0) FROM dbo.CREDITO_CLIENTE WITH (UPDLOCK, ROWLOCK) ' +
  "WHERE ID_CLIENTE = ? AND ESTADO = 'ACTIVO' " +
  'AND (FECHA_VENCIMIENTO IS NULL OR FECHA_VENCIMIENTO >= SYSUTCDATETIME())';

const SQL_APLICAR =
  "UPDATE dbo.CREDITO_CLIENTE SET ESTADO = 'CANJEADO', ID_VENTA = ? " +
  "WHERE ID_CLIENTE = ? AND ESTADO = 'ACTIVO' " +
  'AND (FECHA_VENCIMIENTO IS NULL OR FECHA_VENCIMIENTO >= SYSUTCDATETIME())';

// El esquema PHP aún no trae la migración que agrega ID_VENTA; si falta,
// se omite esa columna pero se conserva la regla de canje.
const SQL_APLICAR_SIN_VENTA =
  "UPDATE dbo.CREDITO_CLIENTE SET ESTADO = 'CANJEADO' " +
  "WHERE ID_CLIENTE = ? AND ESTADO = 'ACTIVO' " +
  'AND (FECHA_VENCIMIENTO IS NULL OR FECHA_VENCIMIENTO >= SYSUTCDATETIME())';

let tieneIdVentaCache: boolean | null = null;

function isDbClient(value: unknown): value is DbClient {
  return (
    typeof value === 'object' &&
    value !== null &&
    typeof (value as { request?: unknown }).request === 'function'
  );
}

function centavos(value: number): number {
  return Math.round((value + Number.EPSILON) * 100);
}

function dosDecimales(value: number): string {
  return (centavos(value) / 100).toFixed(2);
}

/**
 * Registra saldos a favor y ejecuta canjes completos de crédito.
 * Las operaciones aceptan el cliente de la transacción abierta por
 * PedidoDAO/VentaDAO (como primer argumento, al final, o en el constructor).
 */
export class CreditoClienteDAO {
  constructor(private readonly client?: DbClient) {}

  /** Convierte un adelanto no reembolsable en saldo a favor vigente. */
  async crearDesdeCancelacion(
    idCliente: number,
    monto: number | null,
    idUsuario: number,
    client?: DbClient | null,
  ): Promise<void>;
  /** Forma PHP: el primero es el cliente de la transacción activa. */
  async crearDesdeCancelacion(
    client: DbClient | null,
    idCliente: number,
    monto: number | null,
    idUsuario: number,
  ): Promise<void>;
  async crearDesdeCancelacion(
    first: DbClient | number | null,
    second: number | null,
    third: number | DbClient | null,
    fourth?: number | DbClient | null,
  ): Promise<void> {
    let client = this.client;
    let idCliente: number;
    let monto: number | null;
    let idUsuario: number;
    if (isDbClient(first)) {
      client = first;
      idCliente = Number(second);
      monto = typeof third === 'number' ? third : null;
      idUsuario = Number(fourth);
    } else if (first === null) {
      idCliente = Number(second);
      monto = typeof third === 'number' ? third : null;
      idUsuario = Number(fourth);
    } else {
      idCliente = Number(first);
      monto = second;
      idUsuario = typeof third === 'number' ? third : 0;
      if (isDbClient(third)) client = third;
      if (isDbClient(fourth)) client = fourth;
    }

    if (monto === null || monto <= 0) {
      return;
    }

    await query(SQL_CREAR, [idCliente, monto, idUsuario], client);
  }

  /**
   * Marca todos los créditos vigentes como canjeados.
   * El saldo acumulado debe coincidir exactamente con el total de la venta.
   */
  async aplicar(
    idCliente: number,
    total: number,
    idVenta: number,
    client?: DbClient | null,
  ): Promise<void>;
  /** Forma PHP: el primero es el cliente de la transacción activa. */
  async aplicar(
    client: DbClient | null,
    idCliente: number,
    total: number,
    idVenta: number,
  ): Promise<void>;
  async aplicar(
    first: DbClient | number | null,
    second: number,
    third: number,
    fourth?: number | DbClient | null,
  ): Promise<void> {
    let client = this.client;
    let idCliente: number;
    let total: number;
    let idVenta: number;
    if (isDbClient(first)) {
      client = first;
      idCliente = Number(second);
      total = Number(third);
      idVenta = Number(fourth);
    } else if (first === null) {
      idCliente = Number(second);
      total = Number(third);
      idVenta = Number(fourth);
    } else {
      idCliente = Number(first);
      total = Number(second);
      idVenta = Number(third);
      if (isDbClient(fourth)) client = fourth;
    }

    const rawSaldo = await scalar<number | null>(SQL_SALDO, [idCliente], client);
    const saldo = Number(rawSaldo ?? 0);

    if (centavos(saldo) !== centavos(total)) {
      throw new BusinessError(
        `El saldo a favor vigente del cliente es S/ ${dosDecimales(saldo)}. El canje debe usarlo completo.`,
      );
    }

    if (await this.tieneIdVenta(client)) {
      await query(SQL_APLICAR, [idVenta, idCliente], client);
      return;
    }
    await query(SQL_APLICAR_SIN_VENTA, [idCliente], client);
  }

  private async tieneIdVenta(client?: DbClient): Promise<boolean> {
    if (tieneIdVentaCache === null) {
      const valor = await scalar<number | null>(
        "SELECT COL_LENGTH('dbo.CREDITO_CLIENTE', 'ID_VENTA')",
        [],
        client,
      );
      tieneIdVentaCache = valor !== null && valor !== undefined;
    }
    return tieneIdVentaCache;
  }
}
