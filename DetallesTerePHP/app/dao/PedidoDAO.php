<?php

namespace Dao;

use Modelo\DetalleVenta;
use Modelo\Pedido;
use PDO;
use ConexionDB;
use ExcepcionNegocio;

/**
 * Acceso a datos de PEDIDOS (reservados).
 * Reglas clave:
 *  - Al crear se descuenta el stock (se aparta) y se registra un movimiento
 *    SALIDA, en una sola transacción.
 *  - Cada pago genera una fila en VENTA con ID_PEDIDO para el reporte de caja.
 *  - ADELANTO = monto pagado acumulado; Faltante = TOTAL - ADELANTO.
 */
class PedidoDAO
{
    private const SQL_LISTAR =
        "SELECT pd.ID_PEDIDO, pd.ID_CLIENTE, pd.NOMBRE_PAGA, pd.NOMBRE_RECOGE, pd.CELULAR, "
        . "pd.FECHA_RECOJO, pd.HORA_RECOJO, pd.ADELANTO, pd.TOTAL, pd.ESTADO, pd.ESTADO_PRODUCCION, "
        . "pd.ID_USUARIO, pd.FECHA_CREACION, pd.FECHA_LIMITE_CANJE, pd.FECHA_LIMITE_RECOJO, "
        . "pd.FECHA_LIMITE_PAGO, pd.FECHA_PAGO_TOTAL, cl.NOMBRE AS NOMBRE_CLIENTE "
        . "FROM dbo.PEDIDOS pd LEFT JOIN dbo.CLIENTE cl ON pd.ID_CLIENTE = cl.ID_CLIENTE "
        . "WHERE (? IS NULL OR pd.NOMBRE_PAGA LIKE ? OR pd.CELULAR LIKE ? "
        . "       OR ISNULL(pd.NOMBRE_RECOGE, '') LIKE ?) "
        . "AND (? IS NULL OR pd.ID_USUARIO = ?) "
        . "ORDER BY pd.FECHA_CREACION DESC";

    private const SQL_BUSCAR =
        "SELECT pd.ID_PEDIDO, pd.ID_CLIENTE, pd.NOMBRE_PAGA, pd.NOMBRE_RECOGE, pd.CELULAR, "
        . "pd.FECHA_RECOJO, pd.HORA_RECOJO, pd.ADELANTO, pd.TOTAL, pd.ESTADO, pd.ESTADO_PRODUCCION, "
        . "pd.ID_USUARIO, pd.FECHA_CREACION, pd.FECHA_LIMITE_CANJE, pd.FECHA_LIMITE_RECOJO, "
        . "pd.FECHA_LIMITE_PAGO, pd.FECHA_PAGO_TOTAL, cl.NOMBRE AS NOMBRE_CLIENTE "
        . "FROM dbo.PEDIDOS pd LEFT JOIN dbo.CLIENTE cl ON pd.ID_CLIENTE = cl.ID_CLIENTE "
        . "WHERE pd.ID_PEDIDO = ?";

    private const SQL_LINEAS =
        "SELECT dp.ID_INVENTARIO, COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) AS NOMBRE, "
        . "dp.CANTIDAD, dp.COSTO_UNITARIO, dp.SUBTOTAL "
        . "FROM dbo.DETALLE_PEDIDO dp "
        . "JOIN dbo.INVENTARIO i ON dp.ID_INVENTARIO = i.ID_INVENTARIO "
        . "LEFT JOIN dbo.PRODUCTO p ON i.ID_PRODUCTO = p.ID_PRODUCTO "
        . "LEFT JOIN dbo.MATERIAL m ON i.ID_MATERIAL = m.ID_MATERIAL "
        . "LEFT JOIN dbo.ARREGLO a ON i.ID_DETALLE = a.ID_DETALLE "
        . "WHERE dp.ID_PEDIDO = ? ORDER BY NOMBRE";

