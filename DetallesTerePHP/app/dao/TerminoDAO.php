<?php

namespace Dao;

use Modelo\InventarioItem;
use Modelo\Termino;
use ConexionDB;

/** Acceso a términos de búsqueda y sus asociaciones con inventario. */
class TerminoDAO
{
    private const SQL_LISTAR =
        "SELECT t.ID_TERMINO, t.PALABRA, "
        . "COALESCE(STRING_AGG(CAST(COALESCE(pr.NOMBRE, m.NOMBRE, a.NOMBRE) "
        . "AS VARCHAR(MAX)), ', '), 'Sin artículos') AS ASOCIACIONES, "
        . "COUNT(dt.ID_INVENTARIO) AS NUM_PRODUCTOS "
        . "FROM dbo.TERMINOS t "
        . "LEFT JOIN dbo.DETALLE_TERMINO dt ON dt.ID_TERMINO = t.ID_TERMINO "
        . "LEFT JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = dt.ID_INVENTARIO "
        . "LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO "
        . "LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL "
        . "LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE "
        . "GROUP BY t.ID_TERMINO, t.PALABRA ORDER BY t.PALABRA";

    // Equivale a InventarioDAO::listarActivos() del Java. Se reproduce aquí
    // para no modificar el DAO de inventario, que es responsabilidad otro agente.
    private const SQL_INVENTARIO_ACTIVO =
        "SELECT i.ID_INVENTARIO, i.STOCK_ACT, i.STOCK_MIN, i.TIPO_ALMACEN, i.UNIDAD_MEDIDA, "
        . "COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) AS NOMBRE, "
        . "COALESCE(p.TIPO, m.TIPO) AS TIPO, "
        . "c.NOMBRE AS CATEGORIA, "
        . "COALESCE(p.PRECIO_VENTA, m.PRECIO_VENTA, a.PRECIO_VENTA) AS PRECIO_VENTA, "
        . "COALESCE(p.PRECIO_COMPRA, m.PRECIO_COMPRA, a.PRECIO_COMPRA) AS PRECIO_COMPRA, "
        . "CASE WHEN p.ID_PRODUCTO IS NOT NULL THEN 'PRODUCTO' "
        . "     WHEN m.ID_MATERIAL IS NOT NULL THEN 'MATERIAL' "
        . "     ELSE 'ARREGLO' END AS CLASE "
        . "FROM dbo.INVENTARIO i "
        . "LEFT JOIN dbo.PRODUCTO p ON i.ID_PRODUCTO = p.ID_PRODUCTO "
        . "LEFT JOIN dbo.MATERIAL m ON i.ID_MATERIAL = m.ID_MATERIAL "
        . "LEFT JOIN dbo.ARREGLO a ON i.ID_DETALLE = a.ID_DETALLE "
        . "LEFT JOIN dbo.CATEGORIA c "
        . "  ON COALESCE(p.ID_CATEGORIA, m.ID_CATEGORIA, a.ID_CATEGORIA) = c.ID_CATEGORIA "
        . "WHERE COALESCE(p.ESTADO, m.ESTADO, a.ESTADO) = 1 ORDER BY NOMBRE";

    /** @return Termino[] */
    public function listar(): array
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_LISTAR);
        $ps->execute();

        $lista = [];
        while ($fila = $ps->fetch()) {
            $termino = new Termino();
            $termino->idTermino = (int) $fila['ID_TERMINO'];
            $termino->palabra = (string) $fila['PALABRA'];
            $termino->asociaciones = (string) ($fila['ASOCIACIONES'] ?? '');
            $termino->numProductos = (int) ($fila['NUM_PRODUCTOS'] ?? 0);
            $lista[] = $termino;
        }
        return $lista;
    }

    /** Artículos activos disponibles para asociar. @return InventarioItem[] */
    public function listarInventarioActivo(): array
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_INVENTARIO_ACTIVO);
        $ps->execute();

        $lista = [];
        while ($fila = $ps->fetch()) {
            $item = new InventarioItem();
            $item->idInventario = (int) $fila['ID_INVENTARIO'];
            $item->stockAct = (int) $fila['STOCK_ACT'];
            $item->stockMin = (int) $fila['STOCK_MIN'];
            $item->tipoAlmacen = (string) $fila['TIPO_ALMACEN'];
            $item->unidadMedida = (string) $fila['UNIDAD_MEDIDA'];
            $item->nombre = (string) $fila['NOMBRE'];
            $item->tipo = (string) ($fila['TIPO'] ?? '');
            $item->categoria = (string) ($fila['CATEGORIA'] ?? '');
            $item->precioVenta = $fila['PRECIO_VENTA'] !== null ? (float) $fila['PRECIO_VENTA'] : null;
            $item->precioCompra = $fila['PRECIO_COMPRA'] !== null ? (float) $fila['PRECIO_COMPRA'] : null;
            $item->clase = (string) $fila['CLASE'];
            $lista[] = $item;
        }
        return $lista;
    }

    public function crear(string $palabra, int $idUsuario): void
    {
        $cn = ConexionDB::obtener();
        try {
            $cn->prepare("INSERT INTO dbo.TERMINOS (ID_USUARIO, PALABRA) VALUES (?, ?)")
                ->execute([$idUsuario, $palabra]);
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                'No se pudo crear el término. Puede que ya exista.',
                0,
                $e
            );
        }
    }

    public function asociar(int $idTermino, int $idInventario): void
    {
        $cn = ConexionDB::obtener();
        try {
            $cn->prepare(
                "INSERT INTO dbo.DETALLE_TERMINO (ID_TERMINO, ID_INVENTARIO) VALUES (?, ?)"
            )->execute([$idTermino, $idInventario]);
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                'No se pudo asociar el término. Puede que la asociación ya exista.',
                0,
                $e
            );
        }
    }

    public function desasociar(int $idTermino, int $idInventario): void
    {
        $cn = ConexionDB::obtener();
        try {
            $cn->prepare(
                "DELETE FROM dbo.DETALLE_TERMINO WHERE ID_TERMINO = ? AND ID_INVENTARIO = ?"
            )->execute([$idTermino, $idInventario]);
        } catch (\PDOException $e) {
            throw new \RuntimeException('No se pudo quitar la asociación', 0, $e);
        }
    }
}
