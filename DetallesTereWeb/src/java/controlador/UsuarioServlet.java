package controlador;

import dao.UsuarioDAO;
import java.io.IOException;
import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import modelo.Usuario;

/** Alta, edición y baja lógica de usuarios para el administrador. */
@WebServlet(name = "UsuarioServlet", urlPatterns = {"/usuarios"})
public class UsuarioServlet extends HttpServlet {

    private final UsuarioDAO usuarioDAO = new UsuarioDAO();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {
        // Defensa en profundidad: además del filtro de admin (web.xml)
        if (!esAdministrador(req)) {
            resp.sendError(HttpServletResponse.SC_FORBIDDEN,
                    "Esta sección requiere permisos de administrador.");
            return;
        }
        cargarVista(req);
        String idEditar = req.getParameter("editar");
        if (idEditar != null) {
            try {
                int id = Integer.parseInt(idEditar);
                for (Usuario usuario : usuarioDAO.listar()) {
                    if (usuario.getId() == id) {
                        req.setAttribute("usuarioEditar", usuario);
                        break;
                    }
                }
            } catch (NumberFormatException e) {
                req.setAttribute("error", "El usuario solicitado no es válido.");
            }
        }
        req.setAttribute("nuevo", req.getParameter("nuevo") != null);
        req.getRequestDispatcher("/WEB-INF/vistas/usuarios.jsp").forward(req, resp);
    }

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {
        if (!esAdministrador(req)) {
            resp.sendError(HttpServletResponse.SC_FORBIDDEN,
                    "Esta sección requiere permisos de administrador.");
            return;
        }
        req.setCharacterEncoding("UTF-8");
        try {
            String accion = req.getParameter("accion");
            if ("crear".equals(accion)) {
                usuarioDAO.crear(leerUsuario(req, false), password(req, true));
            } else if ("editar".equals(accion)) {
                Usuario usuario = leerUsuario(req, true);
                protegerSesionPropia(req, usuario);
                usuarioDAO.actualizar(usuario, password(req, false));
                actualizarSesionSiCorresponde(req, usuario);
            } else {
                throw new IllegalArgumentException("Acción no válida.");
            }
            req.getSession().setAttribute("usuarioMensaje", "Usuario guardado.");
            resp.sendRedirect(req.getContextPath() + "/usuarios");
        } catch (IllegalArgumentException e) {
            cargarVista(req);
            req.setAttribute("error", e.getMessage());
            req.getRequestDispatcher("/WEB-INF/vistas/usuarios.jsp").forward(req, resp);
        } catch (RuntimeException e) {
            getServletContext().log("Error en el módulo de usuarios", e);
            cargarVista(req);
            req.setAttribute("error", "No pudimos guardar el usuario. Intenta de nuevo.");
            req.getRequestDispatcher("/WEB-INF/vistas/usuarios.jsp").forward(req, resp);
        }
    }

    private boolean esAdministrador(HttpServletRequest req) {
        HttpSession sesion = req.getSession(false);
        return sesion != null && sesion.getAttribute("usuario") instanceof Usuario
                && ((Usuario) sesion.getAttribute("usuario")).isAdmin();
    }

    private Usuario leerUsuario(HttpServletRequest req, boolean edicion) {
        Usuario usuario = new Usuario();
        if (edicion) {
            usuario.setId(entero(req.getParameter("id")));
        }
        usuario.setUsername(obligatorio(req.getParameter("username"), 50, "Indica el usuario."));
        usuario.setNombre(obligatorio(req.getParameter("nombre"), 50, "Indica el nombre."));
        usuario.setApellido(obligatorio(req.getParameter("apellido"), 50,
                "Indica el apellido."));
        usuario.setCorreo(opcional(req.getParameter("correo"), 75));
        String rol = obligatorio(req.getParameter("rol"), 12, "Selecciona un rol.");
        if (!"admin".equals(rol) && !"empleado".equals(rol)) {
            throw new IllegalArgumentException("El rol seleccionado no es válido.");
        }
        usuario.setRol(rol);
        usuario.setEstado(!edicion || "on".equals(req.getParameter("estado")));
        return usuario;
    }

    private String password(HttpServletRequest req, boolean obligatorio) {
        String password = req.getParameter("password");
        if (password == null || password.isEmpty()) {
            if (obligatorio) {
                throw new IllegalArgumentException("Indica una contraseña.");
            }
            return null;
        }
        if (password.length() < 6 || password.length() > 100) {
            throw new IllegalArgumentException(
                    "La contraseña debe tener entre 6 y 100 caracteres.");
        }
        return password;
    }

    private void protegerSesionPropia(HttpServletRequest req, Usuario usuario) {
        Usuario actual = (Usuario) req.getSession().getAttribute("usuario");
        if (actual.getId() == usuario.getId() && !usuario.isEstado()) {
            throw new IllegalArgumentException("No puedes desactivar tu propio usuario.");
        }
    }

    private void actualizarSesionSiCorresponde(HttpServletRequest req, Usuario actualizado) {
        HttpSession session = req.getSession();
        Usuario actual = (Usuario) session.getAttribute("usuario");
        if (actual.getId() == actualizado.getId()) {
            session.setAttribute("usuario", actualizado);
        }
    }

    private void cargarVista(HttpServletRequest req) {
        String mensaje = (String) req.getSession().getAttribute("usuarioMensaje");
        req.getSession().removeAttribute("usuarioMensaje");
        req.setAttribute("mensaje", mensaje);
        req.setAttribute("usuarios", usuarioDAO.listar());
        req.setAttribute("titulo", "Usuarios");
    }

    private int entero(String valor) {
        try {
            int numero = Integer.parseInt(valor);
            if (numero <= 0) {
                throw new NumberFormatException();
            }
            return numero;
        } catch (NumberFormatException e) {
            throw new IllegalArgumentException("Selecciona un usuario válido.");
        }
    }

    private String obligatorio(String valor, int maximo, String mensaje) {
        String resultado = opcional(valor, maximo);
        if (resultado.isEmpty()) {
            throw new IllegalArgumentException(mensaje);
        }
        return resultado;
    }

    private String opcional(String valor, int maximo) {
        String resultado = valor == null ? "" : valor.trim();
        return resultado.length() > maximo ? resultado.substring(0, maximo) : resultado;
    }
}
