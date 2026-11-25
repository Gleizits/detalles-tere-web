<?php

namespace Modelo;

/**
 * Línea de venta usada en el detalle del reporte administrativo.
 */
class ReporteVentaLinea
{
    public ?string $fecha = null;            // 'Y-m-d H:i:s'
    public string $articulo = '';
    public int $cantidad = 0;
    public ?float $precioVenta = null;
    public ?float $costoVigente = null;
    public ?float $subtotal = null;
    public ?float $margen = null;

    /** Equivale a getFechaTexto() del modelo Java. */
    public function fechaTexto(): string
    {
        return $this->fecha === null ? '' : date('d/m H:i', strtotime($this->fecha));
    }
}
