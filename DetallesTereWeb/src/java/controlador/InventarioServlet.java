package controlador;

import java.io.IOException;
import java.util.List;
import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import dao.InventarioDAO;
import modelo.InventarioItem;
import modelo.Usuario;

/**
 * Controlador de Inventario (solo lectura en Fase 2).
 * Empleado ve nombre, categoría y precio de venta.
 * Admin también ve el precio de costo.
 * El filtrado se aplica AQUÍ (no solo en la vista): al empleado se le
 * anula el precio de costo antes de pasar los datos a la página.
 */
@WebServlet(name = "InventarioServlet", urlPatterns = {"/inventario"})
public class InventarioServlet extends HttpServlet {

    private final InventarioDAO inventarioDAO = new InventarioDAO();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {

        Usuario u = (Usuario) req.getSession().getAttribute("usuario");
        String q = req.getParameter("q");

        List<InventarioItem> items = inventarioDAO.listar(q);

        // Seguridad por rol: al empleado no se le entrega el precio de costo
        if (!u.isAdmin()) {
            for (InventarioItem it : items) {
                it.setPrecioCompra(null);
            }
        }

        req.setAttribute("titulo", "Inventario");
        req.setAttribute("items", items);
        req.setAttribute("q", q == null ? "" : q);
        req.setAttribute("total", items.size());

        req.getRequestDispatcher("/WEB-INF/vistas/inventario.jsp").forward(req, resp);
    }
}
