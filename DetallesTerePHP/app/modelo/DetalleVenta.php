<?php

namespace Modelo;

/**
 * Línea de venta / carrito / cotización.
 * Equivale a una fila de DETALLE_VENTA; también se usa en memoria
 * para el carrito de la caja (con nombre y stockDisponible para validar).
 */
class DetalleVenta
{
    public int $idDetalleVenta = 0;
    public int $idInventario = 0;
    public string $nombre = '';            // solo para mostrar en el carrito
    public int $cantidad = 0;
    public ?float $costoUnitario = null;   // precio de venta fijado al momento
    public ?float $subtotal = null;
    public ?int $idCaja = null;
    public ?int $idVenta = null;
    public ?string $fechaCreacion = null;
    public ?string $fechaVencimiento = null;  // cotización: +24h
    public string $estado = '';
    public int $stockDisponible = 0;       // validación en carrito (no persistido)

    /** Crea una línea de carrito con subtotal calculado. */
    public function __construct(
        int $idInventario = 0,
        string $nombre = '',
        int $cantidad = 0,
        ?float $costoUnitario = null,
        int $stockDisponible = 0
    ) {
        $this->idInventario = $idInventario;
        $this->nombre = $nombre;
        $this->cantidad = $cantidad;
        $this->costoUnitario = $costoUnitario;
        $this->stockDisponible = $stockDisponible;
        $this->subtotal = $costoUnitario !== null
            ? round($costoUnitario * $cantidad, 2)
            : null;
    }

    /** Recalcula el subtotal a partir de cantidad y costo unitario. */
    public function recalcularSubtotal(): void
    {
        if ($this->costoUnitario !== null) {
            $this->subtotal = round($this->costoUnitario * $this->cantidad, 2);
        }
    }
}
