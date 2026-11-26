<?php

namespace Dao;

use Modelo\ReporteIngresoLinea;
use Modelo\ReporteResumen;
use Modelo\ReporteVentaLinea;
use PDO;
use ConexionDB;
use ExcepcionNegocio;

/** Consultas agregadas para el tablero y el PDF administrativo. */
class ReporteDAO
{
    private const SQL_TOTALES_VENTA =
        "SELECT COUNT(*) AS VENTAS, "
        . "COALESCE(SUM(TOTAL), 0) AS TOTAL, "
        . "COALESCE(SUM(CASE WHEN v.TIPO_PAGO = 'EFECTIVO' "
        . "THEN v.TOTAL ELSE 0 END), 0) AS EFECTIVO, "
        . "COALESCE(SUM(CASE WHEN v.TIPO_PAGO = 'YAPE' "
        . "THEN v.TOTAL ELSE 0 END), 0) AS YAPE, "
        . "COALESCE(SUM(CASE WHEN v.TIPO_PAGO = 'CREDITO' "
        . "THEN v.TOTAL ELSE 0 END), 0) AS CREDITO "
        . "FROM dbo.VENTA v "
        . "WHERE ESTADO = 'VENDIDO' AND FECHA_VENTA >= ? "
        . "AND FECHA_VENTA < DATEADD(DAY, 1, ?)";

    private const SQL_TOTALES_LINEAS =
        "SELECT COALESCE(SUM(d.CANTIDAD), 0) AS UNIDADES, "
        . "COALESCE(SUM(d.CANTIDAD * "
        . "COALESCE(pr.PRECIO_COMPRA, m.PRECIO_COMPRA, a.PRECIO_COMPRA, 0)), 0) "
        . "AS COSTO_ESTIMADO "
        . "FROM dbo.VENTA v "
        . "JOIN dbo.DETALLE_VENTA d ON d.ID_VENTA = v.ID_VENTA "
        . "JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = d.ID_INVENTARIO "
        . "LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO "
        . "LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL "
        . "LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE "
        . "WHERE v.ESTADO = 'VENDIDO' AND v.FECHA_VENTA >= ? "
        . "AND v.FECHA_VENTA < DATEADD(DAY, 1, ?)";

    private const SQL_TOTALES_INGRESO =
        "SELECT COALESCE(SUM(d.CANTIDAD), 0) AS UNIDADES, "
        . "COALESCE(SUM(d.SUBTOTAL), 0) AS TOTAL "
        . "FROM dbo.INGRESO i "
        . "JOIN dbo.DETALLE_INGRESO d ON d.ID_INGRESO = i.ID_INGRESO "
        . "WHERE i.FECHA_INGRESO >= ? AND i.FECHA_INGRESO <= ?";

    private const SQL_DETALLE_VENTAS =
        "SELECT v.FECHA_VENTA, "
        . "COALESCE(pr.NOMBRE, m.NOMBRE, a.NOMBRE) AS ARTICULO, "
        . "d.CANTIDAD, d.COSTO_UNITARIO AS PRECIO_VENTA, "
        . "COALESCE(pr.PRECIO_COMPRA, m.PRECIO_COMPRA, a.PRECIO_COMPRA, 0) "
        . "AS COSTO_VIGENTE, d.SUBTOTAL "
        . "FROM dbo.VENTA v "
        . "JOIN dbo.DETALLE_VENTA d ON d.ID_VENTA = v.ID_VENTA "
        . "JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = d.ID_INVENTARIO "
        . "LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO "
        . "LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL "
        . "LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE "
        . "WHERE v.ESTADO = 'VENDIDO' AND v.FECHA_VENTA >= ? "
        . "AND v.FECHA_VENTA < DATEADD(DAY, 1, ?) "
        . "ORDER BY v.FECHA_VENTA DESC";

    private const SQL_DETALLE_INGRESOS =
        "SELECT i.FECHA_INGRESO, p.NOMBRE AS PROVEEDOR, "
        . "COALESCE(pr.NOMBRE, m.NOMBRE, a.NOMBRE) AS ARTICULO, "
        . "d.CANTIDAD, d.PRECIO_COMPRA_UNITARIO, d.SUBTOTAL "
        . "FROM dbo.INGRESO i "
        . "JOIN dbo.PROVEEDOR p ON p.ID_PROVEEDOR = i.ID_PROVEEDOR "
        . "JOIN dbo.DETALLE_INGRESO d ON d.ID_INGRESO = i.ID_INGRESO "
        . "JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = d.ID_INVENTARIO "
        . "LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO "
        . "LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL "
        . "LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE "
        . "WHERE i.FECHA_INGRESO >= ? AND i.FECHA_INGRESO <= ? "
        . "ORDER BY i.FECHA_INGRESO DESC, d.ID_DETALLE_INGRESO DESC";

