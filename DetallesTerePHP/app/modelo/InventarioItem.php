<?php

namespace Modelo;

/**
 * Fila del inventario (vista unificada).
 * INVENTARIO apunta a exactamente 1 de: PRODUCTO | MATERIAL | ARREGLO.
 * Aquí se fusiona el nombre/tipo/precios de la tabla correspondiente.
 */
class InventarioItem
{
    public int $idInventario = 0;
    public int $stockAct = 0;
    public int $stockMin = 0;
    public string $tipoAlmacen = '';
    public string $unidadMedida = '';

    public string $nombre = '';    // de producto, material o arreglo
    public string $tipo = '';      // de producto/material (arreglo no tiene)
    public string $categoria = '';
    public string $clase = '';     // PRODUCTO | MATERIAL | ARREGLO

    public ?float $precioVenta = null;
    public ?float $precioCompra = null; // solo admin (se anula para empleado)

    // IDs internos (necesarios para edición/ajustes)
    public int $idProducto = 0;
    public int $idMaterial = 0;
    public int $idArreglo = 0;
    public int $idCategoria = 0;
    public int $idMaterialVinc = 0;
    public bool $estado = true;

    /** true si el stock actual está en o por debajo del mínimo. */
    public function esStockBajo(): bool
    {
        return $this->stockAct <= $this->stockMin;
    }
}
