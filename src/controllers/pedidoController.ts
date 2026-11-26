import type { Request, Response } from 'express';
import { config } from '../config';
import { CajaDAO } from '../dao/cajaDao';
import { InventarioDAO, type InventarioItem } from '../dao/inventarioDao';
import { PedidoDAO, Pedido, type PedidoData } from '../dao/pedidoDao';
import { DetalleVenta, type DetalleVentaData } from '../dao/ventaDao';
import { decimalParam, HttpError, intParam, param } from '../http';
import { paginar } from '../pagination';
import { currentUser, flash } from '../session';
import { redirect, renderPage } from '../view';
import type { Usuario } from '../models/usuario';

const K_CARRITO = 'pedidoCarrito';
const MILLON = 1_000_000.0;

type ItemConStockBajo = InventarioItem & { esStockBajo(): boolean };

function conEsStockBajo(item: InventarioItem): ItemConStockBajo {
  return { ...item, esStockBajo: () => item.stockAct <= item.stockMin };
}

function usuario(req: Request): Usuario {
  const user = currentUser(req);
  if (user === null) throw new HttpError(403);
  return user;
}

function esAdmin(req: Request): boolean {
  const user = currentUser(req);
  return user !== null && user.esAdmin();
}

function tieneClave(req: Request, clave: string): boolean {
  return Object.prototype.hasOwnProperty.call(req.query, clave);
}

function redondea(value: number): number {
  return Math.round((value + Number.EPSILON) * 100) / 100;
}

