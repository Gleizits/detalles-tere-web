<?php

namespace Modelo;

/**
 * Categoría única que agrupa productos, materiales y arreglos.
 */
class Categoria
{
    public int $idCategoria = 0;
    public string $nombre = '';
    public ?string $descripcion = null;
}
