package modelo;

import java.math.BigDecimal;
import java.time.LocalDateTime;

/**
 * Saldo a favor de un cliente (CREDITO_CLIENTE).
 * Nace cuando se cancela un pedido reservado que aún NO está hecho:
 * el monto pagado se convierte en crédito ACTIVO, canjeable por productos
 * por el monto exacto, dentro de los 3 meses (FECHA_VENCIMIENTO).
 */
public class CreditoCliente {

    private int idCredito;
    private int idCliente;
    private Integer idInventario;   // ítem canjeado (NULL si aún no se canjea)
    private BigDecimal monto;
    private String estado;          // ACTIVO / CANJEADO
    private LocalDateTime fecha;
    private LocalDateTime fechaVencimiento;
    private int idUsuario;

    // Datos de apoyo para listar (no son columnas de la tabla)
    private String nombreCliente;
    private String numWsp;

    public CreditoCliente() {
    }

    public int getIdCredito() { return idCredito; }
    public void setIdCredito(int idCredito) { this.idCredito = idCredito; }

    public int getIdCliente() { return idCliente; }
    public void setIdCliente(int idCliente) { this.idCliente = idCliente; }

    public Integer getIdInventario() { return idInventario; }
    public void setIdInventario(Integer idInventario) { this.idInventario = idInventario; }

    public BigDecimal getMonto() { return monto; }
    public void setMonto(BigDecimal monto) { this.monto = monto; }

    public String getEstado() { return estado; }
    public void setEstado(String estado) { this.estado = estado; }

    public LocalDateTime getFecha() { return fecha; }
    public void setFecha(LocalDateTime fecha) { this.fecha = fecha; }

    public LocalDateTime getFechaVencimiento() { return fechaVencimiento; }
    public void setFechaVencimiento(LocalDateTime fechaVencimiento) { this.fechaVencimiento = fechaVencimiento; }

    public int getIdUsuario() { return idUsuario; }
    public void setIdUsuario(int idUsuario) { this.idUsuario = idUsuario; }

    public String getNombreCliente() { return nombreCliente; }
    public void setNombreCliente(String nombreCliente) { this.nombreCliente = nombreCliente; }

    public String getNumWsp() { return numWsp; }
    public void setNumWsp(String numWsp) { this.numWsp = numWsp; }

    /** Fecha como Date para fmt:formatDate en la vista. */
    public java.util.Date getFechaDate() {
        return fecha == null ? null
                : java.util.Date.from(fecha.atZone(java.time.ZoneId.systemDefault()).toInstant());
    }

    public java.util.Date getFechaVencimientoDate() {
        return fechaVencimiento == null ? null
                : java.util.Date.from(fechaVencimiento.atZone(java.time.ZoneId.systemDefault()).toInstant());
    }
}
