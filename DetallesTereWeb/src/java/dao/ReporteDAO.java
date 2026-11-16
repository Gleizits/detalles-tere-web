package dao;

import java.math.BigDecimal;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Timestamp;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.List;
import modelo.ReporteIngresoLinea;
import modelo.ReporteResumen;
import modelo.ReporteVentaLinea;
import util.ConexionDB;

/** Consultas agregadas para el tablero y el PDF administrativo. */
public class ReporteDAO {

    public ReporteResumen generar(LocalDate desde, LocalDate hasta) {
        if (desde == null || hasta == null || hasta.isBefore(desde)) {
            throw new IllegalArgumentException("El periodo seleccionado no es válido.");
        }

        ReporteResumen resumen = new ReporteResumen();
        resumen.setDesde(desde);
        resumen.setHasta(hasta);
        resumen.setEtiquetaPeriodo(desde.equals(hasta)
                ? "Día " + desde : "Del " + desde + " al " + hasta);
        cargarTotalesVenta(resumen);
        cargarTotalesIngreso(resumen);
        resumen.setVentas(listarVentas(desde, hasta));
        resumen.setIngresos(listarIngresos(desde, hasta));
        return resumen;
    }

    private void cargarTotalesVenta(ReporteResumen resumen) {
        String sqlVentas = "SELECT COUNT(*) AS VENTAS, "
                + "COALESCE(SUM(TOTAL), 0) AS TOTAL, "
                + "COALESCE(SUM(CASE WHEN v.TIPO_PAGO = 'EFECTIVO' "
                + "THEN v.TOTAL ELSE 0 END), 0) AS EFECTIVO, "
                + "COALESCE(SUM(CASE WHEN v.TIPO_PAGO = 'YAPE' "
                + "THEN v.TOTAL ELSE 0 END), 0) AS YAPE, "
                + "COALESCE(SUM(CASE WHEN v.TIPO_PAGO = 'CREDITO' "
                + "THEN v.TOTAL ELSE 0 END), 0) AS CREDITO "
                + "FROM dbo.VENTA v "
                + "WHERE ESTADO = 'VENDIDO' AND FECHA_VENTA >= ? "
                + "AND FECHA_VENTA < DATEADD(DAY, 1, ?)";
        String sqlLineas = "SELECT COALESCE(SUM(d.CANTIDAD), 0) AS UNIDADES, "
                + "COALESCE(SUM(d.CANTIDAD * "
                + "COALESCE(pr.PRECIO_COMPRA, m.PRECIO_COMPRA, a.PRECIO_COMPRA, 0)), 0) "
                + "AS COSTO_ESTIMADO "
                + "FROM dbo.VENTA v "
                + "JOIN dbo.DETALLE_VENTA d ON d.ID_VENTA = v.ID_VENTA "
                + "JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = d.ID_INVENTARIO "
                + "LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO "
                + "LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL "
                + "LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE "
                + "WHERE v.ESTADO = 'VENDIDO' AND v.FECHA_VENTA >= ? "
                + "AND v.FECHA_VENTA < DATEADD(DAY, 1, ?)";

        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ventas = cn.prepareStatement(sqlVentas);
                PreparedStatement lineas = cn.prepareStatement(sqlLineas)) {
            ventas.setObject(1, resumen.getDesde());
            ventas.setObject(2, resumen.getHasta());
            try (ResultSet rs = ventas.executeQuery()) {
                if (rs.next()) {
                    resumen.setCantidadVentas(rs.getInt("VENTAS"));
                    resumen.setVentasTotales(decimal(rs, "TOTAL"));
                    resumen.setEfectivo(decimal(rs, "EFECTIVO"));
                    resumen.setYape(decimal(rs, "YAPE"));
                    resumen.setCredito(decimal(rs, "CREDITO"));
                }
            }
            lineas.setObject(1, resumen.getDesde());
            lineas.setObject(2, resumen.getHasta());
            try (ResultSet rs = lineas.executeQuery()) {
                if (rs.next()) {
                    resumen.setUnidadesVendidas(rs.getInt("UNIDADES"));
                    resumen.setMargenEstimado(resumen.getVentasTotales()
                            .subtract(decimal(rs, "COSTO_ESTIMADO")));
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al generar totales de ventas", e);
        }
    }

    private void cargarTotalesIngreso(ReporteResumen resumen) {
        String sql = "SELECT COALESCE(SUM(d.CANTIDAD), 0) AS UNIDADES, "
                + "COALESCE(SUM(d.SUBTOTAL), 0) AS TOTAL "
                + "FROM dbo.INGRESO i "
                + "JOIN dbo.DETALLE_INGRESO d ON d.ID_INGRESO = i.ID_INGRESO "
                + "WHERE i.FECHA_INGRESO >= ? AND i.FECHA_INGRESO <= ?";
        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setObject(1, resumen.getDesde());
            ps.setObject(2, resumen.getHasta());
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    resumen.setUnidadesIngresadas(rs.getInt("UNIDADES"));
                    resumen.setIngresosTotales(decimal(rs, "TOTAL"));
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al generar totales de ingresos", e);
        }
    }

