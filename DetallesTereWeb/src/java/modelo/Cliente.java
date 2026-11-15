package modelo;

/**
 * Cliente de la tienda: solo NOMBRE y NUM_WSP (sin DNI).
 * Se reutiliza en pedidos reservados y en créditos a favor.
 */
public class Cliente {

    private int idCliente;
    private String nombre;
    private String numWsp;

    // Dato de apoyo para listar créditos (no es columna de la tabla)
    private int numCreditos;

    public Cliente() {
    }

    public Cliente(int idCliente, String nombre, String numWsp) {
        this.idCliente = idCliente;
        this.nombre = nombre;
        this.numWsp = numWsp;
    }

    public int getIdCliente() { return idCliente; }
    public void setIdCliente(int idCliente) { this.idCliente = idCliente; }

    public String getNombre() { return nombre; }
    public void setNombre(String nombre) { this.nombre = nombre; }

    public String getNumWsp() { return numWsp; }
    public void setNumWsp(String numWsp) { this.numWsp = numWsp; }

    public int getNumCreditos() { return numCreditos; }
    public void setNumCreditos(int numCreditos) { this.numCreditos = numCreditos; }
}
