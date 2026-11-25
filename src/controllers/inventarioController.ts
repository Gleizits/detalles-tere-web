import type { Request, Response } from 'express';
import {
  InventarioDAO,
  type InventarioItem,
  type Categoria as CategoriaSelector,
} from '../dao/inventarioDao';
import { intParam, param } from '../http';
import { currentUser, flash } from '../session';
import { redirect, renderPage } from '../view';

type Clase = 'PRODUCTO' | 'MATERIAL' | 'ARREGLO';
const CLASES: Clase[] = ['PRODUCTO', 'MATERIAL', 'ARREGLO'];
const MAX_PRECIO = 1_000_000.0;

/** Equivale a Modelo\InventarioItem, con el método de apoyo del modelo PHP. */
class ItemFormulario implements InventarioItem {
  idInventario = 0;
  stockAct = 0;
  stock = 0;
  stockMin = 0;
  tipoAlmacen = '';
  unidadMedida = '';
  nombre = '';
  tipo = '';
  categoria = '';
  clase = '';
  precioVenta: number | null = null;
  precioCompra: number | null = null;
  idProducto = 0;
  idMaterial = 0;
  idArreglo = 0;
  idCategoria = 0;
  idMaterialVinc = 0;
  estado = true;

  /** true si el stock actual está en o por debajo del mínimo. */
  esStockBajo(): boolean {
    return this.stockAct <= this.stockMin;
  }
}

interface Extras {
  idProducto: number;
  idMaterial: number;
  idArreglo: number;
  idCategoria: number;
  estado: boolean;
  idMaterialVinc: number;
}

class ValidacionArticuloError extends Error {
  constructor(mensaje: string) {
    super(mensaje);
    this.name = 'InvalidArgumentException';
  }
}

function esAdmin(req: Request): boolean {
  const user = currentUser(req);
  return user !== null && user.esAdmin();
}

function tieneClave(req: Request, clave: string): boolean {
  return Object.prototype.hasOwnProperty.call(req.query, clave);
}

function tienePost(req: Request, clave: string): boolean {
  const valor = req.body?.[clave];
  return valor !== undefined && valor !== null;
}

/** Equivale a mb_substr(): cuenta caracteres, no unidades UTF-16. */
function mbSubstr(texto: string, longitud: number): string {
  return Array.from(texto).slice(0, longitud).join('');
}

function enteroOpcional(texto: string): number | null {
  const limpio = texto.trim();
  return /^-?\d+$/.test(limpio) ? Number.parseInt(limpio, 10) : null;
}

function decimalOpcional(texto: string): number | null {
  const limpio = texto.trim().replace(/,/g, '.');
  if (limpio === '' || !/^\d+(?:\.\d+)?$/.test(limpio)) return null;
  const numero = Number(limpio);
  return numero <= MAX_PRECIO ? numero : null;
}

function fallar(req: Request, res: Response, mensaje: string): void {
  flash(req, 'error', mensaje, true);
  redirect(req, res, '/inventario');
}

/** GET /inventario: lista, formulario nuevo o formulario de edición. */
export async function index(req: Request, res: Response): Promise<void> {
  if (tieneClave(req, 'nuevo')) {
    await mostrarFormulario(req, res, null, 'Nuevo artículo');
    return;
  }

  if (tieneClave(req, 'editar')) {
    const item = await buscarSeguro(req, param(req, 'editar'));
    if (item === null) {
      fallar(req, res, 'No encontramos ese artículo.');
      return;
    }
    const datosExtras = await extrasDeItem(item.idInventario, req);
    aplicarExtras(item, datosExtras);
    await mostrarFormulario(req, res, item, 'Editar artículo', null, datosExtras);
    return;
  }

  const q = param(req, 'q');
  const lista = await cargarLista(req, q);
  const admin = esAdmin(req);

  // Defensa en profundidad: el empleado no recibe el precio de costo.
  if (!admin) {
    for (const item of lista.items) item.precioCompra = null;
  }

  await renderPage(req, res, 'inventario', 'Inventario', '/inventario', {
    items: lista.items,
    estados: lista.estados,
    q,
    total: lista.items.length,
    esAdmin: admin,
  });
}

