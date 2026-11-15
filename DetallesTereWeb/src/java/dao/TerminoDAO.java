package dao;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.List;
import modelo.InventarioItem;
import modelo.Termino;
import util.ConexionDB;

/** Acceso a términos de búsqueda y sus asociaciones con inventario. */
public class TerminoDAO {

    public List<Termino> listar() {
        List<Termino> terminos = new ArrayList<>();
        String sql = "SELECT t.ID_TERMINO, t.PALABRA, "
                + "COALESCE(STRING_AGG(CAST(COALESCE(pr.NOMBRE, m.NOMBRE, a.NOMBRE) "
                + "AS VARCHAR(MAX)), ', '), 'Sin artículos') AS ASOCIACIONES "
                + "FROM dbo.TERMINOS t "
                + "LEFT JOIN dbo.DETALLE_TERMINO dt ON dt.ID_TERMINO = t.ID_TERMINO "
                + "LEFT JOIN dbo.INVENTARIO inv ON inv.ID_INVENTARIO = dt.ID_INVENTARIO "
                + "LEFT JOIN dbo.PRODUCTO pr ON pr.ID_PRODUCTO = inv.ID_PRODUCTO "
                + "LEFT JOIN dbo.MATERIAL m ON m.ID_MATERIAL = inv.ID_MATERIAL "
                + "LEFT JOIN dbo.ARREGLO a ON a.ID_DETALLE = inv.ID_DETALLE "
                + "GROUP BY t.ID_TERMINO, t.PALABRA ORDER BY t.PALABRA";
        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ps = cn.prepareStatement(sql);
                ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                Termino termino = new Termino();
                termino.setIdTermino(rs.getInt("ID_TERMINO"));
                termino.setPalabra(rs.getString("PALABRA"));
                termino.setAsociaciones(rs.getString("ASOCIACIONES"));
                terminos.add(termino);
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al listar términos", e);
        }
        return terminos;
    }

    public List<InventarioItem> listarInventarioActivo() {
        return new InventarioDAO().listarActivos();
    }

    public void crear(String palabra, int idUsuario) {
        String sql = "INSERT INTO dbo.TERMINOS (ID_USUARIO, PALABRA) VALUES (?, ?)";
        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ps = cn.prepareStatement(sql,
                        Statement.RETURN_GENERATED_KEYS)) {
            ps.setInt(1, idUsuario);
            ps.setString(2, palabra);
            ps.executeUpdate();
        } catch (SQLException e) {
            throw new RuntimeException("No se pudo crear el término. "
                    + "Puede que ya exista.", e);
        }
    }

    public void asociar(int idTermino, int idInventario) {
        String sql = "INSERT INTO dbo.DETALLE_TERMINO "
                + "(ID_TERMINO, ID_INVENTARIO) VALUES (?, ?)";
        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idTermino);
            ps.setInt(2, idInventario);
            ps.executeUpdate();
        } catch (SQLException e) {
            throw new RuntimeException("No se pudo asociar el término. "
                    + "Puede que la asociación ya exista.", e);
        }
    }

    public void desasociar(int idTermino, int idInventario) {
        String sql = "DELETE FROM dbo.DETALLE_TERMINO "
                + "WHERE ID_TERMINO = ? AND ID_INVENTARIO = ?";
        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idTermino);
            ps.setInt(2, idInventario);
            ps.executeUpdate();
        } catch (SQLException e) {
            throw new RuntimeException("No se pudo quitar la asociación", e);
        }
    }
}
