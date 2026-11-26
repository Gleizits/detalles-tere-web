<?php

namespace Dao;

use Modelo\Producto;
use Modelo\Categoria;
use PDO;
use ConexionDB;

/**
 * Acceso a datos de PRODUCTO (y CATEGORIA relacionada).
 */
class ProductoDAO
{
    private const SQL_LISTAR =
        "SELECT p.ID_PRODUCTO, p.ID_CATEGORIA, p.NOMBRE, p.TIPO, p.PRECIO_COMPRA, p.PRECIO_VENTA,
                c.NOMBRE AS CATEGORIA
         FROM dbo.PRODUCTO p
         LEFT JOIN dbo.CATEGORIA c ON p.ID_CATEGORIA = c.ID_CATEGORIA
         ORDER BY p.NOMBRE";

    /** @return Producto[] */
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

    public function obtener(int $id): ?Producto
    {
        $sql = self::SQL_LISTAR . " WHERE p.ID_PRODUCTO = ?";
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare($sql);
        $ps->execute([$id]);
        $fila = $ps->fetch();
        return $fila === false ? null : $this->map($fila);
    }

    public function insertar(Producto $p): int
    {
        $sql = "INSERT INTO dbo.PRODUCTO (ID_CATEGORIA, NOMBRE, TIPO, PRECIO_COMPRA, PRECIO_VENTA)
                VALUES (?, ?, ?, ?, ?)";
        $id = ConexionDB::insertarId($sql, [
            $p->idCategoria, $p->nombre, $p->tipo, $p->precioCompra, $p->precioVenta
        ]);
        return $id;
    }

    public function actualizar(Producto $p): void
    {
        $sql = "UPDATE dbo.PRODUCTO SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, PRECIO_COMPRA=?, PRECIO_VENTA=?
                WHERE ID_PRODUCTO=?";
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare($sql);
        $ps->execute([
            $p->idCategoria, $p->nombre, $p->tipo, $p->precioCompra, $p->precioVenta, $p->idProducto
        ]);
    }

    public function eliminar(int $id): void
    {
        $sql = "DELETE FROM dbo.PRODUCTO WHERE ID_PRODUCTO=?";
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare($sql);
        $ps->execute([$id]);
    }

    private function map(array $fila): Producto
    {
        $p = new Producto();
        $p->idProducto = (int) $fila['ID_PRODUCTO'];
        $p->idCategoria = $fila['ID_CATEGORIA'] !== null ? (int) $fila['ID_CATEGORIA'] : null;
        $p->nombre = (string) $fila['NOMBRE'];
        $p->tipo = $fila['TIPO'] !== null ? (string) $fila['TIPO'] : null;
        $p->precioCompra = $fila['PRECIO_COMPRA'] !== null ? (float) $fila['PRECIO_COMPRA'] : null;
        $p->precioVenta = $fila['PRECIO_VENTA'] !== null ? (float) $fila['PRECIO_VENTA'] : null;
        $p->categoria = (string) ($fila['CATEGORIA'] ?? '');
        return $p;
    }
}