    private const SQL_INSERTAR =
        "INSERT INTO dbo.PEDIDOS (ID_CLIENTE, NOMBRE_PAGA, NOMBRE_RECOGE, CELULAR, "
        . "FECHA_RECOJO, HORA_RECOJO, ADELANTO, TOTAL, ESTADO, ESTADO_PRODUCCION, ID_USUARIO, "
        . "FECHA_CREACION, FECHA_LIMITE_CANJE, FECHA_LIMITE_RECOJO, FECHA_LIMITE_PAGO, FECHA_PAGO_TOTAL) "
        . "OUTPUT INSERTED.ID_PEDIDO "
        . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "
        . "SYSUTCDATETIME(), DATEADD(MONTH, 3, SYSUTCDATETIME()), DATEADD(MONTH, 2, ?), "
        . "DATEADD(MONTH, 1, SYSUTCDATETIME()), "
        . "CASE WHEN ? = 'COMPLETO' THEN SYSUTCDATETIME() ELSE NULL END)";

    private const SQL_INSERTAR_DETALLE =
        "INSERT INTO dbo.DETALLE_PEDIDO (ID_PEDIDO, ID_INVENTARIO, CANTIDAD, COSTO_UNITARIO, SUBTOTAL) "
        . "VALUES (?, ?, ?, ?, ?)";

    private const SQL_DESCONTAR_STOCK =
        "UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT - ? "
        . "WHERE ID_INVENTARIO = ? AND STOCK_ACT >= ?";

    private const SQL_MOVIMIENTO =
        "INSERT INTO dbo.MOVIMIENTO_INVENTARIO (ID_USUARIO, ID_INVENTARIO, TIPO_MOVIMIENTO, "
        . "CANTIDAD, FECHA_MOVIMIENTO) VALUES (?, ?, ?, ?, SYSUTCDATETIME())";

    private const SQL_PAGO =
        "INSERT INTO dbo.VENTA (ID_CAJA, ID_PEDIDO, TOTAL, TIPO_PAGO, ESTADO, FECHA_VENTA) "
        . "VALUES (?, ?, ?, ?, 'VENDIDO', SYSUTCDATETIME())";

    private const SQL_LEER_CANCELAR =
        "SELECT ID_PEDIDO, ID_CLIENTE, ADELANTO, ESTADO, ESTADO_PRODUCCION "
        . "FROM dbo.PEDIDOS WITH (UPDLOCK, ROWLOCK) WHERE ID_PEDIDO = ?";

    private const SQL_DEVOLVER_STOCK =
        "UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT + ? WHERE ID_INVENTARIO = ?";

    private ClienteDAO $clienteDAO;
    private CreditoClienteDAO $creditoDAO;

    public function __construct()
    {
        $this->clienteDAO = new ClienteDAO();
        $this->creditoDAO = new CreditoClienteDAO();
    }

