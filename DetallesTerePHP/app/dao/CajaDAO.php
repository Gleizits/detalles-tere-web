<?php

namespace Dao;

use Modelo\Caja;
use PDO;
use ConexionDB;

/**
 * Acceso a datos de CAJA.
 * Reglas: 1 caja abierta por usuario; cierre manual con conteo físico.
 */
class CajaDAO
{
    private const SQL_ABIERTA =
        "SELECT ID_CAJA, ID_USUARIO, MONTO_INICIAL, MONTO_FINAL, MONTO_REAL, "
        . "DIFERENCIA, FECHA_APERTURA, FECHA_CIERRE "
        . "FROM dbo.CAJA WHERE ID_USUARIO = ? AND FECHA_CIERRE IS NULL";

    private const SQL_ABRIR =
        "INSERT INTO dbo.CAJA (ID_USUARIO, MONTO_INICIAL, FECHA_APERTURA) "
        . "VALUES (?, ?, SYSUTCDATETIME())";

    private const SQL_VENTAS_EFECTIVO =
        "SELECT ISNULL(SUM(TOTAL), 0) FROM dbo.VENTA "
        . "WHERE ID_CAJA = ? AND TIPO_PAGO = 'EFECTIVO' AND ESTADO = 'VENDIDO'";

    // Ventas de productos (sin pagos de reserva): ID_PEDIDO IS NULL
    private const SQL_VENTAS_PROD_EFECTIVO =
        "SELECT ISNULL(SUM(TOTAL), 0) FROM dbo.VENTA "
        . "WHERE ID_CAJA = ? AND TIPO_PAGO = 'EFECTIVO' AND ESTADO = 'VENDIDO' "
        . "AND ID_PEDIDO IS NULL";

    // Pagos de reservas (adelantos/abonos) en efectivo: ID_PEDIDO IS NOT NULL
    private const SQL_PAGOS_RESERVA_EFECTIVO =
        "SELECT ISNULL(SUM(TOTAL), 0) FROM dbo.VENTA "
        . "WHERE ID_CAJA = ? AND TIPO_PAGO = 'EFECTIVO' AND ESTADO = 'VENDIDO' "
        . "AND ID_PEDIDO IS NOT NULL";

    private const SQL_CERRAR =
        "UPDATE dbo.CAJA SET MONTO_FINAL = ?, MONTO_REAL = ?, DIFERENCIA = ?, "
        . "FECHA_CIERRE = SYSUTCDATETIME() WHERE ID_CAJA = ?";

    // TOP con parámetro no se puede preparar en SQL Server: el límite
    // se inyecta como entero validado en historial().
    private const SQL_HISTORIAL =
        "SELECT c.ID_CAJA, c.ID_USUARIO, u.NOMBRE + ' ' + u.APELLIDO AS NOMBRE_USUARIO, "
        . "c.MONTO_INICIAL, c.MONTO_FINAL, c.MONTO_REAL, c.DIFERENCIA, "
        . "c.FECHA_APERTURA, c.FECHA_CIERRE "
        . "FROM dbo.CAJA c JOIN dbo.USUARIO u ON c.ID_USUARIO = u.ID_USUARIO "
        . "WHERE c.ID_USUARIO = ? "
        . "ORDER BY c.FECHA_APERTURA DESC";

    /** Devuelve la caja abierta del usuario, o null si no tiene. */
    public function cajaAbierta(int $idUsuario): ?Caja
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_ABIERTA);
        $ps->execute([$idUsuario]);
        $fila = $ps->fetch();
        return $fila === false ? null : $this->mapCaja($fila);
    }

    /** Abre una caja nueva y devuelve su id. Lanza excepción si ya hay una abierta. */
    public function abrir(int $idUsuario, float $montoInicial): int
    {
        $id = ConexionDB::insertarId(self::SQL_ABRIR, [$idUsuario, $montoInicial]);
        if ($id <= 0) {
            throw new \PDOException('No se pudo abrir la caja');
        }
        return $id;
    }

    /**
     * Suma de ventas en efectivo de una caja (para monto esperado).
     * Incluye ventas de productos Y pagos de reserva (todo el efectivo que entra).
     */
    public function ventasEfectivo(int $idCaja): float
    {
        return $this->sumar($idCaja, self::SQL_VENTAS_EFECTIVO);
    }

    /** Efectivo que entra por ventas de productos (sin reservas). */
    public function ventasProductosEfectivo(int $idCaja): float
    {
        return $this->sumar($idCaja, self::SQL_VENTAS_PROD_EFECTIVO);
    }

    /** Efectivo que entra por pagos de reservas (adelantos/abonos). */
    public function pagosReservasEfectivo(int $idCaja): float
    {
        return $this->sumar($idCaja, self::SQL_PAGOS_RESERVA_EFECTIVO);
    }

    private function sumar(int $idCaja, string $sql): float
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare($sql);
        $ps->execute([$idCaja]);
        $total = $ps->fetchColumn();
        return $total === false || $total === null ? 0.0 : (float) $total;
    }

    /**
     * Cierra la caja: guarda montoFinal (esperado), montoReal (conteo) y diferencia.
     */
    public function cerrar(Caja $c): void
    {
        $montoFinal = $c->montoFinal ?? 0.0;
        $diferencia = round(($c->montoReal ?? 0.0) - $montoFinal, 2);

        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_CERRAR);
        $ps->execute([$montoFinal, $c->montoReal, $diferencia, $c->idCaja]);
        $c->diferencia = $diferencia;
    }

    /** Historial reciente de cajas del usuario. @return Caja[] */
    public function historial(int $idUsuario, int $limite): array
    {
        $limite = max(1, min(100, $limite));   // entero validado para TOP
        $sql = str_replace('SELECT c.ID_CAJA', "SELECT TOP {$limite} c.ID_CAJA", self::SQL_HISTORIAL);

        $cn = ConexionDB::obtener();
        $ps = $cn->prepare($sql);
        $ps->execute([$idUsuario]);

        $lista = [];
        while ($fila = $ps->fetch()) {
            $lista[] = $this->mapCaja($fila);
        }
        return $lista;
    }

    private function mapCaja(array $fila): Caja
    {
        $c = new Caja();
        $c->idCaja = (int) $fila['ID_CAJA'];
        $c->idUsuario = (int) $fila['ID_USUARIO'];
        $c->montoInicial = $fila['MONTO_INICIAL'] !== null ? (float) $fila['MONTO_INICIAL'] : null;
        $c->montoFinal = $fila['MONTO_FINAL'] !== null ? (float) $fila['MONTO_FINAL'] : null;
        $c->montoReal = $fila['MONTO_REAL'] !== null ? (float) $fila['MONTO_REAL'] : null;
        $c->diferencia = $fila['DIFERENCIA'] !== null ? (float) $fila['DIFERENCIA'] : null;
        $c->fechaApertura = $fila['FECHA_APERTURA'] ?? null;
        $c->fechaCierre = $fila['FECHA_CIERRE'] ?? null;
        // la columna NOMBRE_USUARIO no está en todas las consultas
        $c->nombreUsuario = $fila['NOMBRE_USUARIO'] ?? null;
        return $c;
    }
}
