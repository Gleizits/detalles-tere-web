package filtro;

import java.io.IOException;
import javax.servlet.Filter;
import javax.servlet.FilterChain;
import javax.servlet.FilterConfig;
import javax.servlet.ServletException;
import javax.servlet.ServletRequest;
import javax.servlet.ServletResponse;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import modelo.Usuario;

/**
 * Restringe a administradores los módulos con costos y configuración.
 * Declarado en web.xml (sin @WebFilter) para ejecutarse DESPUÉS del filtro
 * de autenticación: así un usuario sin sesión es redirigido al login y uno
 * autenticado sin rol admin recibe 403.
 */
public class RequiereAdministradorFilter implements Filter {

    @Override
    public void doFilter(ServletRequest request, ServletResponse response,
            FilterChain chain) throws IOException, ServletException {
        HttpServletRequest httpRequest = (HttpServletRequest) request;
        HttpServletResponse httpResponse = (HttpServletResponse) response;
        HttpSession session = httpRequest.getSession(false);
        HttpSessionUser sessionUser = new HttpSessionUser(session);

        if (sessionUser.isAdmin()) {
            chain.doFilter(request, response);
        } else {
            httpResponse.sendError(HttpServletResponse.SC_FORBIDDEN,
                    "Esta sección requiere permisos de administrador.");
        }
    }

    @Override
    public void init(FilterConfig filterConfig) {
    }

    @Override
    public void destroy() {
    }

    /**
     * Mantiene la lectura de sesión en un objeto pequeño para que la regla
     * de autorización sea visible y no se mezcle con el flujo del filtro.
     */
    private static final class HttpSessionUser {

        private final Usuario usuario;

        private HttpSessionUser(HttpSession session) {
            this.usuario = session == null
                    ? null : (Usuario) session.getAttribute("usuario");
        }

        private boolean isAdmin() {
            return usuario != null && usuario.isAdmin();
        }
    }
}
