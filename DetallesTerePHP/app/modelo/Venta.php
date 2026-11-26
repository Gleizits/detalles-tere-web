<?php

namespace Modelo;

/**
 * Cabecera de VENTA. Sirve tanto para una venta confirmada
 * como para una cotización (ESTADO='COTIZACION', TIPO_PAGO e ID_CAJA en NULL).
 */
class Venta
{
    public int $idVenta = 0;
    public ?int $idCaja = null;
    public ?float $total = null;
    public ?string $tipoPago = null;   // YAPE / EFECTIVO (NULL en cotización)
    public string $estado = '';        // COTIZACION / VENDIDO / CANCELADO
    public ?string $fechaVenta = null;

    // Datos de apoyo para listar cotizaciones (no son columnas de la tabla)
    public int $numLineas = 0;
    public string $resumen = '';
}
