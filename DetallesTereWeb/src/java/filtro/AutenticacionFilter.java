package filtro;

import java.io.IOException;
import javax.servlet.Filter;
import javax.servlet.FilterChain;
import javax.servlet.FilterConfig;
import javax.servlet.ServletException;
import javax.servlet.ServletRequest;
import javax.servlet.ServletResponse;
import javax.servlet.annotation.WebFilter;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import modelo.Usuario;

/**
 * Filtro de autenticación. Protege todas las rutas salvo /login, /error
 * y los recursos estáticos (assets). Redirige al login si no hay sesión.
 */
@WebFilter("/*")
public class AutenticacionFilter implements Filter {

    @Override
    public void init(FilterConfig filterConfig) throws ServletException {
    }

    @Override
    public void doFilter(ServletRequest req, ServletResponse res, FilterChain chain)
            throws IOException, ServletException {

        HttpServletRequest http = (HttpServletRequest) req;
        HttpServletResponse resp = (HttpServletResponse) res;

        String ruta = http.getRequestURI().substring(http.getContextPath().length());

        if (esPublica(ruta) || estaAutenticado(http)) {
            chain.doFilter(req, res);
            return;
        }

        // Guardar la ruta pedida para volver tras el login
        String q = http.getQueryString();
        String destino = ruta + (q != null ? "?" + q : "");
        resp.sendRedirect(http.getContextPath() + "/login?destino="
                + java.net.URLEncoder.encode(destino, "UTF-8"));
    }

    private boolean esPublica(String ruta) {
        return ruta.equals("/login")
                || ruta.equals("/error.jsp")
                || ruta.equals("/index.jsp")
                || ruta.startsWith("/assets/");
    }

    private boolean estaAutenticado(HttpServletRequest http) {
        HttpSession sesion = http.getSession(false);
        return sesion != null && sesion.getAttribute("usuario") instanceof Usuario;
    }

    @Override
    public void destroy() {
    }
}
