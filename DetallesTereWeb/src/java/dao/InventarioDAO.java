package dao;

import java.math.BigDecimal;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.ArrayList;
import java.util.List;
import modelo.InventarioItem;
import util.ConexionDB;

/**
 * Acceso a datos de INVENTARIO (solo lectura por ahora).
 * Une producto/material/arreglo para mostrar nombre, categoría y precios.
 * La búsqueda coincide por nombre o por término (TERMINOS/DETALLE_TERMINO).
 */
public class InventarioDAO {

    private static final String BASE_SELECT =
            "SELECT i.ID_INVENTARIO, i.STOCK_ACT, i.STOCK_MIN, i.TIPO_ALMACEN, i.UNIDAD_MEDIDA, "
          + "COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) AS NOMBRE, "
          + "COALESCE(p.TIPO, m.TIPO) AS TIPO, "
          + "c.NOMBRE AS CATEGORIA, "
          + "COALESCE(p.PRECIO_VENTA, m.PRECIO_VENTA, a.PRECIO_VENTA) AS PRECIO_VENTA, "
          + "COALESCE(p.PRECIO_COMPRA, m.PRECIO_COMPRA, a.PRECIO_COMPRA) AS PRECIO_COMPRA, "
          + "CASE WHEN p.ID_PRODUCTO IS NOT NULL THEN 'PRODUCTO' "
          + "     WHEN m.ID_MATERIAL IS NOT NULL THEN 'MATERIAL' "
          + "     ELSE 'ARREGLO' END AS CLASE "
          + "FROM dbo.INVENTARIO i "
          + "LEFT JOIN dbo.PRODUCTO p ON i.ID_PRODUCTO = p.ID_PRODUCTO "
          + "LEFT JOIN dbo.MATERIAL m ON i.ID_MATERIAL = m.ID_MATERIAL "
          + "LEFT JOIN dbo.ARREGLO a ON i.ID_DETALLE = a.ID_DETALLE "
          + "LEFT JOIN dbo.CATEGORIA c "
          + "  ON COALESCE(p.ID_CATEGORIA, m.ID_CATEGORIA, a.ID_CATEGORIA) = c.ID_CATEGORIA ";

    private static final String BUSCAR =
            "WHERE (? IS NULL OR ? = '' "
          + "   OR COALESCE(p.NOMBRE, m.NOMBRE, a.NOMBRE) LIKE ? "
          + "   OR EXISTS (SELECT 1 FROM dbo.DETALLE_TERMINO dt "
          + "              JOIN dbo.TERMINOS t ON dt.ID_TERMINO = t.ID_TERMINO "
          + "              WHERE dt.ID_INVENTARIO = i.ID_INVENTARIO AND t.PALABRA LIKE ?)) "
          + "ORDER BY NOMBRE";

    /**
     * Lista el inventario. Si q no está vacío, filtra por nombre o término.
     * La collation Modern_Spanish_CI_AI hace la búsqueda insensible a
     * mayúsculas y acentos automáticamente.
     */
    public List<InventarioItem> listar(String q) {
        List<InventarioItem> lista = new ArrayList<>();
        String sql = BASE_SELECT + BUSCAR;
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(sql)) {
            String consulta = (q == null) ? "" : q.trim();
            String like = "%" + consulta + "%";
            ps.setString(1, consulta);
            ps.setString(2, consulta);
            ps.setString(3, like);
            ps.setString(4, like);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    lista.add(mapItem(rs));
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al listar inventario", e);
        }
        return lista;
    }

    /**
     * Busca un ítem del inventario por su ID (para el carrito de venta:
     * obtener precio de venta y stock actual). Devuelve null si no existe.
     */
    public InventarioItem buscarPorId(int idInventario) {
        String sql = BASE_SELECT + "WHERE i.ID_INVENTARIO = ?";
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(sql)) {
            ps.setInt(1, idInventario);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return mapItem(rs);
                }
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al buscar ítem de inventario", e);
        }
        return null;
    }

    private InventarioItem mapItem(ResultSet rs) throws SQLException {
        InventarioItem it = new InventarioItem();
        it.setIdInventario(rs.getInt("ID_INVENTARIO"));
        it.setStockAct(rs.getInt("STOCK_ACT"));
        it.setStockMin(rs.getInt("STOCK_MIN"));
        it.setTipoAlmacen(rs.getString("TIPO_ALMACEN"));
        it.setUnidadMedida(rs.getString("UNIDAD_MEDIDA"));
        it.setNombre(rs.getString("NOMBRE"));
        it.setTipo(rs.getString("TIPO"));
        it.setCategoria(rs.getString("CATEGORIA"));
        it.setPrecioVenta(rs.getBigDecimal("PRECIO_VENTA"));
        it.setPrecioCompra(rs.getBigDecimal("PRECIO_COMPRA"));
        it.setClase(rs.getString("CLASE"));
        return it;
    }
}
