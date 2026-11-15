package filtro;

import javax.servlet.ServletContextEvent;
import javax.servlet.ServletContextListener;
import javax.servlet.annotation.WebListener;
import dao.VentaDAO;

/**
 * Limpieza automática de cotizaciones: se ejecuta cada 15 minutos y borra
 * las cotizaciones vencidas (más de 24 h). La lista que ve el usuario ya
 * filtra por vencimiento, pero aquí se eliminan físicamente de la BD.
 */
@WebListener
public class LimpiezaCotizacionesListener implements ServletContextListener {

    private static final long RETRASO_INICIAL_MS = 30_000;   // 30 s
    private static final long INTERVALO_MS = 15 * 60_000;     // 15 min

    private final VentaDAO ventaDAO = new VentaDAO();
    private java.util.Timer timer;

    @Override
    public void contextInitialized(ServletContextEvent sce) {
        timer = new java.util.Timer("limpieza-cotizaciones", true);
        timer.scheduleAtFixedRate(new java.util.TimerTask() {
            @Override
            public void run() {
                try {
                    int n = ventaDAO.eliminarExpiradas();
                    if (n > 0) {
                        sce.getServletContext().log(
                                "Cotizaciones vencidas eliminadas: " + n);
                    }
                } catch (Exception e) {
                    sce.getServletContext().log(
                            "Error al limpiar cotizaciones: " + e.getMessage());
                }
            }
        }, RETRASO_INICIAL_MS, INTERVALO_MS);
    }

    @Override
    public void contextDestroyed(ServletContextEvent sce) {
        if (timer != null) {
            timer.cancel();
        }
    }
}
