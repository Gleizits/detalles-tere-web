import { insertId, query, queryOne, withTransaction, type DbClient } from '../db';
import { BusinessError } from '../http';
import { ClienteDAO } from './clienteDao';
import { CreditoClienteDAO } from './creditoClienteDao';
import { DetalleVenta, type DetalleVentaData } from './ventaDao';
import { decimal, integer, nullableStr, str, type Row } from './util';

const SQL_SELECT =
  'SELECT pd.ID_PEDIDO, pd.ID_CLIENTE, pd.NOMBRE_PAGA, pd.NOMBRE_RECOGE, pd.CELULAR, ' +
  'pd.FECHA_RECOJO, pd.HORA_RECOJO, pd.ADELANTO, pd.TOTAL, pd.ESTADO, pd.ESTADO_PRODUCCION, ' +
  'pd.ID_USUARIO, pd.FECHA_CREACION, pd.FECHA_LIMITE_CANJE, pd.FECHA_LIMITE_RECOJO, ' +
  'pd.FECHA_LIMITE_PAGO, pd.FECHA_PAGO_TOTAL, cl.NOMBRE AS NOMBRE_CLIENTE ' +
  'FROM dbo.PEDIDOS pd LEFT JOIN dbo.CLIENTE cl ON pd.ID_CLIENTE = cl.ID_CLIENTE ';

const SQL_LISTAR =
  SQL_SELECT +
  'WHERE (? IS NULL OR pd.NOMBRE_PAGA LIKE ? OR pd.CELULAR LIKE ? ' +
  "       OR ISNULL(pd.NOMBRE_RECOGE, '') LIKE ?) " +
  'AND (? IS NULL OR pd.ID_USUARIO = ?) ' +
  'ORDER BY pd.FECHA_CREACION DESC';

const SQL_BUSCAR = SQL_SELECT + 'WHERE pd.ID_PEDIDO = ?';

const SQL_LINEAS =
  'SELECT dp.ID_INVENTARIO, COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) AS NOMBRE, ' +
  'dp.CANTIDAD, dp.COSTO_UNITARIO, dp.SUBTOTAL ' +
  'FROM dbo.DETALLE_PEDIDO dp ' +
  'JOIN dbo.INVENTARIO i ON dp.ID_INVENTARIO = i.ID_INVENTARIO ' +
  'LEFT JOIN dbo.PRODUCTO p ON i.ID_PRODUCTO = p.ID_PRODUCTO ' +
  'LEFT JOIN dbo.MATERIAL m ON i.ID_MATERIAL = m.ID_MATERIAL ' +
  'LEFT JOIN dbo.ARREGLO a ON i.ID_DETALLE = a.ID_DETALLE ' +
  'WHERE dp.ID_PEDIDO = ? ORDER BY NOMBRE';

const SQL_INSERTAR =
  'INSERT INTO dbo.PEDIDOS (ID_CLIENTE, NOMBRE_PAGA, NOMBRE_RECOGE, CELULAR, ' +
  'FECHA_RECOJO, HORA_RECOJO, ADELANTO, TOTAL, ESTADO, ESTADO_PRODUCCION, ID_USUARIO, ' +
  'FECHA_CREACION, FECHA_LIMITE_CANJE, FECHA_LIMITE_RECOJO, FECHA_LIMITE_PAGO, FECHA_PAGO_TOTAL) ' +
  'OUTPUT INSERTED.ID_PEDIDO ' +
  'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' +
  'SYSUTCDATETIME(), DATEADD(MONTH, 3, SYSUTCDATETIME()), DATEADD(MONTH, 2, ?), ' +
  'DATEADD(MONTH, 1, SYSUTCDATETIME()), ' +
  "CASE WHEN ? = 'COMPLETO' THEN SYSUTCDATETIME() ELSE NULL END)";

const SQL_INSERTAR_DETALLE =
  'INSERT INTO dbo.DETALLE_PEDIDO (ID_PEDIDO, ID_INVENTARIO, CANTIDAD, COSTO_UNITARIO, SUBTOTAL) ' +
  'VALUES (?, ?, ?, ?, ?)';

const SQL_DESCONTAR_STOCK =
  'UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT - ? ' +
  'WHERE ID_INVENTARIO = ? AND STOCK_ACT >= ?';

