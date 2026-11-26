<?php

namespace Modelo;

/**
 * Línea de detalle de un ingreso al inventario.
 */
class DetalleIngreso
{
    public int $idDetalleIngreso = 0;
    public int $idIngreso = 0;
    public int $idInventario = 0;
    public string $nombreInventario = ''; // apoyo de lista
    public int $cantidad = 0;
    public float $precioCompraUnitario = 0.0;
    public float $subtotal = 0.0;
    public ?string $observaciones = null;
}
