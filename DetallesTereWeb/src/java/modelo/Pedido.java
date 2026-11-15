package modelo;

import java.math.BigDecimal;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;

/**
 * Pedido reservado. Reglas de negocio:
 *  - ADELANTO mínimo 50% del TOTAL (solo admin puede bajarlo).
 *  - ADELANTO = monto pagado hasta ahora (se acumula con abonos).
 *  - Faltante = TOTAL - ADELANTO.
 *  - 3 meses para canjear, 2 meses para recoger, 1 mes para pagar el total.
 *  - ESTADO_PRODUCCION='HECHO' es punto de no retorno.
 *  - Al crear, el stock de los ítems se descuenta (se apartan).
 */
public class Pedido {

    private int idPedido;
    private Integer idCliente;
    private String nombrePaga;
    private String nombreRecoge;
    private String celular;
    private LocalDate fechaRecojo;
    private String horaRecojo;
    private BigDecimal adelanto;
    private BigDecimal total;
    private String estado;             // PENDIENTE / COMPLETO / RECOGIDO / CANCELADO
    private String estadoProduccion;   // PENDIENTE / HECHO / CANCELADO
    private int idUsuario;
    private LocalDateTime fechaCreacion;
    private LocalDateTime fechaLimiteCanje;
    private LocalDateTime fechaLimiteRecojo;
    private LocalDateTime fechaLimitePago;
    private LocalDateTime fechaPagoTotal;

    // Datos de apoyo (no son columnas de la tabla)
    private String nombreCliente;
    private List<DetalleVenta> lineas = new ArrayList<>();

    public Pedido() {
    }

    public int getIdPedido() { return idPedido; }
    public void setIdPedido(int idPedido) { this.idPedido = idPedido; }

    public Integer getIdCliente() { return idCliente; }
    public void setIdCliente(Integer idCliente) { this.idCliente = idCliente; }

    public String getNombrePaga() { return nombrePaga; }
    public void setNombrePaga(String nombrePaga) { this.nombrePaga = nombrePaga; }

    public String getNombreRecoge() { return nombreRecoge; }
    public void setNombreRecoge(String nombreRecoge) { this.nombreRecoge = nombreRecoge; }

    public String getCelular() { return celular; }
    public void setCelular(String celular) { this.celular = celular; }

    public LocalDate getFechaRecojo() { return fechaRecojo; }
    public void setFechaRecojo(LocalDate fechaRecojo) { this.fechaRecojo = fechaRecojo; }

    public String getHoraRecojo() { return horaRecojo; }
    public void setHoraRecojo(String horaRecojo) { this.horaRecojo = horaRecojo; }

    public BigDecimal getAdelanto() { return adelanto; }
    public void setAdelanto(BigDecimal adelanto) { this.adelanto = adelanto; }

    public BigDecimal getTotal() { return total; }
    public void setTotal(BigDecimal total) { this.total = total; }

    public String getEstado() { return estado; }
    public void setEstado(String estado) { this.estado = estado; }

    public String getEstadoProduccion() { return estadoProduccion; }
    public void setEstadoProduccion(String estadoProduccion) { this.estadoProduccion = estadoProduccion; }

    public int getIdUsuario() { return idUsuario; }
    public void setIdUsuario(int idUsuario) { this.idUsuario = idUsuario; }

    public LocalDateTime getFechaCreacion() { return fechaCreacion; }
    public void setFechaCreacion(LocalDateTime fechaCreacion) { this.fechaCreacion = fechaCreacion; }

    public LocalDateTime getFechaLimiteCanje() { return fechaLimiteCanje; }
    public void setFechaLimiteCanje(LocalDateTime fechaLimiteCanje) { this.fechaLimiteCanje = fechaLimiteCanje; }

    public LocalDateTime getFechaLimiteRecojo() { return fechaLimiteRecojo; }
    public void setFechaLimiteRecojo(LocalDateTime fechaLimiteRecojo) { this.fechaLimiteRecojo = fechaLimiteRecojo; }

    public LocalDateTime getFechaLimitePago() { return fechaLimitePago; }
    public void setFechaLimitePago(LocalDateTime fechaLimitePago) { this.fechaLimitePago = fechaLimitePago; }

    public LocalDateTime getFechaPagoTotal() { return fechaPagoTotal; }
    public void setFechaPagoTotal(LocalDateTime fechaPagoTotal) { this.fechaPagoTotal = fechaPagoTotal; }

    public String getNombreCliente() { return nombreCliente; }
    public void setNombreCliente(String nombreCliente) { this.nombreCliente = nombreCliente; }

    public List<DetalleVenta> getLineas() { return lineas; }
    public void setLineas(List<DetalleVenta> lineas) { this.lineas = lineas; }

    /** Cuánto falta por pagar: TOTAL - pagado. */
    public BigDecimal getFaltante() {
        if (total == null) return BigDecimal.ZERO;
        BigDecimal pagado = adelanto == null ? BigDecimal.ZERO : adelanto;
        return total.subtract(pagado);
    }

    /** true si ya se pagó el total. */
    public boolean isPagadoTotal() {
        return total != null && adelanto != null && adelanto.compareTo(total) >= 0;
    }

    /** Fecha de recojo como Date para fmt:formatDate. */
    public java.util.Date getFechaRecojoDate() {
        return fechaRecojo == null ? null
                : java.util.Date.from(fechaRecojo.atStartOfDay(java.time.ZoneId.systemDefault()).toInstant());
    }

    public java.util.Date getFechaCreacionDate() {
        return fechaCreacion == null ? null
                : java.util.Date.from(fechaCreacion.atZone(java.time.ZoneId.systemDefault()).toInstant());
    }

    public java.util.Date getFechaLimiteRecojoDate() {
        return fechaLimiteRecojo == null ? null
                : java.util.Date.from(fechaLimiteRecojo.atZone(java.time.ZoneId.systemDefault()).toInstant());
    }

    public java.util.Date getFechaLimiteCanjeDate() {
        return fechaLimiteCanje == null ? null
                : java.util.Date.from(fechaLimiteCanje.atZone(java.time.ZoneId.systemDefault()).toInstant());
    }

    public java.util.Date getFechaLimitePagoDate() {
        return fechaLimitePago == null ? null
                : java.util.Date.from(fechaLimitePago.atZone(java.time.ZoneId.systemDefault()).toInstant());
    }
}
