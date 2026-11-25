import { query, queryOne, type DbClient } from '../db';
import { BusinessError } from '../http';
import { decimal, integer, str, type Row } from './util';

const SQL_TOTALES_VENTA =
  'SELECT COUNT(*) AS VENTAS, ' +
  'COALESCE(SUM(TOTAL), 0) AS TOTAL, ' +
  "COALESCE(SUM(CASE WHEN v.TIPO_PAGO = 'EFECTIVO' " +
  'THEN v.TOTAL ELSE 0 END), 0) AS EFECTIVO, ' +
  "COALESCE(SUM(CASE WHEN v.TIPO_PAGO = 'YAPE' " +
  'THEN v.TOTAL ELSE 0 END), 0) AS YAPE, ' +
  "COALESCE(SUM(CASE WHEN v.TIPO_PAGO = 'CREDITO' " +
  'THEN v.TOTAL ELSE 0 END), 0) AS CREDITO ' +
  'FROM dbo.VENTA v ' +
  "WHERE ESTADO = 'VENDIDO' AND FECHA_VENTA >= ? " +
  'AND FECHA_VENTA < DATEADD(DAY, 1, ?)';

const SQL_TOTALES_LINEAS =
  'SELECT COALESCE(SUM(d.CANTIDAD), 0) AS UNIDADES, ' +
  'COALESCE(SUM(d.CANTIDAD * ' +
  'COALESCE(pr.PRECIO_COMPRA, m.PRECIO_COMPRA, a.PRECIO_COMPRA, 0)), 0) ' +
  'AS COSTO_ESTIMADO ' +
  'FROM dbo.VENTA v ' +
  'JOIN dbo.DETALLE_VENTA d ON d.ID_VENTA = v.ID_VENTA ' +
  'JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = d.ID_INVENTARIO ' +
  'LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO ' +
  'LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL ' +
  'LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE ' +
  "WHERE v.ESTADO = 'VENDIDO' AND v.FECHA_VENTA >= ? " +
  'AND v.FECHA_VENTA < DATEADD(DAY, 1, ?)';

const SQL_TOTALES_INGRESO =
  'SELECT COALESCE(SUM(d.CANTIDAD), 0) AS UNIDADES, ' +
  'COALESCE(SUM(d.SUBTOTAL), 0) AS TOTAL ' +
  'FROM dbo.INGRESO i ' +
  'JOIN dbo.DETALLE_INGRESO d ON d.ID_INGRESO = i.ID_INGRESO ' +
  'WHERE i.FECHA_INGRESO >= ? AND i.FECHA_INGRESO <= ?';

const SQL_DETALLE_VENTAS =
  'SELECT v.FECHA_VENTA, ' +
  'COALESCE(pr.NOMBRE, m.NOMBRE, a.NOMBRE) AS ARTICULO, ' +
  'd.CANTIDAD, d.COSTO_UNITARIO AS PRECIO_VENTA, ' +
  'COALESCE(pr.PRECIO_COMPRA, m.PRECIO_COMPRA, a.PRECIO_COMPRA, 0) ' +
  'AS COSTO_VIGENTE, d.SUBTOTAL ' +
  'FROM dbo.VENTA v ' +
  'JOIN dbo.DETALLE_VENTA d ON d.ID_VENTA = v.ID_VENTA ' +
  'JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = d.ID_INVENTARIO ' +
  'LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO ' +
  'LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL ' +
  'LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE ' +
  "WHERE v.ESTADO = 'VENDIDO' AND v.FECHA_VENTA >= ? " +
  'AND v.FECHA_VENTA < DATEADD(DAY, 1, ?) ' +
  'ORDER BY v.FECHA_VENTA DESC';

const SQL_DETALLE_INGRESOS =
  'SELECT i.FECHA_INGRESO, p.NOMBRE AS PROVEEDOR, ' +
  'COALESCE(pr.NOMBRE, m.NOMBRE, a.NOMBRE) AS ARTICULO, ' +
  'd.CANTIDAD, d.PRECIO_COMPRA_UNITARIO, d.SUBTOTAL ' +
  'FROM dbo.INGRESO i ' +
  'JOIN dbo.PROVEEDOR p ON p.ID_PROVEEDOR = i.ID_PROVEEDOR ' +
  'JOIN dbo.DETALLE_INGRESO d ON d.ID_INGRESO = i.ID_INGRESO ' +
  'JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = d.ID_INVENTARIO ' +
  'LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO ' +
  'LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL ' +
  'LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE ' +
  'WHERE i.FECHA_INGRESO >= ? AND i.FECHA_INGRESO <= ? ' +
  'ORDER BY i.FECHA_INGRESO DESC, d.ID_DETALLE_INGRESO DESC';

export interface ReporteVentaLinea {
  fecha: string | null;
  articulo: string;
  cantidad: number;
  precioVenta: number;
  costeVigente: number;
  subtotal: number;
  margen: number;
}

export interface ReporteIngresoLinea {
  fecha: string | null;
  proveedor: string;
  articulo: string;
  cantidad: number;
  precioCompra: number;
  subtotal: number;
}

