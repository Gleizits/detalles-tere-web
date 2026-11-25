<?php

namespace Modelo;

/**
 * Cabecera de INGRESO (compra a proveedor, solo admin).
 */
class Ingreso
{
    public int $idIngreso = 0;
    public int $idProveedor = 0;
    public int $idUsuario = 0;
    public ?string $fechaIngreso = null; // 'Y-m-d'
    public string $nombreProveedor = ''; // apoyo de lista
    /** @var DetalleIngreso[] */
    public array $lineas = [];
}
