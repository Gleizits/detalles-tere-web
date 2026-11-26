<?php

namespace Dao;

use Modelo\CreditoCliente;
use PDO;
use ConexionDB;

/**
 * Acceso a datos de CREDITO_CLIENTE.
 */
class CreditoDAO
{
    private const SQL_LISTAR =
        "SELECT c.ID_CREDITO_CLIENTE, c.ID_CLIENTE, c.ID_INVENTARIO, c.MONTO, c.ESTADO,
                c.FECHA, c.FECHA_VENCIMIENTO, c.ID_USUARIO,
                cl.NOMBRE AS NOMBRE_CLIENTE, cl.NUM_WSP
         FROM dbo.CREDITO_CLIENTE c
         JOIN dbo.CLIENTE cl ON c.ID_CLIENTE = cl.ID_CLIENTE
         ORDER BY c.FECHA DESC";

    /** @return CreditoCliente[] */
    public function listar(): array
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->query(self::SQL_LISTAR);
        $lista = [];
        while ($fila = $ps->fetch()) {
            $c = new CreditoCliente();
            $c->idCredito = (int) $fila['ID_CREDITO_CLIENTE'];
            $c->idCliente = (int) $fila['ID_CLIENTE'];
            $c->idInventario = $fila['ID_INVENTARIO'] !== null ? (int) $fila['ID_INVENTARIO'] : null;
            $c->monto = $fila['MONTO'] !== null ? (float) $fila['MONTO'] : null;
            $c->estado = (string) $fila['ESTADO'];
            $c->fecha = $fila['FECHA'] !== null ? (string) $fila['FECHA'] : null;
            $c->fechaVencimiento = $fila['FECHA_VENCIMIENTO'] !== null ? (string) $fila['FECHA_VENCIMIENTO'] : null;
            $c->idUsuario = (int) $fila['ID_USUARIO'];
            $c->nombreCliente = (string) $fila['NOMBRE_CLIENTE'];
            $c->numWsp = (string) $fila['NUM_WSP'];
            $lista[] = $c;
        }
        return $lista;
    }

    public function insertar(CreditoCliente $c): int
    {
        $sql = "INSERT INTO dbo.CREDITO_CLIENTE (
                ID_CLIENTE, ID_INVENTARIO, MONTO, ESTADO, FECHA, FECHA_VENCIMIENTO, ID_USUARIO)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        return ConexionDB::insertarId($sql, [
            $c->idCliente, $c->idInventario, $c->monto, $c->estado,
            $c->fecha, $c->fechaVencimiento, $c->idUsuario
        ]);
    }
}
