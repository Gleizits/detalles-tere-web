package controlador;

import java.io.IOException;
import java.math.BigDecimal;
import java.util.ArrayList;
import java.util.List;
import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import dao.CajaDAO;
import dao.InventarioDAO;
import dao.VentaDAO;
import modelo.Caja;
import modelo.DetalleVenta;
import modelo.InventarioItem;
import modelo.Usuario;
import modelo.Venta;

/**
 * Controlador de Venta (caja / punto de venta) y Cotizaciones.
 * El carrito vive en la sesión. El stock solo se descuenta al cobrar.
 * Si hay una cotización cargada (cotizacionId != null), el carrito proviene
 * de ella y el cobro la convierte en venta (no crea una nueva).
 */
@WebServlet(name = "VentaServlet", urlPatterns = {"/venta"})
public class VentaServlet extends HttpServlet {

    private final VentaDAO ventaDAO = new VentaDAO();
    private final InventarioDAO inventarioDAO = new InventarioDAO();
    private final CajaDAO cajaDAO = new CajaDAO();

    private static final String K_CARRITO = "carrito";
    private static final String K_COTIZACION = "cotizacionId";
    private static final String K_MENSAJE = "mensaje";
    private static final String K_MENSAJE_ERROR = "mensajeError";

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {

        Usuario u = (Usuario) req.getSession().getAttribute("usuario");
        Caja caja = cajaDAO.cajaAbierta(u.getId());

        String q = req.getParameter("q");
        List<InventarioItem> items = inventarioDAO.listar(q);

        // Empleado no ve precio de costo (no se usa aquí, pero por coherencia)
        if (!u.isAdmin()) {
            for (InventarioItem it : items) {
                it.setPrecioCompra(null);
            }
        }

        List<DetalleVenta> carrito = carrito(req.getSession());
        Integer cotizacionId = (Integer) req.getSession().getAttribute(K_COTIZACION);

        // Mostrar y limpiar el mensaje flash
        String mensaje = (String) req.getSession().getAttribute(K_MENSAJE);
        boolean mensajeError = Boolean.TRUE.equals(
                req.getSession().getAttribute(K_MENSAJE_ERROR));
        req.getSession().removeAttribute(K_MENSAJE);
        req.getSession().removeAttribute(K_MENSAJE_ERROR);

        List<Venta> cotizaciones = ventaDAO.listarCotizaciones();

        BigDecimal totalCarrito = BigDecimal.ZERO;
        for (DetalleVenta l : carrito) {
            totalCarrito = totalCarrito.add(l.getSubtotal());
        }

        req.setAttribute("titulo", "Venta");
        req.setAttribute("caja", caja);
        req.setAttribute("items", items);
        req.setAttribute("q", q == null ? "" : q);
        req.setAttribute("carrito", carrito);
        req.setAttribute("totalCarrito", totalCarrito);
        req.setAttribute("cotizacionId", cotizacionId);
        req.setAttribute("cotizaciones", cotizaciones);
        if (mensaje != null) {
            req.setAttribute("mensaje", mensaje);
            req.setAttribute("mensajeError", mensajeError);
        }

        req.getRequestDispatcher("/WEB-INF/vistas/venta.jsp").forward(req, resp);
    }

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {

        req.setCharacterEncoding("UTF-8");
        HttpSession sesion = req.getSession();
        Usuario u = (Usuario) sesion.getAttribute("usuario");
        String accion = valor(req, "accion");

        try {
            switch (accion) {
                case "agregar":
                    agregar(sesion, u, req);
                    break;
                case "eliminar":
                    eliminar(sesion, req);
                    break;
                case "vaciar":
                    sesion.removeAttribute(K_CARRITO);
                    sesion.removeAttribute(K_COTIZACION);
                    break;
                case "cancelar_carga":
                    sesion.removeAttribute(K_CARRITO);
                    sesion.removeAttribute(K_COTIZACION);
                    break;
                case "cargar_cotizacion":
                    cargarCotizacion(sesion, req);
                    break;
                case "cotizar":
                    guardarCotizacion(sesion, req);
                    break;
                case "cobrar":
                    cobrar(sesion, u, req);
                    break;
                default:
                    break;
            }
        } catch (IllegalStateException | IllegalArgumentException e) {
            ok(sesion, e.getMessage(), true);
        }

        resp.sendRedirect(req.getContextPath() + "/venta");
    }

    // ------------------------------------------------------------------
    // Acciones
    // ------------------------------------------------------------------

    private void agregar(HttpSession sesion, Usuario u, HttpServletRequest req) {
        int idInventario = entero(req, "idInventario", -1);
        int cantidad = entero(req, "cantidad", 1);

        if (idInventario < 0 || cantidad < 1) {
            ok(sesion, "Cantidad inválida.", true);
            return;
        }
        if (sesion.getAttribute(K_COTIZACION) != null) {
            ok(sesion, "Tienes una cotización cargada. Cancela su carga antes de agregar productos.", true);
            return;
        }

        InventarioItem it = inventarioDAO.buscarPorId(idInventario);
        if (it == null || it.getPrecioVenta() == null) {
            ok(sesion, "Ese producto no está disponible para venta.", true);
            return;
        }

        List<DetalleVenta> carrito = carrito(sesion);

        // Cantidad ya en el carrito de este producto
        int enCarrito = 0;
        DetalleVenta existente = null;
        for (DetalleVenta l : carrito) {
            if (l.getIdInventario() == idInventario) {
                enCarrito += l.getCantidad();
                existente = l;
            }
        }

        if (enCarrito + cantidad > it.getStockAct()) {
            ok(sesion, "Stock insuficiente de «" + it.getNombre()
                    + "». Disponible: " + it.getStockAct() + ".", true);
            return;
        }

        if (existente != null) {
            existente.setCantidad(existente.getCantidad() + cantidad);
            existente.recalcularSubtotal();
        } else {
            carrito.add(new DetalleVenta(idInventario, it.getNombre(), cantidad,
                    it.getPrecioVenta(), it.getStockAct()));
        }
        ok(sesion, "Agregado: " + it.getNombre() + " x" + cantidad + ".", false);
    }

