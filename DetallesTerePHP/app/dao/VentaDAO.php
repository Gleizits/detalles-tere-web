<?php

namespace Dao;

use Modelo\DetalleVenta;
use Modelo\Venta;
use PDO;
use ConexionDB;
use ExcepcionNegocio;

/**
 * Acceso a datos de VENTA y DETALLE_VENTA (ventas y cotizaciones).
 * Reglas clave:
 *  - El stock SOLO se descuenta al confirmar una venta o convertir una cotización.
 *  - Una cotización NO toca el stock y vence a las 24 h.
 *  - Las operaciones que tocan stock son transaccionales.
 */
class VentaDAO
{
    private const SQL_INSERTAR_VENTA =
        "INSERT INTO dbo.VENTA (ID_CAJA, TOTAL, TIPO_PAGO, ESTADO, FECHA_VENTA) "
        . "OUTPUT INSERTED.ID_VENTA VALUES (?, ?, ?, ?, SYSUTCDATETIME())";

    private const SQL_INSERTAR_DETALLE =
        "INSERT INTO dbo.DETALLE_VENTA "
        . "(ID_INVENTARIO, CANTIDAD, COSTO_UNITARIO, SUBTOTAL, ID_CAJA, ID_VENTA, "
        . " FECHA_CREACION, ESTADO) "
        . "VALUES (?, ?, ?, ?, ?, ?, SYSUTCDATETIME(), 'VENDIDO')";

    private const SQL_INSERTAR_DETALLE_COTIZACION =
        "INSERT INTO dbo.DETALLE_VENTA "
        . "(ID_INVENTARIO, CANTIDAD, COSTO_UNITARIO, SUBTOTAL, ID_CAJA, ID_VENTA, "
        . " FECHA_CREACION, FECHA_VENCIMIENTO, ESTADO) "
        . "VALUES (?, ?, ?, ?, NULL, ?, SYSUTCDATETIME(), "
        . "        DATEADD(HOUR, 24, SYSUTCDATETIME()), 'COTIZACION')";

    private const SQL_DESCONTAR_STOCK =
        "UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT - ? "
        . "WHERE ID_INVENTARIO = ? AND STOCK_ACT >= ?";

    private const SQL_MOVIMIENTO =
        "INSERT INTO dbo.MOVIMIENTO_INVENTARIO "
        . "(ID_USUARIO, ID_INVENTARIO, FECHA_MOVIMIENTO, TIPO_MOVIMIENTO, CANTIDAD) "
        . "VALUES (?, ?, SYSUTCDATETIME(), 'SALIDA', ?)";

    private const SQL_COTIZACIONES =
        "SELECT v.ID_VENTA, v.TOTAL, v.FECHA_VENTA, "
        . "COUNT(d.ID_DETALLE_VENTA) AS NUM_LINEAS, "
        . "STRING_AGG(CAST(COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) AS NVARCHAR(200)) "
        . "          + ' x' + CAST(d.CANTIDAD AS VARCHAR(10)), ', ') "
        . "    WITHIN GROUP (ORDER BY d.ID_DETALLE_VENTA) AS RESUMEN "
        . "FROM dbo.VENTA v "
        . "JOIN dbo.DETALLE_VENTA d ON d.ID_VENTA = v.ID_VENTA "
        . "JOIN dbo.INVENTARIO i ON d.ID_INVENTARIO = i.ID_INVENTARIO "
        . "LEFT JOIN dbo.PRODUCTO p ON i.ID_PRODUCTO = p.ID_PRODUCTO "
        . "LEFT JOIN dbo.MATERIAL m ON i.ID_MATERIAL = m.ID_MATERIAL "
        . "LEFT JOIN dbo.ARREGLO a ON i.ID_DETALLE = a.ID_DETALLE "
        . "WHERE v.ESTADO = 'COTIZACION' "
        . "  AND v.FECHA_VENTA >= DATEADD(HOUR, -24, SYSUTCDATETIME()) "
        . "GROUP BY v.ID_VENTA, v.TOTAL, v.FECHA_VENTA "
        . "ORDER BY v.FECHA_VENTA DESC";