/** POST /inventario: crear | editar | ajustar. */
export async function procesar(req: Request, res: Response): Promise<void> {
  switch (param(req, 'accion')) {
    case 'crear':
      await crear(req, res);
      break;
    case 'editar':
      await editar(req, res);
      break;
    case 'ajustar':
      await ajustar(req, res);
      break;
    default:
      redirect(req, res, '/inventario');
  }
}

async function crear(req: Request, res: Response): Promise<void> {
  const clase = param(req, 'clase');
  if (!CLASES.includes(clase as Clase)) {
    await mostrarFormulario(
      req,
      res,
      null,
      'Nuevo artículo',
      'Elige qué tipo de artículo es: producto, material o arreglo.',
    );
    return;
  }

  const item = new ItemFormulario();
  item.clase = clase;
  const datosExtras = extrasPorFormulario(req, false);
  aplicarExtras(item, datosExtras);

  try {
    await leerCampos(req, item, datosExtras, true);
    await guardarCrear(item);
  } catch (error) {
    if (error instanceof ValidacionArticuloError) {
      // Volvemos al formulario con lo capturado para no perderlo.
      await mostrarFormulario(req, res, item, 'Nuevo artículo', error.message, datosExtras);
      return;
    }
    console.error('Error al crear artículo:', error instanceof Error ? error.message : error);
    await mostrarFormulario(
      req,
      res,
      item,
      'Nuevo artículo',
      'No pudimos guardar el artículo. Intenta de nuevo.',
      datosExtras,
    );
    return;
  }

  flash(req, 'aviso', `Artículo "${item.nombre}" guardado en el inventario.`);
  redirect(req, res, '/inventario');
}

async function editar(req: Request, res: Response): Promise<void> {
  const item = await buscarSeguro(req, param(req, 'idInventario'));
  if (item === null) {
    fallar(req, res, 'No encontramos ese artículo.');
    return;
  }

  const datosExtras = await extrasDeItem(item.idInventario, req);
  aplicarExtras(item, datosExtras);

  try {
    // La clase no cambia jamás: se toma de la BD, no del formulario.
    await leerCampos(req, item, datosExtras, false);
    await guardarActualizar(item, esAdmin(req));
  } catch (error) {
    if (error instanceof ValidacionArticuloError) {
      await mostrarFormulario(req, res, item, 'Editar artículo', error.message, datosExtras);
      return;
    }
    console.error('Error al editar artículo:', error instanceof Error ? error.message : error);
    await mostrarFormulario(
      req,
      res,
      item,
      'Editar artículo',
      'No pudimos guardar los cambios. Intenta de nuevo.',
      datosExtras,
    );
    return;
  }

  flash(req, 'aviso', `Cambios guardados en "${item.nombre}".`);
  redirect(req, res, '/inventario');
}

async function ajustar(req: Request, res: Response): Promise<void> {
  const id = intParam(req, 'idInventario');
  if (id <= 0) {
    fallar(req, res, 'No pudimos identificar el artículo.');
    return;
  }

  // Si llegan signo + cantidad, el delta se calcula aquí (sin depender del JS).
  let delta: number;
  if (tienePost(req, 'signo') && tienePost(req, 'cantidad')) {
    const signo = param(req, 'signo') === '-1' ? -1 : 1;
    const cantidad = intParam(req, 'cantidad');
    if (cantidad <= 0) {
      fallar(req, res, 'Indica la cantidad a sumar o restar.');
      return;
    }
    delta = signo * cantidad;
  } else {
    delta = intParam(req, 'delta');
  }

  if (delta === 0) {
    fallar(req, res, 'La cantidad no puede ser 0.');
    return;
  }

  const motivo = mbSubstr(param(req, 'motivo'), 100);

  try {
    await guardarAjuste(id, delta, motivo, currentUser(req)?.id ?? 0);
  } catch (error) {
    const mensaje =
      error instanceof Error && /stock/i.test(error.message)
        ? error.message
        : 'No pudimos actualizar el stock. Intenta de nuevo.';
    fallar(req, res, mensaje);
    return;
  }

  const que = delta > 0 ? 'sumaron' : 'restaron';
  flash(req, 'aviso', `Stock actualizado: se ${que} ${Math.abs(delta)} unidad(es).`);
  redirect(req, res, '/inventario');
}

/**
 * Lee y valida todo el formulario. Se asigna cada campo antes de validar
 * para que, si hay error, el formulario conserve lo que se capturó.
 */
