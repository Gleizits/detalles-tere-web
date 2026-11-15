package modelo;

import java.io.Serializable;

/** Término de búsqueda administrado por el usuario y sus asociaciones. */
public class Termino implements Serializable {

    private static final long serialVersionUID = 1L;

    private int idTermino;
    private String palabra;
    private String asociaciones;

    public int getIdTermino() {
        return idTermino;
    }

    public void setIdTermino(int idTermino) {
        this.idTermino = idTermino;
    }

    public String getPalabra() {
        return palabra;
    }

    public void setPalabra(String palabra) {
        this.palabra = palabra;
    }

    public String getAsociaciones() {
        return asociaciones;
    }

    public void setAsociaciones(String asociaciones) {
        this.asociaciones = asociaciones;
    }
}
