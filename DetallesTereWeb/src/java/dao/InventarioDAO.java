package dao;

import java.math.BigDecimal;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.sql.Types;
import java.util.ArrayList;
import java.util.List;
import modelo.Categoria;
import modelo.InventarioItem;
import util.ConexionDB;

/**
 * Acceso a datos de inventario, incluyendo su CRUD y movimientos de stock.
 * Une producto/material/arreglo para mostrar nombre, categoría y precios.
 * La búsqueda coincide por nombre o por término (TERMINOS/DETALLE_TERMINO).
 */
public class InventarioDAO {

    private static final String BASE_SELECT =
            "SELECT i.ID_INVENTARIO, i.STOCK_ACT, i.STOCK_MIN, i.TIPO_ALMACEN, i.UNIDAD_MEDIDA, "
          + "i.ID_PRODUCTO, i.ID_MATERIAL, i.ID_DETALLE, "
          + "COALESCE(p.ID_CATEGORIA, m.ID_CATEGORIA, a.ID_CATEGORIA) AS ID_CATEGORIA, "
          + "COALESCE(p.ESTADO, m.ESTADO, a.ESTADO) AS ESTADO, "
          + "COALESCE(p.ID_MATERIAL, 0) AS ID_MATERIAL_VINC, "
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

    /** Lista solo artículos activos para operaciones que aumentan stock. */
    public List<InventarioItem> listarActivos() {
        List<InventarioItem> lista = new ArrayList<>();
        String sql = BASE_SELECT + "WHERE COALESCE(p.ESTADO, m.ESTADO, a.ESTADO) = 1 "
                + "ORDER BY NOMBRE";
        try (Connection cn = ConexionDB.obtener();
                PreparedStatement ps = cn.prepareStatement(sql);
                ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                lista.add(mapItem(rs));
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al listar inventario activo", e);
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
        it.setIdProducto(rs.getInt("ID_PRODUCTO"));
        it.setIdMaterial(rs.getInt("ID_MATERIAL"));
        it.setIdArreglo(rs.getInt("ID_DETALLE"));
        it.setIdCategoria(rs.getInt("ID_CATEGORIA"));
        it.setIdMaterialVinc(rs.getInt("ID_MATERIAL_VINC"));
        it.setEstado(rs.getBoolean("ESTADO"));
        return it;
    }

    // ==================================================================
    // Fase 1 — Alta, edición y ajuste manual de stock
    // ==================================================================

    /** Categorías ordenadas para el selector del formulario. */
    public List<Categoria> listarCategorias() {
        List<Categoria> lista = new ArrayList<>();
        String sql = "SELECT ID_CATEGORIA, NOMBRE FROM dbo.CATEGORIA ORDER BY NOMBRE";
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                lista.add(new Categoria(rs.getInt("ID_CATEGORIA"), rs.getString("NOMBRE")));
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al listar categorías", e);
        }
        return lista;
    }

    /**
     * Materiales activos (id + nombre) para el selector de enlace
     * producto ↔ material del formulario.
     */
    public List<Categoria> listarMaterialesActivos() {
        List<Categoria> lista = new ArrayList<>();
        String sql = "SELECT ID_MATERIAL, NOMBRE FROM dbo.MATERIAL WHERE ESTADO = 1 ORDER BY NOMBRE";
        try (Connection cn = ConexionDB.obtener();
             PreparedStatement ps = cn.prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                lista.add(new Categoria(rs.getInt("ID_MATERIAL"), rs.getString("NOMBRE")));
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al listar materiales", e);
        }
        return lista;
    }

