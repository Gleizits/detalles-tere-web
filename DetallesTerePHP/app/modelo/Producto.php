<?php

namespace Modelo;

/**
 * Producto del inventario (clase PRODUCTO).
 */
class Producto
{
    public int $idProducto = 0;
    public ?int $idCategoria = null;
    public string $nombre = '';
    public ?string $tipo = null;
    public ?float $precioCompra = null; // solo admin
    public ?float $precioVenta = null;
    public string $categoria = ''; // apoyo de lista
}
