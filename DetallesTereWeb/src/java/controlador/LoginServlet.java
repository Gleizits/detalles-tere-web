package controlador;

import java.io.IOException;
import java.util.concurrent.ConcurrentHashMap;
import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import modelo.Usuario;
import dao.UsuarioDAO;
import filtro.CsrfFilter;

/**
 * Controlador de autenticación. Muestra y procesa el login.
 *
 * Protecciones de la Fase 7:
 *  - Límite de intentos fallidos (5 en 15 minutos por usuario+IP) para
 *    frenar fuerza bruta; el bloqueo se limpia al iniciar sesión.
 *  - Rotación del token CSRF tras autenticarse (session fixation).
 *  - Destino validado contra una ruta conocida (anti open-redirect).
 */
@WebServlet(name = "LoginServlet", urlPatterns = {"/login"})
public class LoginServlet extends HttpServlet {

    private final UsuarioDAO usuarioDAO = new UsuarioDAO();

    /** Intentos fallidos permitidos antes de bloquear temporalmente. */
    static final int MAX_INTENTOS = 5;
    /** Ventana de tiempo del bloqueo, en milisegundos (15 min). */
    static final long VENTANA_MS = 15 * 60 * 1000L;

    /**
     * Intentos fallidos por "usuario|ip". Se purgan solos al consultar:
     * solo se conserva lo caído dentro de la ventana.
     */
    private static final ConcurrentHashMap<String, long[]> INTENTOS =
            new ConcurrentHashMap<>();

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
        String clave = claveIntento(req, username);

        if (bloqueado(clave)) {
            req.setAttribute("error", "Demasiados intentos fallidos. "
                    + "Vuelve a intentarlo en unos minutos.");
            req.setAttribute("username", username == null ? "" : username);
            req.getRequestDispatcher("/WEB-INF/vistas/login.jsp").forward(req, resp);
            return;
        }

        Usuario u = usuarioDAO.login(username, password);
        if (u == null) {
            registrarFallo(clave);
            req.setAttribute("error", "Usuario o contraseña incorrectos.");
            req.setAttribute("username", username == null ? "" : username);
            req.getRequestDispatcher("/WEB-INF/vistas/login.jsp").forward(req, resp);
            return;
        }

        INTENTOS.remove(clave);

        HttpSession sesion = req.getSession(true);
        req.changeSessionId();                       // anti session fixation
        sesion.setAttribute("usuario", u);
        CsrfFilter.rotar(sesion);                    // token nuevo tras el login

        // Ir a la ruta pedida o al panel (solo destinos internos válidos)
        if (destinoValido(destino)) {
            resp.sendRedirect(req.getContextPath() + destino);
        } else {
            resp.sendRedirect(req.getContextPath() + "/panel");
        }
    }

    // ------------------------------------------------------------------
    // Límite de intentos
    // ------------------------------------------------------------------
    private String claveIntento(HttpServletRequest req, String username) {
        String usuario = username == null ? "" : username.trim().toLowerCase();
        return usuario + "|" + req.getRemoteAddr();
    }

    private boolean bloqueado(String clave) {
        long[] registro = INTENTOS.get(clave);
        if (registro == null) {
            return false;
        }
        long edad = System.currentTimeMillis() - registro[1];
        if (registro[0] >= MAX_INTENTOS && edad < VENTANA_MS) {
            return true;
        }
        // Fuera de la ventana: se limpia el conteo
        if (edad >= VENTANA_MS) {
            INTENTOS.remove(clave, registro);
        }
        return false;
    }

    private void registrarFallo(String clave) {
        long ahora = System.currentTimeMillis();
        INTENTOS.compute(clave, (k, registro) -> {
            if (registro == null || ahora - registro[1] >= VENTANA_MS) {
                return new long[]{1, ahora};
            }
            return new long[]{registro[0] + 1, registro[1]};
        });
    }

    // ------------------------------------------------------------------
    // Destino: solo rutas internas conocidas del sistema
    // ------------------------------------------------------------------
    private static final String[] RUTAS_PERMITIDAS = {
        "/panel", "/caja", "/venta", "/pedidos", "/inventario",
        "/reportes", "/usuarios", "/ingresos", "/terminos"
    };

    private boolean destinoValido(String destino) {
        if (destino == null || destino.isEmpty() || !destino.startsWith("/")) {
            return false;
        }
        // Sin "//" (protocolo relativo), sin ":" ni "\" (variantes de open
        // redirect) y sin saltos de ruta.
        if (destino.startsWith("//") || destino.contains(":")
                || destino.contains("\\") || destino.contains("..")) {
            return false;
        }
        String ruta = destino.split("\\?", 2)[0];
        for (String permitida : RUTAS_PERMITIDAS) {
            if (ruta.equals(permitida) || ruta.startsWith(permitida + "/")) {
                return true;
            }
        }
        return false;
    }
}
