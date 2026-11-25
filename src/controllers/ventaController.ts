import type { Request, Response } from 'express';
import { CajaDAO } from '../dao/cajaDao';
import { ClienteDAO } from '../dao/clienteDao';
import { InventarioDAO } from '../dao/inventarioDao';
import { DetalleVenta, VentaDAO, type DetalleVentaData } from '../dao/ventaDao';
import { numJs } from '../helpers';
import { BusinessError, decimalParam, HttpError, intParam, param } from '../http';
import { currentUser, flash } from '../session';
import { redirect, renderPage } from '../view';

const K_CARRITO = 'carrito';
const K_COTIZACION = 'cotizacionId';

interface ItemInventario {
  idInventario: number;
  nombre: string;
  precioVenta: number | null;
  precioCompra: number | null;
  stockAct: number;
  stockMin: number;
}

interface ItemVista extends ItemInventario {
  esStockBajo(): boolean;
}

/** round() de PHP: mitad hacia afuera de cero, a 2 decimales. */
function round2(value: number): number {
  const sign = value < 0 ? -1 : 1;
  return (sign * Math.round(Math.abs(value) * 100 + Number.EPSILON)) / 100;
}

function totalCarrito(carrito: readonly DetalleVentaData[]): number {
  let total = 0;
  for (const linea of carrito) {
    total += Number(linea.subtotal ?? 0);
  }
  return round2(total);
}

/** Devuelve el carrito de la sesión, creándolo si no existe. */
function carrito(req: Request): DetalleVenta[] {
  const guardado = req.sessionState[K_CARRITO];
  const lineas: DetalleVentaData[] = Array.isArray(guardado)
    ? (guardado as DetalleVentaData[])
    : [];

  // La sesión serializa a JSON: se reconstruyen las instancias para poder
  // recalcular subtotales igual que hacía el modelo PHP.
  const normalizadas = lineas.map((item) => {
    const linea = new DetalleVenta(
      Number(item.idInventario) || 0,
      String(item.nombre ?? ''),
      Number(item.cantidad) || 0,
      item.costoUnitario === null || item.costoUnitario === undefined
        ? null
        : Number(item.costoUnitario),
      Number(item.stockDisponible) || 0,
    );
    linea.idDetalleVenta = Number(item.idDetalleVenta) || 0;
    linea.idVenta = item.idVenta === null || item.idVenta === undefined ? null : Number(item.idVenta);
    linea.idCaja = item.idCaja === null || item.idCaja === undefined ? null : Number(item.idCaja);
    linea.estado = String(item.estado ?? '');
    if (item.subtotal !== null && item.subtotal !== undefined) {
      linea.subtotal = Number(item.subtotal);
    }
    return linea;
  });

  req.sessionState[K_CARRITO] = normalizadas;
  return normalizadas;
}

/** Los arrays de la sesión se guardan por valor: hay que reasignarlos. */
function guardarCarrito(req: Request, lineas: readonly DetalleVenta[]): void {
  req.sessionState[K_CARRITO] = [...lineas];
}

/** ID de la cotización cargada, o null. */
function cotizacionId(req: Request): number | null {
  const id = req.sessionState[K_COTIZACION];
  if (typeof id === 'number' && Number.isInteger(id)) return id;
  if (typeof id === 'string' && /^\d+$/.test(id)) return Number(id);
  return null;
}

/** Equivale al guardado de mensaje en atributos de sesión del servlet. */
function aviso(req: Request, mensaje: string, error = false): void {
  flash(req, error ? 'error' : 'aviso', mensaje, error);
}

/** Mensaje para el usuario tras un error al canjear crédito. */
function mensajeDeCredito(error: unknown): string {
  let causa: unknown = error;
  while (causa instanceof Error && causa.cause !== undefined && causa.cause !== null) {
    causa = causa.cause;
  }
  const mensaje = causa instanceof Error ? causa.message : String(causa ?? '');

  if (mensaje.includes('saldo a favor vigente')) return mensaje;
  if (/stock/i.test(mensaje)) return 'No hay stock suficiente para completar el canje.';
  return 'No se pudo completar el canje. Revisa el saldo y vuelve a intentarlo.';
}

