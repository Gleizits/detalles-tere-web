<?php

namespace Modelo;

/**
 * Cabecera de AJUSTE_INVENTARIO (entrada/salida por inventario físico).
 */
class Ajuste
{
    public int $idAjuste = 0;
    public int $idUsuario = 0;
    public ?string $fecha = null;
    /** @var DetalleAjuste[] */
    public array $lineas = [];
}

/**
 * Línea de detalle de un ajuste de inventario.
 */
class DetalleAjuste
{
    public int $idDetalleAjuste = 0;
    public int $idAjuste = 0;
    public int $idInventario = 0;
    public string $nombreInventario = ''; // apoyo de lista
    public int $cantidad = 0;
    public string $tipoAjuste = ''; // libre: ENTRADA / SALIDA / AJUSTE
    public ?string $motivo = null;
}