const SQL_MOVIMIENTO =
  'INSERT INTO dbo.MOVIMIENTO_INVENTARIO (ID_USUARIO, ID_INVENTARIO, TIPO_MOVIMIENTO, ' +
  'CANTIDAD, FECHA_MOVIMIENTO) VALUES (?, ?, ?, ?, SYSUTCDATETIME())';

const SQL_PAGO =
  "INSERT INTO dbo.VENTA (ID_CAJA, ID_PEDIDO, TOTAL, TIPO_PAGO, ESTADO, FECHA_VENTA) " +
  "VALUES (?, ?, ?, ?, 'VENDIDO', SYSUTCDATETIME())";

const SQL_LEER_CANCELAR =
  'SELECT ID_PEDIDO, ID_CLIENTE, ADELANTO, ESTADO, ESTADO_PRODUCCION ' +
  'FROM dbo.PEDIDOS WITH (UPDLOCK, ROWLOCK) WHERE ID_PEDIDO = ?';

const SQL_DEVOLVER_STOCK =
  'UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT + ? WHERE ID_INVENTARIO = ?';

const SQL_LEER_ABONO = 'SELECT TOTAL, ADELANTO FROM dbo.PEDIDOS WHERE ID_PEDIDO = ?';

const SQL_ACTUALIZAR_ABONO =
  'UPDATE dbo.PEDIDOS SET ADELANTO = ADELANTO + ?, ' +
  "ESTADO = CASE WHEN ADELANTO + ? >= TOTAL THEN 'COMPLETO' ELSE ESTADO END, " +
  'FECHA_PAGO_TOTAL = CASE WHEN ADELANTO + ? >= TOTAL ' +
  'THEN SYSUTCDATETIME() ELSE FECHA_PAGO_TOTAL END ' +
  "WHERE ID_PEDIDO = ? AND ESTADO IN ('PENDIENTE', 'COMPLETO')";

const SQL_ACTUALIZAR_CANCELAR =
  "UPDATE dbo.PEDIDOS SET ESTADO = 'CANCELADO', ESTADO_PRODUCCION = 'CANCELADO' " +
  "WHERE ID_PEDIDO = ? AND ESTADO_PRODUCCION = 'PENDIENTE'";

const SQL_MARCAR_HECHO =
  "UPDATE dbo.PEDIDOS SET ESTADO_PRODUCCION = 'HECHO' " +
  "WHERE ID_PEDIDO = ? AND ESTADO_PRODUCCION = 'PENDIENTE'";

const SQL_RECOGER =
  "UPDATE dbo.PEDIDOS SET ESTADO = 'RECOGIDO' " +
  "WHERE ID_PEDIDO = ? AND ADELANTO >= TOTAL AND ESTADO = 'COMPLETO'";

function round2(value: number): number {
  return Math.round((value + Number.EPSILON) * 100) / 100;
}

function fechaTexto(row: Row, key: string): string | null {
  const value = row[key];
  if (value === null || value === undefined) return null;
  return value instanceof Date ? value.toISOString() : String(value);
}

function fechaSoloDia(row: Row, key: string): string | null {
  const value = fechaTexto(row, key);
  return value === null ? null : value.slice(0, 10);
}

function nullableDecimal(row: Row, key: string): number | null {
  const value = row[key];
  return value === null || value === undefined ? null : decimal(row, key);
}

/** Igual que `query`, pero devuelve @@ROWCOUNT para comprobar actualizaciones. */
async function executeCount(
  sqlText: string,
  values: readonly unknown[] = [],
  client?: DbClient,
): Promise<number> {
  const row = await queryOne<Row>(`${sqlText}; SELECT @@ROWCOUNT AS AFECTADOS`, values, client);
  return row ? Number(decimal(row, 'AFECTADOS')) : 0;
}

/** Datos de un pedido reservado (equivalente a las columnas de dbo.PEDIDOS). */
export interface PedidoData {
  idPedido?: number;
  idCliente?: number | null;
  nombrePaga?: string;
  nombreRecoge?: string | null;
  celular?: string;
  fechaRecojo?: string | null;
  horaRecojo?: string | null;
  adelanto?: number | null;
  total?: number | null;
  estado?: string;
  estadoProduccion?: string;
  idUsuario?: number;
  fechaCreacion?: string | null;
  fechaLimiteCanje?: string | null;
  fechaLimiteRecojo?: string | null;
  fechaLimitePago?: string | null;
  fechaPagoTotal?: string | null;
  nombreCliente?: string;
  lineas?: DetalleVentaData[];
}

