package dao;

import java.math.BigDecimal;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Timestamp;
import java.util.ArrayList;
import java.util.List;
import modelo.Caja;
import util.ConexionDB;

/**
 * Acceso a datos de CAJA.
 * Reglas: 1 caja abierta por usuario; cierre manual con conteo físico.
 */
public class CajaDAO {

    private static final String SQL_ABIERTA =
            "SELECT ID_CAJA, ID_USUARIO, MONTO_INICIAL, MONTO_FINAL, MONTO_REAL, "
          + "DIFERENCIA, FECHA_APERTURA, FECHA_CIERRE "
          + "FROM dbo.CAJA WHERE ID_USUARIO = ? AND FECHA_CIERRE IS NULL";

    private static final String SQL_ABRIR =
            "INSERT INTO dbo.CAJA (ID_USUARIO, MONTO_INICIAL, FECHA_APERTURA) "
          + "OUTPUT INSERTED.ID_CAJA "
          + "VALUES (?, ?, SYSUTCDATETIME())";

    private static final String SQL_VENTAS_EFECTIVO =
            "SELECT ISNULL(SUM(TOTAL), 0) FROM dbo.VENTA "
          + "WHERE ID_CAJA = ? AND TIPO_PAGO = 'EFECTIVO' AND ESTADO = 'VENDIDO'";

    // Ventas de productos (sin pagos de reserva): ID_PEDIDO IS NULL
    private static final String SQL_VENTAS_PROD_EFECTIVO =
            "SELECT ISNULL(SUM(TOTAL), 0) FROM dbo.VENTA "
          + "WHERE ID_CAJA = ? AND TIPO_PAGO = 'EFECTIVO' AND ESTADO = 'VENDIDO' "
          + "AND ID_PEDIDO IS NULL";

    // Pagos de reservas (adelantos/abonos) en efectivo: ID_PEDIDO IS NOT NULL
    private static final String SQL_PAGOS_RESERVA_EFECTIVO =
            "SELECT ISNULL(SUM(TOTAL), 0) FROM dbo.VENTA "
          + "WHERE ID_CAJA = ? AND TIPO_PAGO = 'EFECTIVO' AND ESTADO = 'VENDIDO' "
          + "AND ID_PEDIDO IS NOT NULL";

    private static final String SQL_CERRAR =
            "UPDATE dbo.CAJA SET MONTO_FINAL = ?, MONTO_REAL = ?, DIFERENCIA = ?, "
          + "FECHA_CIERRE = SYSUTCDATETIME() WHERE ID_CAJA = ?";

    private static final String SQL_HISTORIAL =
            "SELECT TOP (?) c.ID_CAJA, c.ID_USUARIO, u.NOMBRE + ' ' + u.APELLIDO AS NOMBRE_USUARIO, "
          + "c.MONTO_INICIAL, c.MONTO_FINAL, c.MONTO_REAL, c.DIFERENCIA, "
          + "c.FECHA_APERTURA, c.FECHA_CIERRE "
          + "FROM dbo.CAJA c JOIN dbo.USUARIO u ON c.ID_USUARIO = u.ID_USUARIO "
          + "WHERE c.ID_USUARIO = ? "
          + "ORDER BY c.FECHA_APERTURA DESC";

    /** Devuelve la caja abierta del usuario, o null si no tiene. */
    public Caja cajaAbierta(int idUsuario) {
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(SQL_ABIERTA)) {
            ps.setInt(1, idUsuario);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return mapCaja(rs);
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al leer caja abierta", e);
        }
        return null;
    }

    /** Abre una caja nueva y devuelve su id. Lanza excepción si ya hay una abierta. */
    public int abrir(int idUsuario, BigDecimal montoInicial) throws SQLException {
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(SQL_ABRIR)) {
            ps.setInt(1, idUsuario);
            ps.setBigDecimal(2, montoInicial);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return rs.getInt(1);
                }
            }
        }
        throw new SQLException("No se pudo abrir la caja");
    }

    /**
     * Suma de ventas en efectivo de una caja (para monto esperado).
     * Incluye ventas de productos Y pagos de reserva (todo el efectivo que entra).
     */
    public BigDecimal ventasEfectivo(int idCaja) {
        return sumar(idCaja, SQL_VENTAS_EFECTIVO, "Error al sumar ventas en efectivo");
    }

    /** Efectivo que entra por ventas de productos (sin reservas). */
    public BigDecimal ventasProductosEfectivo(int idCaja) {
        return sumar(idCaja, SQL_VENTAS_PROD_EFECTIVO, "Error al sumar ventas de productos");
    }

    /** Efectivo que entra por pagos de reservas (adelantos/abonos). */
    public BigDecimal pagosReservasEfectivo(int idCaja) {
        return sumar(idCaja, SQL_PAGOS_RESERVA_EFECTIVO, "Error al sumar pagos de reservas");
    }

    private BigDecimal sumar(int idCaja, String sql, String error) {
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idCaja);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    BigDecimal s = rs.getBigDecimal(1);
                    return s == null ? BigDecimal.ZERO : s;
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException(error, e);
        }
        return BigDecimal.ZERO;
    }

    /**
     * Cierra la caja: guarda montoFinal (esperado), montoReal (conteo) y diferencia.
     */
    public void cerrar(Caja c) throws SQLException {
        BigDecimal montoFinal = c.getMontoFinal();
        BigDecimal diferencia = c.getMontoReal().subtract(montoFinal);
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(SQL_CERRAR)) {
            ps.setBigDecimal(1, montoFinal);
            ps.setBigDecimal(2, c.getMontoReal());
            ps.setBigDecimal(3, diferencia);
            ps.setInt(4, c.getIdCaja());
            ps.executeUpdate();
            c.setDiferencia(diferencia);
        }
    }

    /** Historial reciente de cajas del usuario. */
    public List<Caja> historial(int idUsuario, int limite) {
        List<Caja> lista = new ArrayList<>();
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(SQL_HISTORIAL)) {
            ps.setInt(1, limite);
            ps.setInt(2, idUsuario);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    lista.add(mapCaja(rs));
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al leer historial de cajas", e);
        }
        return lista;
    }

    private Caja mapCaja(ResultSet rs) throws SQLException {
        Caja c = new Caja();
        c.setIdCaja(rs.getInt("ID_CAJA"));
        c.setIdUsuario(rs.getInt("ID_USUARIO"));
        c.setMontoInicial(rs.getBigDecimal("MONTO_INICIAL"));
        c.setMontoFinal(rs.getBigDecimal("MONTO_FINAL"));
        c.setMontoReal(rs.getBigDecimal("MONTO_REAL"));
        c.setDiferencia(rs.getBigDecimal("DIFERENCIA"));
        Timestamp ap = rs.getTimestamp("FECHA_APERTURA");
        if (ap != null) {
            c.setFechaApertura(ap.toLocalDateTime());
        }
        Timestamp ci = rs.getTimestamp("FECHA_CIERRE");
        if (ci != null) {
            c.setFechaCierre(ci.toLocalDateTime());
        }
        try {
            c.setNombreUsuario(rs.getString("NOMBRE_USUARIO"));
        } catch (SQLException ignorada) {
            // la columna no está en todas las consultas
        }
        return c;
    }
}