    private const SQL_LINEAS_COTIZACION =
        "SELECT d.ID_DETALLE_VENTA, d.ID_INVENTARIO, d.CANTIDAD, d.COSTO_UNITARIO, "
        . "d.SUBTOTAL, d.ESTADO, i.STOCK_ACT, "
        . "COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) AS NOMBRE "
        . "FROM dbo.DETALLE_VENTA d "
        . "JOIN dbo.INVENTARIO i ON d.ID_INVENTARIO = i.ID_INVENTARIO "
        . "JOIN dbo.VENTA v ON v.ID_VENTA = d.ID_VENTA AND v.ESTADO = 'COTIZACION' "
        . "LEFT JOIN dbo.PRODUCTO p ON i.ID_PRODUCTO = p.ID_PRODUCTO "
        . "LEFT JOIN dbo.MATERIAL m ON i.ID_MATERIAL = m.ID_MATERIAL "
        . "LEFT JOIN dbo.ARREGLO a ON i.ID_DETALLE = a.ID_DETALLE "
        . "WHERE d.ID_VENTA = ? ORDER BY d.ID_DETALLE_VENTA";

    private const SQL_BORRAR_LINEAS_EXPIRADAS =
        "DELETE FROM dbo.DETALLE_VENTA "
        . "WHERE ESTADO = 'COTIZACION' AND FECHA_VENCIMIENTO IS NOT NULL "
        . "  AND FECHA_VENCIMIENTO < SYSUTCDATETIME()";

    private const SQL_BORRAR_CABECERAS_EXPIRADAS =
        "DELETE FROM dbo.VENTA WHERE ESTADO = 'COTIZACION' AND NOT EXISTS ("
        . "  SELECT 1 FROM dbo.DETALLE_VENTA d WHERE d.ID_VENTA = VENTA.ID_VENTA)";

    private CreditoClienteDAO $creditoDAO;

    public function __construct()
    {
        $this->creditoDAO = new CreditoClienteDAO();
    }

    /**
     * Confirma una venta: VENTA + líneas, descuento de stock y movimientos.
     * Devuelve el ID de la venta creada.
     * @param DetalleVenta[] $carrito
     */
    public function confirmarVenta(array $carrito, int $idCaja, int $idUsuario, string $tipoPago): int
    {
        $total = $this->sumar($carrito);
        $cn = ConexionDB::obtener();
        $cn->beginTransaction();
        try {
            $idVenta = $this->insertarVenta($cn, $idCaja, $total, $tipoPago, 'VENDIDO');
            foreach ($carrito as $linea) {
                $this->insertarDetalle($cn, $linea, $idCaja, $idVenta, false);
                $this->descontarStock($cn, $linea->idInventario, $linea->cantidad);
                $this->insertarMovimiento($cn, $idUsuario, $linea->idInventario, $linea->cantidad);
            }
            $cn->commit();
            return $idVenta;
        } catch (\Throwable $e) {
            $this->revertir($cn);
            if ($e instanceof \PDOException) {
                throw new \RuntimeException('No se pudo registrar la venta', 0, $e);
            }
            throw $e;   // stock insuficiente: el mensaje ya es claro
        }
    }

    /**
     * Confirma una venta usando todo el saldo a favor vigente del cliente.
     * El total debe coincidir exactamente; no admite pagos mixtos.
     * @param DetalleVenta[] $carrito
     */
    public function confirmarVentaConCredito(array $carrito, int $idCaja, int $idUsuario, int $idCliente): int
    {
        $total = $this->sumar($carrito);
        $cn = ConexionDB::obtener();
        $cn->beginTransaction();
        try {
            $idVenta = $this->insertarVenta($cn, $idCaja, $total, 'CREDITO', 'VENDIDO');
            foreach ($carrito as $linea) {
                $this->insertarDetalle($cn, $linea, $idCaja, $idVenta, false);
                $this->descontarStock($cn, $linea->idInventario, $linea->cantidad);
                $this->insertarMovimiento($cn, $idUsuario, $linea->idInventario, $linea->cantidad);
            }
            $this->creditoDAO->aplicar($cn, $idCliente, $total, $idVenta);

            $cn->commit();
            return $idVenta;
        } catch (\Throwable $e) {
            $this->revertir($cn);
            if ($e instanceof \PDOException) {
                throw new \RuntimeException('No se pudo registrar el canje', 0, $e);
            }
            throw $e;
        }
    }

