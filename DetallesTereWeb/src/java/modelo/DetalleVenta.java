package modelo;

import java.math.BigDecimal;
import java.time.LocalDateTime;

/**
 * Línea de venta / carrito / cotización.
 * Equivale a una fila de DETALLE_VENTA; también se usa en memoria
 * para el carrito de la caja (con nombre y stockDisponible para validar).
 */
public class DetalleVenta {

    private int idDetalleVenta;
    private int idInventario;
    private String nombre;             // solo para mostrar en el carrito
    private int cantidad;
    private BigDecimal costoUnitario;  // precio de venta fijado al momento
    private BigDecimal subtotal;
    private Integer idCaja;
    private Integer idVenta;
    private LocalDateTime fechaCreacion;
    private LocalDateTime fechaVencimiento;  // cotización: +24h
    private String estado;
    private int stockDisponible;       // validación en carrito (no persistido)

    public DetalleVenta() {
    }

    /** Crea una línea de carrito con subtotal calculado. */
    public DetalleVenta(int idInventario, String nombre, int cantidad,
                        BigDecimal costoUnitario, int stockDisponible) {
        this.idInventario = idInventario;
        this.nombre = nombre;
        this.cantidad = cantidad;
        this.costoUnitario = costoUnitario;
        this.stockDisponible = stockDisponible;
        this.subtotal = costoUnitario.multiply(BigDecimal.valueOf(cantidad));
    }

    /** Recalcula el subtotal a partir de cantidad y costo unitario. */
    public void recalcularSubtotal() {
        if (costoUnitario != null) {
            this.subtotal = costoUnitario.multiply(BigDecimal.valueOf(cantidad));
        }
    }

    public int getIdDetalleVenta() { return idDetalleVenta; }
    public void setIdDetalleVenta(int idDetalleVenta) { this.idDetalleVenta = idDetalleVenta; }

    public int getIdInventario() { return idInventario; }
    public void setIdInventario(int idInventario) { this.idInventario = idInventario; }

    public String getNombre() { return nombre; }
    public void setNombre(String nombre) { this.nombre = nombre; }

    public int getCantidad() { return cantidad; }
    public void setCantidad(int cantidad) { this.cantidad = cantidad; }

    public BigDecimal getCostoUnitario() { return costoUnitario; }
    public void setCostoUnitario(BigDecimal costoUnitario) { this.costoUnitario = costoUnitario; }

    public BigDecimal getSubtotal() { return subtotal; }
    public void setSubtotal(BigDecimal subtotal) { this.subtotal = subtotal; }

    public Integer getIdCaja() { return idCaja; }
    public void setIdCaja(Integer idCaja) { this.idCaja = idCaja; }

    public Integer getIdVenta() { return idVenta; }
    public void setIdVenta(Integer idVenta) { this.idVenta = idVenta; }

    public LocalDateTime getFechaCreacion() { return fechaCreacion; }
    public void setFechaCreacion(LocalDateTime fechaCreacion) { this.fechaCreacion = fechaCreacion; }

    public LocalDateTime getFechaVencimiento() { return fechaVencimiento; }
    public void setFechaVencimiento(LocalDateTime fechaVencimiento) { this.fechaVencimiento = fechaVencimiento; }

    public String getEstado() { return estado; }
    public void setEstado(String estado) { this.estado = estado; }

    public int getStockDisponible() { return stockDisponible; }
    public void setStockDisponible(int stockDisponible) { this.stockDisponible = stockDisponible; }
}
