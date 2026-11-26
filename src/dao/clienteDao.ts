import { insertId, queryOne, type DbClient } from '../db';
import { BusinessError } from '../http';
import { integer, str, type Row } from './util';

/** Equivale a Modelo\Cliente (numCreditos es dato de apoyo, no columna). */
export interface Cliente {
  idCliente: number;
  nombre: string;
  numWsp: string;
  numCreditos: number;
}

const SQL_POR_WSP =
  'SELECT ID_CLIENTE, NOMBRE, NUM_WSP FROM dbo.CLIENTE WHERE NUM_WSP = ?';

const SQL_INSERT =
  'INSERT INTO dbo.CLIENTE (NOMBRE, NUM_WSP) VALUES (?, ?)';

function isDbClient(value: unknown): value is DbClient {
  return (
    typeof value === 'object' &&
    value !== null &&
    typeof (value as { query?: unknown }).query === 'function' &&
    typeof (value as { release?: unknown }).release === 'function'
  );
}

function map(row: Row): Cliente {
  return {
    idCliente: integer(row, 'ID_CLIENTE'),
    nombre: str(row, 'NOMBRE'),
    numWsp: str(row, 'NUM_WSP'),
    numCreditos: 0,
  };
}

/** Busca un cliente por WhatsApp/celular; se reutiliza en pedidos y créditos. */
export class ClienteDAO {
  constructor(private readonly client?: DbClient) {}

  /** Busca un cliente por número de WhatsApp/celular, o null si no existe. */
  async buscarPorWsp(numWsp: string, client?: DbClient | null): Promise<Cliente | null>;
  /** Forma PHP: el primero es el cliente de la transacción activa. */
  async buscarPorWsp(client: DbClient | null, numWsp: string): Promise<Cliente | null>;
  async buscarPorWsp(
    first: string | DbClient | null,
    second?: string | DbClient | null,
  ): Promise<Cliente | null> {
    let client = this.client;
    let numWsp: string;
    if (isDbClient(first) || first === null) {
      if (isDbClient(first)) client = first;
      numWsp = typeof second === 'string' ? second : '';
    } else {
      numWsp = first;
      if (isDbClient(second)) client = second;
    }

    const row = await queryOne<Row>(SQL_POR_WSP, [numWsp], client);
    return row ? map(row) : null;
  }

  /**
   * Devuelve el id del cliente con ese celular: lo usa si existe, o lo crea si
   * no. Si existe con otro nombre, no lo modifica.
   */
  async buscarOCrear(
    numWsp: string,
    nombre: string,
    client?: DbClient | null,
  ): Promise<number>;
  /** Forma PHP: el primero es el cliente de la transacción activa. */
  async buscarOCrear(
    client: DbClient | null,
    numWsp: string,
    nombre: string,
  ): Promise<number>;
  async buscarOCrear(
    first: string | DbClient | null,
    second: string,
    third?: string | DbClient | null,
  ): Promise<number> {
    let client = this.client;
    let numWsp: string;
    let nombre: string;
    if (isDbClient(first) || first === null) {
      if (isDbClient(first)) client = first;
      numWsp = second;
      nombre = typeof third === 'string' ? third : '';
    } else {
      numWsp = first;
      nombre = second;
      if (isDbClient(third)) client = third;
    }

    const existente = await this.buscarPorWsp(client ?? null, numWsp);
    if (existente !== null) {
      return existente.idCliente;
    }

    const id = await insertId(SQL_INSERT, [nombre, numWsp], client);
    if (id <= 0) {
      throw new BusinessError('No se pudo crear el cliente');
    }
    return id;
  }
}
