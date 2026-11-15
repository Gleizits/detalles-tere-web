package controlador;

import java.io.IOException;
import java.math.BigDecimal;
import java.util.List;
import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import javax.servlet.http.HttpSession;
import dao.InventarioDAO;
import modelo.Categoria;
import modelo.InventarioItem;
import modelo.Usuario;

/**
 * Controlador de Inventario — Fase 1 (alta y edición de artículos).
 *
 * Reglas de negocio (decisión del stakeholder):
 *  - Empleado y admin dan de alta artículos; el precio de compra solo
 *    existe para admin (se ignora en servidor si lo manda un empleado).
 *  - Al crear, quien sea captura el precio de venta (si no hay precio de
 *    compra se captura a mano). Editar precios después: solo admin.
 *  - Baja lógica por ESTADO (nunca se borra el artículo).
 *  - Ajuste manual de stock: ambos roles, con trazabilidad.
 *  - El filtrado por rol se aplica AQUÍ, no solo en la vista.
 */
@WebServlet(name = "InventarioServlet", urlPatterns = {"/inventario"})
public class InventarioServlet extends HttpServlet {

    private final InventarioDAO inventarioDAO = new InventarioDAO();

    private static final String K_MENSAJE = "invMensaje";
    private static final String K_ERROR = "invMensajeError";
    private static final BigDecimal MILLON = new BigDecimal("1000000");

    // ------------------------------------------------------------------
    // GET: lista / formulario nuevo / formulario editar
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

