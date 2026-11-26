<?php

namespace Modelo;

/**
 * Saldo a favor de un cliente (CREDITO_CLIENTE).
 * Nace cuando se cancela un pedido reservado que aún NO está hecho:
 * el monto pagado se convierte en crédito ACTIVO, canjeable por productos
 * por el monto exacto, dentro de los 3 meses (FECHA_VENCIMIENTO).
 */
class CreditoCliente
{
    public int $idCredito = 0;
    public int $idCliente = 0;
    public ?int $idInventario = null;   // ítem canjeado (NULL si aún no se canjea)
    public ?float $monto = null;
    public string $estado = '';         // ACTIVO / CANJEADO
    public ?string $fecha = null;
    public ?string $fechaVencimiento = null;
    public int $idUsuario = 0;

    // Datos de apoyo para listar (no son columnas de la tabla)
    public string $nombreCliente = '';
    public string $numWsp = '';
}
