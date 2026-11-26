<?php

namespace Dao;

use Modelo\Categoria;
use Modelo\InventarioItem;
use PDO;
use ConexionDB;
use RuntimeException;
use InvalidArgumentException;

/**
 * Acceso a datos de inventario: listado, búsqueda, alta, edición y
 * ajuste manual de stock. Une PRODUCTO/MATERIAL/ARREGLO en una vista.
 */
class InventarioDAO
{
    private const BASE_SELECT =
        "SELECT i.ID_INVENTARIO, i.STOCK_ACT, i.STOCK_MIN, i.TIPO_ALMACEN, i.UNIDAD_MEDIDA, "
        . "i.ID_PRODUCTO, i.ID_MATERIAL, i.ID_DETALLE, "
        . "COALESCE(p.ID_CATEGORIA, m.ID_CATEGORIA, a.ID_CATEGORIA) AS ID_CATEGORIA, "
        . "COALESCE(p.ESTADO, m.ESTADO, a.ESTADO) AS ESTADO, "
        . "COALESCE(p.ID_MATERIAL, 0) AS ID_MATERIAL_VINC, "
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
        . "  ON COALESCE(p.ID_CATEGORIA, m.ID_CATEGORIA, a.ID_CATEGORIA) = c.ID_CATEGORIA ";

    /**
     * Lista el inventario. Si q no está vacío, filtra por nombre o término.
     * @return InventarioItem[]
     */
    public function listar(?string $q): array
    {
        $consulta = trim($q ?? '');
        $sql = self::BASE_SELECT;

        if ($consulta !== '') {
            $sql .= "WHERE (COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) LIKE ? "
                . "   OR EXISTS (SELECT 1 FROM dbo.DETALLE_TERMINO dt "
                . "              JOIN dbo.TERMINOS t ON dt.ID_TERMINO = t.ID_TERMINO "
                . "              WHERE dt.ID_INVENTARIO = i.ID_INVENTARIO AND t.PALABRA LIKE ?)) ";
        }
        $sql .= "ORDER BY NOMBRE";

        $cn = ConexionDB::obtener();
        $ps = $cn->prepare($sql);
        if ($consulta !== '') {
            $like = '%' . $consulta . '%';
            $ps->execute([$like, $like]);
        } else {
            $ps->execute();
        }

        $lista = [];
        while ($fila = $ps->fetch()) {
            $lista[] = $this->mapItem($fila);
        }
        return $lista;
    }

    /** @return InventarioItem[] solo activos (para operaciones de stock). */
    public function listarActivos(): array
    {
        $sql = self::BASE_SELECT
            . "WHERE COALESCE(p.ESTADO, m.ESTADO, a.ESTADO) = 1 ORDER BY NOMBRE";
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare($sql);
        $ps->execute();

        $lista = [];
        while ($fila = $ps->fetch()) {
            $lista[] = $this->mapItem($fila);
        }
        return $lista;
    }

    /** Busca un ítem por ID. Devuelve null si no existe. */
    public function buscarPorId(int $idInventario): ?InventarioItem
    {
        $sql = self::BASE_SELECT . "WHERE i.ID_INVENTARIO = ?";
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare($sql);
        $ps->execute([$idInventario]);
        $fila = $ps->fetch();
        return $fila === false ? null : $this->mapItem($fila);
    }

