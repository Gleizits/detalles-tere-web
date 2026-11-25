<?php

namespace Dao;

use Modelo\Material;
use Modelo\Categoria;
use PDO;
use ConexionDB;

/**
 * Acceso a datos de MATERIAL.
 */
class MaterialDAO
{
    private const SQL_LISTAR =
        "SELECT m.ID_MATERIAL, m.ID_CATEGORIA, m.NOMBRE, m.TIPO, m.PRECIO_COMPRA, m.PRECIO_VENTA,
                c.NOMBRE AS CATEGORIA
         FROM dbo.MATERIAL m
         LEFT JOIN dbo.CATEGORIA c ON m.ID_CATEGORIA = c.ID_CATEGORIA
         ORDER BY m.NOMBRE";

    /** @return Material[] */
    public function listar(): array
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->query(self::SQL_LISTAR);
        $lista = [];
        while ($fila = $ps->fetch()) {
            $lista[] = $this->map($fila);
        }
        return $lista;
    }

    public function insertar(Material $m): int
    {
        $sql = "INSERT INTO dbo.MATERIAL (ID_CATEGORIA, NOMBRE, TIPO, PRECIO_COMPRA, PRECIO_VENTA)
                VALUES (?, ?, ?, ?, ?)";
        return ConexionDB::insertarId($sql, [
            $m->idCategoria, $m->nombre, $m->tipo, $m->precioCompra, $m->precioVenta
        ]);
    }

    public function actualizar(Material $m): void
    {
        $sql = "UPDATE dbo.MATERIAL SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, PRECIO_COMPRA=?, PRECIO_VENTA=?
                WHERE ID_MATERIAL=?";
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare($sql);
        $ps->execute([
            $m->idCategoria, $m->nombre, $m->tipo, $m->precioCompra, $m->precioVenta, $m->idMaterial
        ]);
    }

    public function eliminar(int $id): void
    {
        $sql = "DELETE FROM dbo.MATERIAL WHERE ID_MATERIAL=?";
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare($sql);
        $ps->execute([$id]);
    }

    private function map(array $fila): Material
    {
        $m = new Material();
        $m->idMaterial = (int) $fila['ID_MATERIAL'];
        $m->idCategoria = $fila['ID_CATEGORIA'] !== null ? (int) $fila['ID_CATEGORIA'] : null;
        $m->nombre = (string) $fila['NOMBRE'];
        $m->tipo = $fila['TIPO'] !== null ? (string) $fila['TIPO'] : null;
        $m->precioCompra = $fila['PRECIO_COMPRA'] !== null ? (float) $fila['PRECIO_COMPRA'] : null;
        $m->precioVenta = $fila['PRECIO_VENTA'] !== null ? (float) $fila['PRECIO_VENTA'] : null;
        $m->categoria = (string) ($fila['CATEGORIA'] ?? '');
        return $m;
    }
}
