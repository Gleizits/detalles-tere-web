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
import modelo.Cliente;
import modelo.DetalleVenta;
import modelo.Pedido;
import util.ConexionDB;

/**
 * Acceso a datos de PEDIDOS (reservados).
 * Reglas clave:
 *  - Al crear se descuenta el stock de los ítems (se apartan) y se registra
 *    un movimiento SALIDA, en una sola transacción.
 *  - Cada pago (adelanto/abono) genera una fila en VENTA con ID_PEDIDO para
 *    que el reporte de caja lo contabilice como ingreso aparte.
 *  - ADELANTO = monto pagado acumulado; Faltante = TOTAL - ADELANTO.
 */
public class PedidoDAO {

    private final ClienteDAO clienteDAO = new ClienteDAO();
    private final CreditoClienteDAO creditoDAO = new CreditoClienteDAO();

    private static final String SQL_LISTAR =
            "SELECT pd.ID_PEDIDO, pd.ID_CLIENTE, pd.NOMBRE_PAGA, pd.NOMBRE_RECOGE, pd.CELULAR, "
          + "pd.FECHA_RECOJO, pd.HORA_RECOJO, pd.ADELANTO, pd.TOTAL, pd.ESTADO, pd.ESTADO_PRODUCCION, "
          + "pd.ID_USUARIO, pd.FECHA_CREACION, pd.FECHA_LIMITE_CANJE, pd.FECHA_LIMITE_RECOJO, "
          + "pd.FECHA_LIMITE_PAGO, pd.FECHA_PAGO_TOTAL, cl.NOMBRE AS NOMBRE_CLIENTE "
          + "FROM dbo.PEDIDOS pd LEFT JOIN dbo.CLIENTE cl ON pd.ID_CLIENTE = cl.ID_CLIENTE "
          + "WHERE (? IS NULL OR pd.NOMBRE_PAGA LIKE ? OR pd.CELULAR LIKE ? "
          + "       OR ISNULL(pd.NOMBRE_RECOGE, '') LIKE ?) "
          + "AND (? IS NULL OR pd.ID_USUARIO = ?) "
          + "ORDER BY pd.FECHA_CREACION DESC";

    private static final String SQL_BUSCAR =
            "SELECT pd.ID_PEDIDO, pd.ID_CLIENTE, pd.NOMBRE_PAGA, pd.NOMBRE_RECOGE, pd.CELULAR, "
          + "pd.FECHA_RECOJO, pd.HORA_RECOJO, pd.ADELANTO, pd.TOTAL, pd.ESTADO, pd.ESTADO_PRODUCCION, "
          + "pd.ID_USUARIO, pd.FECHA_CREACION, pd.FECHA_LIMITE_CANJE, pd.FECHA_LIMITE_RECOJO, "
          + "pd.FECHA_LIMITE_PAGO, pd.FECHA_PAGO_TOTAL, cl.NOMBRE AS NOMBRE_CLIENTE "
          + "FROM dbo.PEDIDOS pd LEFT JOIN dbo.CLIENTE cl ON pd.ID_CLIENTE = cl.ID_CLIENTE "
          + "WHERE pd.ID_PEDIDO = ?";

    private static final String SQL_LINEAS =
            "SELECT dp.ID_INVENTARIO, COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) AS NOMBRE, "
          + "dp.CANTIDAD, dp.COSTO_UNITARIO, dp.SUBTOTAL "
          + "FROM dbo.DETALLE_PEDIDO dp "
          + "JOIN dbo.INVENTARIO i ON dp.ID_INVENTARIO = i.ID_INVENTARIO "
          + "LEFT JOIN dbo.PRODUCTO p ON i.ID_PRODUCTO = p.ID_PRODUCTO "
          + "LEFT JOIN dbo.MATERIAL m ON i.ID_MATERIAL = m.ID_MATERIAL "
          + "LEFT JOIN dbo.ARREGLO a ON i.ID_DETALLE = a.ID_DETALLE "
          + "WHERE dp.ID_PEDIDO = ? ORDER BY NOMBRE";

    // ------------------------------------------------------------------
    // Crear
    // ------------------------------------------------------------------

