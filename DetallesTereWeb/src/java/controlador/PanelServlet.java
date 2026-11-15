package controlador;

import java.io.IOException;
import java.time.LocalTime;
import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import modelo.Usuario;

/**
 * Panel de inicio (dashboard). Saludo según el momento del día.
 * El rol define qué ve: admin ve reportes del día; empleado solo saludo + accesos.
 */
@WebServlet(name = "PanelServlet", urlPatterns = {"/panel"})
public class PanelServlet extends HttpServlet {

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {

        Usuario u = (Usuario) req.getSession().getAttribute("usuario");
        req.setAttribute("titulo", "Inicio");
        req.setAttribute("saludo", saludoPorHorario());
        req.setAttribute("usuario", u);
        req.getRequestDispatcher("/WEB-INF/vistas/panel.jsp").forward(req, resp);
    }

    private String saludoPorHorario() {
        int hora = LocalTime.now().getHour();
        if (hora < 12) {
            return "Buenos días";
        }
        if (hora < 19) {
            return "Buenas tardes";
        }
        return "Buenas noches";
    }
}