/** Pedido reservado, con los métodos de apoyo del modelo PHP. */
export class Pedido implements PedidoData {
  idPedido: number;
  idCliente: number | null;
  nombrePaga: string;
  nombreRecoge: string | null;
  celular: string;
  fechaRecojo: string | null;
  horaRecojo: string | null;
  adelanto: number | null;
  total: number | null;
  estado: string;
  estadoProduccion: string;
  idUsuario: number;
  fechaCreacion: string | null;
  fechaLimiteCanje: string | null;
  fechaLimiteRecojo: string | null;
  fechaLimitePago: string | null;
  fechaPagoTotal: string | null;
  nombreCliente: string;
  lineas: DetalleVenta[];

  constructor(data: PedidoData = {}) {
    this.idPedido = data.idPedido ?? 0;
    this.idCliente = data.idCliente ?? null;
    this.nombrePaga = data.nombrePaga ?? '';
    this.nombreRecoge = data.nombreRecoge ?? null;
    this.celular = data.celular ?? '';
    this.fechaRecojo = data.fechaRecojo ?? null;
    this.horaRecojo = data.horaRecojo ?? null;
    this.adelanto = data.adelanto ?? null;
    this.total = data.total ?? null;
    this.estado = data.estado ?? '';
    this.estadoProduccion = data.estadoProduccion ?? '';
    this.idUsuario = data.idUsuario ?? 0;
    this.fechaCreacion = data.fechaCreacion ?? null;
    this.fechaLimiteCanje = data.fechaLimiteCanje ?? null;
    this.fechaLimiteRecojo = data.fechaLimiteRecojo ?? null;
    this.fechaLimitePago = data.fechaLimitePago ?? null;
    this.fechaPagoTotal = data.fechaPagoTotal ?? null;
    this.nombreCliente = data.nombreCliente ?? '';
    this.lineas = (data.lineas ?? []) as DetalleVenta[];
  }

  /** Cuánto falta por pagar: TOTAL - pagado. */
  faltante(): number {
    if (this.total === null) return 0;
    return round2(this.total - (this.adelanto ?? 0));
  }

  /** true si ya se pagó el total. */
  pagadoTotal(): boolean {
    return this.total !== null && this.adelanto !== null && this.adelanto >= this.total;
  }
}

function mapLinea(row: Row): DetalleVenta {
  const linea = new DetalleVenta();
  linea.idInventario = integer(row, 'ID_INVENTARIO');
  linea.nombre = str(row, 'NOMBRE');
  linea.cantidad = integer(row, 'CANTIDAD');
  linea.costoUnitario = nullableDecimal(row, 'COSTO_UNITARIO');
  linea.subtotal = nullableDecimal(row, 'SUBTOTAL');
  return linea;
}

function mapear(row: Row): Pedido {
  return new Pedido({
    idPedido: integer(row, 'ID_PEDIDO'),
    idCliente: row.ID_CLIENTE === null || row.ID_CLIENTE === undefined ? null : integer(row, 'ID_CLIENTE'),
    nombrePaga: str(row, 'NOMBRE_PAGA'),
    nombreRecoge: nullableStr(row, 'NOMBRE_RECOGE'),
    celular: str(row, 'CELULAR'),
    fechaRecojo: fechaSoloDia(row, 'FECHA_RECOJO'),
    horaRecojo: nullableStr(row, 'HORA_RECOJO'),
    adelanto: nullableDecimal(row, 'ADELANTO'),
    total: nullableDecimal(row, 'TOTAL'),
    estado: str(row, 'ESTADO'),
    estadoProduccion: str(row, 'ESTADO_PRODUCCION'),
    idUsuario: integer(row, 'ID_USUARIO'),
    fechaCreacion: fechaTexto(row, 'FECHA_CREACION'),
    fechaLimiteCanje: fechaTexto(row, 'FECHA_LIMITE_CANJE'),
    fechaLimiteRecojo: fechaTexto(row, 'FECHA_LIMITE_RECOJO'),
    fechaLimitePago: fechaTexto(row, 'FECHA_LIMITE_PAGO'),
    fechaPagoTotal: fechaTexto(row, 'FECHA_PAGO_TOTAL'),
    nombreCliente: str(row, 'NOMBRE_CLIENTE'),
  });
}

