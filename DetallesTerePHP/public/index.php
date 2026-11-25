<?php

/**
 * Front controller: toda petición HTTP entra aquí.
 *
 * El orden de seguridad replica la cadena de filtros de web.xml:
 *   1. CabecerasSeguridad -> cabeceras en toda respuesta
 *   2. Csrf               -> valida el token de los POST
 *   3. Autenticacion      -> redirige al login a los no autenticados
 *   4. RequiereAdministrador -> 403 a empleados en secciones de admin
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

// ------------------------------------------------------------------
// Ruta solicitada (sin BASE_URL ni query string)
// ------------------------------------------------------------------
$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($ruta)) {
    $ruta = '/';
}
if (BASE_URL !== '' && str_starts_with($ruta, BASE_URL)) {
    $ruta = substr($ruta, strlen(BASE_URL)) ?: '/';
}
$ruta = '/' . ltrim($ruta, '/');

// ------------------------------------------------------------------
// 1. Cabeceras de seguridad (primer paso, también en errores)
// ------------------------------------------------------------------
Seguridad::cabecerasSeguridad($ruta);

$enrutador = new Enrutador();
require APP_RUTA . '/app/rutas.php';
rutas($enrutador);

try {
    // Los recursos estáticos no crean sesión ni llevan token CSRF.
    if (str_starts_with($ruta, '/assets/')) {
        servirRecursoEstatico($ruta);
        exit;
    }

    if ($ruta === '/') {
        redirigir(url(usuarioLogueado() ? '/panel' : '/login'));
    }

    // ----------------------------------------------------------------
    // 2. CSRF en peticiones POST
    // ----------------------------------------------------------------
    Seguridad::verificarCsrf();

    // ----------------------------------------------------------------
    // 3. Autenticación
    // ----------------------------------------------------------------
    if (!$enrutador->esPublica($ruta)) {
        Seguridad::exigirLogin($ruta);
    }

    // ----------------------------------------------------------------
    // 4. Rol administrador
    // ----------------------------------------------------------------
    if ($enrutador->requiereAdmin($ruta)) {
        Seguridad::exigirAdmin();
    }

    // ----------------------------------------------------------------
    // Despacho
    // ----------------------------------------------------------------
    $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $destino = $enrutador->resolver($ruta, $metodo);

    if ($destino === null) {
        throw new ExcepcionHttp(404);
    }

    [$clase, $metodoControlador] = $destino;
    $controlador = new $clase();
    $controlador->$metodoControlador();

} catch (ExcepcionHttp $e) {
    pintarError($e->codigo(), $e->getMessage());
} catch (Throwable $e) {
    error_log('[DetallesTere] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    pintarError(500, mensajeParaUsuario($e));
}

// ==================================================================
// Funciones de apoyo
// ==================================================================

/** ¿Hay un usuario válido en sesión? */
function usuarioLogueado(): bool
{
    return Seguridad::usuarioEnSesion() !== null;
}

/** Traduce excepciones a mensajes sin filtrar información interna. */
function mensajeParaUsuario(Throwable $e): string
{
    if ($e instanceof PDOException) {
        return 'No se pudo contactar con la base de datos. Revisa la configuración del .env.';
    }
    return mensajeDe($e);
}

/**
 * Sirve un archivo de public/assets (solo lectura, sin ruta arbitraria).
 * En Apache/.htaccess los sirve el propio servidor; esto cubre el servidor
 * integrado de PHP usado en desarrollo.
 */
function servirRecursoEstatico(string $ruta): void
{
    $relativo = ltrim($ruta, '/');
    $base = realpath(PUBLIC_RUTA);
    $rutaReal = realpath(PUBLIC_RUTA . '/' . $relativo);

    if ($base === false || $rutaReal === false || !str_starts_with($rutaReal, $base)) {
        throw new ExcepcionHttp(404);
    }
    if (!is_file($rutaReal)) {
        throw new ExcepcionHttp(404);
    }

    $tipos = [
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'map'  => 'application/json',
    ];

    $ext = strtolower(pathinfo($rutaReal, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($tipos[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . (string) filesize($rutaReal));
    readfile($rutaReal);
}

/** Pinta la página de error (equivale a error.jsp / error-page). */
function pintarError(int $codigo, string $mensaje): void
{
    if (!headers_sent()) {
        http_response_code($codigo);
    }

    $titulo = match ($codigo) {
        403 => 'Sin permiso',
        404 => 'Página no encontrada',
        405 => 'Método no permitido',
        default => 'Error',
    };

    $vista = PUBLIC_RUTA . '/error.php';
    if (is_file($vista)) {
        require $vista;
    } else {
        echo '<h1>' . e($titulo) . '</h1><p>' . e($mensaje) . '</p>';
    }
    exit;
}
