package controlador;

import dao.TerminoDAO;
import java.io.IOException;
import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import modelo.Usuario;

/** Administra palabras de búsqueda y sus artículos asociados. */
@WebServlet(name = "TerminoServlet", urlPatterns = {"/terminos"})
public class TerminoServlet extends HttpServlet {

    private final TerminoDAO terminoDAO = new TerminoDAO();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {
        cargarVista(req);
        req.getRequestDispatcher("/WEB-INF/vistas/terminos.jsp").forward(req, resp);
    }

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {
        req.setCharacterEncoding("UTF-8");
        try {
            String accion = req.getParameter("accion");
            if ("crear".equals(accion)) {
                crear(req);
            } else if ("asociar".equals(accion)) {
                terminoDAO.asociar(entero(req.getParameter("idTermino")),
                        entero(req.getParameter("idInventario")));
            } else if ("desasociar".equals(accion)) {
                terminoDAO.desasociar(entero(req.getParameter("idTermino")),
                        entero(req.getParameter("idInventario")));
            } else {
                throw new IllegalArgumentException("Acción no válida.");
            }
            req.getSession().setAttribute("terminoMensaje", "Cambios guardados.");
            resp.sendRedirect(req.getContextPath() + "/terminos");
        } catch (IllegalArgumentException e) {
            cargarVista(req);
            req.setAttribute("error", e.getMessage());
            req.getRequestDispatcher("/WEB-INF/vistas/terminos.jsp").forward(req, resp);
        } catch (RuntimeException e) {
            getServletContext().log("Error en el módulo de términos", e);
            cargarVista(req);
            req.setAttribute("error", "No pudimos guardar el cambio. Intenta de nuevo.");
            req.getRequestDispatcher("/WEB-INF/vistas/terminos.jsp").forward(req, resp);
        }
    }

    private void crear(HttpServletRequest req) {
        String palabra = req.getParameter("palabra");
        if (palabra == null || palabra.trim().isEmpty() || palabra.trim().length() > 75) {
            throw new IllegalArgumentException(
                    "El término es obligatorio y puede tener máximo 75 caracteres.");
        }
        Usuario usuario = (Usuario) req.getSession().getAttribute("usuario");
        terminoDAO.crear(palabra.trim(), usuario.getId());
    }

    private void cargarVista(HttpServletRequest req) {
        String mensaje = (String) req.getSession().getAttribute("terminoMensaje");
        req.getSession().removeAttribute("terminoMensaje");
        req.setAttribute("mensaje", mensaje);
        req.setAttribute("terminos", terminoDAO.listar());
        req.setAttribute("inventario", terminoDAO.listarInventarioActivo());
        req.setAttribute("titulo", "Términos de búsqueda");
    }

    private int entero(String valor) {
        try {
            int numero = Integer.parseInt(valor);
            if (numero <= 0) {
                throw new NumberFormatException();
            }
            return numero;
        } catch (NumberFormatException e) {
            throw new IllegalArgumentException("Selecciona valores válidos.");
        }
    }
}
