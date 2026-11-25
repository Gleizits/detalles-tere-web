<?php

namespace Modelo;

/**
 * Línea de detalle de un pedido reservado.
 */
class DetallePedido
{
    public int $idDetallePedido = 0;
    public int $idPedido = 0;
    public int $idInventario = 0;
    public string $nombreInventario = '';
    public int $cantidad = 0;
    public float $costoUnitario = 0.0;
    public float $subtotal = 0.0;
}