    private List<ReporteVentaLinea> listarVentas(LocalDate desde, LocalDate hasta) {
        List<ReporteVentaLinea> lineas = new ArrayList<>();
        String sql = "SELECT v.FECHA_VENTA, "
                + "COALESCE(pr.NOMBRE, m.NOMBRE, a.NOMBRE) AS ARTICULO, "
                + "d.CANTIDAD, d.COSTO_UNITARIO AS PRECIO_VENTA, "
                + "COALESCE(pr.PRECIO_COMPRA, m.PRECIO_COMPRA, a.PRECIO_COMPRA, 0) "
                + "AS COSTO_VIGENTE, d.SUBTOTAL "
                + "FROM dbo.VENTA v "
                + "JOIN dbo.DETALLE_VENTA d ON d.ID_VENTA = v.ID_VENTA "
                + "JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = d.ID_INVENTARIO "
                + "LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO "
                + "LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL "
                + "LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE "
                + "WHERE v.ESTADO = 'VENDIDO' AND v.FECHA_VENTA >= ? "
                + "AND v.FECHA_VENTA < DATEADD(DAY, 1, ?) "
                + "ORDER BY v.FECHA_VENTA DESC";
        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setObject(1, desde);
            ps.setObject(2, hasta);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    ReporteVentaLinea linea = new ReporteVentaLinea();
                    Timestamp fecha = rs.getTimestamp("FECHA_VENTA");
                    linea.setFecha(fecha == null ? null : fecha.toLocalDateTime());
                    linea.setArticulo(rs.getString("ARTICULO"));
                    linea.setCantidad(rs.getInt("CANTIDAD"));
                    linea.setPrecioVenta(decimal(rs, "PRECIO_VENTA"));
                    linea.setCostoVigente(decimal(rs, "COSTO_VIGENTE"));
                    linea.setSubtotal(decimal(rs, "SUBTOTAL"));
                    linea.setMargen(linea.getSubtotal().subtract(
                            linea.getCostoVigente()
                                    .multiply(BigDecimal.valueOf(linea.getCantidad()))));
                    lineas.add(linea);
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al listar ventas del reporte", e);
        }
        return lineas;
    }

    private List<ReporteIngresoLinea> listarIngresos(LocalDate desde, LocalDate hasta) {
        List<ReporteIngresoLinea> lineas = new ArrayList<>();
        String sql = "SELECT i.FECHA_INGRESO, p.NOMBRE AS PROVEEDOR, "
                + "COALESCE(pr.NOMBRE, m.NOMBRE, a.NOMBRE) AS ARTICULO, "
                + "d.CANTIDAD, d.PRECIO_COMPRA_UNITARIO, d.SUBTOTAL "
                + "FROM dbo.INGRESO i "
                + "JOIN dbo.PROVEEDOR p ON p.ID_PROVEEDOR = i.ID_PROVEEDOR "
                + "JOIN dbo.DETALLE_INGRESO d ON d.ID_INGRESO = i.ID_INGRESO "
                + "JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = d.ID_INVENTARIO "
                + "LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO "
                + "LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL "
                + "LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE "
                + "WHERE i.FECHA_INGRESO >= ? AND i.FECHA_INGRESO <= ? "
                + "ORDER BY i.FECHA_INGRESO DESC, d.ID_DETALLE_INGRESO DESC";
        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setObject(1, desde);
            ps.setObject(2, hasta);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    ReporteIngresoLinea linea = new ReporteIngresoLinea();
                    linea.setFecha(rs.getDate("FECHA_INGRESO").toLocalDate());
                    linea.setProveedor(rs.getString("PROVEEDOR"));
                    linea.setArticulo(rs.getString("ARTICULO"));
                    linea.setCantidad(rs.getInt("CANTIDAD"));
                    linea.setPrecioCompra(decimal(rs, "PRECIO_COMPRA_UNITARIO"));
                    linea.setSubtotal(decimal(rs, "SUBTOTAL"));
                    lineas.add(linea);
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al listar ingresos del reporte", e);
        }
        return lineas;
    }

    private BigDecimal decimal(ResultSet rs, String columna) throws SQLException {
        BigDecimal valor = rs.getBigDecimal(columna);
        return valor == null ? BigDecimal.ZERO : valor;
    }
}