    private void eliminar(HttpSession sesion, HttpServletRequest req) {
        if (sesion.getAttribute(K_COTIZACION) != null) {
            ok(sesion, "Usa «Cancelar carga» para salir de la cotización.", true);
            return;
        }
        int indice = entero(req, "indice", -1);
        List<DetalleVenta> carrito = carrito(sesion);
        if (indice >= 0 && indice < carrito.size()) {
            String nombre = carrito.get(indice).getNombre();
            carrito.remove(indice);
            ok(sesion, "Quitado: " + nombre + ".", false);
        }
    }

    private void cargarCotizacion(HttpSession sesion, HttpServletRequest req) {
        int idVenta = entero(req, "idVenta", -1);
        if (idVenta < 0) {
            return;
        }
        List<DetalleVenta> lineas = ventaDAO.obtenerLineas(idVenta);
        if (lineas.isEmpty()) {
            ok(sesion, "Esa cotización ya no existe.", true);
            return;
        }
        sesion.setAttribute(K_CARRITO, new ArrayList<>(lineas));
        sesion.setAttribute(K_COTIZACION, idVenta);
        ok(sesion, "Cotización #" + idVenta + " cargada. Elige el pago y cobra.", false);
    }

    private void guardarCotizacion(HttpSession sesion, HttpServletRequest req) {
        List<DetalleVenta> carrito = carrito(sesion);
        if (carrito.isEmpty()) {
            ok(sesion, "El carrito está vacío.", true);
            return;
        }
        if (sesion.getAttribute(K_COTIZACION) != null) {
            ok(sesion, "Ya hay una cotización cargada. Cancela su carga antes de guardar otra.", true);
            return;
        }
        int id = ventaDAO.guardarCotizacion(carrito);
        sesion.removeAttribute(K_CARRITO);
        ok(sesion, "Cotización #" + id + " guardada. Vence en 24 h (no reserva stock).", false);
    }

    private void cobrar(HttpSession sesion, Usuario u, HttpServletRequest req) {
        Caja caja = cajaDAO.cajaAbierta(u.getId());
        if (caja == null) {
            ok(sesion, "Abre la caja antes de cobrar.", true);
            return;
        }

        List<DetalleVenta> carrito = carrito(sesion);
        if (carrito.isEmpty()) {
            ok(sesion, "El carrito está vacío.", true);
            return;
        }

        String tipoPago = valor(req, "tipoPago");
        if (!"YAPE".equals(tipoPago) && !"EFECTIVO".equals(tipoPago)) {
            ok(sesion, "Elige un método de pago (Yape o Efectivo).", true);
            return;
        }

        BigDecimal total = BigDecimal.ZERO;
        for (DetalleVenta l : carrito) {
            total = total.add(l.getSubtotal());
        }

        BigDecimal montoRecibido;
        if ("YAPE".equals(tipoPago)) {
            // En Yape se paga el total exacto; no hay vuelto.
            montoRecibido = total;
        } else {
            montoRecibido = decimal(req, "montoRecibido");
            if (montoRecibido == null) {
                ok(sesion, "Ingresa el monto recibido en efectivo.", true);
                return;
            }
            if (montoRecibido.compareTo(total) < 0) {
                ok(sesion, "El monto recibido es menor que el total.", true);
                return;
            }
        }

        Integer cotizacionId = (Integer) sesion.getAttribute(K_COTIZACION);
        if (cotizacionId != null) {
            ventaDAO.convertirCotizacion(cotizacionId, caja.getIdCaja(),
                    u.getId(), tipoPago);
        } else {
            ventaDAO.confirmarVenta(carrito, caja.getIdCaja(),
                    u.getId(), tipoPago);
        }

        sesion.removeAttribute(K_CARRITO);
        sesion.removeAttribute(K_COTIZACION);

        String vuelto = "";
        if ("EFECTIVO".equals(tipoPago)) {
            BigDecimal v = montoRecibido.subtract(total);
            vuelto = " Vuelto: S/ " + v.setScale(2) + ".";
        }
        ok(sesion, "Venta registrada. Total: S/ " + total.setScale(2) + "." + vuelto, false);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    @SuppressWarnings("unchecked")
    private List<DetalleVenta> carrito(HttpSession sesion) {
        Object obj = sesion.getAttribute(K_CARRITO);
        if (!(obj instanceof List)) {
            List<DetalleVenta> nuevo = new ArrayList<>();
            sesion.setAttribute(K_CARRITO, nuevo);
            return nuevo;
        }
        return (List<DetalleVenta>) obj;
    }

    private void ok(HttpSession sesion, String mensaje, boolean error) {
        sesion.setAttribute(K_MENSAJE, mensaje);
        sesion.setAttribute(K_MENSAJE_ERROR, error);
    }

    private String valor(HttpServletRequest req, String p) {
        String v = req.getParameter(p);
        return v == null ? "" : v.trim();
    }

    private int entero(HttpServletRequest req, String p, int defecto) {
        try {
            return Integer.parseInt(valor(req, p));
        } catch (NumberFormatException e) {
            return defecto;
        }
    }

    private BigDecimal decimal(HttpServletRequest req, String p) {
        String v = valor(req, p).replace(",", ".");
        if (v.isEmpty()) {
            return null;
        }
        try {
            return new BigDecimal(v);
        } catch (NumberFormatException e) {
            return null;
        }
    }
}