export interface ReporteResumen {
  desde: string | null;
  hasta: string | null;
  etiquetaPeriodo: string;
  cantidadVentas: number;
  unidadesVendidas: number;
  ventasTotales: number;
  efectivo: number;
  yape: number;
  credito: number;
  ingresosTotales: number;
  unidadesIngresadas: number;
  margenEstimado: number;
  ventas: ReporteVentaLinea[];
  ingresos: ReporteIngresoLinea[];
}

function round2(value: number): number {
  return Math.round((value + Number.EPSILON) * 100) / 100;
}

/** Equivale a DateTimeImmutable::createFromFormat('!Y-m-d'). */
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

/** mssql devuelve Date; el PHP recibía cadenas 'Y-m-d H:i:s'. */
function fechaComoTexto(valor: unknown): string | null {
  if (valor === null || valor === undefined) return null;
  if (valor instanceof Date && !Number.isNaN(valor.getTime())) {
    const dos = (n: number): string => String(n).padStart(2, '0');
    return (
      `${valor.getFullYear()}-${dos(valor.getMonth() + 1)}-${dos(valor.getDate())}` +
      ` ${dos(valor.getHours())}:${dos(valor.getMinutes())}:${dos(valor.getSeconds())}`
    );
  }
  return String(valor);
}

/** Consultas agregadas para el tablero y el reporte administrativo. */
export class ReporteDAO {
  constructor(private readonly client?: DbClient) {}

  /** Genera el resumen y los detalles del periodo (fechas 'Y-m-d'). */
  async generar(desde: string, hasta: string): Promise<ReporteResumen> {
    if (!esFecha(desde) || !esFecha(hasta) || hasta < desde) {
      throw new BusinessError('El periodo seleccionado no es válido.');
    }

    const resumen: ReporteResumen = {
      desde,
      hasta,
      etiquetaPeriodo: desde === hasta ? `Día ${desde}` : `Del ${desde} al ${hasta}`,
      cantidadVentas: 0,
      unidadesVendidas: 0,
      ventasTotales: 0,
      efectivo: 0,
      yape: 0,
      credito: 0,
      ingresosTotales: 0,
      unidadesIngresadas: 0,
      margenEstimado: 0,
      ventas: [],
      ingresos: [],
    };

    await this.cargarTotalesVenta(resumen);
    await this.cargarTotalesIngreso(resumen);
    resumen.ventas = await this.listarVentas(desde, hasta);
    resumen.ingresos = await this.listarIngresos(desde, hasta);
    return resumen;
  }

  private async cargarTotalesVenta(resumen: ReporteResumen): Promise<void> {
    const parametros = [resumen.desde, resumen.hasta];

    const fila = await queryOne<Row>(SQL_TOTALES_VENTA, parametros, this.client);
    if (fila) {
      resumen.cantidadVentas = integer(fila, 'VENTAS');
      resumen.ventasTotales = decimal(fila, 'TOTAL');
      resumen.efectivo = decimal(fila, 'EFECTIVO');
      resumen.yape = decimal(fila, 'YAPE');
      resumen.credito = decimal(fila, 'CREDITO');
    }

    const filaLineas = await queryOne<Row>(SQL_TOTALES_LINEAS, parametros, this.client);
    if (filaLineas) {
      resumen.unidadesVendidas = integer(filaLineas, 'UNIDADES');
      resumen.margenEstimado = round2(
        resumen.ventasTotales - decimal(filaLineas, 'COSTO_ESTIMADO'),
      );
    }
  }

  private async cargarTotalesIngreso(resumen: ReporteResumen): Promise<void> {
    const fila = await queryOne<Row>(
      SQL_TOTALES_INGRESO,
      [resumen.desde, resumen.hasta],
      this.client,
    );
    if (fila) {
      resumen.unidadesIngresadas = integer(fila, 'UNIDADES');
      resumen.ingresosTotales = decimal(fila, 'TOTAL');
    }
  }

  private async listarVentas(desde: string, hasta: string): Promise<ReporteVentaLinea[]> {
    const rows = await query<Row>(SQL_DETALLE_VENTAS, [desde, hasta], this.client);
    return rows.map((row) => {
      const subtotal = decimal(row, 'SUBTOTAL');
      const costeVigente = decimal(row, 'COSTO_VIGENTE');
      const cantidad = integer(row, 'CANTIDAD');
      return {
        fecha: fechaComoTexto(row.FECHA_VENTA),
        articulo: str(row, 'ARTICULO'),
        cantidad,
        precioVenta: decimal(row, 'PRECIO_VENTA'),
        costeVigente,
        subtotal,
        margen: round2(subtotal - costeVigente * cantidad),
      };
    });
  }

  private async listarIngresos(desde: string, hasta: string): Promise<ReporteIngresoLinea[]> {
    const rows = await query<Row>(SQL_DETALLE_INGRESOS, [desde, hasta], this.client);
    return rows.map((row) => {
      const fecha = fechaComoTexto(row.FECHA_INGRESO);
      return {
        fecha: fecha === null ? null : fecha.slice(0, 10),
        proveedor: str(row, 'PROVEEDOR'),
        articulo: str(row, 'ARTICULO'),
        cantidad: integer(row, 'CANTIDAD'),
        precioCompra: decimal(row, 'PRECIO_COMPRA_UNITARIO'),
        subtotal: decimal(row, 'SUBTOTAL'),
      };
    });
  }
}
