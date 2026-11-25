import type { Request, Response } from 'express';
import { config } from '../config';
import { ReporteDAO, type ReporteResumen } from '../dao/reporteDao';
import { fecha, num } from '../helpers';
import { BusinessError, param } from '../http';
import { redirect, renderPage } from '../view';

type Celda = string | number;

/** date('Y-m-d') / date('Y-m') en la zona horaria de la aplicación. */
function fechaActual(tipo: string): string {
  const partes = new Intl.DateTimeFormat('en-US', {
    timeZone: config.timezone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date());
  const dato: Record<string, string> = {};
  for (const parte of partes) dato[parte.type] = parte.value;
  return tipo === 'mes'
    ? `${dato.year}-${dato.month}`
    : `${dato.year}-${dato.month}-${dato.day}`;
}

/** DateTime::createFromFormat('!Y-m-d') con comparación estricta. */
function esFecha(valor: string): boolean {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(valor)) return false;
  const [anio, mes, dia] = valor.split('-').map(Number);
  const fecha = new Date(Date.UTC(anio, mes - 1, dia));
  return (
    fecha.getUTCFullYear() === anio &&
    fecha.getUTCMonth() === mes - 1 &&
    fecha.getUTCDate() === dia
  );
}

/** @return [desde, hasta] con fechas 'Y-m-d'. */
function periodo(tipo: string, valor: string): [string, string] {
  if (tipo === 'mes') {
    const coincidencia = /^(\d{4})-(\d{2})$/.exec(valor);
    if (!coincidencia) throw new BusinessError('Selecciona un periodo válido.');
    const anio = Number(coincidencia[1]);
    const mes = Number(coincidencia[2]);
    if (mes < 1 || mes > 12) throw new BusinessError('Selecciona un periodo válido.');
    const ultimoDia = new Date(Date.UTC(anio, mes, 0)).getUTCDate();
    const dos = (n: number): string => String(n).padStart(2, '0');
    return [`${valor}-01`, `${anio}-${dos(mes)}-${dos(ultimoDia)}`];
  }

  if (!esFecha(valor)) throw new BusinessError('Selecciona un periodo válido.');
  return [valor, valor];
}

function prop(origen: object, nombre: string, defecto: unknown): unknown {
  return Object.prototype.hasOwnProperty.call(origen, nombre)
    ? (origen as Record<string, unknown>)[nombre]
    : defecto;
}

function lista(origen: object, nombre: string): object[] {
  const valor = prop(origen, nombre, []);
  return Array.isArray(valor)
    ? valor.filter((item): item is object => item !== null && typeof item === 'object')
    : [];
}

function texto(valor: unknown): string {
  return valor === null || valor === undefined ? '' : String(valor);
}

function dinero(valor: unknown): string {
  return num(valor, 2, false);
}

function fechaProp(origen: object, nombre: string, formato: string): string {
  const valor = prop(origen, nombre, null);
  return fecha(valor === null || valor === '' ? null : String(valor), formato);
}

function fechaLinea(linea: object): string {
  const textoFecha = prop(linea, 'fechaTexto', '');
  if (typeof textoFecha === 'function') {
    return texto((textoFecha as () => unknown)());
  }
  const resultado = texto(textoFecha);
  return resultado !== '' ? resultado : fechaProp(linea, 'fecha', 'd/m/Y H:i');
}

