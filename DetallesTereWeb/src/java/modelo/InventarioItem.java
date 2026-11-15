package modelo;

import java.io.Serializable;
import java.math.BigDecimal;

/**
 * Fila del inventario (vista unificada).
 * INVENTARIO apunta a exactamente 1 de: PRODUCTO | MATERIAL | ARREGLO.
 * Aquí se fusiona el nombre/tipo/precios de la tabla correspondiente.
 */
public class InventarioItem implements Serializable {

    private static final long serialVersionUID = 1L;

    private int idInventario;
    private int stockAct;
    private int stockMin;
    private String tipoAlmacen;
    private String unidadMedida;

    private String nombre;       // de producto, material o arreglo
    private String tipo;         // de producto/material (arreglo no tiene)
    private String categoria;
    private String clase;        // PRODUCTO | MATERIAL | ARREGLO

    private BigDecimal precioVenta;
    private BigDecimal precioCompra; // solo admin (se anula para empleado)

    public InventarioItem() {
    }

    public int getIdInventario() {
        return idInventario;
    }

    public void setIdInventario(int idInventario) {
        this.idInventario = idInventario;
    }

    public int getStockAct() {
        return stockAct;
    }

    public void setStockAct(int stockAct) {
        this.stockAct = stockAct;
    }

    public int getStockMin() {
        return stockMin;
    }

    public void setStockMin(int stockMin) {
        this.stockMin = stockMin;
    }

    public String getTipoAlmacen() {
        return tipoAlmacen;
    }

    public void setTipoAlmacen(String tipoAlmacen) {
        this.tipoAlmacen = tipoAlmacen;
    }

    public String getUnidadMedida() {
        return unidadMedida;
    }

    public void setUnidadMedida(String unidadMedida) {
        this.unidadMedida = unidadMedida;
    }

    public String getNombre() {
        return nombre;
    }

    public void setNombre(String nombre) {
        this.nombre = nombre;
    }

    public String getTipo() {
        return tipo;
    }

    public void setTipo(String tipo) {
        this.tipo = tipo;
    }

    public String getCategoria() {
        return categoria;
    }

    public void setCategoria(String categoria) {
        this.categoria = categoria;
    }

    public String getClase() {
        return clase;
    }

    public void setClase(String clase) {
        this.clase = clase;
    }

    public BigDecimal getPrecioVenta() {
        return precioVenta;
    }

    public void setPrecioVenta(BigDecimal precioVenta) {
        this.precioVenta = precioVenta;
    }

    public BigDecimal getPrecioCompra() {
        return precioCompra;
    }

    public void setPrecioCompra(BigDecimal precioCompra) {
        this.precioCompra = precioCompra;
    }

    /** true si el stock actual está en o por debajo del mínimo. */
    public boolean isStockBajo() {
        return stockAct <= stockMin;
    }
}
