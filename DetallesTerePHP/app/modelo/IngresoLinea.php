<?php

namespace Modelo;

/**
 * Línea de captura de un ingreso al inventario.
 * El DAO calcula el SUBTOTAL a partir del precio de compra y la cantidad.
 */
class IngresoLinea
{
    public int $idInventario = 0;
    public string $nombre = '';              // solo para mostrar (no es columna)
    public int $cantidad = 0;
    public ?float $precioCompraUnitario = null;
    public ?float $subtotal = null;
    public ?string $observaciones = null;

    /** Recalcula el subtotal con el precio de compra vigente. */
    public function recalcularSubtotal(): void
    {
        if ($this->precioCompraUnitario !== null) {
            $this->subtotal = round($this->precioCompraUnitario * $this->cantidad, 2);
        }
    }
}