/** GET /venta */
export async function index(req: Request, res: Response): Promise<void> {
  const usuario = currentUser(req);
  if (!usuario) throw new HttpError(403, 'Acceso denegado.');

  const caja = await new CajaDAO().cajaAbierta(usuario.id);

  const q = param(req, 'q');
  const itemsRaw = await new InventarioDAO().listar(q === '' ? null : q);

  // El empleado no ve precio de costo (coherencia con el servlet).
  const items: ItemVista[] = itemsRaw.map((raw) => {
    const item = raw as unknown as ItemInventario;
    return {
      ...item,
      precioCompra: usuario.esAdmin() ? item.precioCompra ?? null : null,
      esStockBajo: () => Number(item.stockAct) <= Number(item.stockMin),
    };
  });

  const lineas = carrito(req);
  const total = totalCarrito(lineas);

  await renderPage(req, res, 'venta', 'Venta', '/venta', {
    caja,
    items,
    q,
    carrito: lineas,
    totalCarrito: total,
    cotizacionId: cotizacionId(req),
    cotizaciones: await new VentaDAO().listarCotizaciones(),
  });
}

/** Agrega un producto del inventario al carrito validando stock. */
async function agregar(req: Request): Promise<void> {
  const idInventario = intParam(req, 'idInventario', -1);
  const cantidad = intParam(req, 'cantidad', 1);

  if (idInventario < 0 || cantidad < 1) {
    aviso(req, 'Cantidad inválida.', true);
    return;
  }
  if (cotizacionId(req) !== null) {
    aviso(req, 'Tienes una cotización cargada. Cancela su carga antes de agregar productos.', true);
    return;
  }

  const item = (await new InventarioDAO().buscarPorId(idInventario)) as unknown as ItemInventario | null;
  if (item === null || item.precioVenta === null || item.precioVenta === undefined) {
    aviso(req, 'Ese producto no está disponible para venta.', true);
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

  if (enCarrito + cantidad > Number(item.stockAct)) {
    aviso(
      req,
      `Stock insuficiente de «${item.nombre}». Disponible: ${item.stockAct}.`,
      true,
    );
    return;
  }

  if (existente !== null) {
    existente.cantidad += cantidad;
    existente.recalcularSubtotal();
  } else {
    lineas.push(new DetalleVenta(
      idInventario,
      item.nombre,
      cantidad,
      item.precioVenta,
      Number(item.stockAct),
    ));
  }
  guardarCarrito(req, lineas);

  aviso(req, `Agregado: ${item.nombre} x${cantidad}.`);
}

/** Quita una línea del carrito por su índice. */
function eliminar(req: Request): void {
  if (cotizacionId(req) !== null) {
    aviso(req, 'Usa «Cancelar carga» para salir de la cotización.', true);
    return;
  }

  const indice = intParam(req, 'indice', -1);
  const lineas = carrito(req);
  if (indice >= 0 && indice < lineas.length) {
    const nombre = lineas[indice].nombre;
    lineas.splice(indice, 1);
    guardarCarrito(req, lineas);
    aviso(req, `Quitado: ${nombre}.`);
  }
}

/** Carga las líneas de una cotización en el carrito. */
async function cargarCotizacion(req: Request): Promise<void> {
  const idVenta = intParam(req, 'idVenta', -1);
  if (idVenta < 0) return;

  const lineas = await new VentaDAO().obtenerLineas(idVenta);
  if (lineas.length === 0) {
    aviso(req, 'Esa cotización ya no existe.', true);
    return;
  }

  req.sessionState[K_CARRITO] = [...lineas];
  req.sessionState[K_COTIZACION] = idVenta;
  aviso(req, `Cotización #${idVenta} cargada. Elige el pago y cobra.`);
}

/** Guarda el carrito como cotización de 24 h (sin descontar stock). */
async function guardarCotizacion(req: Request): Promise<void> {
  const lineas = carrito(req);
  if (lineas.length === 0) {
    aviso(req, 'El carrito está vacío.', true);
    return;
  }
  if (cotizacionId(req) !== null) {
    aviso(req, 'Ya hay una cotización cargada. Cancela su carga antes de guardar otra.', true);
    return;
  }

  const id = await new VentaDAO().guardarCotizacion(lineas);
  delete req.sessionState[K_CARRITO];
  aviso(req, `Cotización #${id} guardada. Vence en 24 h (no reserva stock).`);
}

/** Cobra el carrito: efectivo, Yape o canje de saldo a favor. */
async function cobrar(req: Request): Promise<void> {
  const usuario = currentUser(req);
  if (!usuario) throw new HttpError(403, 'Acceso denegado.');

  const caja = await new CajaDAO().cajaAbierta(usuario.id);
  if (caja === null) {
    aviso(req, 'Abre la caja antes de cobrar.', true);
    return;
  }

  const lineas = carrito(req);
  if (lineas.length === 0) {
    aviso(req, 'El carrito está vacío.', true);
    return;
  }

  const tipoPago = param(req, 'tipoPago');
  if (tipoPago !== 'YAPE' && tipoPago !== 'EFECTIVO' && tipoPago !== 'CREDITO') {
    aviso(req, 'Elige un método de pago.', true);
    return;
  }

  const total = totalCarrito(lineas);

  if (tipoPago === 'CREDITO') {
    const cliente = await new ClienteDAO().buscarPorWsp(param(req, 'celularCredito'));
    if (cliente === null) {
      aviso(req, 'No encontramos un cliente con ese celular.', true);
      return;
    }
    if (cotizacionId(req) !== null) {
      aviso(req, 'El canje de crédito no se puede aplicar a una cotización cargada.', true);
      return;
    }

    try {
      await new VentaDAO().confirmarVentaConCredito(
        lineas,
        caja.idCaja,
        usuario.id,
        cliente.idCliente,
      );
    } catch (error) {
      aviso(req, mensajeDeCredito(error), true);
      return;
    }

    delete req.sessionState[K_CARRITO];
    aviso(req, 'Canje registrado. Se utilizó todo el saldo vigente del cliente.');
    return;
  }

  let montoRecibido: number;
  if (tipoPago === 'YAPE') {
    // En Yape se paga el total exacto; no hay vuelto.
    montoRecibido = total;
  } else {
    const recibido = decimalParam(req, 'montoRecibido');
    if (recibido === undefined) {
      aviso(req, 'Ingresa el monto recibido en efectivo.', true);
      return;
    }
    if (recibido < total) {
      aviso(req, 'El monto recibido es menor que el total.', true);
      return;
    }
    montoRecibido = recibido;
  }

  const carga = cotizacionId(req);
  const dao = new VentaDAO();
  if (carga !== null) {
    await dao.convertirCotizacion(carga, caja.idCaja, usuario.id, tipoPago);
  } else {
    await dao.confirmarVenta(lineas, caja.idCaja, usuario.id, tipoPago);
  }

  delete req.sessionState[K_CARRITO];
  delete req.sessionState[K_COTIZACION];

  const vuelto = tipoPago === 'EFECTIVO'
    ? ` Vuelto: S/ ${numJs(montoRecibido - total)}.`
    : '';
  aviso(req, `Venta registrada. Total: S/ ${numJs(total)}.${vuelto}`);
}

/** POST /venta: todas las acciones del carrito y del cobro. */
export async function procesar(req: Request, res: Response): Promise<void> {
  const accion = param(req, 'accion');

  try {
    switch (accion) {
      case 'agregar':
        await agregar(req);
        break;
      case 'eliminar':
        eliminar(req);
        break;
      case 'vaciar':
      case 'cancelar_carga':
        delete req.sessionState[K_CARRITO];
        delete req.sessionState[K_COTIZACION];
        break;
      case 'cargar_cotizacion':
        await cargarCotizacion(req);
        break;
      case 'cotizar':
        await guardarCotizacion(req);
        break;
      case 'cobrar':
        await cobrar(req);
        break;
      default:
        break;
    }
  } catch (error) {
    // Equivale a los IllegalStateException / IllegalArgumentException del servlet.
    if (error instanceof BusinessError) {
      aviso(req, error.message, true);
    } else {
      throw error;
    }
  }

  redirect(req, res, '/venta');
}
