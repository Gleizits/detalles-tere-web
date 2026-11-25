<?php

namespace Dao;

use Modelo\Arreglo;
use PDO;
use ConexionDB;

/**
 * Acceso a datos de ARREGLO.
 */
class ArregloDAO
{
    private const SQL_LISTAR =
        "SELECT a.ID_DETALLE, a.ID_CATEGORIA, a.NOMBRE, a.PRECIO_COMPRA, a.PRECIO_VENTA,
                c.NOMBRE AS CATEGORIA
         FROM dbo.ARREGLO a
         LEFT JOIN dbo.CATEGORIA c ON a.ID_CATEGORIA = c.ID_CATEGORIA
         ORDER BY a.NOMBRE";

    /** @return Arreglo[] */
    public function listar(): array
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->query(self::SQL_LISTAR);
        $lista = [];
        while ($fila = $ps->fetch()) {
            $a = new Arreglo();
            $a->idDetalle = (int) $fila['ID_DETALLE'];
            $a->idCategoria = $fila['ID_CATEGORIA'] !== null ? (int) $fila['ID_CATEGORIA'] : null;
            $a->nombre = (string) $fila['NOMBRE'];
            $a->precioCompra = $fila['PRECIO_COMPRA'] !== null ? (float) $fila['PRECIO_COMPRA'] : null;
            $a->precioVenta = $fila['PRECIO_VENTA'] !== null ? (float) $fila['PRECIO_VENTA'] : null;
            $a->categoria = (string) ($fila['CATEGORIA'] ?? '');
            $lista[] = $a;
        }
        return $lista;
    }

    public function insertar(Arreglo $a): int
    {
        $sql = "INSERT INTO dbo.ARREGLO (ID_CATEGORIA, NOMBRE, PRECIO_COMPRA, PRECIO_VENTA)
                VALUES (?, ?, ?, ?)";
        return ConexionDB::insertarId($sql, [
            $a->idCategoria, $a->nombre, $a->precioCompra, $a->precioVenta
        ]);
    }
}