    /** Genera el resumen y los detalles del periodo (fechas 'Y-m-d'). */
    public function generar(string $desde, string $hasta): ReporteResumen
    {
        if (!$this->esFecha($desde) || !$this->esFecha($hasta) || $hasta < $desde) {
            throw new ExcepcionNegocio('El periodo seleccionado no es válido.');
        }

        $resumen = new ReporteResumen();
        $resumen->desde = $desde;
        $resumen->hasta = $hasta;
        $resumen->etiquetaPeriodo = $desde === $hasta
            ? 'Día ' . $desde
            : 'Del ' . $desde . ' al ' . $hasta;

        $this->cargarTotalesVenta($resumen);
        $this->cargarTotalesIngreso($resumen);
        $resumen->ventas = $this->listarVentas($desde, $hasta);
        $resumen->ingresos = $this->listarIngresos($desde, $hasta);
        return $resumen;
    }

    private function cargarTotalesVenta(ReporteResumen $resumen): void
    {
        $cn = ConexionDB::obtener();
        $parametros = [$resumen->desde, $resumen->hasta];

        $ps = $cn->prepare(self::SQL_TOTALES_VENTA);
        $ps->execute($parametros);
        $fila = $ps->fetch();
        if ($fila !== false) {
            $resumen->cantidadVentas = (int) $fila['VENTAS'];
            $resumen->ventasTotales = $this->decimal($fila, 'TOTAL');
            $resumen->efectivo = $this->decimal($fila, 'EFECTIVO');
            $resumen->yape = $this->decimal($fila, 'YAPE');
            $resumen->credito = $this->decimal($fila, 'CREDITO');
        }

        $ps = $cn->prepare(self::SQL_TOTALES_LINEAS);
        $ps->execute($parametros);
        $fila = $ps->fetch();
        if ($fila !== false) {
            $resumen->unidadesVendidas = (int) $fila['UNIDADES'];
            $resumen->margenEstimado = round(
                $resumen->ventasTotales - $this->decimal($fila, 'COSTO_ESTIMADO'),
                2
            );
        }
    }

    private function cargarTotalesIngreso(ReporteResumen $resumen): void
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_TOTALES_INGRESO);
        $ps->execute([$resumen->desde, $resumen->hasta]);
        $fila = $ps->fetch();
        if ($fila !== false) {
            $resumen->unidadesIngresadas = (int) $fila['UNIDADES'];
            $resumen->ingresosTotales = $this->decimal($fila, 'TOTAL');
        }
    }

    /** @return ReporteVentaLinea[] */
    private function listarVentas(string $desde, string $hasta): array
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_DETALLE_VENTAS);
        $ps->execute([$desde, $hasta]);

        $lineas = [];
        while ($fila = $ps->fetch()) {
            $linea = new ReporteVentaLinea();
            $linea->fecha = $fila['FECHA_VENTA'] ?? null;
            $linea->articulo = (string) ($fila['ARTICULO'] ?? '');
            $linea->cantidad = (int) $fila['CANTIDAD'];
            $linea->precioVenta = $this->decimal($fila, 'PRECIO_VENTA');
            $linea->costoVigente = $this->decimal($fila, 'COSTO_VIGENTE');
            $linea->subtotal = $this->decimal($fila, 'SUBTOTAL');
            $linea->margen = round(
                $linea->subtotal - ($linea->costoVigente * $linea->cantidad),
                2
            );
            $lineas[] = $linea;
        }
        return $lineas;
    }

    /** @return ReporteIngresoLinea[] */
    private function listarIngresos(string $desde, string $hasta): array
    {
        $cn = ConexionDB::obtener();
        $ps = $cn->prepare(self::SQL_DETALLE_INGRESOS);
        $ps->execute([$desde, $hasta]);

        $lineas = [];
        while ($fila = $ps->fetch()) {
            $linea = new ReporteIngresoLinea();
            $linea->fecha = $fila['FECHA_INGRESO'] !== null
                ? substr((string) $fila['FECHA_INGRESO'], 0, 10) : null;
            $linea->proveedor = (string) ($fila['PROVEEDOR'] ?? '');
            $linea->articulo = (string) ($fila['ARTICULO'] ?? '');
            $linea->cantidad = (int) $fila['CANTIDAD'];
            $linea->precioCompra = $this->decimal($fila, 'PRECIO_COMPRA_UNITARIO');
            $linea->subtotal = $this->decimal($fila, 'SUBTOTAL');
            $lineas[] = $linea;
        }
        return $lineas;
    }

    private function decimal(array $fila, string $columna): float
    {
        $valor = $fila[$columna] ?? null;
        return $valor === null ? 0.0 : (float) $valor;
    }

    private function esFecha(string $valor): bool
    {
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        return $fecha !== false && $fecha->format('Y-m-d') === $valor;
    }
}