    /**
     * Devuelve el id de la categoría con ese nombre; si no existe, la crea.
     * (CATEGORIA.NOMBRE no es UNIQUE: se busca primero por si ya existe.)
     */
    public int obOCrearCategoria(String nombre) {
        String buscar = "SELECT ID_CATEGORIA FROM dbo.CATEGORIA WHERE NOMBRE = ?";
        String insertar = "INSERT INTO dbo.CATEGORIA (NOMBRE) VALUES (?)";
        try (Connection cn = ConexionDB.obtener()) {
            try (PreparedStatement ps = cn.prepareStatement(buscar)) {
                ps.setString(1, nombre.trim());
                try (ResultSet rs = ps.executeQuery()) {
                    if (rs.next()) {
                        return rs.getInt("ID_CATEGORIA");
                    }
                }
            }
            try (PreparedStatement ps = cn.prepareStatement(insertar, Statement.RETURN_GENERATED_KEYS)) {
                ps.setString(1, nombre.trim());
                ps.executeUpdate();
                try (ResultSet rs = ps.getGeneratedKeys()) {
                    if (rs.next()) {
                        return rs.getInt(1);
                    }
                }
            }
            throw new SQLException("No se pudo crear la categoría");
        } catch (SQLException e) {
            throw new RuntimeException("Error al resolver la categoría", e);
        }
    }