    /**
     * Crea un pedido reservado de forma transaccional: cliente, cabecera,
     * líneas, descuento de stock + movimiento, y el pago en VENTA.
     * Devuelve el id del pedido.
     */
    public int crear(Pedido p, List<DetalleVenta> lineas, int idCaja, String metodoPago)
            throws SQLException {
        // El cliente se busca/crea en su propia conexión (queda confirmado
        // aunque la transacción del pedido falle; un cliente huérfano es inocuo).
        int idCliente = clienteDAO.buscarOCrear(p.getCelular(), p.getNombrePaga());

        Connection cn = null;
        try {
            cn = ConexionDB.obtener();
            cn.setAutoCommit(false);

            int idPedido = insertarPedido(cn, p, idCliente);
            for (DetalleVenta l : lineas) {
                insertarDetalle(cn, idPedido, l);
                descontarStock(cn, l.getIdInventario(), l.getCantidad());
                insertarMovimiento(cn, p.getIdUsuario(), l.getIdInventario(), l.getCantidad(), "SALIDA");
            }
            if (p.getAdelanto() != null && p.getAdelanto().compareTo(BigDecimal.ZERO) > 0) {
                insertarPago(cn, idCaja, idPedido, p.getAdelanto(), metodoPago);
            }

            cn.commit();
            return idPedido;
        } catch (SQLException | RuntimeException e) {
            if (cn != null) {
                try { cn.rollback(); } catch (SQLException ignorada) { }
            }
            throw e;
        } finally {
            if (cn != null) {
                try { cn.setAutoCommit(true); cn.close(); } catch (SQLException ignorada) { }
            }
        }
    }