/** Acceso a datos de PEDIDOS (reservados). */
export class PedidoDAO {
  constructor(private readonly client?: DbClient) {}

  /**
   * Crea un pedido reservado: cliente, cabecera, líneas, stock apartado,
   * movimiento de SALIDA y el pago inicial en VENTA.
   */
  async crear(
    p: PedidoData,
    lineas: readonly DetalleVentaData[],
    idCaja: number,
    metodoPago: string,
  ): Promise<number> {
    // El cliente se confirma aparte: un cliente huérfano es inocuo.
    const idCliente = await new ClienteDAO(this.client).buscarOCrear(p.celular ?? '', p.nombrePaga ?? '');

    return withTransaction(async (client) => {
      const idPedido = await this.insertarPedido(client, p, idCliente);
      for (const linea of lineas) {
        await this.insertarDetalle(client, idPedido, linea);
        await this.descontarStock(client, linea.idInventario ?? 0, linea.cantidad ?? 0);
        await this.insertarMovimiento(
          client,
          p.idUsuario ?? 0,
          linea.idInventario ?? 0,
          linea.cantidad ?? 0,
          'SALIDA',
        );
      }
      if (p.adelanto !== null && p.adelanto !== undefined && p.adelanto > 0) {
        await this.insertarPago(client, idCaja, idPedido, p.adelanto, metodoPago);
      }
      return idPedido;
    });
  }

  /** Registra un abono: suma al ADELANTO y crea el pago en VENTA. */
  async abono(
    idPedido: number,
    monto: number,
    idCaja: number,
    metodo: string,
    idUsuario: number,
  ): Promise<void> {
    await withTransaction(async (client) => {
      const fila = await queryOne<Row>(SQL_LEER_ABONO, [idPedido], client);
      if (!fila) {
        throw new BusinessError('El pedido no existe');
      }

      const total = decimal(fila, 'TOTAL');
      const adelanto =
        fila.ADELANTO === null || fila.ADELANTO === undefined ? 0 : decimal(fila, 'ADELANTO');
      const saldo = round2(total - adelanto);
      // Tope: el abono nunca puede superar lo que falta por pagar (H4).
      if (round2(monto) > saldo) {
        throw new BusinessError('El abono supera el saldo pendiente del pedido');
      }

      const afectadas = await executeCount(
        SQL_ACTUALIZAR_ABONO,
        [monto, monto, monto, idPedido],
        client,
      );
      if (afectadas === 0) {
        // Cancelado/recogido: no se registra el pago en VENTA.
        throw new BusinessError('El pedido ya no admite abonos (cancelado o entregado)');
      }

      await this.insertarPago(client, idCaja, idPedido, monto, metodo);
      void idUsuario; // el PHP lo recibe pero no lo usa en el abono
    });
  }

  /** Marca el pedido como HECHO (punto de no retorno). */
  async marcarHecho(idPedido: number): Promise<void> {
    await this.ejecutarEstado(SQL_MARCAR_HECHO, idPedido);
  }

  /** Marca el pedido como RECOGIDO (entregado). Debe estar pagado por completo. */
  async recoger(idPedido: number): Promise<void> {
    await this.ejecutarEstado(SQL_RECOGER, idPedido);
  }

  /**
   * Cancela antes de producción, devuelve las unidades apartadas al
   * inventario y convierte el adelanto en saldo a favor, sin reembolso.
   */
  async cancelar(idPedido: number, idUsuario: number): Promise<void> {
    await withTransaction(async (client) => {
      const pedido = await this.leerParaCancelar(client, idPedido);
      if (pedido === null) {
        throw new BusinessError('El pedido no existe.');
      }
      if (
        pedido.estadoProduccion !== 'PENDIENTE' ||
        pedido.estado === 'CANCELADO' ||
        pedido.estado === 'RECOGIDO'
      ) {
        throw new BusinessError('Solo se puede cancelar un pedido antes de iniciar producción.');
      }

      for (const linea of await this.lineas(client, idPedido)) {
        await this.devolverStock(client, linea.idInventario, linea.cantidad);
        await this.insertarMovimiento(client, idUsuario, linea.idInventario, linea.cantidad, 'ENTRADA');
      }

      const afectadas = await executeCount(SQL_ACTUALIZAR_CANCELAR, [idPedido], client);
      if (afectadas === 0) {
        throw new BusinessError('El pedido cambió de estado y ya no puede cancelarse.');
      }

      await new CreditoClienteDAO(client).crearDesdeCancelacion(
        client,
        pedido.idCliente ?? 0,
        pedido.adelanto,
        idUsuario,
      );
    });
  }

