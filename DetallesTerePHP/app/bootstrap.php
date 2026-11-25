<?php

/**
 * Bootstrap de la aplicación: rutas base, autoloader, configuración,
 * sesión y manejo de errores.
 * Se incluye desde public/index.php (front controller).
 */

declare(strict_types=1);

// ------------------------------------------------------------------
// Rutas
// ------------------------------------------------------------------
define('APP_RUTA', dirname(__DIR__));
define('PUBLIC_RUTA', APP_RUTA . '/public');
define('APP_CONFIG', APP_RUTA . '/app/config.php');

// URL base de la aplicación (para subcarpetas tipo /DetallesTerePHP/public).
// Si se sirve desde la raíz del dominio, queda vacía.
$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
define('BASE_URL', rtrim(str_replace('/index.php', '', $script), '/'));

// ------------------------------------------------------------------
// Configuración
// ------------------------------------------------------------------
require APP_RUTA . '/app/lib/Entorno.php';
Entorno::cargar();

$cfg = require APP_CONFIG;

date_default_timezone_set($cfg['zona_horaria'] ?? 'America/Lima');
mb_internal_encoding('UTF-8');

// ------------------------------------------------------------------
// Autoloader: Modelo\*, Dao\*, Controlador\* y clases de app/lib
// (nombre de clase = nombre del archivo).
// ------------------------------------------------------------------
spl_autoload_register(static function (string $clase): void {
    $directorio = match (true) {
        str_starts_with($clase, 'Modelo\\')      => APP_RUTA . '/app/modelo/',
        str_starts_with($clase, 'Dao\\')         => APP_RUTA . '/app/dao/',
        str_starts_with($clase, 'Controlador\\') => APP_RUTA . '/app/controlador/',
        default                                  => APP_RUTA . '/app/lib/',
    };

    $nombre = str_contains($clase, '\\')
        ? substr($clase, (int) strrpos($clase, '\\') + 1)
        : $clase;

    $archivo = $directorio . $nombre . '.php';
    if (is_file($archivo)) {
        require $archivo;
    }
});

require APP_RUTA . '/app/lib/helpers.php';

// ------------------------------------------------------------------
// Sesión (30 min de inactividad, cookie httpOnly, como en web.xml)
// ------------------------------------------------------------------
$minutos = (int) ($cfg['sesion_minutos'] ?? 30);
ini_set('session.gc_maxlifetime', (string) ($minutos * 60));
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

$cookieSegura = !empty($cfg['cookie_segura'])
    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => BASE_URL !== '' ? BASE_URL : '/',
    'httponly' => true,
    'secure'   => $cookieSegura,
    'samesite' => 'Lax',
]);
session_name('DETALLESTERESESS');
session_start();

// ------------------------------------------------------------------
// Errores: se registran en log, nunca se muestran en pantalla.
// ------------------------------------------------------------------
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_RUTA . '/var/log/app.log');

if (!is_dir(APP_RUTA . '/var/log')) {
    @mkdir(APP_RUTA . '/var/log', 0775, true);
}

set_error_handler(static function (int $nivel, string $mensaje, string $archivo = '', int $linea = 0): bool {
    if (!(error_reporting() & $nivel)) {
        return false;
    }
    throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
});
