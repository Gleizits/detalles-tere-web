package dao;

import java.math.BigDecimal;
import java.sql.Connection;
import java.sql.Date;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.List;
import modelo.IngresoLinea;
import util.ConexionDB;

/**
 * Registra ingresos completos en una transacción: cabecera, detalle, stock,
 * costo vigente y movimiento de entrada.
 */
public class IngresoDAO {

    public void registrar(int idProveedor, int idUsuario, LocalDate fecha,
            List<IngresoLinea> lineas) {
        if (lineas == null || lineas.isEmpty()) {
            throw new IllegalArgumentException("Agrega al menos un artículo al ingreso.");
        }

        String insertarIngreso = "INSERT INTO dbo.INGRESO "
                + "(ID_PROVEEDOR, ID_USUARIO, FECHA_INGRESO) VALUES (?, ?, ?)";
        try (Connection cn = ConexionDB.obtener()) {
            cn.setAutoCommit(false);
            try {
                int idIngreso = insertarCabecera(cn, insertarIngreso, idProveedor,
                        idUsuario, fecha);
                for (IngresoLinea linea : lineas) {
                    validarLinea(cn, linea);
                    insertarDetalle(cn, idIngreso, linea);
                    actualizarInventario(cn, linea);
                    registrarMovimiento(cn, idUsuario, linea);
                }
                cn.commit();
            } catch (SQLException | RuntimeException e) {
                cn.rollback();
                throw e;
            } finally {
                cn.setAutoCommit(true);
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al registrar el ingreso", e);
        }
    }

    public List<IngresoLinea> listarRecientes(int limite) {
        List<IngresoLinea> ingresos = new ArrayList<>();
        String sql = "SELECT TOP (?) i.FECHA_INGRESO, p.NOMBRE AS PROVEEDOR, "
                + "COALESCE(pr.NOMBRE, m.NOMBRE, a.NOMBRE) AS ARTICULO, "
                + "d.CANTIDAD, d.PRECIO_COMPRA_UNITARIO, d.SUBTOTAL "
                + "FROM dbo.DETALLE_INGRESO d "
                + "JOIN dbo.INGRESO i ON i.ID_INGRESO = d.ID_INGRESO "
                + "JOIN dbo.PROVEEDOR p ON p.ID_PROVEEDOR = i.ID_PROVEEDOR "
                + "JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = d.ID_INVENTARIO "
                + "LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO "
                + "LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL "
                + "LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE "
                + "ORDER BY i.FECHA_INGRESO DESC, d.ID_DETALLE_INGRESO DESC";

        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, Math.max(1, Math.min(limite, 100)));
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    IngresoLinea linea = new IngresoLinea();
                    linea.setNombre(rs.getString("PROVEEDOR") + " · "
                            + rs.getString("ARTICULO"));
                    linea.setCantidad(rs.getInt("CANTIDAD"));
                    linea.setPrecioCompraUnitario(
                            rs.getBigDecimal("PRECIO_COMPRA_UNITARIO"));
                    linea.setSubtotal(rs.getBigDecimal("SUBTOTAL"));
                    ingresos.add(linea);
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al listar ingresos recientes", e);
        }
        return ingresos;
    }

    private int insertarCabecera(Connection cn, String sql, int idProveedor,
            int idUsuario, LocalDate fecha) throws SQLException {
        try (PreparedStatement ps = cn.prepareStatement(sql,
                Statement.RETURN_GENERATED_KEYS)) {
            ps.setInt(1, idProveedor);
            ps.setInt(2, idUsuario);
            ps.setDate(3, Date.valueOf(fecha));
            ps.executeUpdate();
            try (ResultSet rs = ps.getGeneratedKeys()) {
                if (rs.next()) {
                    return rs.getInt(1);
                }
            }
        }
        throw new SQLException("No se pudo crear la cabecera del ingreso.");
    }

    private void validarLinea(Connection cn, IngresoLinea linea) throws SQLException {
        if (linea.getCantidad() <= 0
                || linea.getPrecioCompraUnitario() == null
                || linea.getPrecioCompraUnitario().signum() < 0
                || linea.getPrecioCompraUnitario().compareTo(new BigDecimal("1000000")) > 0) {
            throw new IllegalArgumentException("Cantidad o precio de compra inválido.");
        }

        String sql = "SELECT 1 FROM dbo.INVENTARIO inv "
                + "LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO "
                + "LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL "
                + "LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE "
                + "WHERE inv.ID_INVENTARIO = ? "
                + "AND COALESCE(pr.ESTADO, m.ESTADO, a.ESTADO) = 1";
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, linea.getIdInventario());
            try (ResultSet rs = ps.executeQuery()) {
                if (!rs.next()) {
                    throw new IllegalArgumentException(
                            "El artículo seleccionado no está activo.");
                }
            }
        }
    }

    private void insertarDetalle(Connection cn, int idIngreso, IngresoLinea linea)
            throws SQLException {
        String sql = "INSERT INTO dbo.DETALLE_INGRESO "
                + "(ID_INGRESO, ID_INVENTARIO, CANTIDAD, "
                + "PRECIO_COMPRA_UNITARIO, SUBTOTAL, OBSERVACIONES) "
                + "VALUES (?, ?, ?, ?, ?, ?)";
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idIngreso);
            ps.setInt(2, linea.getIdInventario());
            ps.setInt(3, linea.getCantidad());
            ps.setBigDecimal(4, linea.getPrecioCompraUnitario());
            ps.setBigDecimal(5, linea.getPrecioCompraUnitario()
                    .multiply(BigDecimal.valueOf(linea.getCantidad())));
            ps.setString(6, linea.getObservaciones());
            ps.executeUpdate();
        }
    }

    private void actualizarInventario(Connection cn, IngresoLinea linea)
            throws SQLException {
        String sqlStock = "UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT + ? "
                + "WHERE ID_INVENTARIO = ?";
        try (PreparedStatement ps = cn.prepareStatement(sqlStock)) {
            ps.setInt(1, linea.getCantidad());
            ps.setInt(2, linea.getIdInventario());
            if (ps.executeUpdate() != 1) {
                throw new SQLException("No se pudo actualizar el stock.");
            }
        }

        actualizarCosto(cn, "PRODUCTO", "ID_PRODUCTO", "ID_INVENTARIO", linea);
        actualizarCosto(cn, "MATERIAL", "ID_MATERIAL", "ID_INVENTARIO", linea);
        actualizarCosto(cn, "ARREGLO", "ID_DETALLE", "ID_INVENTARIO", linea);
    }

    private void actualizarCosto(Connection cn, String tabla, String clave,
            String inventarioClave, IngresoLinea linea) throws SQLException {
        String sql = "UPDATE entidad SET PRECIO_COMPRA = ? "
                + "FROM dbo." + tabla + " entidad "
                + "JOIN dbo.INVENTARIO inv ON inv." + clave + " = entidad." + clave
                + " WHERE inv." + inventarioClave + " = ?";
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setBigDecimal(1, linea.getPrecioCompraUnitario());
            ps.setInt(2, linea.getIdInventario());
            ps.executeUpdate();
        }
    }

    private void registrarMovimiento(Connection cn, int idUsuario, IngresoLinea linea)
            throws SQLException {
        String sql = "INSERT INTO dbo.MOVIMIENTO_INVENTARIO "
                + "(ID_USUARIO, ID_INVENTARIO, FECHA_MOVIMIENTO, "
                + "TIPO_MOVIMIENTO, CANTIDAD) VALUES (?, ?, SYSUTCDATETIME(), 'ENTRADA', ?)";
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idUsuario);
            ps.setInt(2, linea.getIdInventario());
            ps.setInt(3, linea.getCantidad());
            ps.executeUpdate();
        }
    }
}