async function leerCampos(
  req: Request,
  item: ItemFormulario,
  datosExtras: Extras,
  alta: boolean,
): Promise<void> {
  let error: string | null = null;

  // Nombre
  const nombre = param(req, 'nombre');
  const tope = item.clase === 'ARREGLO' ? 100 : 50;
  if (nombre === '') {
    error = 'El nombre del artículo es obligatorio.';
  } else if (Array.from(nombre).length > tope) {
    error = `El nombre puede tener máximo ${tope} caracteres.`;
  }
  item.nombre = nombre;

  // Tipo (los arreglos no lo llevan)
  if (item.clase !== 'ARREGLO') {
    item.tipo = mbSubstr(param(req, 'tipo'), 50);
  }

  // Categoría: selector o nombre nuevo
  const categoriaNueva = mbSubstr(param(req, 'categoriaNueva'), 100);
  if (categoriaNueva !== '') {
    try {
      datosExtras.idCategoria = await new InventarioDAO().obOCrearCategoria(categoriaNueva);
    } catch {
      error ??= 'No pudimos guardar la categoría nueva. Intenta de nuevo.';
    }
  } else {
    const idCategoria = enteroOpcional(param(req, 'idCategoria'));
    if (idCategoria === null) {
      error ??= 'Elige una categoría o escribe una nueva.';
    }
    datosExtras.idCategoria = idCategoria ?? 0;
  }
  if (datosExtras.idCategoria > 0) {
    item.idCategoria = datosExtras.idCategoria;
  }

  // Unidad de medida
  const unidad = param(req, 'unidadMedida');
  if (unidad === '') {
    error ??= 'Indica la unidad de medida o empaque (ej.: paquete de 12).';
  } else if (Array.from(unidad).length > 50) {
    error ??= 'La unidad de medida puede tener máximo 50 caracteres.';
  }
  item.unidadMedida = unidad;

  // Tipo de almacén
  const almacen = param(req, 'tipoAlmacen');
  if (almacen === '') {
    error ??= 'Indica cómo se guarda (ej.: paquete, unidad, costal).';
  } else if (Array.from(almacen).length > 35) {
    error ??= 'El tipo de almacén puede tener máximo 35 caracteres.';
  }
  item.tipoAlmacen = almacen;

  // Stock mínimo
  const stockMin = enteroOpcional(param(req, 'stockMin'));
  if (stockMin === null) {
    error ??= 'El stock mínimo debe ser un número entero.';
  } else if (stockMin < 0) {
    error ??= 'El stock mínimo no puede ser negativo.';
  } else {
    item.stockMin = stockMin;
  }

  // Stock inicial (solo alta) y estado
  if (alta) {
    const stock = enteroOpcional(param(req, 'stockAct'));
    if (stock === null) {
      error ??= 'El stock inicial debe ser un número entero.';
    } else if (stock < 0) {
      error ??= 'El stock inicial no puede ser negativo.';
    } else {
      item.stockAct = stock;
    }
    datosExtras.estado = true;
  } else if (esAdmin(req)) {
    datosExtras.estado = param(req, 'estado') === 'on';
  }
  item.estado = datosExtras.estado;

  // Precio de venta: obligatorio al crear; vacío en edición = conservar.
  const pvTxt = param(req, 'precioVenta');
  if (pvTxt !== '') {
    const precioVenta = decimalOpcional(pvTxt);
    if (precioVenta === null) {
      error ??= 'Captura un precio de venta válido (ej.: 12.50).';
    } else {
      item.precioVenta = precioVenta;
    }
  } else if (alta) {
    error ??= 'Captura un precio de venta válido (ej.: 12.50).';
  }

  // Precio de compra: solo admin; al empleado se le ignora.
  if (esAdmin(req)) {
    const pcTxt = param(req, 'precioCompra');
    if (pcTxt !== '') {
      const precioCompra = decimalOpcional(pcTxt);
      if (precioCompra === null) {
        error ??= 'Captura un precio de compra válido.';
      } else {
        item.precioCompra = precioCompra;
      }
    } else {
      item.precioCompra = null;
    }
  } else {
    item.precioCompra = null;
  }

  // Material enlazado (solo productos)
  if (item.clase === 'PRODUCTO') {
    datosExtras.idMaterialVinc = enteroOpcional(param(req, 'idMaterialVinc')) ?? 0;
    item.idMaterialVinc = datosExtras.idMaterialVinc;
  }

  if (error !== null) {
    throw new ValidacionArticuloError(error);
  }
}

