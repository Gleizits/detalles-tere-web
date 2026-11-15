package modelo;

import java.io.Serializable;
import java.math.BigDecimal;
import java.time.LocalDateTime;

/**
 * Caja. Apertura/cierre manual por usuario (ambos roles).
 * 1 caja abierta por usuario (FECHA_CIERRE IS NULL).
 */
public class Caja implements Serializable {

    private static final long serialVersionUID = 1L;

    private int idCaja;
    private int idUsuario;
    private BigDecimal montoInicial;
    private BigDecimal montoFinal;   // calculado al cierre
    private BigDecimal montoReal;    // conteo físico al cierre
    private BigDecimal diferencia;   // montoReal - montoFinal
    private LocalDateTime fechaApertura;
    private LocalDateTime fechaCierre;
    private String nombreUsuario;    // para historial/reportes

    public Caja() {
    }

    public int getIdCaja() {
        return idCaja;
    }

    public void setIdCaja(int idCaja) {
        this.idCaja = idCaja;
    }

    public int getIdUsuario() {
        return idUsuario;
    }

    public void setIdUsuario(int idUsuario) {
        this.idUsuario = idUsuario;
    }

    public BigDecimal getMontoInicial() {
        return montoInicial;
    }

    public void setMontoInicial(BigDecimal montoInicial) {
        this.montoInicial = montoInicial;
    }

    public BigDecimal getMontoFinal() {
        return montoFinal;
    }

    public void setMontoFinal(BigDecimal montoFinal) {
        this.montoFinal = montoFinal;
    }

    public BigDecimal getMontoReal() {
        return montoReal;
    }

    public void setMontoReal(BigDecimal montoReal) {
        this.montoReal = montoReal;
    }

    public BigDecimal getDiferencia() {
        return diferencia;
    }

    public void setDiferencia(BigDecimal diferencia) {
        this.diferencia = diferencia;
    }

    public LocalDateTime getFechaApertura() {
        return fechaApertura;
    }

    public void setFechaApertura(LocalDateTime fechaApertura) {
        this.fechaApertura = fechaApertura;
    }

    public LocalDateTime getFechaCierre() {
        return fechaCierre;
    }

    public void setFechaCierre(LocalDateTime fechaCierre) {
        this.fechaCierre = fechaCierre;
    }

    public String getNombreUsuario() {
        return nombreUsuario;
    }

    public void setNombreUsuario(String nombreUsuario) {
        this.nombreUsuario = nombreUsuario;
    }

    /** true si la caja sigue abierta (sin fecha de cierre). */
    public boolean isAbierta() {
        return fechaCierre == null;
    }

    /**
     * fmt:formatDate solo acepta java.util.Date, no java.time.LocalDateTime.
     * Estos helpers convierten para poder mostrar las fechas en el JSP.
     */
    public java.util.Date getFechaAperturaDate() {
        if (fechaApertura == null) {
            return null;
        }
        return java.util.Date.from(
                fechaApertura.atZone(java.time.ZoneId.systemDefault()).toInstant());
    }

    public java.util.Date getFechaCierreDate() {
        if (fechaCierre == null) {
            return null;
        }
        return java.util.Date.from(
                fechaCierre.atZone(java.time.ZoneId.systemDefault()).toInstant());
    }
}
