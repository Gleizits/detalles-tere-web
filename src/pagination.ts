import type { Request } from 'express';

/** Tamaños de página permitidos; cualquier otro valor se ignora. */
export const TAMANOS_PAGINA = [10, 25, 50, 100] as const;
export type TamanoPagina = (typeof TAMANOS_PAGINA)[number];

export interface ClavesPaginacion {
  pagina: string;
  tamano: string;
}

export interface OpcionesPaginacion {
  porPagina?: TamanoPagina;
  /** Claves de query cuando una misma vista tiene más de una tabla paginada. */
  claves?: ClavesPaginacion;
}

export interface Paginacion<T> {
  items: T[];
  total: number;
  pagina: number;
  porPagina: TamanoPagina;
  totalPaginas: number;
  desde: number;
  hasta: number;
  claves: ClavesPaginacion;
}

const CLAVES_POR_DEFECTO: ClavesPaginacion = { pagina: 'page', tamano: 'pageSize' };

function entero(valor: unknown): number | null {
  if (typeof valor !== 'string' || !/^\d+$/.test(valor)) return null;
  const numero = Number(valor);
  return Number.isSafeInteger(numero) && numero > 0 ? numero : null;
}

/**
 * Paginación en memoria sobre un listado ya obtenido. Mantiene la URL limpia
 * y acepta claves propias para vistas con varias tablas paginadas.
 */
export function paginar<T>(
  lista: T[],
  req: Request,
  opciones: OpcionesPaginacion = {},
): Paginacion<T> {
  const claves = opciones.claves ?? CLAVES_POR_DEFECTO;
  const tamanoSolicitado = entero(req.query[claves.tamano]);
  const porPagina = (TAMANOS_PAGINA as readonly number[]).includes(tamanoSolicitado ?? -1)
    ? (tamanoSolicitado as TamanoPagina)
    : (opciones.porPagina ?? 10);

  const total = lista.length;
  const totalPaginas = Math.max(1, Math.ceil(total / porPagina));
  const solicitada = entero(req.query[claves.pagina]) ?? 1;
  const pagina = Math.min(solicitada, totalPaginas);
  const inicio = (pagina - 1) * porPagina;

  return {
    items: lista.slice(inicio, inicio + porPagina),
    total,
    pagina,
    porPagina,
    totalPaginas,
    desde: total === 0 ? 0 : inicio + 1,
    hasta: Math.min(inicio + porPagina, total),
    claves,
  };
}
