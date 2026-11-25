<?php

namespace Dao;

use Modelo\Proveedor;
use PDO;
use ConexionDB;

/** Acceso a proveedores usados por los ingresos de inventario. */
class ProveedorDAO
{
    private const SQL_LISTAR =
        "SELECT ID_PROVEEDOR, NOMBRE, NUMERO FROM dbo.PROVEEDOR ORDER BY NOMBRE";

    private const SQL_CREAR =
        "INSERT INTO dbo.PROVEEDOR (NOMBRE, NUMERO) VALUES (?, ?)";

    /** @return Proveedor[] */
    public function listar(): array
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_LISTAR);
        $ps->execute();

        $lista = [];
        while ($fila = $ps->fetch()) {
            $proveedor = new Proveedor();
            $proveedor->idProveedor = (int) $fila['ID_PROVEEDOR'];
            $proveedor->nombre = (string) $fila['NOMBRE'];
            $proveedor->numero = (string) $fila['NUMERO'];
            $lista[] = $proveedor;
        }
        return $lista;
    }

    public function crear(string $nombre, string $numero): void
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_CREAR);
        $ps->execute([$nombre, $numero]);
    }
}
