package util;

import java.sql.Connection;
import java.sql.SQLException;
import javax.naming.Context;
import javax.naming.InitialContext;
import javax.naming.NamingException;
import javax.sql.DataSource;

/**
 * Acceso al pool de conexiones mediante JNDI.
 * El DataSource se define en META-INF/context.xml (nombre jdbc/DetallesTere).
 */
public final class ConexionDB {

    private static final String JNDI = "java:comp/env/jdbc/DetallesTere";

    private ConexionDB() {
    }

    public static Connection obtener() throws SQLException {
        try {
            Context ctx = new InitialContext();
            DataSource ds = (DataSource) ctx.lookup(JNDI);
            return ds.getConnection();
        } catch (NamingException e) {
            throw new SQLException("No se encontró el DataSource JNDI: " + JNDI, e);
        }
    }
}
