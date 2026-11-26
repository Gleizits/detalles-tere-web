<?php

/**
 * Acceso a la base de datos mediante PDO + pdo_sqlsrv.
 * Sustituye al pool de conexiones JNDI (java:comp/env/jdbc/DetallesTere)
 * que se definía en META-INF/context.xml del proyecto Java.
 *
 * La conexión es perezosa: solo se abre al primer uso.
 */
final class ConexionDB
{
    private static ?PDO $pdo = null;

    private function __construct()
    {
    }

    /** Devuelve la conexión PDO (se crea una sola vez por petición). */
    public static function obtener(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = require APP_CONFIG;
        $db = $cfg['db'];

        $dsn = sprintf(
            'sqlsrv:Server=%s,%d;Database=%s;LoginTimeout=%d',
            $db['servidor'],
            (int) $db['puerto'],
            $db['base'],
            (int) ($db['timeout'] ?? 10)
        );
        if (!empty($db['confianza'])) {
            $dsn .= ';TrustServerCertificate=yes';
        }

        // Con autenticación integrada se omiten usuario y contraseña:
        // pdo_sqlsrv usa la identidad del proceso (típico en local).
        $usuario = !empty($db['autenticacion_windows']) ? null : $db['usuario'];
        $clave = !empty($db['autenticacion_windows']) ? null : $db['clave'];

        self::$pdo = new PDO($dsn, $usuario, $clave, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return self::$pdo;
    }

    /**
     * Ejecuta un INSERT y devuelve el IDENTITY recién creado.
     *
     * SCOPE_IDENTITY() va en el MISMO lote SQL que el INSERT: en una
     * consulta aparte devolvería NULL porque cambia el ámbito. El INSERT
     * no produce result set, así que fetchColumn() lee directamente el
     * SELECT añadido.
     */
    public static function insertarId(string $sql, array $parametros): int
    {
        $cn = self::obtener();
        $sentencia = rtrim($sql, " \t\r\n;") . '; SELECT CAST(SCOPE_IDENTITY() AS INT)';
        $ps = $cn->prepare($sentencia);
        $ps->execute($parametros);

        $id = $ps->fetchColumn();
        if ($id === false || $id === null) {
            throw new PDOException('No se pudo obtener el ID generado');
        }
        return (int) $id;
    }
}
