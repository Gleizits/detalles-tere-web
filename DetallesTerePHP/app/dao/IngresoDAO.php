<?php

namespace Dao;

use Modelo\DetalleIngreso;
use Modelo\IngresoLinea;
use PDO;
use ConexionDB;
use ExcepcionNegocio;

/**
 * Registra ingresos completos en una transacción: cabecera, detalle, stock,
 * costo vigente y movimiento de entrada.
 */
class IngresoDAO
{
    private const SQL_INSERTAR =
        "INSERT INTO dbo.INGRESO (ID_PROVEEDOR, ID_USUARIO, FECHA_INGRESO) "
        . "OUTPUT INSERTED.ID_INGRESO VALUES (?, ?, ?)";

    // TOP con parámetro no se puede preparar en SQL Server: el límite se
    // inyecta como entero validado en listarRecientes(), como en CajaDAO.
    private const SQL_LISTAR_RECIENTES =
        "SELECT i.FECHA_INGRESO, p.NOMBRE AS PROVEEDOR, "
        . "COALESCE(pr.NOMBRE, m.NOMBRE, a.NOMBRE) AS ARTICULO, "
        . "d.CANTIDAD, d.PRECIO_COMPRA_UNITARIO, d.SUBTOTAL "
        . "FROM dbo.DETALLE_INGRESO d "
        . "JOIN dbo.INGRESO i ON i.ID_INGRESO = d.ID_INGRESO "
        . "JOIN dbo.PROVEEDOR p ON p.ID_PROVEEDOR = i.ID_PROVEEDOR "
        . "JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = d.ID_INVENTARIO "
        . "LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO "
        . "LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL "
        . "LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE "
        . "ORDER BY i.FECHA_INGRESO DESC, d.ID_DETALLE_INGRESO DESC";

    private const SQL_LINEA_ACTIVA =
        "SELECT 1 FROM dbo.INVENTARIO inv "
        . "LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO "
        . "LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL "
        . "LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE "
        . "WHERE inv.ID_INVENTARIO = ? "
        . "AND COALESCE(pr.ESTADO, m.ESTADO, a.ESTADO) = 1";

    private const SQL_INSERTAR_DETALLE =
        "INSERT INTO dbo.DETALLE_INGRESO "
        . "(ID_INGRESO, ID_INVENTARIO, CANTIDAD, PRECIO_COMPRA_UNITARIO, SUBTOTAL, OBSERVACIONES) "
        . "VALUES (?, ?, ?, ?, ?, ?)";

    private const SQL_ACTUALIZAR_STOCK =
        "UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT + ? WHERE ID_INVENTARIO = ?";

    // %1$s = tabla entidad, %2$s = clave (valores fijos del propio DAO).
    private const SQL_ACTUALIZAR_COSTO =
        'UPDATE entidad SET PRECIO_COMPRA = ? '
        . 'FROM dbo.%1$s entidad '
        . 'JOIN dbo.INVENTARIO inv ON inv.%2$s = entidad.%2$s '
        . 'WHERE inv.ID_INVENTARIO = ?';

    private const SQL_MOVIMIENTO =
        "INSERT INTO dbo.MOVIMIENTO_INVENTARIO "
        . "(ID_USUARIO, ID_INVENTARIO, FECHA_MOVIMIENTO, TIPO_MOVIMIENTO, CANTIDAD) "
        . "VALUES (?, ?, SYSUTCDATETIME(), 'ENTRADA', ?)";

