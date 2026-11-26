import type { Request, Response } from 'express';
import { config } from '../config';
import { IngresoDAO, type IngresoLinea } from '../dao/ingresoDao';
import { InventarioDAO } from '../dao/inventarioDao';
import { ProveedorDAO } from '../dao/proveedorDao';
import { BusinessError, intParam, param, stringsParam } from '../http';
import { paginar } from '../pagination';
import { currentUser, flash } from '../session';
import { redirect, renderPage } from '../view';

const MAX_PRECIO = 1_000_000;

/** mb_substr() trabaja con caracteres, no con unidades de código UTF-16. */
function recortar(valor: string, maximo: number): string {
  return Array.from(valor).slice(0, maximo).join('');
}

/** date('Y-m-d') en la zona horaria configurada para la aplicación. */
function fechaActual(): string {
  const partes = new Intl.DateTimeFormat('en-US', {
    timeZone: config.timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date());
  const dato: Record<string, string> = {};
  for (const parte of partes) dato[parte.type] = parte.value;
  return `${dato.year}-${dato.month}-${dato.day}`;
}

/** DateTime::createFromFormat('!Y-m-d') con comparación estricta. */
function fechaValida(valor: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(valor)) return false;
  const [anio, mes, dia] = valor.split('-').map(Number);
  const fecha = new Date(Date.UTC(anio, mes - 1, dia));
  return (
    fecha.getUTCFullYear() === anio &&
    fecha.getUTCMonth() === mes - 1 &&
    fecha.getUTCDate() === dia
  );
}

function obligatorio(valor: string, maximo: number, mensaje: string): string {
  const resultado = recortar(valor.trim(), maximo);
  if (resultado === '') throw new BusinessError(mensaje);
  return resultado;
}

function positivo(valor: string, mensaje: string): number {
  if (!/^\d+$/.test(valor) || Number(valor) <= 0) throw new BusinessError(mensaje);
  return Number.parseInt(valor, 10);
}

function decimal(valor: string, mensaje: string): number {
  const texto = valor.trim().replace(',', '.');
  if (!/^\d+(?:\.\d+)?$/.test(texto)) throw new BusinessError(mensaje);
  const numero = Number(texto);
  if (numero > MAX_PRECIO) throw new BusinessError(mensaje);
  return numero;
}

async function crearProveedor(req: Request): Promise<void> {
  const nombre = obligatorio(
    param(req, 'nombreProveedor'),
    50,
    'Indica el nombre del proveedor.',
  );
  const numero = obligatorio(
    param(req, 'numeroProveedor'),
    12,
    'Indica el número del proveedor.',
  );
  await new ProveedorDAO().crear(nombre, numero);
}

function leerLineas(req: Request): IngresoLinea[] {
  const ids = stringsParam(req, 'idInventario');
  const cantidades = stringsParam(req, 'cantidad');
  const precios = stringsParam(req, 'precioCompra');
  const observaciones = stringsParam(req, 'observaciones');

  if (
    ids.length !== cantidades.length ||
    ids.length !== precios.length
  ) {
    throw new BusinessError('Agrega al menos un artículo válido.');
  }

  const lineas: IngresoLinea[] = [];
  for (let i = 0; i < ids.length; i++) {
    const idTxt = ids[i];
    if (idTxt.trim() === '') continue; // Las filas vacías se ignoran.

    const id = positivo(idTxt, 'Selecciona un artículo válido.');
    const cantidad = positivo(
      cantidades[i] ?? '',
      'La cantidad debe ser un entero positivo.',
    );
    const precio = decimal(
      precios[i] ?? '',
      'Captura un precio de compra válido.',
    );
    const obs = (observaciones[i] ?? '').trim();

    lineas.push({
      idInventario: id,
      cantidad,
      precioCompraUnitario: precio,
      subtotal: Math.round((precio * cantidad + Number.EPSILON) * 100) / 100,
      observaciones: obs === '' ? null : recortar(obs, 100),
    });
  }

  if (lineas.length === 0) {
    throw new BusinessError('Agrega al menos un artículo al ingreso.');
  }
  return lineas;
}

async function registrarIngreso(req: Request): Promise<void> {
  const idProveedor = intParam(req, 'idProveedor');
  if (idProveedor <= 0) throw new BusinessError('Selecciona un proveedor.');

  const fecha = param(req, 'fechaIngreso');
  if (!fechaValida(fecha)) throw new BusinessError('Selecciona una fecha válida.');

  await new IngresoDAO().registrar(
    idProveedor,
    currentUser(req)?.id ?? 0,
    fecha,
    leerLineas(req),
  );
}

async function cargarVista(
  req: Request,
  res: Response,
  error: string | null = null,
): Promise<void> {
  const [proveedores, inventario, recientes] = await Promise.all([
    new ProveedorDAO().listar(),
    new InventarioDAO().listarActivos(),
    new IngresoDAO().listarRecientes(100),
  ]);
  const pagina = paginar(recientes, req);

  await renderPage(req, res, 'ingresos', 'Ingresos', '/ingresos', {
    proveedores,
    inventario,
    ingresos: pagina.items,
    paginacion: pagina,
    fechaActual: fechaActual(),
    error,
  });
}

/** GET /ingresos */
export async function index(req: Request, res: Response): Promise<void> {
  await cargarVista(req, res);
}

/** POST /ingresos */
export async function procesar(req: Request, res: Response): Promise<void> {
  let aviso = '';
  try {
    switch (param(req, 'accion')) {
      case 'crearProveedor':
        await crearProveedor(req);
        aviso = 'Proveedor guardado.';
        break;

      case 'registrar':
        await registrarIngreso(req);
        aviso = 'Ingreso registrado y stock actualizado.';
        break;

      default:
        flash(req, 'error', 'Acción no válida.');
        redirect(req, res, '/ingresos');
        return;
    }
  } catch (error) {
    if (error instanceof BusinessError) {
      await cargarVista(req, res, error.message);
      return;
    }
    console.error('Error en el módulo de ingresos:', error);
    await cargarVista(req, res, 'No pudimos guardar la información. Intenta de nuevo.');
    return;
  }

  flash(req, 'aviso', aviso);
  redirect(req, res, '/ingresos');
}
