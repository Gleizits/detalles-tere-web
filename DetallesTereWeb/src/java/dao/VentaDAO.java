package dao;

import java.math.BigDecimal;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Timestamp;
import java.util.ArrayList;
import java.util.List;
import modelo.DetalleVenta;
import modelo.Venta;
import util.ConexionDB;

/**
 * Acceso a datos de VENTA y DETALLE_VENTA (ventas y cotizaciones).
 * Reglas clave:
 *  - El stock SOLO se descuenta al confirmar una venta o al convertir una cotización.
 *  - Una cotización NO toca el stock; se guarda con ESTADO='COTIZACION' y
 *    FECHA_VENCIMIENTO = +24 h; se elimina sola al vencer.
 *  - Las operaciones que tocan stock y ventas son transaccionales.
 */
public class VentaDAO {

    private final CreditoClienteDAO creditoDAO = new CreditoClienteDAO();

    // ------------------------------------------------------------------
    // Venta directa (cobro del carrito)
    // ------------------------------------------------------------------

    /**
     * Confirma una venta: crea VENTA + líneas, descuenta stock y registra
     * movimientos de salida, todo en una sola transacción.
     * Devuelve el ID de la venta creada.
     */
    public int confirmarVenta(List<DetalleVenta> carrito, int idCaja, int idUsuario,
                              String tipoPago) {
        BigDecimal total = sumar(carrito);
        Connection cn = null;
        try {
            cn = ConexionDB.obtener();
            cn.setAutoCommit(false);

            int idVenta = insertarVenta(cn, idCaja, total, tipoPago, "VENDIDO");

            for (DetalleVenta l : carrito) {
                insertarDetalle(cn, l, idCaja, idVenta, "VENDIDO", false);
                descontarStock(cn, l.getIdInventario(), l.getCantidad());
                insertarMovimiento(cn, idUsuario, l.getIdInventario(), l.getCantidad());
            }

            cn.commit();
            return idVenta;
        } catch (SQLException e) {
            rollback(cn);
            throw new RuntimeException("No se pudo registrar la venta", e);
        } catch (IllegalStateException e) {
            rollback(cn);
            throw e;   // stock insuficiente: el mensaje ya es claro
        } finally {
            cerrar(cn);
        }
    }

    /**
     * Confirma una venta usando todo el saldo a favor vigente del cliente.
     * El total debe coincidir exactamente; no admite pagos mixtos ni parciales.
     */
    public int confirmarVentaConCredito(List<DetalleVenta> carrito, int idCaja,
                                        int idUsuario, int idCliente) {
        BigDecimal total = sumar(carrito);
        Connection cn = null;
        try {
            cn = ConexionDB.obtener();
            cn.setAutoCommit(false);

            int idVenta = insertarVenta(cn, idCaja, total, "CREDITO", "VENDIDO");
            for (DetalleVenta l : carrito) {
                insertarDetalle(cn, l, idCaja, idVenta, "VENDIDO", false);
                descontarStock(cn, l.getIdInventario(), l.getCantidad());
                insertarMovimiento(cn, idUsuario, l.getIdInventario(), l.getCantidad());
            }
            creditoDAO.aplicar(cn, idCliente, total, idVenta);

            cn.commit();
            return idVenta;
        } catch (SQLException e) {
            rollback(cn);
            throw new RuntimeException("No se pudo registrar el canje", e);
        } catch (IllegalStateException e) {
            rollback(cn);
            throw e;
        } finally {
            cerrar(cn);
        }
    }

    // ------------------------------------------------------------------
    // Cotizaciones
    // ------------------------------------------------------------------

    /**
     * Guarda el carrito como cotización (24 h) SIN descontar stock.
     * Crea una VENTA con ESTADO='COTIZACION' y líneas con FECHA_VENCIMIENTO=+24h.
     * Devuelve el ID de la cotización.
     */
    public int guardarCotizacion(List<DetalleVenta> carrito) {
        BigDecimal total = sumar(carrito);
        Connection cn = null;
        try {
            cn = ConexionDB.obtener();
            cn.setAutoCommit(false);

            int idVenta = insertarVenta(cn, null, total, null, "COTIZACION");
            for (DetalleVenta l : carrito) {
                insertarDetalle(cn, l, null, idVenta, "COTIZACION", true);
            }

            cn.commit();
            return idVenta;
        } catch (SQLException e) {
            rollback(cn);
            throw new RuntimeException("No se pudo guardar la cotización", e);
        } finally {
            cerrar(cn);
        }
    }

