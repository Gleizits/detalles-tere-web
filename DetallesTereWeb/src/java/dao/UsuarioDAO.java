package dao;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.List;
import modelo.Usuario;
import util.ConexionDB;
import org.mindrot.jbcrypt.BCrypt;

/**
 * Acceso a datos de USUARIO. Login por username + BCrypt.
 */
public class UsuarioDAO {

    private static final String SQL_LOGIN
            = "SELECT ID_USUARIO, USERNAME, NOMBRE, APELLIDO, ROL "
            + "FROM dbo.USUARIO WHERE USERNAME = ? AND ESTADO = 1";

    /**
     * Hash ficticio para igualar el tiempo de respuesta cuando el usuario
     * no existe (evita descubrir usuarios válidos por el tiempo que tarda
     * BCrypt). Se calcula una sola vez al cargar la clase.
     */
    private static final String HASH_DUMMY =
            BCrypt.hashpw("tiempo-de-respuesta-dummy", BCrypt.gensalt(10));

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
                } else {
                    // Usuario inexistente: se compara contra un hash válido
                    // para que el tiempo de respuesta sea el mismo (H5).
                    org.mindrot.jbcrypt.BCrypt.checkpw(password, HASH_DUMMY);
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al iniciar sesión", e);
        }
        return null;
    }

    public List<Usuario> listar() {
        List<Usuario> usuarios = new ArrayList<>();
        String sql = "SELECT ID_USUARIO, USERNAME, NOMBRE, APELLIDO, CORREO, ROL, ESTADO "
                + "FROM dbo.USUARIO ORDER BY APELLIDO, NOMBRE";
        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ps = cn.prepareStatement(sql);
                ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                usuarios.add(mapear(rs));
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al listar usuarios", e);
        }
        return usuarios;
    }

    public void crear(Usuario usuario, String password) {
        String sql = "INSERT INTO dbo.USUARIO "
                + "(USERNAME, NOMBRE, APELLIDO, CORREO, CONTRASENA, ROL, ESTADO) "
                + "VALUES (?, ?, ?, ?, ?, ?, 1)";
        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ps = cn.prepareStatement(sql,
                        Statement.RETURN_GENERATED_KEYS)) {
            ps.setString(1, usuario.getUsername());
            ps.setString(2, usuario.getNombre());
            ps.setString(3, usuario.getApellido());
            if (usuario.getCorreo() == null || usuario.getCorreo().isEmpty()) {
                ps.setNull(4, java.sql.Types.VARCHAR);
            } else {
                ps.setString(4, usuario.getCorreo());
            }
            ps.setString(5, BCrypt.hashpw(password, BCrypt.gensalt(10)));
            ps.setString(6, usuario.getRol());
            ps.executeUpdate();
        } catch (SQLException e) {
            throw new RuntimeException("No se pudo crear el usuario. "
                    + "Verifica que usuario y correo no estén repetidos.", e);
        }
    }

    public void actualizar(Usuario usuario, String password) {
        if (contarAdministradoresActivos() <= 1
                && (!usuario.isEstado() || !"admin".equalsIgnoreCase(usuario.getRol()))) {
            throw new IllegalArgumentException(
                    "Debe permanecer al menos un administrador activo.");
        }

        String sqlConPassword = "UPDATE dbo.USUARIO SET USERNAME=?, NOMBRE=?, "
                + "APELLIDO=?, CORREO=?, ROL=?, ESTADO=?, CONTRASENA=? WHERE ID_USUARIO=?";
        String sqlSinPassword = "UPDATE dbo.USUARIO SET USERNAME=?, NOMBRE=?, "
                + "APELLIDO=?, CORREO=?, ROL=?, ESTADO=? WHERE ID_USUARIO=?";
        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ps = cn.prepareStatement(
                        password == null ? sqlSinPassword : sqlConPassword)) {
            llenarDatos(ps, usuario);
            int indice = 6;
            ps.setBoolean(indice++, usuario.isEstado());
            if (password != null) {
                ps.setString(indice++, BCrypt.hashpw(password, BCrypt.gensalt(10)));
            }
            ps.setInt(indice, usuario.getId());
            ps.executeUpdate();
        } catch (SQLException e) {
            throw new RuntimeException("No se pudo actualizar el usuario. "
                    + "Verifica que usuario y correo no estén repetidos.", e);
        }
    }

    private int contarAdministradoresActivos() {
        String sql = "SELECT COUNT(*) FROM dbo.USUARIO WHERE ROL = 'admin' AND ESTADO = 1";
        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ps = cn.prepareStatement(sql);
                ResultSet rs = ps.executeQuery()) {
            rs.next();
            return rs.getInt(1);
        } catch (SQLException e) {
            throw new RuntimeException("No se pudo validar administradores activos", e);
        }
    }

    private void llenarDatos(PreparedStatement ps, Usuario usuario) throws SQLException {
        ps.setString(1, usuario.getUsername());
        ps.setString(2, usuario.getNombre());
        ps.setString(3, usuario.getApellido());
        if (usuario.getCorreo() == null || usuario.getCorreo().isEmpty()) {
            ps.setNull(4, java.sql.Types.VARCHAR);
        } else {
            ps.setString(4, usuario.getCorreo());
        }
        ps.setString(5, usuario.getRol());
    }

    private Usuario mapear(ResultSet rs) throws SQLException {
        Usuario usuario = new Usuario(rs.getInt("ID_USUARIO"),
                rs.getString("USERNAME"), rs.getString("NOMBRE"),
                rs.getString("APELLIDO"), rs.getString("ROL"));
        usuario.setCorreo(rs.getString("CORREO"));
        usuario.setEstado(rs.getBoolean("ESTADO"));
        return usuario;
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
