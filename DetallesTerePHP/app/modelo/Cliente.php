<?php

namespace Modelo;

/**
 * Cliente de la tienda: solo NOMBRE y NUM_WSP (sin DNI).
 * Se reutiliza en pedidos reservados y en créditos a favor.
 */
class Cliente
{
    public int $idCliente = 0;
    public string $nombre = '';
    public string $numWsp = '';

    /** Dato de apoyo para listar créditos (no es columna de la tabla). */
    public int $numCreditos = 0;
}