    /**
     * Guarda el carrito como cotización (24 h) SIN descontar stock.
     * Devuelve el ID de la cotización.
     * @param DetalleVenta[] $carrito
     */
    public function guardarCotizacion(array $carrito): int
    {
        $total = $this->sumar($carrito);
        $cn = ConexionDB::obtener();
        $cn->beginTransaction();
        try {
            $idVenta = $this->insertarVenta($cn, null, $total, null, 'COTIZACION');
            foreach ($carrito as $linea) {
                $this->insertarDetalle($cn, $linea, null, $idVenta, true);
            }
            $cn->commit();
            return $idVenta;
        } catch (\Throwable $e) {
            $this->revertir($cn);
            if ($e instanceof \PDOException) {
                throw new \RuntimeException('No se pudo guardar la cotización', 0, $e);
            }
            throw $e;
        }
    }

    /** Cotizaciones activas (no vencidas). @return Venta[] */
    public function listarCotizaciones(): array
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_COTIZACIONES);
        $ps->execute();

        $lista = [];
        while ($fila = $ps->fetch()) {
            $v = new Venta();
            $v->idVenta = (int) $fila['ID_VENTA'];
            $v->total = $fila['TOTAL'] !== null ? (float) $fila['TOTAL'] : null;
            $v->estado = 'COTIZACION';
            $v->numLineas = (int) $fila['NUM_LINEAS'];
            $v->resumen = (string) ($fila['RESUMEN'] ?? '');
            $v->fechaVenta = $fila['FECHA_VENTA'] ?? null;
            $lista[] = $v;
        }
        return $lista;
    }

    /**
     * Líneas de una cotización (nombre y stock actual).
     * Cualquier otra venta queda fuera (H8).
     * @return DetalleVenta[]
     */
    public function obtenerLineas(int $idVenta): array
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_LINEAS_COTIZACION);
        $ps->execute([$idVenta]);

        $lineas = [];
        while ($fila = $ps->fetch()) {
            $linea = new DetalleVenta();
            $linea->idDetalleVenta = (int) $fila['ID_DETALLE_VENTA'];
            $linea->idInventario = (int) $fila['ID_INVENTARIO'];
            $linea->cantidad = (int) $fila['CANTIDAD'];
            $linea->costoUnitario = $fila['COSTO_UNITARIO'] !== null ? (float) $fila['COSTO_UNITARIO'] : null;
            $linea->subtotal = $fila['SUBTOTAL'] !== null ? (float) $fila['SUBTOTAL'] : null;
            $linea->estado = (string) ($fila['ESTADO'] ?? '');
            $linea->nombre = (string) ($fila['NOMBRE'] ?? '');
            $linea->stockDisponible = (int) $fila['STOCK_ACT'];
            $linea->idVenta = $idVenta;
            $lineas[] = $linea;
        }
        return $lineas;
    }

    /**
     * Convierte una cotización en venta: cabecera y líneas a VENDIDO,
     * descuenta stock y registra movimientos. Transaccional.
     */
    public function convertirCotizacion(int $idVenta, int $idCaja, int $idUsuario, string $tipoPago): void
    {
        $cn = ConexionDB::obtener();
        $cn->beginTransaction();
        try {
            // 0) Leer las líneas ANTES de cambiar su estado.
            $lineas = $this->obtenerLineasCn($cn, $idVenta);

            // 1) Marcar cabecera como vendida (solo si aún es cotización).
            $ps = $cn->prepare(
                "UPDATE dbo.VENTA SET ID_CAJA = ?, TIPO_PAGO = ?, ESTADO = 'VENDIDO' "
                . "WHERE ID_VENTA = ? AND ESTADO = 'COTIZACION'"
            );
            $ps->execute([$idCaja, $tipoPago, $idVenta]);
            if ($ps->rowCount() === 0) {
                throw new ExcepcionNegocio('La cotización ya fue cobrada o ya no existe.');
            }

            // 2) Marcar líneas como vendidas y fijar la caja.
            $cn->prepare(
                "UPDATE dbo.DETALLE_VENTA SET ID_CAJA = ?, ESTADO = 'VENDIDO' "
                . "WHERE ID_VENTA = ? AND ESTADO = 'COTIZACION'"
            )->execute([$idCaja, $idVenta]);

            // 3) Descontar stock por cada línea leída antes del UPDATE.
            foreach ($lineas as $linea) {
                $this->descontarStock($cn, $linea->idInventario, $linea->cantidad);
                $this->insertarMovimiento($cn, $idUsuario, $linea->idInventario, $linea->cantidad);
            }

            $cn->commit();
        } catch (\Throwable $e) {
            $this->revertir($cn);
            if ($e instanceof \PDOException) {
                throw new \RuntimeException('No se pudo cobrar la cotización', 0, $e);
            }
            throw $e;
        }
    }

    /** Elimina cotizaciones vencidas (>24 h). Devuelve cuántas líneas borró. */
    public function eliminarExpiradas(): int
    {
        $cn = ConexionDB::obtener();
        $cn->beginTransaction();
        try {
            $ps = $cn->prepare(self::SQL_BORRAR_LINEAS_EXPIRADAS);
            $ps->execute();
            $borradas = $ps->rowCount();
            $cn->prepare(self::SQL_BORRAR_CABECERAS_EXPIRADAS)->execute();
            $cn->commit();
            return $borradas;
        } catch (\Throwable $e) {
            $this->revertir($cn);
            if ($e instanceof \PDOException) {
                throw new \RuntimeException('Error al limpiar cotizaciones vencidas', 0, $e);
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Helpers internos
    // ------------------------------------------------------------------

    /** @param DetalleVenta[] $carrito */
    private function sumar(array $carrito): float
    {
        $total = 0.0;
        foreach ($carrito as $linea) {
            $total += $linea->subtotal ?? 0.0;
        }
        return round($total, 2);
    }

    private function insertarVenta(PDO $cn, ?int $idCaja, float $total, ?string $tipoPago, string $estado): int
    {
        $ps = $cn->prepare(self::SQL_INSERTAR_VENTA);
        $ps->bindValue(1, $idCaja, $idCaja === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $ps->bindValue(2, $total);
        $ps->bindValue(3, $tipoPago, $tipoPago === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $ps->bindValue(4, $estado, PDO::PARAM_STR);
        $ps->execute();

        $id = $ps->fetchColumn();
        $ps->closeCursor();
        if ($id === false || $id === null) {
            throw new \PDOException('No se pudo crear la venta');
        }
        return (int) $id;
    }

    private function insertarDetalle(
        PDO $cn,
        DetalleVenta $linea,
        ?int $idCaja,
        int $idVenta,
        bool $cotizacion
    ): void {
        if ($cotizacion) {
            $ps = $cn->prepare(self::SQL_INSERTAR_DETALLE_COTIZACION);
            $ps->execute([
                $linea->idInventario, $linea->cantidad, $linea->costoUnitario,
                $linea->subtotal, $idVenta,
            ]);
            return;
        }

        $ps = $cn->prepare(self::SQL_INSERTAR_DETALLE);
        $ps->execute([
            $linea->idInventario, $linea->cantidad, $linea->costoUnitario,
            $linea->subtotal, $idCaja, $idVenta,
        ]);
    }

    private function descontarStock(PDO $cn, int $idInventario, int $cantidad): void
    {
        $ps = $cn->prepare(self::SQL_DESCONTAR_STOCK);
        $ps->execute([$cantidad, $idInventario, $cantidad]);
        if ($ps->rowCount() === 0) {
            throw new ExcepcionNegocio('Stock insuficiente para uno de los productos. Revisa el carrito.');
        }
    }

    private function insertarMovimiento(PDO $cn, int $idUsuario, int $idInventario, int $cantidad): void
    {
        $ps = $cn->prepare(self::SQL_MOVIMIENTO);
        $ps->execute([$idUsuario, $idInventario, $cantidad]);
    }

    /** @return DetalleVenta[] */
    private function obtenerLineasCn(PDO $cn, int $idVenta): array
    {
        $ps = $cn->prepare(
            "SELECT ID_INVENTARIO, CANTIDAD FROM dbo.DETALLE_VENTA "
            . "WHERE ID_VENTA = ? AND ESTADO = 'COTIZACION'"
        );
        $ps->execute([$idVenta]);

        $lineas = [];
        while ($fila = $ps->fetch()) {
            $linea = new DetalleVenta();
            $linea->idInventario = (int) $fila['ID_INVENTARIO'];
            $linea->cantidad = (int) $fila['CANTIDAD'];
            $lineas[] = $linea;
        }
        return $lineas;
    }

    private function revertir(PDO $cn): void
    {
        if ($cn->inTransaction()) {
            $cn->rollBack();
        }
    }
}
