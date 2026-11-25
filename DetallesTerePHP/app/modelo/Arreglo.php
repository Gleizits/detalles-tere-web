<?php

namespace Modelo;

/**
 * Arreglo (clase ARREGLO): conjunto compuesto de productos/materiales.
 */
class Arreglo
{
    public int $idDetalle = 0;
    public ?int $idCategoria = null;
    public string $nombre = '';
    public ?float $precioCompra = null;
    public ?float $precioVenta = null;
    public string $categoria = '';
}

/**
 * Línea de detalle de un arreglo (qué lleva cada uno).
 */
class DetalleArreglo
{
    public int $idDetalleArreglo = 0;
    public int $idDetalle = 0; // cabecera del arreglo
    public ?int $idProducto = null;
    public ?int $idMaterial = null;
    public int $cantProducto = 0;
    public int $cantMaterial = 0;
}
