package modelo;

import java.io.Serializable;
import java.math.BigDecimal;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;

/** Línea de venta usada en el detalle del reporte administrativo. */
public class ReporteVentaLinea implements Serializable {

    private static final long serialVersionUID = 1L;

    private LocalDateTime fecha;
    private String articulo;
    private int cantidad;
    private BigDecimal precioVenta;
    private BigDecimal costoVigente;
    private BigDecimal subtotal;
    private BigDecimal margen;

    public LocalDateTime getFecha() {
        return fecha;
    }

    public void setFecha(LocalDateTime fecha) {
        this.fecha = fecha;
    }

    public String getFechaTexto() {
        return fecha == null ? "" : fecha.format(
                DateTimeFormatter.ofPattern("dd/MM HH:mm"));
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

    public BigDecimal getPrecioVenta() {
        return precioVenta;
    }

    public void setPrecioVenta(BigDecimal precioVenta) {
        this.precioVenta = precioVenta;
    }

    public BigDecimal getCostoVigente() {
        return costoVigente;
    }

    public void setCostoVigente(BigDecimal costoVigente) {
        this.costoVigente = costoVigente;
    }

    public BigDecimal getSubtotal() {
        return subtotal;
    }

    public void setSubtotal(BigDecimal subtotal) {
        this.subtotal = subtotal;
    }

    public BigDecimal getMargen() {
        return margen;
    }

    public void setMargen(BigDecimal margen) {
        this.margen = margen;
    }
}
