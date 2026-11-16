package controlador;

import dao.ReporteDAO;
import java.io.IOException;
import java.time.LocalDate;
import java.time.YearMonth;
import java.time.format.DateTimeParseException;
import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import modelo.ReporteResumen;
import util.ReportePdf;

/** Presenta y exporta los reportes diario y mensual para administradores. */
@WebServlet(name = "ReporteServlet", urlPatterns = {"/reportes"})
public class ReporteServlet extends HttpServlet {

    private final ReporteDAO reporteDAO = new ReporteDAO();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {
        try {
            Periodo periodo = periodo(req);
            ReporteResumen reporte = reporteDAO.generar(periodo.desde, periodo.hasta);
            if ("pdf".equalsIgnoreCase(req.getParameter("formato"))) {
                resp.setContentType("application/pdf");
                resp.setHeader("Content-Disposition",
                        "attachment; filename=reporte-detallestere.pdf");
                ReportePdf.escribir(reporte, resp.getOutputStream());
                return;
            }
            req.setAttribute("reporte", reporte);
            req.setAttribute("tipoPeriodo", periodo.tipo);
            req.setAttribute("valorPeriodo", periodo.valor);
            req.setAttribute("titulo", "Reportes");
            req.getRequestDispatcher("/WEB-INF/vistas/reportes.jsp").forward(req, resp);
        } catch (IllegalArgumentException e) {
            req.setAttribute("error", e.getMessage());
            req.setAttribute("titulo", "Reportes");
            req.getRequestDispatcher("/WEB-INF/vistas/reportes.jsp").forward(req, resp);
        } catch (RuntimeException e) {
            getServletContext().log("Error al generar reporte", e);
            throw new ServletException("No se pudo generar el reporte", e);
        }
    }

    private Periodo periodo(HttpServletRequest req) {
        String tipo = "mes".equals(req.getParameter("tipo")) ? "mes" : "dia";
        String valor = req.getParameter("periodo");
        if (valor == null || valor.trim().isEmpty()) {
            valor = "mes".equals(tipo) ? YearMonth.now().toString() : LocalDate.now().toString();
        }
        try {
            if ("mes".equals(tipo)) {
                YearMonth mes = YearMonth.parse(valor);
                return new Periodo(mes.atDay(1), mes.atEndOfMonth(), tipo, valor);
            }
            LocalDate dia = LocalDate.parse(valor);
            return new Periodo(dia, dia, tipo, valor);
        } catch (DateTimeParseException e) {
            throw new IllegalArgumentException("Selecciona un periodo válido.");
        }
    }

    private static final class Periodo {

        private final LocalDate desde;
        private final LocalDate hasta;
        private final String tipo;
        private final String valor;

        private Periodo(LocalDate desde, LocalDate hasta, String tipo, String valor) {
            this.desde = desde;
            this.hasta = hasta;
            this.tipo = tipo;
            this.valor = valor;
        }
    }
}
