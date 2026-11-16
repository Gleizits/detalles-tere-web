package modelo;

import java.io.Serializable;
import java.math.BigDecimal;
import java.time.LocalDate;
import java.util.List;

/** Resumen y detalles de un periodo seleccionado por el administrador. */
public class ReporteResumen implements Serializable {

    private static final long serialVersionUID = 1L;

    private LocalDate desde;
    private LocalDate hasta;
    private String etiquetaPeriodo;
    private int cantidadVentas;
    private int unidadesVendidas;
    private BigDecimal ventasTotales = BigDecimal.ZERO;
    private BigDecimal efectivo = BigDecimal.ZERO;
    private BigDecimal yape = BigDecimal.ZERO;
    private BigDecimal credito = BigDecimal.ZERO;
    private BigDecimal ingresosTotales = BigDecimal.ZERO;
    private int unidadesIngresadas;
    private BigDecimal margenEstimado = BigDecimal.ZERO;
    private List<ReporteVentaLinea> ventas;
    private List<ReporteIngresoLinea> ingresos;

    public LocalDate getDesde() {
        return desde;
    }

    public void setDesde(LocalDate desde) {
        this.desde = desde;
    }

    public LocalDate getHasta() {
        return hasta;
    }

    public void setHasta(LocalDate hasta) {
        this.hasta = hasta;
    }

    public String getEtiquetaPeriodo() {
        return etiquetaPeriodo;
    }

    public void setEtiquetaPeriodo(String etiquetaPeriodo) {
        this.etiquetaPeriodo = etiquetaPeriodo;
    }

    public int getCantidadVentas() {
        return cantidadVentas;
    }

    public void setCantidadVentas(int cantidadVentas) {
        this.cantidadVentas = cantidadVentas;
    }

    public int getUnidadesVendidas() {
        return unidadesVendidas;
    }

    public void setUnidadesVendidas(int unidadesVendidas) {
        this.unidadesVendidas = unidadesVendidas;
    }

    public BigDecimal getVentasTotales() {
        return ventasTotales;
    }

    public void setVentasTotales(BigDecimal ventasTotales) {
        this.ventasTotales = ventasTotales;
    }

    public BigDecimal getEfectivo() {
        return efectivo;
    }

    public void setEfectivo(BigDecimal efectivo) {
        this.efectivo = efectivo;
    }

    public BigDecimal getYape() {
        return yape;
    }

    public void setYape(BigDecimal yape) {
        this.yape = yape;
    }

    public BigDecimal getCredito() {
        return credito;
    }

    public void setCredito(BigDecimal credito) {
        this.credito = credito;
    }

    public BigDecimal getIngresosTotales() {
        return ingresosTotales;
    }

    public void setIngresosTotales(BigDecimal ingresosTotales) {
        this.ingresosTotales = ingresosTotales;
    }

    public int getUnidadesIngresadas() {
        return unidadesIngresadas;
    }

    public void setUnidadesIngresadas(int unidadesIngresadas) {
        this.unidadesIngresadas = unidadesIngresadas;
    }

    public BigDecimal getMargenEstimado() {
        return margenEstimado;
    }

    public void setMargenEstimado(BigDecimal margenEstimado) {
        this.margenEstimado = margenEstimado;
    }

    public List<ReporteVentaLinea> getVentas() {
        return ventas;
    }

    public void setVentas(List<ReporteVentaLinea> ventas) {
        this.ventas = ventas;
    }

    public List<ReporteIngresoLinea> getIngresos() {
        return ingresos;
    }

    public void setIngresos(List<ReporteIngresoLinea> ingresos) {
        this.ingresos = ingresos;
    }
}
