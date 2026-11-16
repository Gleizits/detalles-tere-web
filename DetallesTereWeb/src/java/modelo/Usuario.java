package modelo;

import java.io.Serializable;

/**
 * Usuario del sistema. Roles: admin | empleado.
 * Ambos roles pueden abrir caja; solo admin ve costos/proveedor.
 */
public class Usuario implements Serializable {

    private static final long serialVersionUID = 1L;

    private int id;
    private String username;
    private String nombre;
    private String apellido;
    private String correo;
    private String rol;
    private boolean estado;

    public Usuario() {
    }

    public Usuario(int id, String username, String nombre, String apellido, String rol) {
        this.id = id;
        this.username = username;
        this.nombre = nombre;
        this.apellido = apellido;
        this.rol = rol;
        this.estado = true;
    }

    public int getId() {
        return id;
    }

    public void setId(int id) {
        this.id = id;
    }

    public String getUsername() {
        return username;
    }

    public void setUsername(String username) {
        this.username = username;
    }

    public String getNombre() {
        return nombre;
    }

    public void setNombre(String nombre) {
        this.nombre = nombre;
    }

    public String getApellido() {
        return apellido;
    }

    public void setApellido(String apellido) {
        this.apellido = apellido;
    }

    public String getCorreo() {
        return correo;
    }

    public void setCorreo(String correo) {
        this.correo = correo;
    }

    public String getRol() {
        return rol;
    }

    public void setRol(String rol) {
        this.rol = rol;
    }

    public boolean isEstado() {
        return estado;
    }

    public void setEstado(boolean estado) {
        this.estado = estado;
    }

    /** Solo el administrador accede a costos, proveedor y reportes. */
    public boolean isAdmin() {
        return "admin".equalsIgnoreCase(rol);
    }

    public String getNombreCompleto() {
        return (nombre == null ? "" : nombre) + " " + (apellido == null ? "" : apellido);
    }
}
