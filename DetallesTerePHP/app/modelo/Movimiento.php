<?php

namespace Modelo;

/**
 * Fila de trazabilidad MOVIMIENTO_INVENTARIO.
 */
class Movimiento
{
    public int $idMovimiento = 0;
    public int $idUsuario = 0;
    public int $idInventario = 0;
    public string $nombreInventario = '';
    public ?string $fechaMovimiento = null;
    public string $tipoMovimiento = ''; // ENTRADA / SALIDA / AJUSTE
    public int $cantidad = 0;
}
