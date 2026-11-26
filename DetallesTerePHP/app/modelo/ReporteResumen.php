<?php

namespace Modelo;

/**
 * Resumen y detalle de un periodo seleccionado por el administrador.
 * Los importes se inicializan en cero, como en el modelo Java.
 */
class ReporteResumen
{
    public ?string $desde = null;            // 'Y-m-d'
    public ?string $hasta = null;            // 'Y-m-d'
    public string $etiquetaPeriodo = '';
    public int $cantidadVentas = 0;
    public int $unidadesVendidas = 0;
    public float $ventasTotales = 0.0;
    public float $efectivo = 0.0;
    public float $yape = 0.0;
    public float $credito = 0.0;
    public float $ingresosTotales = 0.0;
    public int $unidadesIngresadas = 0;
    public float $margenEstimado = 0.0;

    /** @var ReporteVentaLinea[] */
    public array $ventas = [];
    /** @var ReporteIngresoLinea[] */
    public array $ingresos = [];
}