/** Todas las secciones del reporte, en filas de CSV. */
function filasCsv(reporte: ReporteResumen): Celda[][] {
  const filas: Celda[][] = [
    ['DetallesTere · Reporte administrativo'],
    [texto(prop(reporte, 'etiquetaPeriodo', ''))],
    [],
    ['RESUMEN'],
    ['Desde', texto(prop(reporte, 'desde', ''))],
    ['Hasta', texto(prop(reporte, 'hasta', ''))],
    ['Ventas totales (S/)', dinero(prop(reporte, 'ventasTotales', 0))],
    ['Operaciones de venta', texto(prop(reporte, 'cantidadVentas', 0))],
    ['Unidades vendidas', texto(prop(reporte, 'unidadesVendidas', 0))],
    ['Ingresos totales (S/)', dinero(prop(reporte, 'ingresosTotales', 0))],
    ['Unidades ingresadas', texto(prop(reporte, 'unidadesIngresadas', 0))],
    ['Margen estimado (S/)', dinero(prop(reporte, 'margenEstimado', 0))],
    ['Efectivo (S/)', dinero(prop(reporte, 'efectivo', 0))],
    ['Yape (S/)', dinero(prop(reporte, 'yape', 0))],
    ['Crédito (S/)', dinero(prop(reporte, 'credito', 0))],
  ];

  if ('cajasTotales' in reporte) {
    filas.push(['Cajas (S/)', dinero(prop(reporte, 'cajasTotales', 0))]);
  }

  filas.push([]);
  filas.push(['DETALLE DE VENTAS']);
  filas.push([
    'Fecha', 'Artículo', 'Cantidad', 'Precio venta (S/)',
    'Subtotal (S/)', 'Costo vigente (S/)', 'Margen (S/)',
  ]);
  for (const linea of lista(reporte, 'ventas')) {
    filas.push([
      fechaLinea(linea),
      texto(prop(linea, 'articulo', '')),
      texto(prop(linea, 'cantidad', 0)),
      dinero(prop(linea, 'precioVenta', 0)),
      dinero(prop(linea, 'subtotal', 0)),
      dinero(prop(linea, 'costeVigente', 0)),
      dinero(prop(linea, 'margen', 0)),
    ]);
  }

  filas.push([]);
  filas.push(['DETALLE DE INGRESOS']);
  filas.push([
    'Fecha', 'Proveedor', 'Artículo', 'Cantidad',
    'Costo unitario (S/)', 'Subtotal (S/)',
  ]);
  for (const linea of lista(reporte, 'ingresos')) {
    filas.push([
      fechaProp(linea, 'fecha', 'd/m/Y'),
      texto(prop(linea, 'proveedor', '')),
      texto(prop(linea, 'articulo', '')),
      texto(prop(linea, 'cantidad', 0)),
      dinero(prop(linea, 'precioCompra', 0)),
      dinero(prop(linea, 'subtotal', 0)),
    ]);
  }

  const cajas = lista(reporte, 'cajas');
  if (cajas.length > 0) {
    filas.push([]);
    filas.push(['CAJAS']);
    filas.push([
      'Usuario', 'Apertura', 'Cierre', 'Monto inicial (S/)',
      'Monto final (S/)', 'Monto real (S/)', 'Diferencia (S/)',
    ]);
    for (const caja of cajas) {
      filas.push([
        texto(prop(caja, 'nombreUsuario', '')),
        fechaProp(caja, 'fechaApertura', 'd/m/Y H:i'),
        fechaProp(caja, 'fechaCierre', 'd/m/Y H:i'),
        dinero(prop(caja, 'montoInicial', 0)),
        dinero(prop(caja, 'montoFinal', 0)),
        dinero(prop(caja, 'montoReal', 0)),
        dinero(prop(caja, 'diferencia', 0)),
      ]);
    }
  }

  return filas;
}

/** Equivale a fputcsv($salida, $fila, ',', '"', ''). */
function celdaCsv(valor: Celda): string {
  const contenido = texto(valor);
  return /[",\r\n\t ]/.test(contenido)
    ? `"${contenido.replace(/"/g, '""')}"`
    : contenido;
}

function descargarCsv(res: Response, reporte: ReporteResumen, valor: string): void {
  const nombre = `reporte-detallestere-${valor.replace(/[^0-9]/g, '')}.csv`;
  const csv = filasCsv(reporte)
    .map((fila) => fila.map(celdaCsv).join(','))
    .join('\n') + '\n';

  res.setHeader('Content-Type', 'text/csv; charset=UTF-8');
  res.setHeader('Content-Disposition', `attachment; filename="${nombre}"`);
  res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
  res.status(200).send(Buffer.from(`\uFEFF${csv}`, 'utf8'));
}

/** GET /reportes (?tipo=dia|mes&periodo=...&formato=csv) */
export async function index(req: Request, res: Response): Promise<void> {
  const tipo = param(req, 'tipo') === 'mes' ? 'mes' : 'dia';
  const valor = param(req, 'periodo') || fechaActual(tipo);

  let reporte: ReporteResumen | null = null;
  let error: string | null = null;
  try {
    const [desde, hasta] = periodo(tipo, valor);
    reporte = await new ReporteDAO().generar(desde, hasta);
  } catch (fallo) {
    if (fallo instanceof BusinessError) {
      error = fallo.message;
    } else {
      console.error('Error al generar reporte:', fallo);
      error = 'No se pudo generar el reporte. Intenta de nuevo.';
    }
  }

  if (error !== null || reporte === null) {
    await renderPage(req, res, 'reportes', 'Reportes', '/reportes', {
      error: error ?? 'No se pudo generar el reporte. Intenta de nuevo.',
      reporte: null,
      tipoPeriodo: tipo,
      valorPeriodo: valor,
    });
    return;
  }

  // "pdf" se acepta para no romper enlaces antiguos; ahora entrega CSV.
  if (['csv', 'pdf'].includes(param(req, 'formato'))) {
    descargarCsv(res, reporte, valor);
    return;
  }

  await renderPage(req, res, 'reportes', 'Reportes', '/reportes', {
    error: null,
    reporte,
    tipoPeriodo: tipo,
    valorPeriodo: valor,
  });
}

/** POST /reportes: normaliza los filtros y vuelve a la vista. */
export function procesar(req: Request, res: Response): void {
  const tipo = param(req, 'tipo') === 'mes' ? 'mes' : 'dia';
  const periodoValor = param(req, 'periodo') || fechaActual(tipo);

  let consulta = `tipo=${tipo}&periodo=${encodeURIComponent(periodoValor)}`;
  const formato = param(req, 'formato');
  if (formato !== '') consulta += `&formato=${encodeURIComponent(formato)}`;

  redirect(req, res, `/reportes?${consulta}`);
}