        if (req.getParameter("nuevo") != null) {
            mostrarFormulario(req, resp, u, null);
            return;
        }
        String idTxt = req.getParameter("editar");
        if (idTxt != null) {
            InventarioItem it = buscarSeguro(idTxt);
            if (it == null) {
                flash(req, "No encontramos ese artículo.", true);
                resp.sendRedirect(req.getContextPath() + "/inventario");
                return;
            }
            mostrarFormulario(req, resp, u, it);
            return;
        }
        mostrarLista(req, resp, u);
    }

    private void mostrarLista(HttpServletRequest req, HttpServletResponse resp, Usuario u)
            throws ServletException, IOException {
        String q = req.getParameter("q");
        List<InventarioItem> items = inventarioDAO.listar(q);

        // Seguridad por rol: al empleado no se le entrega el precio de costo
        if (!u.isAdmin()) {
            for (InventarioItem it : items) {
                it.setPrecioCompra(null);
            }
        }

        req.setAttribute("titulo", "Inventario");
        req.setAttribute("items", items);
        req.setAttribute("q", q == null ? "" : q);
        req.setAttribute("total", items.size());
        req.getRequestDispatcher("/WEB-INF/vistas/inventario.jsp").forward(req, resp);
    }

    private void mostrarFormulario(HttpServletRequest req, HttpServletResponse resp,
            Usuario u, InventarioItem it) throws ServletException, IOException {
        if (it != null && !u.isAdmin()) {
            it.setPrecioCompra(null);   // el empleado no recibe el precio de compra
        }
        List<Categoria> categorias = inventarioDAO.listarCategorias();
        List<Categoria> materiales = inventarioDAO.listarMaterialesActivos();

        boolean esEdicion = it != null && it.getIdInventario() > 0;
        req.setAttribute("titulo", esEdicion ? "Editar artículo" : "Nuevo artículo");
        req.setAttribute("item", it);
        req.setAttribute("categorias", categorias);
        req.setAttribute("materiales", materiales);
        req.getRequestDispatcher("/WEB-INF/vistas/inventario_form.jsp").forward(req, resp);
    }

    // ------------------------------------------------------------------
    // POST: crear / editar / ajustar stock
    // ------------------------------------------------------------------
    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {

        Usuario u = (Usuario) req.getSession().getAttribute("usuario");
        String accion = req.getParameter("accion");

        try {
            switch (accion == null ? "" : accion) {
                case "crear":
                    crear(req, resp, u);
                    break;
                case "editar":
                    editar(req, resp, u);
                    break;
                case "ajustar":
                    ajustar(req, resp, u);
                    break;
                default:
                    resp.sendRedirect(req.getContextPath() + "/inventario");
            }
        } catch (IllegalArgumentException | IllegalStateException e) {
            flash(req, e.getMessage(), true);
            resp.sendRedirect(req.getContextPath() + "/inventario");
        }
    }

    private void crear(HttpServletRequest req, HttpServletResponse resp, Usuario u)
            throws ServletException, IOException {

        String clase = req.getParameter("clase");
        if (!"PRODUCTO".equals(clase) && !"MATERIAL".equals(clase) && !"ARREGLO".equals(clase)) {
            mostrarFormularioError(req, resp, u, null,
                "Elige qué tipo de artículo es: producto, material o arreglo.");
            return;
        }

        InventarioItem it = new InventarioItem();
        it.setClase(clase);
        try {
            leerCampos(req, u, it, true);
            inventarioDAO.crear(it);
        } catch (IllegalArgumentException e) {
            // Volvemos al formulario con lo que se capturó para no perderlo
            mostrarFormularioError(req, resp, u, it, e.getMessage());
            return;
        } catch (RuntimeException e) {
            getServletContext().log("Error al crear artículo", e);
            mostrarFormularioError(req, resp, u, it,
                "No pudimos guardar el artículo. Intenta de nuevo.");
            return;
        }
        flash(req, "Artículo \"" + it.getNombre() + "\" guardado en el inventario.", false);
        resp.sendRedirect(req.getContextPath() + "/inventario");
    }

    private void editar(HttpServletRequest req, HttpServletResponse resp, Usuario u)
            throws ServletException, IOException {

        InventarioItem actual = buscarSeguro(req.getParameter("idInventario"));
        if (actual == null) {
            flash(req, "No encontramos ese artículo.", true);
            resp.sendRedirect(req.getContextPath() + "/inventario");
            return;
        }
        try {
            // La clase no cambia jamás: se toma de la BD, no del formulario.
            leerCampos(req, u, actual, false);
            inventarioDAO.actualizar(actual, u.isAdmin());
        } catch (IllegalArgumentException e) {
            mostrarFormularioError(req, resp, u, actual, e.getMessage());
            return;
        } catch (RuntimeException e) {
            getServletContext().log("Error al editar artículo", e);
            mostrarFormularioError(req, resp, u, actual,
                "No pudimos guardar los cambios. Intenta de nuevo.");
            return;
        }
        flash(req, "Cambios guardados en \"" + actual.getNombre() + "\".", false);
        resp.sendRedirect(req.getContextPath() + "/inventario");
    }

    /** Reenvía al formulario mostrando el error y conservando lo capturado. */
    private void mostrarFormularioError(HttpServletRequest req, HttpServletResponse resp,
            Usuario u, InventarioItem it, String error)
            throws ServletException, IOException {
        req.setAttribute("mensaje", error);
        req.setAttribute("mensajeError", true);
        mostrarFormulario(req, resp, u, it);
    }

    private void ajustar(HttpServletRequest req, HttpServletResponse resp, Usuario u)
            throws IOException {

        int idInventario = parseInt(req.getParameter("idInventario"),
            "No pudimos identificar el artículo.");

        // Delta calculado en el servidor a partir de signo (+1/-1) y cantidad:
        // así funciona aunque el navegador no ejecute JavaScript.
        int delta;
        String signoTxt = req.getParameter("signo");
        String cantTxt = req.getParameter("cantidad");
        if (signoTxt != null && cantTxt != null) {
            int signo = "-1".equals(signoTxt.trim()) ? -1 : 1;
            int cant = parseInt(cantTxt, "Indica la cantidad a sumar o restar.");
            delta = signo * cant;
        } else {
            delta = parseInt(req.getParameter("delta"),
                "Indica la cantidad a sumar o restar.");
        }

        if (delta == 0) {
            flash(req, "La cantidad no puede ser 0.", true);
            resp.sendRedirect(req.getContextPath() + "/inventario");
            return;
        }
        String motivo = limpiar(req.getParameter("motivo"), 100);

        try {
            inventarioDAO.ajustarStock(idInventario, delta, motivo, u.getId());
        } catch (IllegalStateException e) {
            flash(req, e.getMessage(), true);
            resp.sendRedirect(req.getContextPath() + "/inventario");
            return;
        } catch (RuntimeException e) {
            getServletContext().log("Error al ajustar stock", e);
            flash(req, "No pudimos actualizar el stock. Intenta de nuevo.", true);
            resp.sendRedirect(req.getContextPath() + "/inventario");
            return;
        }
        String que = delta > 0 ? "sumaron" : "restaron";
        flash(req, "Stock actualizado: se " + que + " " + Math.abs(delta)
                + " unidad(es).", false);
        resp.sendRedirect(req.getContextPath() + "/inventario");
    }

    // ------------------------------------------------------------------
    // Lectura y validación de los campos del formulario.
    // Se lee TODO primero y se valida al final: si hay un error, el objeto
    // conserva lo capturado y el formulario puede mostrarlo de nuevo sin
    // perder lo que ya escribió la usuaria.
    // ------------------------------------------------------------------
    private void leerCampos(HttpServletRequest req, Usuario u, InventarioItem it,
            boolean esAlta) {

        String error = null;

        // ---- Nombre ----
        String nombre = limpiar(req.getParameter("nombre"), 0);
        int tope = "ARREGLO".equals(it.getClase()) ? 100 : 50;
        if (nombre.isEmpty()) {
            error = "El nombre del artículo es obligatorio.";
        } else if (nombre.length() > tope) {
            error = "El nombre puede tener máximo " + tope + " caracteres.";
        }
        it.setNombre(nombre);

        // ---- Tipo (no lo llevan los arreglos) ----
        if (!"ARREGLO".equals(it.getClase())) {
            String tipo = limpiar(req.getParameter("tipo"), 50);
            it.setTipo(tipo.isEmpty() ? null : tipo);
        }

        // ---- Categoría: id del selector o nombre nuevo ----
        String categoriaNueva = limpiar(req.getParameter("categoriaNueva"), 100);
        Integer idCat = null;
        if (!categoriaNueva.isEmpty()) {
            try {
                idCat = inventarioDAO.obOCrearCategoria(categoriaNueva);
            } catch (RuntimeException e) {
                if (error == null) {
                    error = "No pudimos guardar la categoría nueva. Intenta de nuevo.";
                }
            }
        } else {
            idCat = parseIntOrNull(req.getParameter("idCategoria"));
            if (idCat == null && error == null) {
                error = "Elige una categoría o escribe una nueva.";
            }
        }
        if (idCat != null && idCat > 0) {
            it.setIdCategoria(idCat);
        }

        // ---- Unidad de medida ----
        String unidad = limpiar(req.getParameter("unidadMedida"), 0);
        if (unidad.isEmpty()) {
            if (error == null) {
                error = "Indica la unidad de medida o empaque (ej.: paquete de 12).";
            }
        } else if (unidad.length() > 50) {
            if (error == null) {
                error = "La unidad de medida puede tener máximo 50 caracteres.";
            }
        }
        it.setUnidadMedida(unidad);

        // ---- Tipo de almacén ----
        String tipoAlmacen = limpiar(req.getParameter("tipoAlmacen"), 0);
        if (tipoAlmacen.isEmpty()) {
            if (error == null) {
                error = "Indica cómo se guarda (ej.: paquete, unidad, costal).";
            }
        } else if (tipoAlmacen.length() > 35) {
            if (error == null) {
                error = "El tipo de almacén puede tener máximo 35 caracteres.";
            }
        }
        it.setTipoAlmacen(tipoAlmacen);

        // ---- Stock mínimo ----
        Integer stockMin = parseIntOrNull(req.getParameter("stockMin"));
        if (stockMin == null) {
            if (error == null) {
                error = "El stock mínimo debe ser un número entero.";
            }
        } else if (stockMin < 0) {
            if (error == null) {
                error = "El stock mínimo no puede ser negativo.";
            }
        } else {
            it.setStockMin(stockMin);
        }

        // ---- Stock inicial (solo en el alta) y estado ----
        if (esAlta) {
            Integer stock = parseIntOrNull(req.getParameter("stockAct"));
            if (stock == null) {
                if (error == null) {
                    error = "El stock inicial debe ser un número entero.";
                }
            } else if (stock < 0) {
                if (error == null) {
                    error = "El stock inicial no puede ser negativo.";
                }
            } else {
                it.setStockAct(stock);
            }
            it.setEstado(true);
        } else if (u.isAdmin()) {
            it.setEstado("on".equals(req.getParameter("estado")));
        }

        // ---- Precio de venta: obligatorio al crear; en edición vacío =
        //      conservar el actual (así el empleado no lo pisa) ----
        String pvTxt = limpiar(req.getParameter("precioVenta"), 0);
        if (!pvTxt.isEmpty()) {
            BigDecimal pv = parseDecimalOrNull(pvTxt);
            if (pv == null) {
                if (error == null) {
                    error = "Captura un precio de venta válido (ej.: 12.50).";
                }
            } else {
                it.setPrecioVenta(pv);
            }
        } else if (esAlta) {
            if (error == null) {
                error = "Captura un precio de venta válido (ej.: 12.50).";
            }
        }

        // ---- Precio de compra: SOLO admin; al empleado se le ignora ----
        if (u.isAdmin()) {
            String pcTxt = limpiar(req.getParameter("precioCompra"), 0);
            if (!pcTxt.isEmpty()) {
                BigDecimal pc = parseDecimalOrNull(pcTxt);
                if (pc == null) {
                    if (error == null) {
                        error = "Captura un precio de compra válido.";
                    }
                } else {
                    it.setPrecioCompra(pc);
                }
            } else {
                // Vacío = sin precio de compra (alta) o se limpia (edición)
                it.setPrecioCompra(null);
            }
        } else {
            it.setPrecioCompra(null);
        }

        // ---- Material enlazado (solo productos) ----
        if ("PRODUCTO".equals(it.getClase())) {
            Integer idMat = parseIntOrNull(req.getParameter("idMaterialVinc"));
            it.setIdMaterialVinc(idMat == null ? 0 : idMat);
        }

        if (error != null) {
            throw new IllegalArgumentException(error);
        }
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------
    private InventarioItem buscarSeguro(String txt) {
        try {
            return inventarioDAO.buscarPorId(Integer.parseInt(txt.trim()));
        } catch (Exception e) {
            return null;
        }
    }

    private String limpiar(String valor, int tope) {
        if (valor == null) {
            return "";
        }
        String v = valor.trim();
        return (tope > 0 && v.length() > tope) ? v.substring(0, tope) : v;
    }

    private int parseInt(String txt, String mensajeError) {
        Integer v = parseIntOrNull(txt);
        if (v == null) {
            throw new IllegalArgumentException(mensajeError);
        }
        return v;
    }

    /** Entero del parámetro o null si está vacío / no es número. */
    private Integer parseIntOrNull(String txt) {
        String v = limpiar(txt, 0);
        if (v.isEmpty()) {
            return null;
        }
        try {
            return Integer.valueOf(v);
        } catch (NumberFormatException e) {
            return null;
        }
    }

    /** Precio del parámetro o null si está vacío / no es número / fuera de rango. */
    private BigDecimal parseDecimalOrNull(String txt) {
        String v = limpiar(txt, 0);
        if (v.isEmpty()) {
            return null;
        }
        BigDecimal d;
        try {
            d = new BigDecimal(v.replace(',', '.'));
        } catch (NumberFormatException e) {
            return null;
        }
        if (d.signum() < 0 || d.compareTo(MILLON) > 0) {
            return null;
        }
        return d;
    }

    private void flash(HttpServletRequest req, String mensaje, boolean error) {
        HttpSession s = req.getSession();
        s.setAttribute(K_MENSAJE, mensaje);
        s.setAttribute(K_ERROR, error);
    }
}