    private int insertarPedido(Connection cn, Pedido p, int idCliente) throws SQLException {
        String sql =
            "INSERT INTO dbo.PEDIDOS (ID_CLIENTE, NOMBRE_PAGA, NOMBRE_RECOGE, CELULAR, "
          + "FECHA_RECOJO, HORA_RECOJO, ADELANTO, TOTAL, ESTADO, ESTADO_PRODUCCION, ID_USUARIO, "
          + "FECHA_CREACION, FECHA_LIMITE_CANJE, FECHA_LIMITE_RECOJO, FECHA_LIMITE_PAGO, FECHA_PAGO_TOTAL) "
          + "OUTPUT INSERTED.ID_PEDIDO "
          + "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "
          + "SYSUTCDATETIME(), DATEADD(MONTH, 3, SYSUTCDATETIME()), DATEADD(MONTH, 2, ?), "
          + "DATEADD(MONTH, 1, SYSUTCDATETIME()), "
          + "CASE WHEN ? = 'COMPLETO' THEN SYSUTCDATETIME() ELSE NULL END)";
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idCliente);
            ps.setString(2, p.getNombrePaga());
            ps.setString(3, p.getNombreRecoge());
            ps.setString(4, p.getCelular());
            ps.setObject(5, p.getFechaRecojo());
            ps.setString(6, p.getHoraRecojo());
            ps.setBigDecimal(7, p.getAdelanto());
            ps.setBigDecimal(8, p.getTotal());
            ps.setString(9, p.getEstado());
            ps.setString(10, p.getEstadoProduccion());
            ps.setInt(11, p.getIdUsuario());
            ps.setObject(12, p.getFechaRecojo());   // base de FECHA_LIMITE_RECOJO
            ps.setString(13, p.getEstado());        // decide FECHA_PAGO_TOTAL
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return rs.getInt(1);
                }
            }
        }
        throw new SQLException("No se pudo crear el pedido");
    }

    private void insertarDetalle(Connection cn, int idPedido, DetalleVenta l) throws SQLException {
        String sql =
            "INSERT INTO dbo.DETALLE_PEDIDO (ID_PEDIDO, ID_INVENTARIO, CANTIDAD, COSTO_UNITARIO, SUBTOTAL) "
          + "VALUES (?, ?, ?, ?, ?)";
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idPedido);
            ps.setInt(2, l.getIdInventario());
            ps.setInt(3, l.getCantidad());
            ps.setBigDecimal(4, l.getCostoUnitario());
            ps.setBigDecimal(5, l.getSubtotal());
            ps.executeUpdate();
        }
    }

    private void descontarStock(Connection cn, int idInventario, int cantidad) throws SQLException {
        String sql =
            "UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT - ? "
          + "WHERE ID_INVENTARIO = ? AND STOCK_ACT >= ?";
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, cantidad);
            ps.setInt(2, idInventario);
            ps.setInt(3, cantidad);
            if (ps.executeUpdate() == 0) {
                throw new SQLException("Stock insuficiente para apartar el ítem");
            }
        }
    }

    private void insertarMovimiento(Connection cn, int idUsuario, int idInventario,
                                    int cantidad, String tipo) throws SQLException {
        String sql =
            "INSERT INTO dbo.MOVIMIENTO_INVENTARIO (ID_USUARIO, ID_INVENTARIO, TIPO_MOVIMIENTO, "
          + "CANTIDAD, FECHA_MOVIMIENTO) VALUES (?, ?, ?, ?, SYSUTCDATETIME())";
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idUsuario);
            ps.setInt(2, idInventario);
            ps.setString(3, tipo);
            ps.setInt(4, cantidad);
            ps.executeUpdate();
        }
    }

    private void insertarPago(Connection cn, int idCaja, int idPedido,
                              BigDecimal monto, String metodo) throws SQLException {
        String sql =
            "INSERT INTO dbo.VENTA (ID_CAJA, ID_PEDIDO, TOTAL, TIPO_PAGO, ESTADO, FECHA_VENTA) "
          + "VALUES (?, ?, ?, ?, 'VENDIDO', SYSUTCDATETIME())";
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idCaja);
            ps.setInt(2, idPedido);
            ps.setBigDecimal(3, monto);
            ps.setString(4, metodo);
            ps.executeUpdate();
        }
    }

    // ------------------------------------------------------------------
    // Abono (pago adicional sobre un pedido existente)
    // ------------------------------------------------------------------

    /** Registra un abono: suma al ADELANTO y crea el pago en VENTA. Transaccional. */
    public void abono(int idPedido, BigDecimal monto, int idCaja, String metodo, int idUsuario)
            throws SQLException {
        Connection cn = null;
        try {
            cn = ConexionDB.obtener();
            cn.setAutoCommit(false);

            // Leer total y adelanto actual (para validar el tope del abono)
            BigDecimal total = null;
            BigDecimal adelanto = null;
            try (PreparedStatement ps = cn.prepareStatement(
                    "SELECT TOTAL, ADELANTO FROM dbo.PEDIDOS WHERE ID_PEDIDO = ?")) {
                ps.setInt(1, idPedido);
                try (ResultSet rs = ps.executeQuery()) {
                    if (rs.next()) {
                        total = rs.getBigDecimal(1);
                        adelanto = rs.getBigDecimal(2);
                    }
                }
            }
            if (total == null) {
                throw new SQLException("El pedido no existe");
            }
            // Tope: el abono nunca puede superar lo que falta por pagar (H4)
            BigDecimal saldo = total.subtract(adelanto == null ? BigDecimal.ZERO : adelanto);
            if (monto.compareTo(saldo) > 0) {
                throw new IllegalStateException(
                        "El abono supera el saldo pendiente del pedido");
            }

            // Sumar abono y, si llega al total, marcar COMPLETO
            try (PreparedStatement ps = cn.prepareStatement(
                    "UPDATE dbo.PEDIDOS SET ADELANTO = ADELANTO + ?, "
                  + "ESTADO = CASE WHEN ADELANTO + ? >= TOTAL THEN 'COMPLETO' ELSE ESTADO END, "
                  + "FECHA_PAGO_TOTAL = CASE WHEN ADELANTO + ? >= TOTAL "
                  + "THEN SYSUTCDATETIME() ELSE FECHA_PAGO_TOTAL END "
                  + "WHERE ID_PEDIDO = ? AND ESTADO IN ('PENDIENTE', 'COMPLETO')")) {
                ps.setBigDecimal(1, monto);
                ps.setBigDecimal(2, monto);
                ps.setBigDecimal(3, monto);
                ps.setInt(4, idPedido);
                if (ps.executeUpdate() == 0) {
                    // Estado que no admite pagos (cancelado/recogido): no se
                    // registra el pago en VENTA.
                    throw new IllegalStateException(
                            "El pedido ya no admite abonos (cancelado o entregado)");
                }
            }

            insertarPago(cn, idCaja, idPedido, monto, metodo);

            cn.commit();
        } catch (SQLException | RuntimeException e) {
            if (cn != null) {
                try { cn.rollback(); } catch (SQLException ignorada) { }
            }
            throw e;
        } finally {
            if (cn != null) {
                try { cn.setAutoCommit(true); cn.close(); } catch (SQLException ignorada) { }
            }
        }
    }

    // ------------------------------------------------------------------
    // Estados
    // ------------------------------------------------------------------

    /** Marca el pedido como HECHO (punto de no retorno). */
    public void marcarHecho(int idPedido) throws SQLException {
        ejecutarEstado(
            "UPDATE dbo.PEDIDOS SET ESTADO_PRODUCCION = 'HECHO' "
          + "WHERE ID_PEDIDO = ? AND ESTADO_PRODUCCION = 'PENDIENTE'", idPedido);
    }

    /** Marca el pedido como RECOGIDO (entregado). Debe estar pagado por completo. */
    public void recoger(int idPedido) throws SQLException {
        ejecutarEstado(
            "UPDATE dbo.PEDIDOS SET ESTADO = 'RECOGIDO' "
          + "WHERE ID_PEDIDO = ? AND ADELANTO >= TOTAL AND ESTADO = 'COMPLETO'", idPedido);
    }

    /**
     * Cancela un pedido antes de producción, devuelve las unidades apartadas
     * al inventario y convierte el adelanto en saldo a favor, sin reembolso.
     */
    public void cancelar(int idPedido, int idUsuario) throws SQLException {
        Connection cn = null;
        try {
            cn = ConexionDB.obtener();
            cn.setAutoCommit(false);

            Pedido pedido = leerParaCancelar(cn, idPedido);
            if (pedido == null) {
                throw new SQLException("El pedido no existe.");
            }
            if (!"PENDIENTE".equals(pedido.getEstadoProduccion())
                    || "CANCELADO".equals(pedido.getEstado())
                    || "RECOGIDO".equals(pedido.getEstado())) {
                throw new IllegalStateException(
                        "Solo se puede cancelar un pedido antes de iniciar producción.");
            }

            for (DetalleVenta linea : lineas(cn, idPedido)) {
                devolverStock(cn, linea.getIdInventario(), linea.getCantidad());
                insertarMovimiento(cn, idUsuario, linea.getIdInventario(),
                        linea.getCantidad(), "ENTRADA");
            }

            try (PreparedStatement ps = cn.prepareStatement(
                    "UPDATE dbo.PEDIDOS SET ESTADO = 'CANCELADO', "
                  + "ESTADO_PRODUCCION = 'CANCELADO' "
                  + "WHERE ID_PEDIDO = ? AND ESTADO_PRODUCCION = 'PENDIENTE'")) {
                ps.setInt(1, idPedido);
                if (ps.executeUpdate() == 0) {
                    throw new IllegalStateException(
                            "El pedido cambió de estado y ya no puede cancelarse.");
                }
            }

            creditoDAO.crearDesdeCancelacion(cn, pedido.getIdCliente(),
                    pedido.getAdelanto(), idUsuario);
            cn.commit();
        } catch (SQLException | RuntimeException e) {
            if (cn != null) {
                try { cn.rollback(); } catch (SQLException ignorada) { }
            }
            throw e;
        } finally {
            if (cn != null) {
                try { cn.setAutoCommit(true); cn.close(); } catch (SQLException ignorada) { }
            }
        }
    }

    private Pedido leerParaCancelar(Connection cn, int idPedido) throws SQLException {
        String sql = "SELECT ID_PEDIDO, ID_CLIENTE, ADELANTO, ESTADO, "
                + "ESTADO_PRODUCCION FROM dbo.PEDIDOS WITH (UPDLOCK, ROWLOCK) "
                + "WHERE ID_PEDIDO = ?";
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idPedido);
            try (ResultSet rs = ps.executeQuery()) {
                if (!rs.next()) {
                    return null;
                }
                Pedido p = new Pedido();
                p.setIdPedido(rs.getInt("ID_PEDIDO"));
                p.setIdCliente(rs.getInt("ID_CLIENTE"));
                p.setAdelanto(rs.getBigDecimal("ADELANTO"));
                p.setEstado(rs.getString("ESTADO"));
                p.setEstadoProduccion(rs.getString("ESTADO_PRODUCCION"));
                return p;
            }
        }
    }

    private void devolverStock(Connection cn, int idInventario, int cantidad)
            throws SQLException {
        try (PreparedStatement ps = cn.prepareStatement(
                "UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT + ? "
              + "WHERE ID_INVENTARIO = ?")) {
            ps.setInt(1, cantidad);
            ps.setInt(2, idInventario);
            if (ps.executeUpdate() == 0) {
                throw new SQLException("No se encontró el artículo reservado.");
            }
        }
    }

    private void ejecutarEstado(String sql, int idPedido) throws SQLException {
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idPedido);
            ps.executeUpdate();
        } catch (SQLException e) {
            throw new SQLException("No se pudo actualizar el pedido (revísalo e inténtalo de nuevo)", e);
        }
    }

    // ------------------------------------------------------------------
    // Lecturas
    // ------------------------------------------------------------------

    /** Lista pedidos; q filtra por nombre, celular o quién recoge (todos). */
    public List<Pedido> listar(String q) {
        return listar(q, null);
    }

    /**
     * Lista pedidos; q filtra por nombre, celular o quién recoge.
     * idUsuario = null (admin) lista todos; un empleado solo ve los suyos (H4).
     */
    public List<Pedido> listar(String q, Integer idUsuario) {
        List<Pedido> lista = new ArrayList<>();
        String filtro = (q == null || q.trim().isEmpty()) ? null : "%" + q.trim() + "%";
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(SQL_LISTAR)) {
            if (filtro == null) {
                ps.setNull(1, java.sql.Types.VARCHAR);
                ps.setNull(2, java.sql.Types.VARCHAR);
                ps.setNull(3, java.sql.Types.VARCHAR);
                ps.setNull(4, java.sql.Types.VARCHAR);
            } else {
                ps.setString(1, filtro);
                ps.setString(2, filtro);
                ps.setString(3, filtro);
                ps.setString(4, filtro);
            }
            if (idUsuario == null) {
                ps.setNull(5, java.sql.Types.INTEGER);
                ps.setNull(6, java.sql.Types.INTEGER);
            } else {
                ps.setInt(5, idUsuario);
                ps.setInt(6, idUsuario);
            }
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    lista.add(mapear(rs));
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al listar pedidos", e);
        }
        return lista;
    }

    /** Busca un pedido por id con sus líneas, o null si no existe. */
    public Pedido buscarPorId(int idPedido) {
        Pedido p = null;
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(SQL_BUSCAR)) {
            ps.setInt(1, idPedido);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    p = mapear(rs);
                }
            }
            if (p != null) {
                p.setLineas(lineas(cn, idPedido));
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al leer el pedido", e);
        }
        return p;
    }

    private List<DetalleVenta> lineas(Connection cn, int idPedido) throws SQLException {
        List<DetalleVenta> lineas = new ArrayList<>();
        try (PreparedStatement ps = cn.prepareStatement(SQL_LINEAS)) {
            ps.setInt(1, idPedido);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    DetalleVenta l = new DetalleVenta();
                    l.setIdInventario(rs.getInt("ID_INVENTARIO"));
                    l.setNombre(rs.getString("NOMBRE"));
                    l.setCantidad(rs.getInt("CANTIDAD"));
                    l.setCostoUnitario(rs.getBigDecimal("COSTO_UNITARIO"));
                    l.setSubtotal(rs.getBigDecimal("SUBTOTAL"));
                    lineas.add(l);
                }
            }
        }
        return lineas;
    }

    private Pedido mapear(ResultSet rs) throws SQLException {
        Pedido p = new Pedido();
        p.setIdPedido(rs.getInt("ID_PEDIDO"));
        int idCliente = rs.getInt("ID_CLIENTE");
        p.setIdCliente(rs.wasNull() ? null : idCliente);
        p.setNombrePaga(rs.getString("NOMBRE_PAGA"));
        p.setNombreRecoge(rs.getString("NOMBRE_RECOGE"));
        p.setCelular(rs.getString("CELULAR"));
        Timestamp fr = rs.getTimestamp("FECHA_RECOJO");
        if (fr != null) p.setFechaRecojo(fr.toLocalDateTime().toLocalDate());
        p.setHoraRecojo(rs.getString("HORA_RECOJO"));
        p.setAdelanto(rs.getBigDecimal("ADELANTO"));
        p.setTotal(rs.getBigDecimal("TOTAL"));
        p.setEstado(rs.getString("ESTADO"));
        p.setEstadoProduccion(rs.getString("ESTADO_PRODUCCION"));
        p.setIdUsuario(rs.getInt("ID_USUARIO"));
        Timestamp fc = rs.getTimestamp("FECHA_CREACION");
        if (fc != null) p.setFechaCreacion(fc.toLocalDateTime());
        Timestamp lc = rs.getTimestamp("FECHA_LIMITE_CANJE");
        if (lc != null) p.setFechaLimiteCanje(lc.toLocalDateTime());
        Timestamp lr = rs.getTimestamp("FECHA_LIMITE_RECOJO");
        if (lr != null) p.setFechaLimiteRecojo(lr.toLocalDateTime());
        Timestamp lp = rs.getTimestamp("FECHA_LIMITE_PAGO");
        if (lp != null) p.setFechaLimitePago(lp.toLocalDateTime());
        Timestamp fpg = rs.getTimestamp("FECHA_PAGO_TOTAL");
        if (fpg != null) p.setFechaPagoTotal(fpg.toLocalDateTime());
        try {
            p.setNombreCliente(rs.getString("NOMBRE_CLIENTE"));
        } catch (SQLException ignorada) {
            // columna no presente en algunas consultas
        }
        return p;
    }
}
