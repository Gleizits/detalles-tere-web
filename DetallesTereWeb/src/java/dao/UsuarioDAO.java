package dao;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import modelo.Usuario;
import util.ConexionDB;

/**
 * Acceso a datos de USUARIO. Login por username + BCrypt.
 */
public class UsuarioDAO {

    private static final String SQL_LOGIN
            = "SELECT ID_USUARIO, USERNAME, NOMBRE, APELLIDO, ROL "
            + "FROM dbo.USUARIO WHERE USERNAME = ? AND ESTADO = 1";

    /**
     * Valida credenciales. Devuelve el Usuario o null si no coinciden.
     */
    public Usuario login(String username, String password) {
        if (username == null || password == null) {
            return null;
        }
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(SQL_LOGIN)) {

            ps.setString(1, username.trim());
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    String hash = obtenerHash(username.trim());
                    if (hash != null && org.mindrot.jbcrypt.BCrypt.checkpw(password, hash)) {
                        return new Usuario(
                                rs.getInt("ID_USUARIO"),
                                rs.getString("USERNAME"),
                                rs.getString("NOMBRE"),
                                rs.getString("APELLIDO"),
                                rs.getString("ROL"));
                    }
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al iniciar sesión", e);
        }
        return null;
    }

    private static final String SQL_HASH
            = "SELECT CONTRASENA FROM dbo.USUARIO WHERE USERNAME = ? AND ESTADO = 1";

    private String obtenerHash(String username) {
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(SQL_HASH)) {
            ps.setString(1, username);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return rs.getString("CONTRASENA");
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al leer credencial", e);
        }
        return null;
    }
}
