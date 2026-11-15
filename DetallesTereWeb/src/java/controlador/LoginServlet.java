package controlador;

import java.io.IOException;
import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import modelo.Usuario;
import dao.UsuarioDAO;

/**
 * Controlador de autenticación. Muestra y procesa el login.
 */
@WebServlet(name = "LoginServlet", urlPatterns = {"/login"})
public class LoginServlet extends HttpServlet {

    private final UsuarioDAO usuarioDAO = new UsuarioDAO();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {
        // Si ya tiene sesión, ir al panel
        HttpSession sesion = req.getSession(false);
        if (sesion != null && sesion.getAttribute("usuario") instanceof Usuario) {
            resp.sendRedirect(req.getContextPath() + "/panel");
            return;
        }
        req.getRequestDispatcher("/WEB-INF/vistas/login.jsp").forward(req, resp);
    }

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {
        req.setCharacterEncoding("UTF-8");
        String username = req.getParameter("username");
        String password = req.getParameter("password");
        String destino = req.getParameter("destino");

        Usuario u = usuarioDAO.login(username, password);
        if (u == null) {
            req.setAttribute("error", "Usuario o contraseña incorrectos.");
            req.setAttribute("username", username == null ? "" : username);
            req.getRequestDispatcher("/WEB-INF/vistas/login.jsp").forward(req, resp);
            return;
        }

        HttpSession sesion = req.getSession(true);
        sesion.setAttribute("usuario", u);

        // Ir a la ruta pedida o al panel
        if (destino != null && !destino.isEmpty() && destino.startsWith("/")
                && !destino.startsWith("//")) {
            resp.sendRedirect(req.getContextPath() + destino);
        } else {
            resp.sendRedirect(req.getContextPath() + "/panel");
        }
    }
}
