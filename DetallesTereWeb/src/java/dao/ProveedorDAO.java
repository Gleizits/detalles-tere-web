package dao;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.List;
import modelo.Proveedor;
import util.ConexionDB;

/** Acceso a proveedores usados por los ingresos de inventario. */
public class ProveedorDAO {

    public List<Proveedor> listar() {
        List<Proveedor> proveedores = new ArrayList<>();
        String sql = "SELECT ID_PROVEEDOR, NOMBRE, NUMERO "
                + "FROM dbo.PROVEEDOR ORDER BY NOMBRE";

        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ps = cn.prepareStatement(sql);
                ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                Proveedor proveedor = new Proveedor();
                proveedor.setIdProveedor(rs.getInt("ID_PROVEEDOR"));
                proveedor.setNombre(rs.getString("NOMBRE"));
                proveedor.setNumero(rs.getString("NUMERO"));
                proveedores.add(proveedor);
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al listar proveedores", e);
        }
        return proveedores;
    }

    public void crear(String nombre, String numero) {
        String sql = "INSERT INTO dbo.PROVEEDOR (NOMBRE, NUMERO) VALUES (?, ?)";
        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ps = cn.prepareStatement(sql,
                        Statement.RETURN_GENERATED_KEYS)) {
            ps.setString(1, nombre);
            ps.setString(2, numero);
            ps.executeUpdate();
        } catch (SQLException e) {
            throw new RuntimeException("Error al crear el proveedor", e);
        }
    }
}