    /** @return Categoria[] para el selector del formulario. */
    public function listarCategorias(): array
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare("SELECT ID_CATEGORIA, NOMBRE FROM dbo.CATEGORIA ORDER BY NOMBRE");
        $ps->execute();
        $lista = [];
        while ($f = $ps->fetch()) {
            $lista[] = new Categoria((int) $f['ID_CATEGORIA'], (string) $f['NOMBRE']);
        }
        return $lista;
    }

    /** @return Categoria[] materiales activos (id + nombre) para el selector. */
    public function listarMaterialesActivos(): array
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(
            "SELECT ID_MATERIAL, NOMBRE FROM dbo.MATERIAL WHERE ESTADO = 1 ORDER BY NOMBRE"
        );
        $ps->execute();
        $lista = [];
        while ($f = $ps->fetch()) {
            $lista[] = new Categoria((int) $f['ID_MATERIAL'], (string) $f['NOMBRE']);
        }
        return $lista;
    }

    /**
     * Devuelve el ID de la categoría con ese nombre; si no existe, la crea.
     * CATEGORIA.NOMBRE no es UNIQUE: se busca primero.
     */
    public function obOCrearCategoria(string $nombre): int
    {
        $nombre = trim($nombre);
        $cn = ConexionDB::obtener();

        $ps = $cn->prepare("SELECT ID_CATEGORIA FROM dbo.CATEGORIA WHERE NOMBRE = ?");
        $ps->execute([$nombre]);
        $id = $ps->fetchColumn();
        if ($id !== false && $id !== null) {
            return (int) $id;
        }

        return ConexionDB::insertarId(
            "INSERT INTO dbo.CATEGORIA (NOMBRE) VALUES (?)",
            [$nombre]
        );
    }

    /**
     * Alta de artículo: inserta PRODUCTO/MATERIAL/ARREGLO y su fila en
     * INVENTARIO en una sola transacción.
     */
    public function crear(InventarioItem $it): void
    {
        [$sqlEnt, $colInventario] = $this->sqlEntidadInsert($it->clase);

        $cn = ConexionDB::obtener();
        $cn->beginTransaction();
        try {
            $ps = $cn->prepare($sqlEnt);
            $ps->execute($this->paramsEntidad($it, true));
            $idEnt = (int) $cn->query('SELECT CAST(SCOPE_IDENTITY() AS INT)')->fetchColumn();

            $psInv = $cn->prepare(
                "INSERT INTO dbo.INVENTARIO ({$colInventario}, STOCK_ACT, STOCK_MIN, TIPO_ALMACEN, UNIDAD_MEDIDA) "
                . "VALUES (?,?,?,?,?)"
            );
            $psInv->execute([$idEnt, $it->stockAct, $it->stockMin, $it->tipoAlmacen, $it->unidadMedida]);
            $cn->commit();
        } catch (\Throwable $e) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Edición de artículo. Precios y estado SOLO se aplican si $admin es true.
     */
    public function actualizar(InventarioItem $it, bool $admin): void
    {
        [$sqlEnt, $idEntidad] = $this->sqlEntidadUpdate($it, $admin);

        $cn = ConexionDB::obtener();
        $cn->beginTransaction();
        try {
            $ps = $cn->prepare($sqlEnt);
            $ps->execute($this->paramsEntidadUpdate($it, $admin, $idEntidad));

            $psInv = $cn->prepare(
                "UPDATE dbo.INVENTARIO SET STOCK_MIN=?, UNIDAD_MEDIDA=?, TIPO_ALMACEN=? WHERE ID_INVENTARIO=?"
            );
            $psInv->execute([$it->stockMin, $it->unidadMedida, $it->tipoAlmacen, $it->idInventario]);
            $cn->commit();
        } catch (\Throwable $e) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Ajuste manual de stock (entrada o salida). Registra ajuste, detalle,
     * movimiento y actualiza STOCK_ACT en una transacción. Rechaza stock negativo.
     */
    public function ajustarStock(int $idInventario, int $delta, string $motivo, int $idUsuario): void
    {
        if ($delta === 0) {
            throw new InvalidArgumentException('La cantidad del ajuste no puede ser 0');
        }
        $tipoMov = $delta > 0 ? 'ENTRADA' : 'SALIDA';
        $cantidad = abs($delta);

        $cn = ConexionDB::obtener();
        $cn->beginTransaction();
        try {
            $psA = $cn->prepare(
                "INSERT INTO dbo.AJUSTE_INVENTARIO (ID_USUARIO, FECHA) VALUES (?, SYSUTCDATETIME());"
                . " SELECT CAST(SCOPE_IDENTITY() AS INT)"
            );
            $psA->execute([$idUsuario]);
            $idAjuste = (int) $psA->fetchColumn();

            $psD = $cn->prepare(
                "INSERT INTO dbo.DETALLE_AJUSTE (ID_AJUSTE, ID_INVENTARIO, CANTIDAD, TIPO_AJUSTE, MOTIVO) "
                . "VALUES (?,?,?,?,?)"
            );
            $psD->execute([$idAjuste, $idInventario, $delta, 'MANUAL', $motivo]);

            $psU = $cn->prepare(
                "UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT + ? "
                . "WHERE ID_INVENTARIO = ? AND STOCK_ACT + ? >= 0"
            );
            $psU->execute([$delta, $idInventario, $delta]);
            if ($psU->rowCount() === 0) {
                throw new \RuntimeException('No hay stock suficiente para esa salida');
            }

            $psM = $cn->prepare(
                "INSERT INTO dbo.MOVIMIENTO_INVENTARIO (ID_USUARIO, ID_INVENTARIO, FECHA_MOVIMIENTO, TIPO_MOVIMIENTO, CANTIDAD) "
                . "VALUES (?, ?, SYSUTCDATETIME(), ?, ?)"
            );
            $psM->execute([$idUsuario, $idInventario, $tipoMov, $cantidad]);

            $cn->commit();
        } catch (\Throwable $e) {
            if ($cn->inTransaction()) {
                $cn->rollBack();
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------

    private function mapItem(array $fila): InventarioItem
    {
        $it = new InventarioItem();
        $it->idInventario = (int) $fila['ID_INVENTARIO'];
        $it->stockAct = (int) $fila['STOCK_ACT'];
        $it->stockMin = (int) $fila['STOCK_MIN'];
        $it->tipoAlmacen = (string) $fila['TIPO_ALMACEN'];
        $it->unidadMedida = (string) $fila['UNIDAD_MEDIDA'];
        $it->nombre = (string) $fila['NOMBRE'];
        $it->tipo = (string) ($fila['TIPO'] ?? '');
        $it->categoria = (string) ($fila['CATEGORIA'] ?? '');
        $it->precioVenta = $fila['PRECIO_VENTA'] !== null ? (float) $fila['PRECIO_VENTA'] : null;
        $it->precioCompra = $fila['PRECIO_COMPRA'] !== null ? (float) $fila['PRECIO_COMPRA'] : null;
        $it->clase = (string) $fila['CLASE'];
        $it->idProducto = (int) ($fila['ID_PRODUCTO'] ?? 0);
        $it->idMaterial = (int) ($fila['ID_MATERIAL'] ?? 0);
        $it->idArreglo = (int) ($fila['ID_DETALLE'] ?? 0);
        $it->idCategoria = (int) ($fila['ID_CATEGORIA'] ?? 0);
        $it->idMaterialVinc = (int) ($fila['ID_MATERIAL_VINC'] ?? 0);
        $it->estado = (bool) ($fila['ESTADO'] ?? true);
        return $it;
    }

    private function sqlEntidadInsert(string $clase): array
    {
        return match ($clase) {
            'PRODUCTO' => [
                "INSERT INTO dbo.PRODUCTO (ID_CATEGORIA, NOMBRE, TIPO, PRECIO_COMPRA, PRECIO_VENTA, ESTADO, ID_MATERIAL) VALUES (?,?,?,?,?,?,?)",
                'ID_PRODUCTO',
            ],
            'MATERIAL' => [
                "INSERT INTO dbo.MATERIAL (ID_CATEGORIA, NOMBRE, TIPO, PRECIO_COMPRA, PRECIO_VENTA, ESTADO) VALUES (?,?,?,?,?,?)",
                'ID_MATERIAL',
            ],
            'ARREGLO' => [
                "INSERT INTO dbo.ARREGLO (ID_CATEGORIA, NOMBRE, PRECIO_COMPRA, PRECIO_VENTA, ESTADO) VALUES (?,?,?,?,?)",
                'ID_DETALLE',
            ],
            default => throw new InvalidArgumentException('Clase no válida: ' . $clase),
        };
    }

    private function sqlEntidadUpdate(InventarioItem $it, bool $admin): array
    {
        $id = $this->idEntidad($it);
        $sql = match ($it->clase) {
            'PRODUCTO' => $admin
                ? "UPDATE dbo.PRODUCTO SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, PRECIO_COMPRA=?, PRECIO_VENTA=?, ESTADO=?, ID_MATERIAL=? WHERE ID_PRODUCTO=?"
                : "UPDATE dbo.PRODUCTO SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, ID_MATERIAL=? WHERE ID_PRODUCTO=?",
            'MATERIAL' => $admin
                ? "UPDATE dbo.MATERIAL SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, PRECIO_COMPRA=?, PRECIO_VENTA=?, ESTADO=? WHERE ID_MATERIAL=?"
                : "UPDATE dbo.MATERIAL SET ID_CATEGORIA=?, NOMBRE=?, TIPO=? WHERE ID_MATERIAL=?",
            'ARREGLO' => $admin
                ? "UPDATE dbo.ARREGLO SET ID_CATEGORIA=?, NOMBRE=?, PRECIO_COMPRA=?, PRECIO_VENTA=?, ESTADO=? WHERE ID_DETALLE=?"
                : "UPDATE dbo.ARREGLO SET ID_CATEGORIA=?, NOMBRE=? WHERE ID_DETALLE=?",
            default => throw new InvalidArgumentException('Clase no válida: ' . $it->clase),
        };
        return [$sql, $id];
    }

    private function paramsEntidad(InventarioItem $it, bool $esCrear): array
    {
        $p = [];
        $p[] = $it->idCategoria > 0 ? $it->idCategoria : null;
        $p[] = $it->nombre;
        if ($it->clase !== 'ARREGLO') {
            $p[] = $it->tipo;
        }
        $p[] = $it->precioCompra;
        $p[] = $it->precioVenta;
        $p[] = $it->estado ? 1 : 0;
        if ($it->clase === 'PRODUCTO') {
            $p[] = $it->idMaterialVinc > 0 ? $it->idMaterialVinc : null;
        }
        return $p;
    }

    private function paramsEntidadUpdate(InventarioItem $it, bool $admin, int $idEntidad): array
    {
        $p = [];
        $p[] = $it->idCategoria > 0 ? $it->idCategoria : null;
        $p[] = $it->nombre;
        if ($it->clase !== 'ARREGLO') {
            $p[] = $it->tipo;
        }
        if ($admin) {
            $p[] = $it->precioCompra;
            $p[] = $it->precioVenta;
            $p[] = $it->estado ? 1 : 0;
        }
        if ($it->clase === 'PRODUCTO') {
            $p[] = $it->idMaterialVinc > 0 ? $it->idMaterialVinc : null;
        }
        $p[] = $idEntidad;
        return $p;
    }

    private function idEntidad(InventarioItem $it): int
    {
        return match ($it->clase) {
            'PRODUCTO' => $it->idProducto,
            'MATERIAL' => $it->idMaterial,
            'ARREGLO'  => $it->idArreglo,
            default    => throw new InvalidArgumentException('Clase no válida: ' . $it->clase),
        };
    }
}