    /**
     * Crea un pedido reservado de forma transaccional: cliente, cabecera,
     * líneas, descuento de stock + movimiento y el pago en VENTA.
     * Devuelve el id del pedido.
     * @param DetalleVenta[] $lineas
     */
    public function crear(Pedido $p, array $lineas, int $idCaja, string $metodoPago): int
    {
        // El cliente se confirma aparte: un cliente huérfano es inocuo.
        $idCliente = $this->clienteDAO->buscarOCrear($p->celular, $p->nombrePaga);

        $cn = ConexionDB::obtener();
        $cn->beginTransaction();
        try {
            $idPedido = $this->insertarPedido($cn, $p, $idCliente);
            foreach ($lineas as $linea) {
                $this->insertarDetalle($cn, $idPedido, $linea);
                $this->descontarStock($cn, $linea->idInventario, $linea->cantidad);
                $this->insertarMovimiento($cn, $p->idUsuario, $linea->idInventario, $linea->cantidad, 'SALIDA');
            }
            if ($p->adelanto !== null && $p->adelanto > 0) {
                $this->insertarPago($cn, $idCaja, $idPedido, $p->adelanto, $metodoPago);
            }

            $cn->commit();
            return $idPedido;
        } catch (\Throwable $e) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
            }
            throw $e;
        }
    }

    /** Registra un abono: suma al ADELANTO y crea el pago en VENTA. Transaccional. */
    public function abono(int $idPedido, float $monto, int $idCaja, string $metodo, int $idUsuario): void
    {
        $cn = ConexionDB::obtener();
        $cn->beginTransaction();
        try {
            $ps = $cn->prepare("SELECT TOTAL, ADELANTO FROM dbo.PEDIDOS WHERE ID_PEDIDO = ?");
            $ps->execute([$idPedido]);
            $fila = $ps->fetch();
            if ($fila === false) {
                throw new ExcepcionNegocio('El pedido no existe');
            }

            $total = (float) $fila['TOTAL'];
            $adelanto = $fila['ADELANTO'] !== null ? (float) $fila['ADELANTO'] : 0.0;
            $saldo = round($total - $adelanto, 2);
            // Tope: el abono nunca puede superar lo que falta por pagar (H4).
            if (round($monto, 2) > $saldo) {
                throw new ExcepcionNegocio('El abono supera el saldo pendiente del pedido');
            }

            $ps = $cn->prepare(
                "UPDATE dbo.PEDIDOS SET ADELANTO = ADELANTO + ?, "
                . "ESTADO = CASE WHEN ADELANTO + ? >= TOTAL THEN 'COMPLETO' ELSE ESTADO END, "
                . "FECHA_PAGO_TOTAL = CASE WHEN ADELANTO + ? >= TOTAL "
                . "THEN SYSUTCDATETIME() ELSE FECHA_PAGO_TOTAL END "
                . "WHERE ID_PEDIDO = ? AND ESTADO IN ('PENDIENTE', 'COMPLETO')"
            );
            $ps->execute([$monto, $monto, $monto, $idPedido]);
            if ($ps->rowCount() === 0) {
                // Cancelado/recogido: no se registra el pago en VENTA.
                throw new ExcepcionNegocio('El pedido ya no admite abonos (cancelado o entregado)');
            }

            $this->insertarPago($cn, $idCaja, $idPedido, $monto, $metodo);
            $cn->commit();
        } catch (\Throwable $e) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
            }
            throw $e;
        }
    }

    /** Marca el pedido como HECHO (punto de no retorno). */
    public function marcarHecho(int $idPedido): void
    {
        $this->ejecutarEstado(
            "UPDATE dbo.PEDIDOS SET ESTADO_PRODUCCION = 'HECHO' "
            . "WHERE ID_PEDIDO = ? AND ESTADO_PRODUCCION = 'PENDIENTE'",
            $idPedido
        );
    }

    /** Marca el pedido como RECOGIDO (entregado). Debe estar pagado por completo. */
    public function recoger(int $idPedido): void
    {
        $this->ejecutarEstado(
            "UPDATE dbo.PEDIDOS SET ESTADO = 'RECOGIDO' "
            . "WHERE ID_PEDIDO = ? AND ADELANTO >= TOTAL AND ESTADO = 'COMPLETO'",
            $idPedido
        );
    }

    /**
     * Cancela antes de producción, devuelve las unidades apartadas al
     * inventario y convierte el adelanto en saldo a favor, sin reembolso.
     */
    public function cancelar(int $idPedido, int $idUsuario): void
    {
        $cn = ConexionDB::obtener();
        $cn->beginTransaction();
        try {
            $pedido = $this->leerParaCancelar($cn, $idPedido);
            if ($pedido === null) {
                throw new ExcepcionNegocio('El pedido no existe.');
            }
            if ($pedido->estadoProduccion !== 'PENDIENTE'
                || $pedido->estado === 'CANCELADO'
                || $pedido->estado === 'RECOGIDO') {
                throw new ExcepcionNegocio('Solo se puede cancelar un pedido antes de iniciar producción.');
            }

            foreach ($this->lineas($cn, $idPedido) as $linea) {
                $this->devolverStock($cn, $linea->idInventario, $linea->cantidad);
                $this->insertarMovimiento($cn, $idUsuario, $linea->idInventario, $linea->cantidad, 'ENTRADA');
            }

            $ps = $cn->prepare(
                "UPDATE dbo.PEDIDOS SET ESTADO = 'CANCELADO', ESTADO_PRODUCCION = 'CANCELADO' "
                . "WHERE ID_PEDIDO = ? AND ESTADO_PRODUCCION = 'PENDIENTE'"
            );
            $ps->execute([$idPedido]);
            if ($ps->rowCount() === 0) {
                throw new ExcepcionNegocio('El pedido cambió de estado y ya no puede cancelarse.');
            }

            $this->creditoDAO->crearDesdeCancelacion(
                $cn,
                $pedido->idCliente ?? 0,
                $pedido->adelanto,
                $idUsuario
            );
            $cn->commit();
        } catch (\Throwable $e) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
            }
            throw $e;
        }
    }

    /** Lista pedidos; q filtra por nombre, celular o quién recoge (todos). */
    public function listar(?string $q = null, ?int $idUsuario = null): array
    {
        $filtro = ($q === null || trim($q) === '') ? null : '%' . trim($q) . '%';

        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_LISTAR);
        for ($i = 1; $i <= 4; $i++) {
            if ($filtro === null) {
                $ps->bindValue($i, null, PDO::PARAM_NULL);
            } else {
                $ps->bindValue($i, $filtro, PDO::PARAM_STR);
            }
        }
        if ($idUsuario === null) {
            $ps->bindValue(5, null, PDO::PARAM_NULL);
            $ps->bindValue(6, null, PDO::PARAM_NULL);
        } else {
            $ps->bindValue(5, $idUsuario, PDO::PARAM_INT);
            $ps->bindValue(6, $idUsuario, PDO::PARAM_INT);
        }
        $ps->execute();

        $lista = [];
        while ($fila = $ps->fetch()) {
            $lista[] = $this->mapear($fila);
        }
        return $lista;
    }

    /** Busca un pedido por id con sus líneas, o null si no existe. */
    public function buscarPorId(int $idPedido): ?Pedido
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_BUSCAR);
        $ps->execute([$idPedido]);
        $fila = $ps->fetch();
        if ($fila === false) {
            return null;
        }

        $pedido = $this->mapear($fila);
        $pedido->lineas = $this->lineas($cn, $idPedido);
        return $pedido;
    }

    // ------------------------------------------------------------------
    // Helpers internos
    // ------------------------------------------------------------------

    private function insertarPedido(PDO $cn, Pedido $p, int $idCliente): int
    {
        $ps = $cn->prepare(self::SQL_INSERTAR);
        $ps->execute([
            $idCliente, $p->nombrePaga, $p->nombreRecoge, $p->celular,
            $p->fechaRecojo, $p->horaRecojo, $p->adelanto, $p->total,
            $p->estado, $p->estadoProduccion, $p->idUsuario,
            $p->fechaRecojo,   // base de FECHA_LIMITE_RECOJO
            $p->estado,        // decide FECHA_PAGO_TOTAL
        ]);
        $id = $ps->fetchColumn();
        $ps->closeCursor();
        if ($id === false || $id === null) {
            throw new \PDOException('No se pudo crear el pedido');
        }
        return (int) $id;
    }

    private function insertarDetalle(PDO $cn, int $idPedido, DetalleVenta $linea): void
    {
        $ps = $cn->prepare(self::SQL_INSERTAR_DETALLE);
        $ps->execute([
            $idPedido, $linea->idInventario, $linea->cantidad,
            $linea->costoUnitario, $linea->subtotal,
        ]);
    }

    private function descontarStock(PDO $cn, int $idInventario, int $cantidad): void
    {
        $ps = $cn->prepare(self::SQL_DESCONTAR_STOCK);
        $ps->execute([$cantidad, $idInventario, $cantidad]);
        if ($ps->rowCount() === 0) {
            throw new ExcepcionNegocio('Stock insuficiente para apartar el ítem');
        }
    }

    private function insertarMovimiento(PDO $cn, int $idUsuario, int $idInventario, int $cantidad, string $tipo): void
    {
        $ps = $cn->prepare(self::SQL_MOVIMIENTO);
        $ps->execute([$idUsuario, $idInventario, $tipo, $cantidad]);
    }

    private function insertarPago(PDO $cn, int $idCaja, int $idPedido, float $monto, string $metodo): void
    {
        $ps = $cn->prepare(self::SQL_PAGO);
        $ps->execute([$idCaja, $idPedido, $monto, $metodo]);
    }

    private function ejecutarEstado(string $sql, int $idPedido): void
    {
        $cn = ConexionDB::obtener();
        try {
            $cn->prepare($sql)->execute([$idPedido]);
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                'No se pudo actualizar el pedido (revísalo e inténtalo de nuevo)',
                0,
                $e
            );
        }
    }

    private function leerParaCancelar(PDO $cn, int $idPedido): ?Pedido
    {
        $ps = $cn->prepare(self::SQL_LEER_CANCELAR);
        $ps->execute([$idPedido]);
        $fila = $ps->fetch();
        if ($fila === false) {
            return null;
        }

        $p = new Pedido();
        $p->idPedido = (int) $fila['ID_PEDIDO'];
        $p->idCliente = (int) $fila['ID_CLIENTE'];
        $p->adelanto = $fila['ADELANTO'] !== null ? (float) $fila['ADELANTO'] : null;
        $p->estado = (string) $fila['ESTADO'];
        $p->estadoProduccion = (string) $fila['ESTADO_PRODUCCION'];
        return $p;
    }

    private function devolverStock(PDO $cn, int $idInventario, int $cantidad): void
    {
        $ps = $cn->prepare(self::SQL_DEVOLVER_STOCK);
        $ps->execute([$cantidad, $idInventario]);
        if ($ps->rowCount() === 0) {
            throw new ExcepcionNegocio('No se encontró el artículo reservado.');
        }
    }

    /** @return DetalleVenta[] */
    private function lineas(PDO $cn, int $idPedido): array
    {
        $ps = $cn->prepare(self::SQL_LINEAS);
        $ps->execute([$idPedido]);

        $lineas = [];
        while ($fila = $ps->fetch()) {
            $linea = new DetalleVenta();
            $linea->idInventario = (int) $fila['ID_INVENTARIO'];
            $linea->nombre = (string) ($fila['NOMBRE'] ?? '');
            $linea->cantidad = (int) $fila['CANTIDAD'];
            $linea->costoUnitario = $fila['COSTO_UNITARIO'] !== null ? (float) $fila['COSTO_UNITARIO'] : null;
            $linea->subtotal = $fila['SUBTOTAL'] !== null ? (float) $fila['SUBTOTAL'] : null;
            $lineas[] = $linea;
        }
        return $lineas;
    }

    private function mapear(array $fila): Pedido
    {
        $p = new Pedido();
        $p->idPedido = (int) $fila['ID_PEDIDO'];
        $p->idCliente = $fila['ID_CLIENTE'] !== null ? (int) $fila['ID_CLIENTE'] : null;
        $p->nombrePaga = (string) $fila['NOMBRE_PAGA'];
        $p->nombreRecoge = $fila['NOMBRE_RECOGE'] !== null ? (string) $fila['NOMBRE_RECOGE'] : null;
        $p->celular = (string) $fila['CELULAR'];
        $p->fechaRecojo = $fila['FECHA_RECOJO'] !== null ? substr((string) $fila['FECHA_RECOJO'], 0, 10) : null;
        $p->horaRecojo = $fila['HORA_RECOJO'] !== null ? (string) $fila['HORA_RECOJO'] : null;
        $p->adelanto = $fila['ADELANTO'] !== null ? (float) $fila['ADELANTO'] : null;
        $p->total = $fila['TOTAL'] !== null ? (float) $fila['TOTAL'] : null;
        $p->estado = (string) $fila['ESTADO'];
        $p->estadoProduccion = (string) $fila['ESTADO_PRODUCCION'];
        $p->idUsuario = (int) $fila['ID_USUARIO'];
        $p->fechaCreacion = $fila['FECHA_CREACION'] ?? null;
        $p->fechaLimiteCanje = $fila['FECHA_LIMITE_CANJE'] ?? null;
        $p->fechaLimiteRecojo = $fila['FECHA_LIMITE_RECOJO'] ?? null;
        $p->fechaLimitePago = $fila['FECHA_LIMITE_PAGO'] ?? null;
        $p->fechaPagoTotal = $fila['FECHA_PAGO_TOTAL'] ?? null;
        $p->nombreCliente = (string) ($fila['NOMBRE_CLIENTE'] ?? '');
        return $p;
    }
}
