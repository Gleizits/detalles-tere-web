<?php

namespace Dao;

use PDO;
use ConexionDB;
use ExcepcionNegocio;

/**
 * Registra saldos a favor y ejecuta canjes completos de crédito.
 * Las operaciones reciben la conexión activa para participar de la
 * transacción abierta por PedidoDAO/VentaDAO.
 */
class CreditoClienteDAO
{
    private const SQL_CREAR =
        "INSERT INTO dbo.CREDITO_CLIENTE "
        . "(ID_CLIENTE, MONTO, ESTADO, FECHA, FECHA_VENCIMIENTO, ID_USUARIO) "
        . "VALUES (?, ?, 'ACTIVO', SYSUTCDATETIME(), "
        . "DATEADD(MONTH, 3, SYSUTCDATETIME()), ?)";

    private const SQL_SALDO =
        "SELECT COALESCE(SUM(MONTO), 0) FROM dbo.CREDITO_CLIENTE WITH (UPDLOCK, ROWLOCK) "
        . "WHERE ID_CLIENTE = ? AND ESTADO = 'ACTIVO' "
        . "AND (FECHA_VENCIMIENTO IS NULL OR FECHA_VENCIMIENTO >= SYSUTCDATETIME())";

    private const SQL_APLICAR =
        "UPDATE dbo.CREDITO_CLIENTE SET ESTADO = 'CANJEADO', ID_VENTA = ? "
        . "WHERE ID_CLIENTE = ? AND ESTADO = 'ACTIVO' "
        . "AND (FECHA_VENCIMIENTO IS NULL OR FECHA_VENCIMIENTO >= SYSUTCDATETIME())";

    // El esquema PHP aún no trae la migración que agrega ID_VENTA; si falta,
    // se omite esa columna pero se conserva la regla de canje.
    private const SQL_APLICAR_SIN_VENTA =
        "UPDATE dbo.CREDITO_CLIENTE SET ESTADO = 'CANJEADO' "
        . "WHERE ID_CLIENTE = ? AND ESTADO = 'ACTIVO' "
        . "AND (FECHA_VENCIMIENTO IS NULL OR FECHA_VENCIMIENTO >= SYSUTCDATETIME())";

    private static ?bool $tieneIdVenta = null;

    /** Convierte un adelanto no reembolsable en saldo a favor vigente. */
    public function crearDesdeCancelacion(?PDO $cn, int $idCliente, ?float $monto, int $idUsuario): void
    {
        if ($monto === null || $monto <= 0) {
            return;
        }
        $cn ??= ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_CREAR);
        $ps->execute([$idCliente, $monto, $idUsuario]);
    }

    /**
     * Marca todos los créditos vigentes como canjeados.
     * El saldo acumulado debe coincidir exactamente con el total de la venta.
     */
    public function aplicar(?PDO $cn, int $idCliente, float $total, int $idVenta): void
    {
        $cn ??= ConexionDB::obtener();

        $ps = $cn->prepare(self::SQL_SALDO);
        $ps->execute([$idCliente]);
        $saldo = (float) ($ps->fetchColumn() ?: 0);

        if (round($saldo, 2) != round($total, 2)) {
            throw new ExcepcionNegocio(
                'El saldo a favor vigente del cliente es S/ ' . number_format($saldo, 2, '.', '')
                . '. El canje debe usarlo completo.'
            );
        }

        if ($this->tieneIdVenta($cn)) {
            $cn->prepare(self::SQL_APLICAR)->execute([$idVenta, $idCliente]);
            return;
        }
        $cn->prepare(self::SQL_APLICAR_SIN_VENTA)->execute([$idCliente]);
    }

    private function tieneIdVenta(PDO $cn): bool
    {
        if (self::$tieneIdVenta === null) {
            $ps = $cn->prepare("SELECT COL_LENGTH('dbo.CREDITO_CLIENTE', 'ID_VENTA')");
            $ps->execute();
            $valor = $ps->fetchColumn();
            $ps->closeCursor();
            self::$tieneIdVenta = $valor !== false && $valor !== null;
        }
        return self::$tieneIdVenta;
    }
}