    /** Cotizaciones activas (no vencidas). */
    public List<Venta> listarCotizaciones() {
        String sql =
            "SELECT v.ID_VENTA, v.TOTAL, v.FECHA_VENTA, "
          + "COUNT(d.ID_DETALLE_VENTA) AS NUM_LINEAS, "
          + "STRING_AGG(CAST(COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) AS NVARCHAR(200)) "
          + "          + ' x' + CAST(d.CANTIDAD AS VARCHAR(10)), ', ') "
          + "    WITHIN GROUP (ORDER BY d.ID_DETALLE_VENTA) AS RESUMEN "
          + "FROM dbo.VENTA v "
          + "JOIN dbo.DETALLE_VENTA d ON d.ID_VENTA = v.ID_VENTA "
          + "JOIN dbo.INVENTARIO i ON d.ID_INVENTARIO = i.ID_INVENTARIO "
          + "LEFT JOIN dbo.PRODUCTO p ON i.ID_PRODUCTO = p.ID_PRODUCTO "
          + "LEFT JOIN dbo.MATERIAL m ON i.ID_MATERIAL = m.ID_MATERIAL "
          + "LEFT JOIN dbo.ARREGLO a ON i.ID_DETALLE = a.ID_DETALLE "
          + "WHERE v.ESTADO = 'COTIZACION' "
          + "  AND v.FECHA_VENTA >= DATEADD(HOUR, -24, SYSUTCDATETIME()) "
          + "GROUP BY v.ID_VENTA, v.TOTAL, v.FECHA_VENTA "
          + "ORDER BY v.FECHA_VENTA DESC";

        List<Venta> lista = new ArrayList<>();
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                Venta v = new Venta();
                v.setIdVenta(rs.getInt("ID_VENTA"));
                v.setTotal(rs.getBigDecimal("TOTAL"));
                v.setEstado("COTIZACION");
                v.setNumLineas(rs.getInt("NUM_LINEAS"));
                v.setResumen(rs.getString("RESUMEN"));
                Timestamp f = rs.getTimestamp("FECHA_VENTA");
                if (f != null) {
                    v.setFechaVenta(f.toLocalDateTime());
                }
                lista.add(v);
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al listar cotizaciones", e);
        }
        return lista;
    }

    /** Líneas de una venta o cotización (con nombre e inventario actual). */
    public List<DetalleVenta> obtenerLineas(int idVenta) {
        String sql =
            "SELECT d.ID_DETALLE_VENTA, d.ID_INVENTARIO, d.CANTIDAD, d.COSTO_UNITARIO, "
          + "d.SUBTOTAL, d.ESTADO, i.STOCK_ACT, "
          + "COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) AS NOMBRE "
          + "FROM dbo.DETALLE_VENTA d "
          + "JOIN dbo.INVENTARIO i ON d.ID_INVENTARIO = i.ID_INVENTARIO "
          + "LEFT JOIN dbo.PRODUCTO p ON i.ID_PRODUCTO = p.ID_PRODUCTO "
          + "LEFT JOIN dbo.MATERIAL m ON i.ID_MATERIAL = m.ID_MATERIAL "
          + "LEFT JOIN dbo.ARREGLO a ON i.ID_DETALLE = a.ID_DETALLE "
          + "WHERE d.ID_VENTA = ? "
          + "ORDER BY d.ID_DETALLE_VENTA";

        List<DetalleVenta> lineas = new ArrayList<>();
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idVenta);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    DetalleVenta l = new DetalleVenta();
                    l.setIdDetalleVenta(rs.getInt("ID_DETALLE_VENTA"));
                    l.setIdInventario(rs.getInt("ID_INVENTARIO"));
                    l.setCantidad(rs.getInt("CANTIDAD"));
                    l.setCostoUnitario(rs.getBigDecimal("COSTO_UNITARIO"));
                    l.setSubtotal(rs.getBigDecimal("SUBTOTAL"));
                    l.setEstado(rs.getString("ESTADO"));
                    l.setNombre(rs.getString("NOMBRE"));
                    l.setStockDisponible(rs.getInt("STOCK_ACT"));
                    l.setIdVenta(idVenta);
                    lineas.add(l);
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al leer líneas de venta", e);
        }
        return lineas;
    }

    /**
     * Convierte una cotización en venta: actualiza cabecera y líneas a VENDIDO,
     * descuenta stock y registra movimientos. Transaccional.
     */
    public void convertirCotizacion(int idVenta, int idCaja, int idUsuario,
                                    String tipoPago) {
        Connection cn = null;
        try {
            cn = ConexionDB.obtener();
            cn.setAutoCommit(false);

            // 0) Leer las líneas ANTES de cambiar su estado (siguen en COTIZACION)
            List<DetalleVenta> lineas = obtenerLineasCn(cn, idVenta);

            // 1) Marcar cabecera como vendida (solo si aún es cotización)
            int cab = 0;
            try (PreparedStatement ps = cn.prepareStatement(
                    "UPDATE dbo.VENTA SET ID_CAJA = ?, TIPO_PAGO = ?, ESTADO = 'VENDIDO' "
                  + "WHERE ID_VENTA = ? AND ESTADO = 'COTIZACION'")) {
                ps.setInt(1, idCaja);
                ps.setString(2, tipoPago);
                ps.setInt(3, idVenta);
                cab = ps.executeUpdate();
            }
            if (cab == 0) {
                throw new IllegalStateException("La cotización ya fue cobrada o ya no existe.");
            }

            // 2) Marcar líneas como vendidas y fijar la caja
            try (PreparedStatement ps = cn.prepareStatement(
                    "UPDATE dbo.DETALLE_VENTA SET ID_CAJA = ?, ESTADO = 'VENDIDO' "
                  + "WHERE ID_VENTA = ? AND ESTADO = 'COTIZACION'")) {
                ps.setInt(1, idCaja);
                ps.setInt(2, idVenta);
                ps.executeUpdate();
            }

            // 3) Descontar stock por cada línea (leídas antes del UPDATE)
            for (DetalleVenta l : lineas) {
                descontarStock(cn, l.getIdInventario(), l.getCantidad());
                insertarMovimiento(cn, idUsuario, l.getIdInventario(), l.getCantidad());
            }

            cn.commit();
        } catch (SQLException e) {
            rollback(cn);
            throw new RuntimeException("No se pudo cobrar la cotización", e);
        } catch (IllegalStateException e) {
            rollback(cn);
            throw e;
        } finally {
            cerrar(cn);
        }
    }

    /** Elimina cotizaciones vencidas (>24 h). Devuelve cuántas líneas borró. */
    public int eliminarExpiradas() {
        String borrarLineas =
            "DELETE FROM dbo.DETALLE_VENTA "
          + "WHERE ESTADO = 'COTIZACION' AND FECHA_VENCIMIENTO IS NOT NULL "
          + "  AND FECHA_VENCIMIENTO < SYSUTCDATETIME()";
        String borrarCabeceras =
            "DELETE FROM dbo.VENTA WHERE ESTADO = 'COTIZACION' AND NOT EXISTS ("
          + "  SELECT 1 FROM dbo.DETALLE_VENTA d WHERE d.ID_VENTA = VENTA.ID_VENTA)";
        Connection cn = null;
        try {
            cn = ConexionDB.obtener();
            cn.setAutoCommit(false);
            int n;
            try (PreparedStatement ps = cn.prepareStatement(borrarLineas)) {
                n = ps.executeUpdate();
            }
            try (PreparedStatement ps = cn.prepareStatement(borrarCabeceras)) {
                ps.executeUpdate();
            }
            cn.commit();
            return n;
        } catch (SQLException e) {
            rollback(cn);
            throw new RuntimeException("Error al limpiar cotizaciones vencidas", e);
        } finally {
            cerrar(cn);
        }
    }

    // ------------------------------------------------------------------
    // Helpers internos
    // ------------------------------------------------------------------

    private BigDecimal sumar(List<DetalleVenta> carrito) {
        BigDecimal t = BigDecimal.ZERO;
        for (DetalleVenta l : carrito) {
            t = t.add(l.getSubtotal());
        }
        return t;
    }

    private int insertarVenta(Connection cn, Integer idCaja, BigDecimal total,
                              String tipoPago, String estado)
            throws SQLException {
        String sql =
            "INSERT INTO dbo.VENTA (ID_CAJA, TOTAL, TIPO_PAGO, ESTADO, FECHA_VENTA) "
          + "OUTPUT INSERTED.ID_VENTA VALUES (?, ?, ?, ?, SYSUTCDATETIME())";
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            if (idCaja == null) {
                ps.setNull(1, java.sql.Types.INTEGER);
            } else {
                ps.setInt(1, idCaja);
            }
            ps.setBigDecimal(2, total);
            if (tipoPago == null) {
                ps.setNull(3, java.sql.Types.VARCHAR);
            } else {
                ps.setString(3, tipoPago);
            }
            ps.setString(4, estado);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return rs.getInt(1);
                }
            }
        }
        throw new SQLException("No se pudo crear la venta");
    }

    private void insertarDetalle(Connection cn, DetalleVenta l, Integer idCaja, int idVenta,
                                 String estado, boolean cotizacion) throws SQLException {
        String sql;
        if (cotizacion) {
            sql = "INSERT INTO dbo.DETALLE_VENTA "
                + "(ID_INVENTARIO, CANTIDAD, COSTO_UNITARIO, SUBTOTAL, ID_CAJA, ID_VENTA, "
                + " FECHA_CREACION, FECHA_VENCIMIENTO, ESTADO) "
                + "VALUES (?, ?, ?, ?, NULL, ?, SYSUTCDATETIME(), "
                + "        DATEADD(HOUR, 24, SYSUTCDATETIME()), 'COTIZACION')";
        } else {
            sql = "INSERT INTO dbo.DETALLE_VENTA "
                + "(ID_INVENTARIO, CANTIDAD, COSTO_UNITARIO, SUBTOTAL, ID_CAJA, ID_VENTA, "
                + " FECHA_CREACION, ESTADO) "
                + "VALUES (?, ?, ?, ?, ?, ?, SYSUTCDATETIME(), 'VENDIDO')";
        }
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, l.getIdInventario());
            ps.setInt(2, l.getCantidad());
            ps.setBigDecimal(3, l.getCostoUnitario());
            ps.setBigDecimal(4, l.getSubtotal());
            if (cotizacion) {
                ps.setInt(5, idVenta);
            } else {
                if (idCaja == null) {
                    ps.setNull(5, java.sql.Types.INTEGER);
                } else {
                    ps.setInt(5, idCaja);
                }
                ps.setInt(6, idVenta);
            }
            ps.executeUpdate();
        }
    }

    private void descontarStock(Connection cn, int idInventario, int cantidad) throws SQLException {
        String sql = "UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT - ? "
                   + "WHERE ID_INVENTARIO = ? AND STOCK_ACT >= ?";
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, cantidad);
            ps.setInt(2, idInventario);
            ps.setInt(3, cantidad);
            if (ps.executeUpdate() == 0) {
                throw new IllegalStateException(
                        "Stock insuficiente para uno de los productos. Revisa el carrito.");
            }
        }
    }

    private void insertarMovimiento(Connection cn, int idUsuario, int idInventario, int cantidad)
            throws SQLException {
        String sql = "INSERT INTO dbo.MOVIMIENTO_INVENTARIO "
                   + "(ID_USUARIO, ID_INVENTARIO, FECHA_MOVIMIENTO, TIPO_MOVIMIENTO, CANTIDAD) "
                   + "VALUES (?, ?, SYSUTCDATETIME(), 'SALIDA', ?)";
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idUsuario);
            ps.setInt(2, idInventario);
            ps.setInt(3, cantidad);
            ps.executeUpdate();
        }
    }

    private List<DetalleVenta> obtenerLineasCn(Connection cn, int idVenta) throws SQLException {
        String sql = "SELECT ID_INVENTARIO, CANTIDAD FROM dbo.DETALLE_VENTA "
                   + "WHERE ID_VENTA = ? AND ESTADO = 'COTIZACION'";
        List<DetalleVenta> lineas = new ArrayList<>();
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idVenta);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    DetalleVenta l = new DetalleVenta();
                    l.setIdInventario(rs.getInt("ID_INVENTARIO"));
                    l.setCantidad(rs.getInt("CANTIDAD"));
                    lineas.add(l);
                }
            }
        }
        return lineas;
    }

    private void rollback(Connection cn) {
        if (cn != null) {
            try {
                cn.rollback();
            } catch (SQLException ignorada) {
            }
        }
    }

    private void cerrar(Connection cn) {
        if (cn != null) {
            try {
                cn.setAutoCommit(true);
            } catch (SQLException ignorada) {
            }
            try {
                cn.close();
            } catch (SQLException ignorada) {
            }
        }
    }
}