  /** Lista pedidos; q filtra por nombre, celular o quién recoge (todos). */
  async listar(q: string | null = null, idUsuario: number | null = null): Promise<Pedido[]> {
    const filtro = q === null || q.trim() === '' ? null : `%${q.trim()}%`;
    const rows = await query<Row>(
      SQL_LISTAR,
      [filtro, filtro, filtro, filtro, idUsuario, idUsuario],
      this.client,
    );
    return rows.map(mapear);
  }

  /** Busca un pedido por id con sus líneas, o null si no existe. */
  async buscarPorId(idPedido: number): Promise<Pedido | null> {
    const fila = await queryOne<Row>(SQL_BUSCAR, [idPedido], this.client);
    if (!fila) return null;

    const pedido = mapear(fila);
    pedido.lineas = await this.lineas(this.client, idPedido);
    return pedido;
  }

  private async insertarPedido(client: DbClient, p: PedidoData, idCliente: number): Promise<number> {
    return insertId(
      SQL_INSERTAR,
      [
        idCliente,
        p.nombrePaga,
        p.nombreRecoge,
        p.celular,
        p.fechaRecojo,
        p.horaRecojo,
        p.adelanto,
        p.total,
        p.estado,
        p.estadoProduccion,
        p.idUsuario,
        p.fechaRecojo, // base de FECHA_LIMITE_RECOJO
        p.estado, // decide FECHA_PAGO_TOTAL
      ],
      client,
    );
  }

  private async insertarDetalle(
    client: DbClient,
    idPedido: number,
    linea: DetalleVentaData,
  ): Promise<void> {
    await query(
      SQL_INSERTAR_DETALLE,
      [idPedido, linea.idInventario ?? 0, linea.cantidad ?? 0, linea.costoUnitario, linea.subtotal],
      client,
    );
  }

  private async descontarStock(client: DbClient, idInventario: number, cantidad: number): Promise<void> {
    const afectadas = await executeCount(SQL_DESCONTAR_STOCK, [cantidad, idInventario, cantidad], client);
    if (afectadas === 0) {
      throw new BusinessError('Stock insuficiente para apartar el ítem');
    }
  }

  private async insertarMovimiento(
    client: DbClient,
    idUsuario: number,
    idInventario: number,
    cantidad: number,
    tipo: string,
  ): Promise<void> {
    await query(SQL_MOVIMIENTO, [idUsuario, idInventario, tipo, cantidad], client);
  }

  private async insertarPago(
    client: DbClient,
    idCaja: number,
    idPedido: number,
    monto: number,
    metodo: string,
  ): Promise<void> {
    await query(SQL_PAGO, [idCaja, idPedido, monto, metodo], client);
  }

  private async ejecutarEstado(sql: string, idPedido: number): Promise<void> {
    try {
      await query(sql, [idPedido], this.client);
    } catch (error) {
      if (error instanceof BusinessError) throw error;
      throw new BusinessError(
        'No se pudo actualizar el pedido (revísalo e inténtalo de nuevo)',
        { cause: error },
      );
    }
  }

  private async leerParaCancelar(client: DbClient, idPedido: number): Promise<Pedido | null> {
    const fila = await queryOne<Row>(SQL_LEER_CANCELAR, [idPedido], client);
    if (!fila) return null;

    return new Pedido({
      idPedido: integer(fila, 'ID_PEDIDO'),
      idCliente: integer(fila, 'ID_CLIENTE'),
      adelanto: nullableDecimal(fila, 'ADELANTO'),
      estado: str(fila, 'ESTADO'),
      estadoProduccion: str(fila, 'ESTADO_PRODUCCION'),
    });
  }

  private async devolverStock(client: DbClient, idInventario: number, cantidad: number): Promise<void> {
    const afectadas = await executeCount(SQL_DEVOLVER_STOCK, [cantidad, idInventario], client);
    if (afectadas === 0) {
      throw new BusinessError('No se encontró el artículo reservado.');
    }
  }

  private async lineas(client: DbClient | undefined, idPedido: number): Promise<DetalleVenta[]> {
    const rows = await query<Row>(SQL_LINEAS, [idPedido], client);
    return rows.map(mapLinea);
  }
}