    /**
     * @param IngresoLinea[]|DetalleIngreso[] $lineas
     *        (el controlador captura con DetalleIngreso; Java usaba IngresoLinea)
     */
    public function registrar(int $idProveedor, int $idUsuario, string $fecha, array $lineas): void
    {
        if ($lineas === []) {
            throw new ExcepcionNegocio('Agrega al menos un artículo al ingreso.');
        }

        $cn = ConexionDB::obtener();
        $cn->beginTransaction();
        try {
            $idIngreso = $this->insertarCabecera($cn, $idProveedor, $idUsuario, $fecha);
            foreach ($lineas as $linea) {
                $this->validarLinea($cn, $linea);
                $this->insertarDetalle($cn, $idIngreso, $linea);
                $this->actualizarInventario($cn, $linea);
                $this->registrarMovimiento($cn, $idUsuario, $linea);
            }
            $cn->commit();
        } catch (\Throwable $e) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
            }
            throw $e;
        }
    }

    /** @return IngresoLinea[] */
    public function listarRecientes(int $limite): array
    {
        $limite = max(1, min(100, $limite));
        $sql = str_replace(
            'SELECT i.FECHA_INGRESO',
            "SELECT TOP {$limite} i.FECHA_INGRESO",
            self::SQL_LISTAR_RECIENTES
        );

        $cn = ConexionDB::obtener();
        $ps = $cn->prepare($sql);
        $ps->execute();

        $ingresos = [];
        while ($fila = $ps->fetch()) {
            $linea = new IngresoLinea();
            $linea->nombre = $fila['PROVEEDOR'] . ' · ' . $fila['ARTICULO'];
            $linea->cantidad = (int) $fila['CANTIDAD'];
            $linea->precioCompraUnitario = $fila['PRECIO_COMPRA_UNITARIO'] !== null
                ? (float) $fila['PRECIO_COMPRA_UNITARIO'] : null;
            $linea->subtotal = $fila['SUBTOTAL'] !== null ? (float) $fila['SUBTOTAL'] : null;
            $ingresos[] = $linea;
        }
        return $ingresos;
    }

    // ------------------------------------------------------------------
    // Helpers internos
    // ------------------------------------------------------------------

    private function insertarCabecera(PDO $cn, int $idProveedor, int $idUsuario, string $fecha): int
    {
        $ps = $cn->prepare(self::SQL_INSERTAR);
        $ps->execute([$idProveedor, $idUsuario, $fecha]);
        $id = $ps->fetchColumn();
        $ps->closeCursor();
        if ($id === false || $id === null) {
            throw new \PDOException('No se pudo crear la cabecera del ingreso.');
        }
        return (int) $id;
    }

    private function validarLinea(PDO $cn, IngresoLinea|DetalleIngreso $linea): void
    {
        if ($linea->cantidad <= 0
            || $linea->precioCompraUnitario === null
            || $linea->precioCompraUnitario < 0
            || $linea->precioCompraUnitario > 1000000) {
            throw new ExcepcionNegocio('Cantidad o precio de compra inválido.');
        }

        $ps = $cn->prepare(self::SQL_LINEA_ACTIVA);
        $ps->execute([$linea->idInventario]);
        if ($ps->fetchColumn() === false) {
            throw new ExcepcionNegocio('El artículo seleccionado no está activo.');
        }
    }

    private function insertarDetalle(PDO $cn, int $idIngreso, IngresoLinea|DetalleIngreso $linea): void
    {
        $precio = $linea->precioCompraUnitario ?? 0.0;
        $ps = $cn->prepare(self::SQL_INSERTAR_DETALLE);
        $ps->execute([
            $idIngreso,
            $linea->idInventario,
            $linea->cantidad,
            $precio,
            round($precio * $linea->cantidad, 2),
            $linea->observaciones,
        ]);
    }

    private function actualizarInventario(PDO $cn, IngresoLinea|DetalleIngreso $linea): void
    {
        $ps = $cn->prepare(self::SQL_ACTUALIZAR_STOCK);
        $ps->execute([$linea->cantidad, $linea->idInventario]);
        if ($ps->rowCount() !== 1) {
            throw new \PDOException('No se pudo actualizar el stock.');
        }

        $this->actualizarCosto($cn, 'PRODUCTO', 'ID_PRODUCTO', $linea);
        $this->actualizarCosto($cn, 'MATERIAL', 'ID_MATERIAL', $linea);
        $this->actualizarCosto($cn, 'ARREGLO', 'ID_DETALLE', $linea);
    }

    private function actualizarCosto(PDO $cn, string $tabla, string $clave, IngresoLinea|DetalleIngreso $linea): void
    {
        $sql = sprintf(self::SQL_ACTUALIZAR_COSTO, $tabla, $clave);
        $ps = $cn->prepare($sql);
        $ps->execute([$linea->precioCompraUnitario, $linea->idInventario]);
    }

    private function registrarMovimiento(PDO $cn, int $idUsuario, IngresoLinea|DetalleIngreso $linea): void
    {
        $ps = $cn->prepare(self::SQL_MOVIMIENTO);
        $ps->execute([$idUsuario, $linea->idInventario, $linea->cantidad]);
    }
}
