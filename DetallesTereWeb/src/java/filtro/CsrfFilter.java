package filtro;

import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.security.SecureRandom;
import java.util.Base64;
import javax.servlet.Filter;
import javax.servlet.FilterChain;
import javax.servlet.ServletException;
import javax.servlet.ServletRequest;
import javax.servlet.ServletResponse;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;

/**
 * Protege las peticiones que cambian estado contra solicitudes cross-site.
 * Declarado en web.xml (sin @WebFilter) para fijar su lugar en la cadena.
 */
public class CsrfFilter implements Filter {

    public static final String REQUEST_TOKEN = "csrfToken";
    private static final String SESSION_TOKEN = "csrfToken";
    private static final String PARAMETER = "csrfToken";
    private static final SecureRandom RANDOM = new SecureRandom();

    @Override
    public void init(javax.servlet.FilterConfig filterConfig) {
        // No requiere configuración externa.
    }

    @Override
    public void destroy() {
        // No mantiene recursos.
    }

    @Override
    public void doFilter(ServletRequest request, ServletResponse response,
                         FilterChain chain) throws IOException, ServletException {
        HttpServletRequest req = (HttpServletRequest) request;
        HttpServletResponse resp = (HttpServletResponse) response;

        // Los assets estáticos no llevan formularios: no crear sesión para
        // ellos (antes cada petición de CSS/JS generaba una sesión huérfana).
        String ruta = req.getRequestURI().substring(req.getContextPath().length());
        if (ruta.startsWith("/assets/")) {
            chain.doFilter(request, response);
            return;
        }

        HttpSession session = req.getSession(true);
        String token = token(session);
        req.setAttribute(REQUEST_TOKEN, token);

        if ("POST".equalsIgnoreCase(req.getMethod())) {
            String recibido = req.getParameter(PARAMETER);
            if (recibido == null || !coincide(token, recibido)) {
                resp.sendError(HttpServletResponse.SC_FORBIDDEN,
                        "Token de seguridad inválido o ausente.");
                return;
            }
        }
        chain.doFilter(request, response);
    }

    /** Descarta el token de la sesión para que se emita uno nuevo. */
    public static void rotar(HttpSession session) {
        if (session != null) {
            session.removeAttribute(SESSION_TOKEN);
        }
    }

    private String token(HttpSession session) {
        Object existente = session.getAttribute(SESSION_TOKEN);
        if (existente instanceof String && !((String) existente).isEmpty()) {
            return (String) existente;
        }
        byte[] bytes = new byte[32];
        RANDOM.nextBytes(bytes);
        String nuevo = Base64.getUrlEncoder().withoutPadding().encodeToString(bytes);
        session.setAttribute(SESSION_TOKEN, nuevo);
        return nuevo;
    }

    private boolean coincide(String esperado, String recibido) {
        return MessageDigest.isEqual(
                esperado.getBytes(StandardCharsets.UTF_8),
                recibido.getBytes(StandardCharsets.UTF_8));
    }
}
