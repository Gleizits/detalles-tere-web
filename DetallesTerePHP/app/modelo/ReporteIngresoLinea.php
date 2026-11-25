<?php

namespace Modelo;

/**
 * Línea de ingreso usada en el detalle del reporte administrativo.
 */
class ReporteIngresoLinea
{
    public ?string $fecha = null;            // 'Y-m-d'
    public string $proveedor = '';
    public string $articulo = '';
    public int $cantidad = 0;
    public ?float $precioCompra = null;
    public ?float $subtotal = null;
}
