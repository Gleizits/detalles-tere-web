package modelo;

import java.math.BigDecimal;
import java.time.LocalDateTime;

/**
 * Cabecera de VENTA. Sirve tanto para una venta confirmada
 * como para una cotización (ESTADO='COTIZACION', TIPO_PAGO e ID_CAJA en NULL).
 */
public class Venta {

    private int idVenta;
    private Integer idCaja;
    private BigDecimal total;
    private String tipoPago;        // YAPE / EFECTIVO (NULL en cotización)
    private String estado;          // COTIZACION / VENDIDO / CANCELADO
    private LocalDateTime fechaVenta;

    // Datos de apoyo para listar cotizaciones (no son columnas de la tabla)
    private int numLineas;
    private String resumen;

    public Venta() {
    }

    public int getIdVenta() { return idVenta; }
    public void setIdVenta(int idVenta) { this.idVenta = idVenta; }

    public Integer getIdCaja() { return idCaja; }
    public void setIdCaja(Integer idCaja) { this.idCaja = idCaja; }

    public BigDecimal getTotal() { return total; }
    public void setTotal(BigDecimal total) { this.total = total; }

    public String getTipoPago() { return tipoPago; }
    public void setTipoPago(String tipoPago) { this.tipoPago = tipoPago; }

    public String getEstado() { return estado; }
    public void setEstado(String estado) { this.estado = estado; }

    public LocalDateTime getFechaVenta() { return fechaVenta; }
    public void setFechaVenta(LocalDateTime fechaVenta) { this.fechaVenta = fechaVenta; }

    public int getNumLineas() { return numLineas; }
    public void setNumLineas(int numLineas) { this.numLineas = numLineas; }

    public String getResumen() { return resumen; }
    public void setResumen(String resumen) { this.resumen = resumen; }

    /** Fecha convertida a Date para fmt:formatDate en la vista. */
    public java.util.Date getFechaVentaDate() {
        return fechaVenta == null ? null
                : java.util.Date.from(fechaVenta.atZone(java.time.ZoneId.systemDefault()).toInstant());
    }
}