    /**
     * Alta de artículo: inserta la fila en PRODUCTO/MATERIAL/ARREGLO y su
     * fila única en INVENTARIO, en una sola transacción (2 operaciones
     * ligadas por la FK, sin duplicar datos → 3FN).
     */
    public void crear(InventarioItem it) {
        final String sqlEnt;
        final String colInventario;
        switch (it.getClase()) {
            case "PRODUCTO":
                sqlEnt = "INSERT INTO dbo.PRODUCTO (ID_CATEGORIA, NOMBRE, TIPO, PRECIO_COMPRA, "
                       + "PRECIO_VENTA, ESTADO, ID_MATERIAL) VALUES (?,?,?,?,?,?,?)";
                colInventario = "ID_PRODUCTO";
                break;
            case "MATERIAL":
                sqlEnt = "INSERT INTO dbo.MATERIAL (ID_CATEGORIA, NOMBRE, TIPO, PRECIO_COMPRA, "
                       + "PRECIO_VENTA, ESTADO) VALUES (?,?,?,?,?,?)";
                colInventario = "ID_MATERIAL";
                break;
            case "ARREGLO":
                sqlEnt = "INSERT INTO dbo.ARREGLO (ID_CATEGORIA, NOMBRE, PRECIO_COMPRA, "
                       + "PRECIO_VENTA, ESTADO) VALUES (?,?,?,?,?)";
                colInventario = "ID_DETALLE";
                break;
            default:
                throw new IllegalArgumentException("Clase no válida: " + it.getClase());
        }

        try (Connection cn = ConexionDB.obtener()) {
            cn.setAutoCommit(false);
            try {
                int idEnt;
                try (PreparedStatement ps =
                        cn.prepareStatement(sqlEnt, Statement.RETURN_GENERATED_KEYS)) {
                    int i = 1;
                    if (it.getIdCategoria() > 0) {
                        ps.setInt(i++, it.getIdCategoria());
                    } else {
                        ps.setNull(i++, Types.INTEGER);
                    }
                    ps.setString(i++, it.getNombre());
                    if (!"ARREGLO".equals(it.getClase())) {
                        ps.setString(i++, it.getTipo());
                    }
                    ps.setBigDecimal(i++, it.getPrecioCompra());   // null si no aplica
                    ps.setBigDecimal(i++, it.getPrecioVenta());
                    ps.setBoolean(i++, it.isEstado());
                    if ("PRODUCTO".equals(it.getClase())) {
                        if (it.getIdMaterialVinc() > 0) {
                            ps.setInt(i++, it.getIdMaterialVinc());
                        } else {
                            ps.setNull(i, Types.INTEGER);
                        }
                    }
                    ps.executeUpdate();
                    try (ResultSet rs = ps.getGeneratedKeys()) {
                        if (!rs.next()) {
                            throw new SQLException("Sin id generado al crear el artículo");
                        }
                        idEnt = rs.getInt(1);
                    }
                }

                String sqlInv = "INSERT INTO dbo.INVENTARIO (" + colInventario
                              + ", STOCK_ACT, STOCK_MIN, TIPO_ALMACEN, UNIDAD_MEDIDA) "
                              + "VALUES (?,?,?,?,?)";
                try (PreparedStatement ps = cn.prepareStatement(sqlInv)) {
                    ps.setInt(1, idEnt);
                    ps.setInt(2, it.getStockAct());
                    ps.setInt(3, it.getStockMin());
                    ps.setString(4, it.getTipoAlmacen());
                    ps.setString(5, it.getUnidadMedida());
                    ps.executeUpdate();
                }
                cn.commit();
            } catch (SQLException | RuntimeException e) {
                cn.rollback();
                throw e;
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al crear el artículo", e);
        }
    }

    /**
     * Edición de artículo. Los precios y el estado SOLO se aplican si
     * `admin` es true: al empleado se le ignoran en el servidor aunque
     * mande los campos (decisión de negocio de la Fase 1).
     */
    public void actualizar(InventarioItem it, boolean admin) {
        final String sql;
        switch (it.getClase()) {
            case "PRODUCTO":
                sql = admin
                    ? "UPDATE dbo.PRODUCTO SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, "
                    + "PRECIO_COMPRA=?, PRECIO_VENTA=?, ESTADO=?, ID_MATERIAL=? WHERE ID_PRODUCTO=?"
                    : "UPDATE dbo.PRODUCTO SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, "
                    + "ID_MATERIAL=? WHERE ID_PRODUCTO=?";
                break;
            case "MATERIAL":
                sql = admin
                    ? "UPDATE dbo.MATERIAL SET ID_CATEGORIA=?, NOMBRE=?, TIPO=?, "
                    + "PRECIO_COMPRA=?, PRECIO_VENTA=?, ESTADO=? WHERE ID_MATERIAL=?"
                    : "UPDATE dbo.MATERIAL SET ID_CATEGORIA=?, NOMBRE=?, TIPO=? "
                    + "WHERE ID_MATERIAL=?";
                break;
            case "ARREGLO":
                sql = admin
                    ? "UPDATE dbo.ARREGLO SET ID_CATEGORIA=?, NOMBRE=?, PRECIO_COMPRA=?, "
                    + "PRECIO_VENTA=?, ESTADO=? WHERE ID_DETALLE=?"
                    : "UPDATE dbo.ARREGLO SET ID_CATEGORIA=?, NOMBRE=? WHERE ID_DETALLE=?";
                break;
            default:
                throw new IllegalArgumentException("Clase no válida: " + it.getClase());
        }

        try (Connection cn = ConexionDB.obtener()) {
            cn.setAutoCommit(false);
            try {
                try (PreparedStatement ps = cn.prepareStatement(sql)) {
                    int i = 1;
                    if (it.getIdCategoria() > 0) {
                        ps.setInt(i++, it.getIdCategoria());
                    } else {
                        ps.setNull(i++, Types.INTEGER);
                    }
                    ps.setString(i++, it.getNombre());
                    if (!"ARREGLO".equals(it.getClase())) {
                        ps.setString(i++, it.getTipo());
                    }
                    if (admin) {
                        ps.setBigDecimal(i++, it.getPrecioCompra());
                        ps.setBigDecimal(i++, it.getPrecioVenta());
                        ps.setBoolean(i++, it.isEstado());
                    }
                    if ("PRODUCTO".equals(it.getClase())) {
                        if (it.getIdMaterialVinc() > 0) {
                            ps.setInt(i++, it.getIdMaterialVinc());
                        } else {
                            ps.setNull(i++, Types.INTEGER);
                        }
                    }
                    ps.setInt(i, idEntidad(it));
                    ps.executeUpdate();
                }

                try (PreparedStatement ps = cn.prepareStatement(
                        "UPDATE dbo.INVENTARIO SET STOCK_MIN=?, UNIDAD_MEDIDA=?, "
                      + "TIPO_ALMACEN=? WHERE ID_INVENTARIO=?")) {
                    ps.setInt(1, it.getStockMin());
                    ps.setString(2, it.getUnidadMedida());
                    ps.setString(3, it.getTipoAlmacen());
                    ps.setInt(4, it.getIdInventario());
                    ps.executeUpdate();
                }
                cn.commit();
            } catch (SQLException | RuntimeException e) {
                cn.rollback();
                throw e;
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al editar el artículo", e);
        }
    }

    /**
     * Ajuste manual de stock (entrada o salida). Registra cabecera de
     * ajuste, su detalle, el movimiento de trazabilidad y actualiza
     * STOCK_ACT — todo en una transacción. Rechaza stock negativo.
     */
    public void ajustarStock(int idInventario, int delta, String motivo, int idUsuario) {
        if (delta == 0) {
            throw new IllegalArgumentException("La cantidad del ajuste no puede ser 0");
        }
        String tipoMov = delta > 0 ? "ENTRADA" : "SALIDA";
        int cantidad = Math.abs(delta);
        try (Connection cn = ConexionDB.obtener()) {
            cn.setAutoCommit(false);
            try {
                int idAjuste;
                try (PreparedStatement ps = cn.prepareStatement(
                        "INSERT INTO dbo.AJUSTE_INVENTARIO (ID_USUARIO, FECHA) VALUES (?, ?)",
                        Statement.RETURN_GENERATED_KEYS)) {
                    ps.setInt(1, idUsuario);
                    ps.setObject(2, java.time.LocalDateTime.now());
                    ps.executeUpdate();
                    try (ResultSet rs = ps.getGeneratedKeys()) {
                        if (!rs.next()) {
                            throw new SQLException("Sin id de ajuste");
                        }
                        idAjuste = rs.getInt(1);
                    }
                }
                try (PreparedStatement ps = cn.prepareStatement(
                        "INSERT INTO dbo.DETALLE_AJUSTE (ID_AJUSTE, ID_INVENTARIO, CANTIDAD, "
                      + "TIPO_AJUSTE, MOTIVO) VALUES (?,?,?,?,?)")) {
                    ps.setInt(1, idAjuste);
                    ps.setInt(2, idInventario);
                    ps.setInt(3, delta);
                    ps.setString(4, "MANUAL");
                    ps.setString(5, motivo);
                    ps.executeUpdate();
                }
                try (PreparedStatement ps = cn.prepareStatement(
                        "UPDATE dbo.INVENTARIO SET STOCK_ACT = STOCK_ACT + ? "
                      + "WHERE ID_INVENTARIO = ? AND STOCK_ACT + ? >= 0")) {
                    ps.setInt(1, delta);
                    ps.setInt(2, idInventario);
                    ps.setInt(3, delta);
                    if (ps.executeUpdate() == 0) {
                        throw new IllegalStateException(
                            "No hay stock suficiente para esa salida");
                    }
                }
                try (PreparedStatement ps = cn.prepareStatement(
                        "INSERT INTO dbo.MOVIMIENTO_INVENTARIO (ID_USUARIO, ID_INVENTARIO, "
                      + "FECHA_MOVIMIENTO, TIPO_MOVIMIENTO, CANTIDAD) VALUES (?,?,?,?,?)")) {
                    ps.setInt(1, idUsuario);
                    ps.setInt(2, idInventario);
                    ps.setObject(3, java.time.LocalDateTime.now());
                    ps.setString(4, tipoMov);
                    ps.setInt(5, cantidad);
                    ps.executeUpdate();
                }
                cn.commit();
            } catch (SQLException | RuntimeException e) {
                cn.rollback();
                throw e;
            }
        } catch (SQLException e) {
            throw new RuntimeException("Error al ajustar el stock", e);
        }
    }

    private int idEntidad(InventarioItem it) {
        switch (it.getClase()) {
            case "PRODUCTO": return it.getIdProducto();
            case "MATERIAL": return it.getIdMaterial();
            case "ARREGLO":  return it.getIdArreglo();
            default: throw new IllegalArgumentException("Clase no válida: " + it.getClase());
        }
    }
}
