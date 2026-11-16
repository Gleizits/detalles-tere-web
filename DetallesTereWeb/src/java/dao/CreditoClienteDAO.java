package dao;

import java.math.BigDecimal;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;

/**
 * Registra saldos a favor y ejecuta canjes completos de crédito.
 */
public class CreditoClienteDAO {

    /**
     * Convierte un adelanto no reembolsable en saldo a favor vigente.
     */
    public void crearDesdeCancelacion(Connection cn, int idCliente,
                                      BigDecimal monto, int idUsuario)
            throws SQLException {
        if (monto == null || monto.signum() <= 0) {
            return;
        }
        String sql = "INSERT INTO dbo.CREDITO_CLIENTE "
                + "(ID_CLIENTE, MONTO, ESTADO, FECHA, FECHA_VENCIMIENTO, ID_USUARIO) "
                + "VALUES (?, ?, 'ACTIVO', SYSUTCDATETIME(), "
                + "DATEADD(MONTH, 3, SYSUTCDATETIME()), ?)";
        try (PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idCliente);
            ps.setBigDecimal(2, monto);
            ps.setInt(3, idUsuario);
            ps.executeUpdate();
        }
    }

    /**
     * Marca todos los créditos vigentes del cliente como canjeados.
     * El saldo acumulado debe coincidir exactamente con el total de la venta.
     */
    public void aplicar(Connection cn, int idCliente, BigDecimal total, int idVenta)
            throws SQLException {
        String sqlSaldo = "SELECT COALESCE(SUM(MONTO), 0) "
                + "FROM dbo.CREDITO_CLIENTE WITH (UPDLOCK, ROWLOCK) "
                + "WHERE ID_CLIENTE = ? AND ESTADO = 'ACTIVO' "
                + "AND (FECHA_VENCIMIENTO IS NULL OR FECHA_VENCIMIENTO >= SYSUTCDATETIME())";
        BigDecimal saldo;
        try (PreparedStatement ps = cn.prepareStatement(sqlSaldo)) {
            ps.setInt(1, idCliente);
            try (ResultSet rs = ps.executeQuery()) {
                rs.next();
                saldo = rs.getBigDecimal(1);
            }
        }
        if (saldo.compareTo(total) != 0) {
            throw new IllegalStateException("El saldo a favor vigente del cliente es S/ "
                    + saldo.setScale(2) + ". El canje debe usarlo completo.");
        }

        String sqlActualizar = "UPDATE dbo.CREDITO_CLIENTE SET ESTADO = 'CANJEADO', "
                + "ID_VENTA = ? WHERE ID_CLIENTE = ? AND ESTADO = 'ACTIVO' "
                + "AND (FECHA_VENCIMIENTO IS NULL OR FECHA_VENCIMIENTO >= SYSUTCDATETIME())";
        try (PreparedStatement ps = cn.prepareStatement(sqlActualizar)) {
            ps.setInt(1, idVenta);
            ps.setInt(2, idCliente);
            ps.executeUpdate();
        }
    }
}
