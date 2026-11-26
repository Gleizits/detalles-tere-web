<?php

/**
 * Configuración de DetallesTere (versión PHP).
 *
 * Los valores se leen del archivo .env de la raíz del proyecto (ver
 * app/lib/Entorno.php y .env.example). Así las credenciales NUNCA quedan
 * versionadas y cada entorno (XAMPP local, Render) usa las suyas.
 *
 * La base de datos sigue siendo la misma de siempre:
 * SQL Server 2019 Developer Edition, base DetallesTereDB.
 */

return [

    'db' => [
        'servidor' => Entorno::obtener('DB_SERVER', 'localhost'),
        'puerto'   => Entorno::entero('DB_PORT', 1433),
        'base'     => Entorno::obtener('DB_NAME', 'DetallesTereDB'),
        'usuario'  => Entorno::obtener('DB_USER', 'sa'),
        'clave'    => Entorno::obtener('DB_PASSWORD', ''),
        // true => autenticación integrada de Windows (no se envían UID/PWD).
        'autenticacion_windows' => Entorno::booleano('DB_WINDOWS_AUTH', false),
        // true => TrustServerCertificate=yes (certificado autofirmado,
        // el caso típico de SQL Server 2019 Developer en local).
        'confianza' => Entorno::booleano('DB_TRUST_CERT', true),
        // Segundos que espera antes de cortar una conexión colgada.
        'timeout'   => Entorno::entero('DB_TIMEOUT', 10),
    ],

    // Sesión: expira a los 30 minutos de inactividad (como en web.xml).
    'sesion_minutos' => Entorno::entero('SESSION_MINUTES', 30),

    // Limpieza automática de cotizaciones vencidas (> 24 h).
    // En Java era un Timer cada 15 min; en PHP se ejecuta de forma
    // oportunista (ver app/lib/LimpiezaCotizaciones.php).
    'limpieza_cotizaciones_minutos' => Entorno::entero('CLEANUP_MINUTES', 15),

    // Zona horaria para los saludos y "hoy" del panel/formularios.
    'zona_horaria' => Entorno::obtener('APP_TIMEZONE', 'America/Lima'),

    // true en Render/producción (HTTPS): marca la cookie como Secure.
    'cookie_segura' => Entorno::booleano('COOKIE_SECURE', false),

    // true en producción: no mostrar trazas en pantalla.
    'produccion' => Entorno::booleano('APP_PRODUCTION', false),
];