async function mostrarFormulario(
  req: Request,
  res: Response,
  item: ItemFormulario | null,
  titulo: string,
  error: string | null = null,
  datosExtras?: Extras,
): Promise<void> {
  let valores = datosExtras;
  if (valores === undefined) {
    valores =
      item !== null
        ? await extrasDeItem(item.idInventario, req)
        : extrasPorFormulario(req, false);
  }
  if (item !== null) aplicarExtras(item, valores);

  if (item !== null && !esAdmin(req)) {
    item.precioCompra = null;
  }

  const categorias = await listarCategorias();
  const materiales = await listarMaterialesActivos();

  await renderPage(req, res, 'inventario_form', titulo, '/inventario', {
    item,
    extras: valores,
    categorias,
    materiales,
    esAdmin: esAdmin(req),
    error,
  });
}

async function cargarLista(
  req: Request,
  q: string,
): Promise<{ items: ItemFormulario[]; estados: Record<number, boolean> }> {
  const items = (await new InventarioDAO().listar(q)).map((item) =>
    Object.assign(new ItemFormulario(), item),
  );
  const estados: Record<number, boolean> = {};
  for (const item of items) estados[item.idInventario] = item.estado;
  void req;
  return { items, estados };
}

async function buscarSeguro(req: Request, texto: string): Promise<ItemFormulario | null> {
  const id = enteroOpcional(texto);
  if (id === null || id <= 0) return null;
  const item = await new InventarioDAO().buscarPorId(id);
  return item ? Object.assign(new ItemFormulario(), item) : null;
}

/** Datos del formulario que hoy no están en el modelo PHP (ESTADO, etc.). */
async function extrasDeItem(id: number, req: Request): Promise<Extras> {
  const item = await new InventarioDAO().buscarPorId(id);
  if (item === null) return extrasPorFormulario(req, false);

  return {
    idProducto: item.idProducto,
    idMaterial: item.idMaterial,
    idArreglo: item.idArreglo,
    idCategoria: item.idCategoria,
    estado: item.estado,
    idMaterialVinc: item.idMaterialVinc,
  };
}

/** Valores por defecto / recuperados del POST para el formulario. */
function extrasPorFormulario(req: Request, edicion: boolean): Extras {
  return {
    idProducto: edicion ? intParam(req, 'idProducto') : 0,
    idMaterial: edicion ? intParam(req, 'idMaterial') : 0,
    idArreglo: edicion ? intParam(req, 'idArreglo') : 0,
    idCategoria: intParam(req, 'idCategoria'),
    estado: !edicion || param(req, 'estado') === 'on',
    idMaterialVinc: intParam(req, 'idMaterialVinc'),
  };
}

function aplicarExtras(item: ItemFormulario, datosExtras: Extras): void {
  item.idProducto = datosExtras.idProducto;
  item.idMaterial = datosExtras.idMaterial;
  item.idArreglo = datosExtras.idArreglo;
  item.idCategoria = datosExtras.idCategoria;
  item.estado = datosExtras.estado;
  item.idMaterialVinc = datosExtras.idMaterialVinc;
}

/** @return Categoria[] */
async function listarCategorias(): Promise<CategoriaSelector[]> {
  return new InventarioDAO().listarCategorias();
}

/**
 * El JSP esperaba Categoria(id, nombre) con el ID del MATERIAL.
 * Se mantiene ese contrato.
 */
async function listarMaterialesActivos(): Promise<CategoriaSelector[]> {
  return new InventarioDAO().listarMaterialesActivos();
}

async function guardarCrear(item: ItemFormulario): Promise<void> {
  await new InventarioDAO().crear(item);
}

async function guardarActualizar(item: ItemFormulario, admin: boolean): Promise<void> {
  await new InventarioDAO().actualizar(item, admin);
}

async function guardarAjuste(
  id: number,
  delta: number,
  motivo: string,
  idUsuario: number,
): Promise<void> {
  await new InventarioDAO().ajustarStock(id, delta, motivo, idUsuario);
}
