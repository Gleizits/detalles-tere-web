package controlador;

import java.io.IOException;
import java.math.BigDecimal;
import java.util.List;
import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import dao.CajaDAO;
import modelo.Caja;
import modelo.Usuario;

/**
 * Controlador de Caja. Apertura y cierre manual (ambos roles).
 * GET muestra estado (abierta / cerrada). POST abre o cierra.
 */
@WebServlet(name = "CajaServlet", urlPatterns = {"/caja"})
public class CajaServlet extends HttpServlet {

    private final CajaDAO cajaDAO = new CajaDAO();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {

        Usuario u = (Usuario) req.getSession().getAttribute("usuario");
        Caja abierta = cajaDAO.cajaAbierta(u.getId());

        req.setAttribute("titulo", "Caja");
        req.setAttribute("caja", abierta); // null si no hay abierta

        if (abierta != null) {
            // Monto esperado al cierre: inicial + ventas en efectivo (productos + reservas)
            BigDecimal ventasEfectivo = cajaDAO.ventasEfectivo(abierta.getIdCaja());
            BigDecimal esperado = abierta.getMontoInicial().add(ventasEfectivo);
            req.setAttribute("ventasEfectivo", ventasEfectivo);
            req.setAttribute("esperado", esperado);
            req.setAttribute("ventasProductos", cajaDAO.ventasProductosEfectivo(abierta.getIdCaja()));
            req.setAttribute("pagosReservas", cajaDAO.pagosReservasEfectivo(abierta.getIdCaja()));
        }

        // Historial reciente (útil para el reporte del día)
        List<Caja> historial = cajaDAO.historial(u.getId(), 7);
        req.setAttribute("historial", historial);

        req.getRequestDispatcher("/WEB-INF/vistas/caja.jsp").forward(req, resp);
    }

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {
        req.setCharacterEncoding("UTF-8");

        Usuario u = (Usuario) req.getSession().getAttribute("usuario");
        String accion = req.getParameter("accion");

        if ("abrir".equals(accion)) {
            abrir(req, resp, u);
        } else if ("cerrar".equals(accion)) {
            cerrar(req, resp, u);
        } else {
            resp.sendRedirect(req.getContextPath() + "/caja");
        }
    }

    private void abrir(HttpServletRequest req, HttpServletResponse resp, Usuario u)
            throws ServletException, IOException {
        String montoTxt = req.getParameter("montoInicial");
        try {
            BigDecimal monto = new BigDecimal(montoTxt.trim());
            if (monto.compareTo(BigDecimal.ZERO) < 0 || monto.compareTo(new BigDecimal("1000000")) > 0) {
                req.setAttribute("error", "El monto inicial debe estar entre 0 y 1,000,000.00.");
                reenviarEstado(req, resp, u);
                return;
            }
            if (cajaDAO.cajaAbierta(u.getId()) != null) {
                req.setAttribute("error", "Ya tienes una caja abierta.");
                reenviarEstado(req, resp, u);
                return;
            }
            cajaDAO.abrir(u.getId(), monto);
        } catch (NumberFormatException e) {
            req.setAttribute("error", "Ingresa un monto válido.");
            reenviarEstado(req, resp, u);
        } catch (Exception e) {
            throw new ServletException("No se pudo abrir la caja", e);
        }
        resp.sendRedirect(req.getContextPath() + "/caja");
    }

    private void cerrar(HttpServletRequest req, HttpServletResponse resp, Usuario u)
            throws ServletException, IOException {
        String idTxt = req.getParameter("idCaja");
        String realTxt = req.getParameter("montoReal");
        try {
            int idCaja = Integer.parseInt(idTxt);
            BigDecimal montoReal = new BigDecimal(realTxt.trim());
            if (montoReal.compareTo(BigDecimal.ZERO) < 0 || montoReal.compareTo(new BigDecimal("1000000")) > 0) {
                req.setAttribute("error", "El monto contado debe estar entre 0 y 1,000,000.00.");
                reenviarEstado(req, resp, u);
                return;
            }
            Caja abierta = cajaDAO.cajaAbierta(u.getId());
            if (abierta == null || abierta.getIdCaja() != idCaja) {
                resp.sendRedirect(req.getContextPath() + "/caja");
                return;
            }
            BigDecimal ventasEfectivo = cajaDAO.ventasEfectivo(idCaja);
            BigDecimal montoFinal = abierta.getMontoInicial().add(ventasEfectivo);
            abierta.setMontoFinal(montoFinal);
            abierta.setMontoReal(montoReal);
            cajaDAO.cerrar(abierta);
            req.setAttribute("resumen", abierta);
            reenviarEstado(req, resp, u);
        } catch (NumberFormatException e) {
            req.setAttribute("error", "Ingresa un monto válido.");
            reenviarEstado(req, resp, u);
        } catch (Exception e) {
            throw new ServletException("No se pudo cerrar la caja", e);
        }
    }

    /** Re-renderiza la vista de caja conservando el estado actual. */
    private void reenviarEstado(HttpServletRequest req, HttpServletResponse resp, Usuario u)
            throws ServletException, IOException {
        Caja abierta = cajaDAO.cajaAbierta(u.getId());
        req.setAttribute("titulo", "Caja");
        req.setAttribute("caja", abierta);
        if (abierta != null) {
            BigDecimal ventasEfectivo = cajaDAO.ventasEfectivo(abierta.getIdCaja());
            req.setAttribute("ventasEfectivo", ventasEfectivo);
            req.setAttribute("esperado", abierta.getMontoInicial().add(ventasEfectivo));
            req.setAttribute("ventasProductos", cajaDAO.ventasProductosEfectivo(abierta.getIdCaja()));
            req.setAttribute("pagosReservas", cajaDAO.pagosReservasEfectivo(abierta.getIdCaja()));
        }
        req.setAttribute("historial", cajaDAO.historial(u.getId(), 7));
        req.getRequestDispatcher("/WEB-INF/vistas/caja.jsp").forward(req, resp);
    }
}
