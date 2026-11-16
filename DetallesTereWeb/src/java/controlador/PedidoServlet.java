package controlador;

import java.io.IOException;
import java.math.BigDecimal;
import java.time.LocalDate;
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
import dao.PedidoDAO;
import modelo.Caja;
import modelo.DetalleVenta;
import modelo.InventarioItem;
import modelo.Pedido;
import modelo.Usuario;

/**
 * Controlador de Pedidos Reservados.
 * Pantalla propia (no reutiliza el carrito de Venta). El carrito de la reserva
 * vive en la sesión bajo "pedidoCarrito". Al crear se aparta stock y se cobra
 * el adelanto (o el total) contra la caja abierta.
 */
@WebServlet(name = "PedidoServlet", urlPatterns = {"/pedidos"})
public class PedidoServlet extends HttpServlet {

    private final PedidoDAO pedidoDAO = new PedidoDAO();
    private final InventarioDAO inventarioDAO = new InventarioDAO();
    private final CajaDAO cajaDAO = new CajaDAO();

    private static final String K_CARRITO = "pedidoCarrito";
    private static final String K_MENSAJE = "pedidoMensaje";
    private static final String K_ERROR = "pedidoMensajeError";

    private static final BigDecimal CERO = BigDecimal.ZERO;
    private static final BigDecimal MILLON = new BigDecimal("1000000");

    // ------------------------------------------------------------------
    // GET: lista / formulario nuevo / detalle
    // ------------------------------------------------------------------
    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {

        Usuario u = (Usuario) req.getSession().getAttribute("usuario");

        // Mensaje flash
        String mensaje = (String) req.getSession().getAttribute(K_MENSAJE);
        boolean mensajeError = Boolean.TRUE.equals(req.getSession().getAttribute(K_ERROR));
        req.getSession().removeAttribute(K_MENSAJE);
        req.getSession().removeAttribute(K_ERROR);
        if (mensaje != null) {
            req.setAttribute("mensaje", mensaje);
            req.setAttribute("mensajeError", mensajeError);
        }

        String idTxt = req.getParameter("id");
        if (idTxt != null) {
            mostrarDetalle(req, resp, idTxt);
            return;
        }
        if (req.getParameter("nuevo") != null) {
            mostrarFormulario(req, resp, u);
            return;
        }
        mostrarLista(req, resp);
    }

