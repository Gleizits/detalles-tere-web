<?php

namespace Modelo;

/**
 * Material del inventario (clase MATERIAL).
 */
class Material
{
    public int $idMaterial = 0;
    public ?int $idCategoria = null;
    public string $nombre = '';
    public ?string $tipo = null;
    public ?float $precioCompra = null; // solo admin
    public ?float $precioVenta = null;
    public string $categoria = ''; // apoyo de lista
}
