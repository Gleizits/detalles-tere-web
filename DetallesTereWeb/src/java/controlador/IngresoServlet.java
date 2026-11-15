package controlador;

import dao.IngresoDAO;
import dao.InventarioDAO;
import dao.ProveedorDAO;
import java.io.IOException;
import java.math.BigDecimal;
import java.time.LocalDate;
import java.time.format.DateTimeParseException;
import java.util.ArrayList;
import java.util.List;
import javax.servlet.ServletException;
import javax.servlet.annotation.WebServlet;
import javax.servlet.http.HttpServlet;
import javax.servlet.http.HttpServletRequest;
import javax.servlet.http.HttpServletResponse;
import modelo.IngresoLinea;
import modelo.InventarioItem;
import modelo.Proveedor;
import modelo.Usuario;

/** Captura ingresos administrativos y sus líneas de inventario. */
@WebServlet(name = "IngresoServlet", urlPatterns = {"/ingresos"})
public class IngresoServlet extends HttpServlet {

    private static final BigDecimal MAXIMO = new BigDecimal("1000000");

    private final IngresoDAO ingresoDAO = new IngresoDAO();
    private final InventarioDAO inventarioDAO = new InventarioDAO();
    private final ProveedorDAO proveedorDAO = new ProveedorDAO();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {
        cargarVista(req);
        req.getRequestDispatcher("/WEB-INF/vistas/ingresos.jsp").forward(req, resp);
    }

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp)
            throws ServletException, IOException {
        req.setCharacterEncoding("UTF-8");
        String accion = req.getParameter("accion");
        try {
            if ("crearProveedor".equals(accion)) {
                crearProveedor(req);
                redirigirConMensaje(req, resp, "Proveedor guardado.");
            } else if ("registrar".equals(accion)) {
                registrarIngreso(req);
                redirigirConMensaje(req, resp, "Ingreso registrado y stock actualizado.");
            } else {
                redirigirConMensaje(req, resp, "Acción no válida.");
            }
        } catch (IllegalArgumentException e) {
            cargarVista(req);
            req.setAttribute("error", e.getMessage());
            req.getRequestDispatcher("/WEB-INF/vistas/ingresos.jsp").forward(req, resp);
        } catch (RuntimeException e) {
            getServletContext().log("Error en el módulo de ingresos", e);
            cargarVista(req);
            req.setAttribute("error", "No pudimos guardar la información. Intenta de nuevo.");
            req.getRequestDispatcher("/WEB-INF/vistas/ingresos.jsp").forward(req, resp);
        }
    }

    private void crearProveedor(HttpServletRequest req) {
        String nombre = textoObligatorio(req.getParameter("nombreProveedor"), 50,
                "Indica el nombre del proveedor.");
        String numero = textoObligatorio(req.getParameter("numeroProveedor"), 12,
                "Indica el número del proveedor.");
        proveedorDAO.crear(nombre, numero);
    }

    private void registrarIngreso(HttpServletRequest req) {
        Usuario usuario = (Usuario) req.getSession().getAttribute("usuario");
        int idProveedor = entero(req.getParameter("idProveedor"),
                "Selecciona un proveedor.");
        LocalDate fecha = fecha(req.getParameter("fechaIngreso"));
        List<IngresoLinea> lineas = leerLineas(req);
        ingresoDAO.registrar(idProveedor, usuario.getId(), fecha, lineas);
    }

    private List<IngresoLinea> leerLineas(HttpServletRequest req) {
        String[] ids = req.getParameterValues("idInventario");
        String[] cantidades = req.getParameterValues("cantidad");
        String[] precios = req.getParameterValues("precioCompra");
        String[] observaciones = req.getParameterValues("observaciones");
        if (ids == null || cantidades == null || precios == null
                || ids.length != cantidades.length || ids.length != precios.length) {
            throw new IllegalArgumentException("Agrega al menos un artículo válido.");
        }

        List<IngresoLinea> lineas = new ArrayList<>();
        for (int i = 0; i < ids.length; i++) {
            if (texto(ids[i]).isEmpty()) {
                continue;
            }
            IngresoLinea linea = new IngresoLinea();
            linea.setIdInventario(entero(ids[i], "Selecciona un artículo válido."));
            linea.setCantidad(entero(cantidades[i], "La cantidad debe ser un entero positivo."));
            linea.setPrecioCompraUnitario(decimal(precios[i],
                    "Captura un precio de compra válido."));
            linea.setObservaciones(observaciones != null && i < observaciones.length
                    ? limitar(observaciones[i], 100) : null);
            lineas.add(linea);
        }
        if (lineas.isEmpty()) {
            throw new IllegalArgumentException("Agrega al menos un artículo al ingreso.");
        }
        return lineas;
    }

    private void cargarVista(HttpServletRequest req) {
        String mensaje = (String) req.getSession().getAttribute("ingresoMensaje");
        req.getSession().removeAttribute("ingresoMensaje");
        List<Proveedor> proveedores = proveedorDAO.listar();
        List<InventarioItem> inventario = inventarioDAO.listarActivos();
        req.setAttribute("mensaje", mensaje);
        req.setAttribute("proveedores", proveedores);
        req.setAttribute("inventario", inventario);
        req.setAttribute("ingresos", ingresoDAO.listarRecientes(10));
        req.setAttribute("fechaActual", LocalDate.now());
        req.setAttribute("titulo", "Ingresos");
    }

    private void redirigirConMensaje(HttpServletRequest req, HttpServletResponse resp,
            String mensaje) throws IOException {
        req.getSession().setAttribute("ingresoMensaje", mensaje);
        resp.sendRedirect(req.getContextPath() + "/ingresos");
    }

    private LocalDate fecha(String valor) {
        try {
            return LocalDate.parse(texto(valor));
        } catch (DateTimeParseException e) {
            throw new IllegalArgumentException("Selecciona una fecha válida.");
        }
    }

    private int entero(String valor, String mensaje) {
        try {
            int numero = Integer.parseInt(texto(valor));
            if (numero <= 0) {
                throw new NumberFormatException();
            }
            return numero;
        } catch (NumberFormatException e) {
            throw new IllegalArgumentException(mensaje);
        }
    }

    private BigDecimal decimal(String valor, String mensaje) {
        try {
            BigDecimal numero = new BigDecimal(texto(valor).replace(',', '.'));
            if (numero.signum() < 0 || numero.compareTo(MAXIMO) > 0) {
                throw new NumberFormatException();
            }
            return numero;
        } catch (NumberFormatException e) {
            throw new IllegalArgumentException(mensaje);
        }
    }

    private String textoObligatorio(String valor, int maximo, String mensaje) {
        String resultado = limitar(valor, maximo);
        if (resultado.isEmpty()) {
            throw new IllegalArgumentException(mensaje);
        }
        return resultado;
    }

    private String limitar(String valor, int maximo) {
        String resultado = texto(valor);
        return resultado.length() > maximo ? resultado.substring(0, maximo) : resultado;
    }

    private String texto(String valor) {
        return valor == null ? "" : valor.trim();
    }
}
