package modelo;

import java.io.Serializable;

/** Proveedor registrado para documentar los ingresos de inventario. */
public class Proveedor implements Serializable {

    private static final long serialVersionUID = 1L;

    private int idProveedor;
    private String nombre;
    private String numero;

    public int getIdProveedor() {
        return idProveedor;
    }

    public void setIdProveedor(int idProveedor) {
        this.idProveedor = idProveedor;
    }

    public String getNombre() {
        return nombre;
    }

    public void setNombre(String nombre) {
        this.nombre = nombre;
    }

    public String getNumero() {
        return numero;
    }

    public void setNumero(String numero) {
        this.numero = numero;
    }
}
