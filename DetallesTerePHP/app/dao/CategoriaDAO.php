<?php

namespace Dao;

use Modelo\Categoria;
use PDO;
use ConexionDB;

/**
 * Acceso a datos de CATEGORIA.
 */
class CategoriaDAO
{
    private const SQL_LISTAR = "SELECT ID_CATEGORIA, NOMBRE, DESCRIPCION FROM dbo.CATEGORIA ORDER BY NOMBRE";

    /** @return Categoria[] */
    public function listar(): array
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->query(self::SQL_LISTAR);
        $lista = [];
        while ($fila = $ps->fetch()) {
            $c = new Categoria();
            $c->idCategoria = (int) $fila['ID_CATEGORIA'];
            $c->nombre = (string) $fila['NOMBRE'];
            $c->descripcion = $fila['DESCRIPCION'] !== null ? (string) $fila['DESCRIPCION'] : null;
            $lista[] = $c;
        }
        return $lista;
    }

    public function insertar(Categoria $c): int
    {
        $sql = "INSERT INTO dbo.CATEGORIA (NOMBRE, DESCRIPCION) VALUES (?, ?)";
        return ConexionDB::insertarId($sql, [$c->nombre, $c->descripcion]);
    }

    public function actualizar(Categoria $c): void
    {
        $sql = "UPDATE dbo.CATEGORIA SET NOMBRE=?, DESCRIPCION=? WHERE ID_CATEGORIA=?";
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare($sql);
        $ps->execute([$c->nombre, $c->descripcion, $c->idCategoria]);
    }

    public function eliminar(int $id): void
    {
        $sql = "DELETE FROM dbo.CATEGORIA WHERE ID_CATEGORIA=?";
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare($sql);
        $ps->execute([$id]);
    }
}
