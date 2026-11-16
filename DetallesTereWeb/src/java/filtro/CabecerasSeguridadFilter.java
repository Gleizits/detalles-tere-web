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

/**
 * Agrega cabeceras de seguridad a TODAS las respuestas:
 *
 *  - X-Content-Type-Options: nosniff  → impide que el navegador "adivine"
 *    el tipo de contenido (mitiga ataques de MIME-sniffing).
 *  - X-Frame-Options: DENY            → impide que el sitio se cargue dentro
 *    de un iframe (anti-clickjacking).
 *  - Referrer-Policy                  → no filtra la URL completa a sitios
 *    externos.
 *  - Permissions-Policy               → desactiva cámara, micrófono y
 *    geolocalización en este sitio.
 *  - Content-Security-Policy          → solo permite recursos propios; los
 *    estilos y scripts en línea de los JSP se autorizan explícitamente porque
 *    la interfaz los usa, pero queda bloqueado cualquier origen externo.
 *
 * Es el PRIMER filtro de la cadena (ver web.xml) para que las cabeceras
 * también queden en las respuestas de error y de redirección.
 */
public class CabecerasSeguridadFilter implements Filter {

    private static final String CSP = String.join("; ",
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'");

    @Override
    public void init(FilterConfig filterConfig) throws ServletException {
    }

    @Override
    public void doFilter(ServletRequest request, ServletResponse response,
            FilterChain chain) throws IOException, ServletException {

        HttpServletRequest req = (HttpServletRequest) request;
        HttpServletResponse resp = (HttpServletResponse) response;

        resp.setHeader("X-Content-Type-Options", "nosniff");
        resp.setHeader("X-Frame-Options", "DENY");
        resp.setHeader("Referrer-Policy", "strict-origin-when-cross-origin");
        resp.setHeader("Permissions-Policy",
                "geolocation=(), microphone=(), camera=(), payment=()");
        resp.setHeader("Content-Security-Policy", CSP);

        // Los datos del negocio no deben quedar en la caché del navegador.
        String ruta = req.getRequestURI().substring(req.getContextPath().length());
        if (!ruta.startsWith("/assets/")) {
            resp.setHeader("Cache-Control", "no-store, no-cache, must-revalidate");
            resp.setHeader("Pragma", "no-cache");
        }

        chain.doFilter(request, response);
    }

    @Override
    public void destroy() {
    }
}