    private void mostrarLista(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {
        String q = req.getParameter("q");
        List<Pedido> pedidos = pedidoDAO.listar(q);
        req.setAttribute("titulo", "Pedidos reservados");
        req.setAttribute("pedidos", pedidos);
        req.setAttribute("q", q == null ? "" : q);
        req.getRequestDispatcher("/WEB-INF/vistas/pedidos.jsp").forward(req, resp);
    }

    private void mostrarFormulario(HttpServletRequest req, HttpServletResponse resp, Usuario u)
            throws ServletException, IOException {
        String q = req.getParameter("q");
        List<InventarioItem> items = inventarioDAO.listar(q);
        if (!u.isAdmin()) {
            for (InventarioItem it : items) {
                it.setPrecioCompra(null);
            }
        }
        List<DetalleVenta> carrito = carrito(req.getSession());
        Caja caja = cajaDAO.cajaAbierta(u.getId());

        BigDecimal total = CERO;
        for (DetalleVenta l : carrito) {
            total = total.add(l.getSubtotal());
        }

        req.setAttribute("titulo", "Nuevo pedido reservado");
        req.setAttribute("items", items);
        req.setAttribute("q", q == null ? "" : q);
        req.setAttribute("carrito", carrito);
        req.setAttribute("totalCarrito", total);
        req.setAttribute("caja", caja);
        req.setAttribute("hoy", LocalDate.now().toString());
        req.getRequestDispatcher("/WEB-INF/vistas/pedido_form.jsp").forward(req, resp);
    }

    private void mostrarDetalle(HttpServletRequest req, HttpServletResponse resp, String idTxt)
            throws ServletException, IOException {
        int id;
        try {
            id = Integer.parseInt(idTxt);
        } catch (NumberFormatException e) {
            resp.sendRedirect(req.getContextPath() + "/pedidos");
            return;
        }
        Pedido p = pedidoDAO.buscarPorId(id);
        if (p == null) {
            resp.sendRedirect(req.getContextPath() + "/pedidos");
            return;
        }
        Caja caja = cajaDAO.cajaAbierta(
                ((Usuario) req.getSession().getAttribute("usuario")).getId());
        req.setAttribute("titulo", "Pedido #" + p.getIdPedido());
        req.setAttribute("pedido", p);
        req.setAttribute("caja", caja);
        req.getRequestDispatcher("/WEB-INF/vistas/pedido_detalle.jsp").forward(req, resp);
    }

    // ------------------------------------------------------------------
    // POST: acciones
    // ------------------------------------------------------------------
    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {
        req.setCharacterEncoding("UTF-8");
        HttpSession sesion = req.getSession();
        Usuario u = (Usuario) sesion.getAttribute("usuario");
        String accion = valor(req, "accion");
        String destino = req.getContextPath() + "/pedidos";

        try {
            switch (accion) {
                case "agregar":
                    agregar(sesion, req);
                    destino = req.getContextPath() + "/pedidos?nuevo=1";
                    break;
                case "eliminar":
                    eliminar(sesion, req);
                    destino = req.getContextPath() + "/pedidos?nuevo=1";
                    break;
                case "vaciar":
                    sesion.removeAttribute(K_CARRITO);
                    destino = req.getContextPath() + "/pedidos?nuevo=1";
                    break;
                case "crear":
                    crear(sesion, u, req, resp);
                    return;   // crear ya redirige
                case "abono": {
                    int idAbono = entero(req, "idPedido", -1);
                    abono(sesion, u, req);
                    destino = req.getContextPath() + "/pedidos?id=" + idAbono;
                    break;
                }
                case "hecho": {
                    int idHecho = entero(req, "idPedido", -1);
                    pedidoDAO.marcarHecho(idHecho);
                    ok(sesion, "Pedido marcado como HECHO. Ya no se puede cancelar; se cobrará el total.", false);
                    destino = req.getContextPath() + "/pedidos?id=" + idHecho;
                    break;
                }
                case "cancelar": {
                    int idCancelar = entero(req, "idPedido", -1);
                    pedidoDAO.cancelar(idCancelar, u.getId());
                    ok(sesion, "Pedido cancelado. El stock volvió al inventario y el adelanto "
                            + "quedó como saldo a favor; no se devuelve en efectivo.", false);
                    destino = req.getContextPath() + "/pedidos?id=" + idCancelar;
                    break;
                }
                case "recoger": {
                    int idRec = entero(req, "idPedido", -1);
                    pedidoDAO.recoger(idRec);
                    ok(sesion, "Pedido entregado (RECOGIDO).", false);
                    destino = req.getContextPath() + "/pedidos?id=" + idRec;
                    break;
                }
                default:
                    break;
            }
        } catch (IllegalStateException | IllegalArgumentException | java.sql.SQLException e) {
            ok(sesion, mensajeDe(e), true);
        }

        resp.sendRedirect(destino);
    }

    // ------------------------------------------------------------------
    // Acciones
    // ------------------------------------------------------------------

    private void agregar(HttpSession sesion, HttpServletRequest req) {
        int idInventario = entero(req, "idInventario", -1);
        int cantidad = entero(req, "cantidad", 1);
        if (idInventario < 0 || cantidad < 1) {
            ok(sesion, "Cantidad inválida.", true);
            return;
        }
        InventarioItem it = inventarioDAO.buscarPorId(idInventario);
        if (it == null || it.getPrecioVenta() == null) {
            ok(sesion, "Ese producto no está disponible para reserva.", true);
            return;
        }
        List<DetalleVenta> carrito = carrito(sesion);
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
        int indice = entero(req, "indice", -1);
        List<DetalleVenta> carrito = carrito(sesion);
        if (indice >= 0 && indice < carrito.size()) {
            String nombre = carrito.get(indice).getNombre();
            carrito.remove(indice);
            ok(sesion, "Quitado: " + nombre + ".", false);
        }
    }

    private void crear(HttpSession sesion, Usuario u, HttpServletRequest req,
                       HttpServletResponse resp) throws IOException {
        // Caja abierta obligatoria (el pago entra al cajón)
        Caja caja = cajaDAO.cajaAbierta(u.getId());
        if (caja == null) {
            ok(sesion, "Abre la caja antes de registrar un pedido.", true);
            resp.sendRedirect(req.getContextPath() + "/pedidos?nuevo=1");
            return;
        }
        List<DetalleVenta> carrito = carrito(sesion);
        if (carrito.isEmpty()) {
            ok(sesion, "Agrega al menos un producto a la reserva.", true);
            resp.sendRedirect(req.getContextPath() + "/pedidos?nuevo=1");
            return;
        }

        String celular = valor(req, "celular");
        String nombrePaga = valor(req, "nombrePaga");
        String nombreRecoge = valor(req, "nombreRecoge");
        String fechaRecojoTxt = valor(req, "fechaRecojo");
        String horaRecojo = valor(req, "horaRecojo");
        String tipoReserva = valor(req, "tipoReserva");
        String metodo = valor(req, "metodo");

        if (celular.isEmpty() || nombrePaga.isEmpty()) {
            ok(sesion, "Ingresa el celular y el nombre de quien paga.", true);
            resp.sendRedirect(req.getContextPath() + "/pedidos?nuevo=1");
            return;
        }
        LocalDate fechaRecojo;
        try {
            fechaRecojo = LocalDate.parse(fechaRecojoTxt);
        } catch (Exception e) {
            ok(sesion, "Elige la fecha de recojo.", true);
            resp.sendRedirect(req.getContextPath() + "/pedidos?nuevo=1");
            return;
        }
        if (!"YAPE".equals(metodo) && !"EFECTIVO".equals(metodo)) {
            ok(sesion, "Elige un método de pago (Yape o Efectivo).", true);
            resp.sendRedirect(req.getContextPath() + "/pedidos?nuevo=1");
            return;
        }

        BigDecimal total = CERO;
        for (DetalleVenta l : carrito) {
            total = total.add(l.getSubtotal());
        }

        // Monto a pagar ahora
        BigDecimal monto;
        if ("completo".equals(tipoReserva)) {
            monto = total;
        } else {
            monto = decimal(req, "monto");
            if (monto == null) {
                ok(sesion, "Ingresa el adelanto o elige pago completo.", true);
                resp.sendRedirect(req.getContextPath() + "/pedidos?nuevo=1");
                return;
            }
        }
        if (monto.compareTo(CERO) < 0 || monto.compareTo(total) > 0) {
            ok(sesion, "El monto debe estar entre S/ 0.00 y el total (S/ " + total.setScale(2) + ").", true);
            resp.sendRedirect(req.getContextPath() + "/pedidos?nuevo=1");
            return;
        }
        // Adelanto mínimo 50%; solo el administrador puede bajarlo
        BigDecimal minimo = total.multiply(new BigDecimal("0.5"));
        if (monto.compareTo(minimo) < 0 && !u.isAdmin()) {
            ok(sesion, "El adelanto mínimo es 50% (S/ " + minimo.setScale(2)
                    + "). Solo el administrador puede modificarlo.", true);
            resp.sendRedirect(req.getContextPath() + "/pedidos?nuevo=1");
            return;
        }

        Pedido p = new Pedido();
        p.setNombrePaga(nombrePaga);
        p.setNombreRecoge(nombreRecoge.isEmpty() ? null : nombreRecoge);
        p.setCelular(celular);
        p.setFechaRecojo(fechaRecojo);
        p.setHoraRecojo(horaRecojo.isEmpty() ? null : horaRecojo);
        p.setAdelanto(monto);
        p.setTotal(total);
        boolean completo = monto.compareTo(total) >= 0;
        p.setEstado(completo ? "COMPLETO" : "PENDIENTE");
        p.setEstadoProduccion("PENDIENTE");
        p.setIdUsuario(u.getId());

        try {
            int idPedido = pedidoDAO.crear(p, carrito, caja.getIdCaja(), metodo);
            sesion.removeAttribute(K_CARRITO);
            ok(sesion, "Pedido #" + idPedido + " reservado. Stock apartado. "
                    + (completo ? "Pagado por completo." : "Falta por pagar: S/ " + total.subtract(monto).setScale(2) + "."), false);
            resp.sendRedirect(req.getContextPath() + "/pedidos?id=" + idPedido);
        } catch (java.sql.SQLException e) {
            getServletContext().log("Error al crear pedido: " + e, e);
            ok(sesion, mensajeDe(e), true);
            resp.sendRedirect(req.getContextPath() + "/pedidos?nuevo=1");
        }
    }

    private void abono(HttpSession sesion, Usuario u, HttpServletRequest req) {
        Caja caja = cajaDAO.cajaAbierta(u.getId());
        if (caja == null) {
            ok(sesion, "Abre la caja antes de registrar un pago.", true);
            return;
        }
        int idPedido = entero(req, "idPedido", -1);
        BigDecimal monto = decimal(req, "monto");
        String metodo = valor(req, "metodo");
        if (idPedido < 0 || monto == null || monto.compareTo(CERO) <= 0 || monto.compareTo(MILLON) > 0) {
            ok(sesion, "Ingresa un monto válido para el abono.", true);
            return;
        }
        if (!"YAPE".equals(metodo) && !"EFECTIVO".equals(metodo)) {
            ok(sesion, "Elige un método de pago (Yape o Efectivo).", true);
            return;
        }
        try {
            pedidoDAO.abono(idPedido, monto, caja.getIdCaja(), metodo, u.getId());
            ok(sesion, "Abono de S/ " + monto.setScale(2) + " registrado para el pedido #" + idPedido + ".", false);
        } catch (java.sql.SQLException e) {
            ok(sesion, mensajeDe(e), true);
        }
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
        sesion.setAttribute(K_ERROR, error);
    }

    /** Mensaje amigable a partir de una excepción (p. ej. stock insuficiente). */
    private String mensajeDe(Exception e) {
        Throwable t = e;
        while (t.getCause() != null) {
            t = t.getCause();
        }
        String m = t.getMessage();
        if (m != null && (m.contains("Stock insuficiente") || m.contains("stock"))) {
            return "No hay stock suficiente para apartar ese ítem. Quita o reduce la cantidad.";
        }
        if (m != null && m.contains("pedido (revísalo")) {
            return "No se pudo actualizar el pedido. Puede que ya no esté en ese estado.";
        }
        if (m != null && m.contains("Solo se puede cancelar")) {
            return m;
        }
        return "Ocurrió un error. Revisa los datos e inténtalo de nuevo.";
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
