<?php

/**
 * Renderizador de vistas (equivalente a RequestDispatcher.forward en Java).
 *
 * Las vistas viven en app/vista/*.php y comparten cabecera y pie mediante
 * app/vista/comunes/. El controlador le pasa datos ya preparados; la vista
 * solo imprime con e() para escapar HTML.
 */
final class Vista
{
    /**
     * Pinta una vista dentro de la plantilla común.
     *
     * @param string $nombre  Nombre base del archivo (sin .php), p. ej. 'panel'.
     * @param string $titulo  Título de la pestaña.
     * @param string $seccion Sección activa del menú ('/panel', '/caja'...).
     * @param array  $datos   Variables disponibles dentro de la vista.
     */
    public static function mostrar(
        string $nombre,
        string $titulo,
        string $seccion,
        array $datos = []
    ): void {
        $archivo = self::rutaVista($nombre);
        $csrfToken = Seguridad::tokenCsrf();

        // Variables compartidas por toda vista con plantilla.
        $usuario = Seguridad::usuarioEnSesion();
        $mensajesFlash = self::recogerFlash();

        extract($datos, EXTR_SKIP);

        require APP_RUTA . '/app/vista/comunes/cabecera.php';
        require $archivo;
        require APP_RUTA . '/app/vista/comunes/pie.php';
    }

    /**
     * Pinta una vista completa sin plantilla (login, error): página suelta.
     *
     * @param array $datos Variables disponibles dentro de la vista.
     */
    public static function suelta(string $nombre, string $titulo, array $datos = []): void
    {
        $archivo = self::rutaVista($nombre);
        $csrfToken = Seguridad::tokenCsrf();
        $usuario = Seguridad::usuarioEnSesion();

        extract($datos, EXTR_SKIP);

        require $archivo;
    }

    /** Resuelve el archivo de la vista evitando rutas fuera de app/vista. */
    private static function rutaVista(string $nombre): string
    {
        if ($nombre === '' || preg_match('/[^a-z0-9_]/i', $nombre) === 1) {
            throw new ExcepcionHttp(404, 'Vista no válida.');
        }

        $archivo = APP_RUTA . '/app/vista/' . $nombre . '.php';
        if (!is_file($archivo)) {
            throw new ExcepcionHttp(404, 'No encontramos la página que buscas.');
        }

        return $archivo;
    }

    /**
     * Lee y limpia los mensajes flash de la sesión (una sola vez).
     * @return array<int, array{mensaje:string, error:bool, titulo:string}>
     */
    private static function recogerFlash(): array
    {
        $pendientes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        if (!is_array($pendientes)) {
            return [];
        }

        $salida = [];
        foreach ($pendientes as $item) {
            if (is_array($item) && isset($item['mensaje']) && is_string($item['mensaje'])) {
                $salida[] = [
                    'mensaje' => $item['mensaje'],
                    'error'   => (bool) ($item['error'] ?? false),
                    'titulo'  => (string) ($item['titulo'] ?? ''),
                ];
            }
        }
        return $salida;
    }
}
