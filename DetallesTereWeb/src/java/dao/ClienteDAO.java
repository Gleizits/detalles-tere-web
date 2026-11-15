package dao;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import modelo.Cliente;
import util.ConexionDB;

/**
 * Acceso a datos de CLIENTE (solo NOMBRE y NUM_WSP).
 * En pedidos se busca por celular y, si no existe, se crea.
 */
public class ClienteDAO {

    private static final String SQL_POR_WSP =
            "SELECT ID_CLIENTE, NOMBRE, NUM_WSP FROM dbo.CLIENTE WHERE NUM_WSP = ?";

    private static final String SQL_INSERT =
            "INSERT INTO dbo.CLIENTE (NOMBRE, NUM_WSP) OUTPUT INSERTED.ID_CLIENTE VALUES (?, ?)";

    /** Busca un cliente por número de WhatsApp/celular, o null si no existe. */
    public Cliente buscarPorWsp(String numWsp) {
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(SQL_POR_WSP)) {
            ps.setString(1, numWsp);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    Cliente c = new Cliente();
                    c.setIdCliente(rs.getInt("ID_CLIENTE"));
                    c.setNombre(rs.getString("NOMBRE"));
                    c.setNumWsp(rs.getString("NUM_WSP"));
                    return c;
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al buscar cliente", e);
        }
        return null;
    }

    /**
     * Devuelve el id del cliente con ese celular: lo usa si existe,
     * o lo crea si no. Si existe pero con otro nombre, no lo modifica.
     */
    public int buscarOCrear(String numWsp, String nombre) throws SQLException {
        Cliente existente = buscarPorWsp(numWsp);
        if (existente != null) {
            return existente.getIdCliente();
        }
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(SQL_INSERT)) {
            ps.setString(1, nombre);
            ps.setString(2, numWsp);
            // OUTPUT INSERTED.ID_CLIENTE devuelve el id como result set
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return rs.getInt(1);
                }
            }
        }
        throw new SQLException("No se pudo crear el cliente");
    }
}
