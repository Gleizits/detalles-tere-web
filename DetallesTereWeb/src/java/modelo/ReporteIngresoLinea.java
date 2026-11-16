package modelo;

import java.io.Serializable;
import java.math.BigDecimal;
import java.time.LocalDate;

/** Línea de ingreso usada en el detalle del reporte administrativo. */
public class ReporteIngresoLinea implements Serializable {

    private static final long serialVersionUID = 1L;

    private LocalDate fecha;
    private String proveedor;
    private String articulo;
    private int cantidad;
    private BigDecimal precioCompra;
    private BigDecimal subtotal;

    public LocalDate getFecha() {
        return fecha;
    }

    public void setFecha(LocalDate fecha) {
        this.fecha = fecha;
    }

    public String getProveedor() {
        return proveedor;
    }

    public void setProveedor(String proveedor) {
        this.proveedor = proveedor;
    }

    public String getArticulo() {
        return articulo;
    }

    public void setArticulo(String articulo) {
        this.articulo = articulo;
    }

    public int getCantidad() {
        return cantidad;
    }

    public void setCantidad(int cantidad) {
        this.cantidad = cantidad;
    }

    public BigDecimal getPrecioCompra() {
        return precioCompra;
    }

    public void setPrecioCompra(BigDecimal precioCompra) {
        this.precioCompra = precioCompra;
    }

    public BigDecimal getSubtotal() {
        return subtotal;
    }

    public void setSubtotal(BigDecimal subtotal) {
        this.subtotal = subtotal;
    }
}