function partesFecha(fecha: Date): Record<string, string> {
  const partes = new Intl.DateTimeFormat('en-US', {
    timeZone: config.timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(fecha);
  const salida: Record<string, string> = {};
  for (const parte of partes) salida[parte.type] = parte.value;
  return salida;
}

function hoy(): string {
  const p = partesFecha(new Date());
  return `${p.year}-${p.month}-${p.day}`;
}

function ahora(): string {
  const p = partesFecha(new Date());
  return `${p.year}-${p.month}-${p.day} ${p.hour}:${p.minute}:${p.second}`;
}

function sumarMeses(fechaTxt: string, meses: number): string {
  const [anio, mes, dia] = fechaTxt.split('-').map((parte) => Number(parte));
  const fecha = new Date(Date.UTC(anio, mes - 1 + meses, dia));
  const pad = (valor: number): string => String(valor).padStart(2, '0');
  return `${fecha.getUTCFullYear()}-${pad(fecha.getUTCMonth() + 1)}-${pad(fecha.getUTCDate())} 00:00:00`;
}

function sumarMesesAhora(meses: number): string {
  const fecha = ahora();
  return `${sumarMeses(fecha.slice(0, 10), meses).slice(0, 10)} ${fecha.slice(11)}`;
}

function fechaValida(texto: string): string | null {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(texto)) return null;
  const [anio, mes, dia] = texto.split('-').map((parte) => Number(parte));
  const fecha = new Date(Date.UTC(anio, mes - 1, dia));
  return (
    fecha.getUTCFullYear() === anio &&
    fecha.getUTCMonth() === mes - 1 &&
    fecha.getUTCDate() === dia
  )
    ? texto
    : null;
}

/** El carrito vive en la sesión (clave pedidoCarrito), igual que en PHP. */
function carrito(req: Request): DetalleVenta[] {
  const guardado = req.sessionState[K_CARRITO];
  if (!Array.isArray(guardado)) {
    req.sessionState[K_CARRITO] = [];
    return [];
  }

  // Al serializarse en la cookie se pierden los métodos del modelo: se rehidrata.
  return (guardado as DetalleVentaData[]).map((dato) => {
    const linea = new DetalleVenta(
      Number(dato.idInventario ?? 0),
      String(dato.nombre ?? ''),
      Number(dato.cantidad ?? 0),
      dato.costoUnitario ?? null,
      Number(dato.stockDisponible ?? 0),
    );
    linea.subtotal = dato.subtotal ?? null;
    return linea;
  });
}

function guardarCarrito(req: Request, lineas: DetalleVenta[]): void {
  req.sessionState[K_CARRITO] = lineas.map((linea) => ({
    idInventario: linea.idInventario,
    nombre: linea.nombre,
    cantidad: linea.cantidad,
    costoUnitario: linea.costoUnitario,
    subtotal: linea.subtotal,
    stockDisponible: linea.stockDisponible,
  }));
}

function totalCarrito(lineas: DetalleVenta[]): number {
  return redondea(lineas.reduce((suma, linea) => suma + Number(linea.subtotal ?? 0), 0));
}

// -------------------------------------------------------------------
// GET: lista / formulario nuevo / detalle
// -------------------------------------------------------------------

export async function index(req: Request, res: Response): Promise<void> {
  if (tieneClave(req, 'id')) {
    await mostrarDetalle(req, res, param(req, 'id'));
    return;
  }
  if (tieneClave(req, 'nuevo')) {
    await mostrarFormulario(req, res);
    return;
  }
  await mostrarLista(req, res);
}

async function mostrarLista(req: Request, res: Response): Promise<void> {
  const q = param(req, 'q');
  const user = usuario(req);
  const idUsuario = user.esAdmin() ? null : user.id;

  const pedidos = await new PedidoDAO().listar(q, idUsuario);
  const pagina = paginar(pedidos, req);

  await renderPage(req, res, 'pedidos', 'Pedidos reservados', '/pedidos', {
    pedidos: pagina.items,
    q,
    paginacion: pagina,
  });
}

async function mostrarFormulario(req: Request, res: Response): Promise<void> {
  const q = param(req, 'q');
  const todos = (await new InventarioDAO().listar(q)).map(conEsStockBajo);
  const pagina = paginar(todos, req);
  const items = pagina.items;

  // Un empleado no ve costos de compra.
  if (!esAdmin(req)) {
    for (const item of items) item.precioCompra = null;
  }

  const lineas = carrito(req);

  await renderPage(req, res, 'pedido_form', 'Nuevo pedido reservado', '/pedidos', {
    items,
    q,
    paginacion: pagina,
    carrito: lineas,
    totalCarrito: totalCarrito(lineas),
    caja: await new CajaDAO().cajaAbierta(usuario(req).id),
    hoy: hoy(),
  });
}

async function mostrarDetalle(req: Request, res: Response, idTxt: string): Promise<void> {
  if (!/^\d+$/.test(idTxt)) {
    redirect(req, res, '/pedidos');
    return;
  }
  const id = Number.parseInt(idTxt, 10);
  const pedido = await new PedidoDAO().buscarPorId(id);

  if (pedido === null) {
    flash(req, 'error', 'Ese pedido no existe.', true);
    redirect(req, res, '/pedidos');
    return;
  }
  if (!esAdmin(req) && pedido.idUsuario !== usuario(req).id) {
    flash(req, 'error', 'Solo puedes ver y modificar tus propios pedidos.', true);
    redirect(req, res, '/pedidos');
    return;
  }

  await renderPage(req, res, 'pedido_detalle', `Pedido #${pedido.idPedido}`, '/pedidos', {
    pedido,
    caja: await new CajaDAO().cajaAbierta(usuario(req).id),
  });
}

// -------------------------------------------------------------------
// POST: acciones
// -------------------------------------------------------------------

export async function procesar(req: Request, res: Response): Promise<void> {
  const accion = param(req, 'accion');
  let destino = '/pedidos';

  try {
    switch (accion) {
      case 'agregar':
        await agregar(req, res);
        destino = '/pedidos?nuevo=1';
        break;

      case 'eliminar':
        eliminar(req, res);
        destino = '/pedidos?nuevo=1';
        break;

      case 'vaciar':
        delete req.sessionState[K_CARRITO];
        destino = '/pedidos?nuevo=1';
        break;

      case 'crear':
        // crear() termina siempre con una redirección.
        await crear(req, res);
        return;

      case 'abono': {
        const id = intParam(req, 'idPedido', -1);
        await validarPropiedadPedidoId(req, id);
        await abono(req, res, id);
        destino = `/pedidos?id=${id}`;
        break;
      }

      case 'hecho': {
        const id = intParam(req, 'idPedido', -1);
        await validarPropiedadPedidoId(req, id);
        await new PedidoDAO().marcarHecho(id);
        flash(req, 'aviso', 'Pedido marcado como HECHO. Ya no se puede cancelar; se cobrará el total.');
        destino = `/pedidos?id=${id}`;
        break;
      }

      case 'cancelar': {
        const id = intParam(req, 'idPedido', -1);
        await validarPropiedadPedidoId(req, id);
        await new PedidoDAO().cancelar(id, usuario(req).id);
        flash(
          req,
          'aviso',
          'Pedido cancelado. El stock volvió al inventario y el adelanto '
            + 'quedó como saldo a favor; no se devuelve en efectivo.',
        );
        destino = `/pedidos?id=${id}`;
        break;
      }

      case 'recoger': {
        const id = intParam(req, 'idPedido', -1);
        await validarPropiedadPedidoId(req, id);
        await new PedidoDAO().recoger(id);
        flash(req, 'aviso', 'Pedido entregado (RECOGIDO).');
        destino = `/pedidos?id=${id}`;
        break;
      }

      default:
        break;
    }
  } catch (error) {
    flash(req, 'error', mensajeAmigable(error), true);
  }

  redirect(req, res, destino);
}

// -------------------------------------------------------------------
// Acciones del carrito
// -------------------------------------------------------------------

async function agregar(req: Request, res: Response): Promise<void> {
  const idInventario = intParam(req, 'idInventario', -1);
  const cantidad = intParam(req, 'cantidad', 1);

  if (idInventario < 0 || cantidad < 1) {
    flash(req, 'error', 'Cantidad inválida.', true);
    return;
  }

  const item = await new InventarioDAO().buscarPorId(idInventario);
  if (item === null || item.precioVenta === null) {
    flash(req, 'error', 'Ese producto no está disponible para reserva.', true);
    return;
  }

  const lineas = carrito(req);
  let enCarrito = 0;
  let existente: DetalleVenta | null = null;
  for (const linea of lineas) {
    if (linea.idInventario === idInventario) {
      enCarrito += linea.cantidad;
      existente = linea;
    }
  }

  if (enCarrito + cantidad > item.stockAct) {
    flash(
      req,
      'error',
      `Stock insuficiente de «${item.nombre}». Disponible: ${item.stockAct}.`,
      true,
    );
    return;
  }

  if (existente !== null) {
    existente.cantidad += cantidad;
    existente.recalcularSubtotal();
  } else {
    lineas.push(new DetalleVenta(idInventario, item.nombre, cantidad, item.precioVenta, item.stockAct));
  }
  guardarCarrito(req, lineas);

  flash(req, 'aviso', `Agregado: ${item.nombre} x${cantidad}.`);
  void res;
}

function eliminar(req: Request, res: Response): void {
  const indice = intParam(req, 'indice', -1);
  const lineas = carrito(req);

  if (indice >= 0 && indice < lineas.length) {
    const nombre = lineas[indice].nombre;
    lineas.splice(indice, 1);
    guardarCarrito(req, lineas);
    flash(req, 'aviso', `Quitado: ${nombre}.`);
  }
  void res;
}

/** Crea la reserva: validaciones, stock, pago y vencimientos. */
async function crear(req: Request, res: Response): Promise<void> {
  const user = usuario(req);
  const caja = await new CajaDAO().cajaAbierta(user.id);
  if (caja === null) {
    flash(req, 'error', 'Abre la caja antes de registrar un pedido.', true);
    redirect(req, res, '/pedidos?nuevo=1');
    return;
  }

  const lineas = carrito(req);
  if (lineas.length === 0) {
    flash(req, 'error', 'Agrega al menos un producto a la reserva.', true);
    redirect(req, res, '/pedidos?nuevo=1');
    return;
  }

  const celular = param(req, 'celular');
  const nombrePaga = param(req, 'nombrePaga');
  const nombreRecoge = param(req, 'nombreRecoge');
  const fechaRecojoTxt = param(req, 'fechaRecojo');
  const horaRecojo = param(req, 'horaRecojo');
  const tipoReserva = param(req, 'tipoReserva');
  const metodo = param(req, 'metodo');

  if (celular === '' || nombrePaga === '') {
    flash(req, 'error', 'Ingresa el celular y el nombre de quien paga.', true);
    redirect(req, res, '/pedidos?nuevo=1');
    return;
  }

  const fechaRecojo = fechaValida(fechaRecojoTxt);
  if (fechaRecojo === null) {
    flash(req, 'error', 'Elige la fecha de recojo.', true);
    redirect(req, res, '/pedidos?nuevo=1');
    return;
  }

  if (metodo !== 'YAPE' && metodo !== 'EFECTIVO') {
    flash(req, 'error', 'Elige un método de pago (Yape o Efectivo).', true);
    redirect(req, res, '/pedidos?nuevo=1');
    return;
  }

  const total = totalCarrito(lineas);

  let monto: number;
  if (tipoReserva === 'completo') {
    monto = total;
  } else {
    const capturado = decimalParam(req, 'monto');
    if (capturado === undefined) {
      flash(req, 'error', 'Ingresa el adelanto o elige pago completo.', true);
      redirect(req, res, '/pedidos?nuevo=1');
      return;
    }
    monto = capturado;
  }

  if (monto < 0 || monto > total) {
    flash(req, 'error', `El monto debe estar entre S/ 0.00 y el total (S/ ${formatJs(total)}).`, true);
    redirect(req, res, '/pedidos?nuevo=1');
    return;
  }

  // Adelanto mínimo del 50%; solo el administrador puede bajarlo.
  const minimo = redondea(total * 0.5);
  if (monto < minimo && !user.esAdmin()) {
    flash(
      req,
      'error',
      `El adelanto mínimo es 50% (S/ ${formatJs(minimo)}). Solo el administrador puede modificarlo.`,
      true,
    );
    redirect(req, res, '/pedidos?nuevo=1');
    return;
  }

  const completo = monto >= total;
  const fecha = ahora();
  const datos: PedidoData = {
    nombrePaga,
    nombreRecoge: nombreRecoge !== '' ? nombreRecoge : null,
    celular,
    fechaRecojo,
    horaRecojo: horaRecojo !== '' ? horaRecojo : null,
    adelanto: monto,
    total,
    estado: completo ? 'COMPLETO' : 'PENDIENTE',
    estadoProduccion: 'PENDIENTE',
    idUsuario: user.id,
    // Vencimientos (3 meses canje, 2 desde el recojo, 1 mes pago).
    fechaCreacion: fecha,
    fechaLimiteCanje: sumarMesesAhora(3),
    fechaLimiteRecojo: sumarMeses(fechaRecojo, 2),
    fechaLimitePago: sumarMesesAhora(1),
    fechaPagoTotal: completo ? fecha : null,
  };
  const pedido = new Pedido(datos);

  let idPedido: number;
  try {
    idPedido = await new PedidoDAO().crear(pedido, lineas, caja.idCaja, metodo);
  } catch (error) {
    console.error('[DetallesTere] Error al crear pedido:', error instanceof Error ? error.message : error);
    flash(req, 'error', mensajeAmigable(error), true);
    redirect(req, res, '/pedidos?nuevo=1');
    return;
  }

  delete req.sessionState[K_CARRITO];
  flash(
    req,
    'aviso',
    `Pedido #${idPedido} reservado. Stock apartado. `
      + (completo ? 'Pagado por completo.' : `Falta por pagar: S/ ${formatJs(redondea(total - monto))}.`),
  );
  redirect(req, res, `/pedidos?id=${idPedido}`);
}

// -------------------------------------------------------------------
// Acciones sobre un pedido existente
// -------------------------------------------------------------------

async function abono(req: Request, res: Response, idPedido: number): Promise<void> {
  const caja = await new CajaDAO().cajaAbierta(usuario(req).id);
  if (caja === null) {
    flash(req, 'error', 'Abre la caja antes de registrar un pago.', true);
    return;
  }

  const monto = decimalParam(req, 'monto');
  const metodo = param(req, 'metodo');
  if (idPedido < 0 || monto === undefined || monto <= 0 || monto > MILLON) {
    flash(req, 'error', 'Ingresa un monto válido para el abono.', true);
    return;
  }
  if (metodo !== 'YAPE' && metodo !== 'EFECTIVO') {
    flash(req, 'error', 'Elige un método de pago (Yape o Efectivo).', true);
    return;
  }

  try {
    await new PedidoDAO().abono(idPedido, monto, caja.idCaja, metodo, usuario(req).id);
    flash(req, 'aviso', `Abono de S/ ${formatJs(monto)} registrado para el pedido #${idPedido}.`);
  } catch (error) {
    flash(req, 'error', mensajeAmigable(error), true);
  }
}

/** H4: existe el pedido y, si no eres admin, solo puedes tocar los tuyos. */
async function validarPropiedadPedidoId(req: Request, idPedido: number): Promise<void> {
  await validarPropiedadPedido(await new PedidoDAO().buscarPorId(idPedido), req);
}

async function validarPropiedadPedido(pedido: Pedido | null, req: Request): Promise<void> {
  if (pedido === null) {
    throw new Error('Ese pedido no existe.');
  }
  if (!esAdmin(req) && pedido.idUsuario !== usuario(req).id) {
    throw new Error('Solo puedes ver y modificar tus propios pedidos.');
  }
}

// -------------------------------------------------------------------
// Helpers
// -------------------------------------------------------------------

/** numJs(): dos decimales sin agrupación de miles. */
function formatJs(valor: number): string {
  return Number.isFinite(valor) ? valor.toFixed(2) : '';
}

/** Traduce excepciones de negocio a mensajes amigables (PedidoServlet.mensajeDe). */
function mensajeAmigable(error: unknown): string {
  let actual = error;
  let causa = (actual as { cause?: unknown } | null)?.cause;
  while (causa !== null && causa !== undefined) {
    actual = causa;
    causa = (actual as { cause?: unknown } | null)?.cause;
  }
  const mensaje = actual instanceof Error ? actual.message : String(actual ?? '');

  if (/stock/i.test(mensaje)) {
    return 'No hay stock suficiente para apartar ese ítem. Quita o reduce la cantidad.';
  }
  if (mensaje.includes('pedido (revísalo')) {
    return 'No se pudo actualizar el pedido. Puede que ya no esté en ese estado.';
  }
  if (mensaje.includes('Solo se puede cancelar')) return mensaje;
  if (mensaje.includes('propios pedidos') || mensaje.includes('no existe')) return mensaje;
  if (mensaje.includes('supera el saldo')) {
    return 'El abono supera el saldo pendiente del pedido.';
  }
  if (mensaje.includes('no admite abonos')) {
    return 'El pedido ya no admite abonos (cancelado o entregado).';
  }
  if (mensaje.includes('ya no puede cancelarse')) return mensaje;
  return 'Ocurrió un error. Revisa los datos e inténtalo de nuevo.';
}
