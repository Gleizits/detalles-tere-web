<?php

namespace Dao;

use Modelo\Movimiento;
use PDO;
use ConexionDB;

/**
 * Acceso a datos de MOVIMIENTO_INVENTARIO (trazabilidad).
 */
class MovimientoDAO
{
    private const SQL_LISTAR =
        "SELECT m.ID_MOVIMIENTO, m.ID_USUARIO, m.ID_INVENTARIO, m.FECHA_MOVIMIENTO,
                m.TIPO_MOVIMIENTO, m.CANTIDAD,
                COALESCE(p.NOMBRE, m2.NOMBRE, a.NOMBRE) AS NOMBRE_INVENTARIO
         FROM dbo.MOVIMIENTO_INVENTARIO m
         LEFT JOIN dbo.PRODUCTO p ON m.ID_INVENTARIO = p.ID_PRODUCTO
         LEFT JOIN dbo.MATERIAL m2 ON m.ID_INVENTARIO = m2.ID_MATERIAL
         LEFT JOIN dbo.ARREGLO a ON m.ID_INVENTARIO = a.ID_DETALLE
         ORDER BY m.FECHA_MOVIMIENTO DESC";

    /** @return Movimiento[] */
    public function listar(): array
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->query(self::SQL_LISTAR);
        $lista = [];
        while ($fila = $ps->fetch()) {
            $m = new Movimiento();
            $m->idMovimiento = (int) $fila['ID_MOVIMIENTO'];
            $m->idUsuario = (int) $fila['ID_USUARIO'];
            $m->idInventario = (int) $fila['ID_INVENTARIO'];
            $m->fechaMovimiento = $fila['FECHA_MOVIMIENTO'] !== null ? (string) $fila['FECHA_MOVIMIENTO'] : null;
            $m->tipoMovimiento = (string) $fila['TIPO_MOVIMIENTO'];
            $m->cantidad = (int) $fila['CANTIDAD'];
            $m->nombreInventario = (string) ($fila['NOMBRE_INVENTARIO'] ?? '');
            $lista[] = $m;
        }
        return $lista;
    }

    public function insertar(Movimiento $m): void
    {
        $sql = "INSERT INTO dbo.MOVIMIENTO_INVENTARIO (
                ID_USUARIO, ID_INVENTARIO, FECHA_MOVIMIENTO, TIPO_MOVIMIENTO, CANTIDAD)
                VALUES (?, ?, SYSUTCDATETIME(), ?, ?)";
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare($sql);
        $ps->execute([
            $m->idUsuario, $m->idInventario, $m->tipoMovimiento, $m->cantidad
        ]);
    }
}
