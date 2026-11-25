<?php

/**
 * Capa de seguridad: sustituye a los cuatro filtros del proyecto Java.
 *
 *  - CabecerasSeguridadFilter -> cabecerasSeguridad()
 *  - CsrfFilter               -> tokenCsrf() / verificarCsrf()
 *  - AutenticacionFilter      -> exigirLogin()
 *  - RequiereAdministradorFilter -> exigirAdmin()
 *
 * El orden se respeta en app/bootstrap.php: primero cabeceras, luego CSRF,
 * después autenticación y por último el rol administrador.
 */
final class Seguridad
{
    private const CLAVE_TOKEN = 'csrfToken';

    /** Cabeceras anti-clickjacking/sniffing y CSP (idénticas a las de Java). */
    public static function cabecerasSeguridad(string $ruta): void
    {
        if (headers_sent()) {
            return;
        }

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
        header('Content-Security-Policy: ' . $csp);

        if (str_starts_with($ruta, '/assets/')) {
            // Recursos estáticos: siempre revalidar para no servir CSS/JS viejos.
            header('Cache-Control: no-cache');
        } else {
            // Los datos del negocio no deben quedar en la caché del navegador.
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
        }
    }

    // ------------------------------------------------------------------
    // CSRF
    // ------------------------------------------------------------------

    /** Devuelve el token de la sesión, creándolo si hace falta. */
    public static function tokenCsrf(): string
    {
        $token = $_SESSION[self::CLAVE_TOKEN] ?? null;

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::CLAVE_TOKEN] = $token;
        }

        return $token;
    }

    /** Descarta el token para que se emita uno nuevo (tras login, p. ej.). */
    public static function rotarCsrf(): void
    {
        unset($_SESSION[self::CLAVE_TOKEN]);
    }

    /**
     * Valida el token en peticiones POST. Lanza 403 si no coincide.
     * Usa comparación en tiempo constante.
     */
    public static function verificarCsrf(): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $esperado = $_SESSION[self::CLAVE_TOKEN] ?? null;
        $recibido = $_POST[self::CLAVE_TOKEN] ?? null;

        if (!is_string($esperado) || !is_string($recibido) || $recibido === '') {
            throw new ExcepcionHttp(403, 'Token de seguridad inválido o ausente.');
        }

        if (!hash_equals($esperado, $recibido)) {
            throw new ExcepcionHttp(403, 'Token de seguridad inválido o ausente.');
        }
    }

    // ------------------------------------------------------------------
    // Autenticación
    // ------------------------------------------------------------------

    public static function usuarioEnSesion(): ?Modelo\Usuario
    {
        $u = $_SESSION['usuario'] ?? null;
        return $u instanceof Modelo\Usuario ? $u : null;
    }

    /** Cierra la sesión y limpia la cookie. */
    public static function cerrarSesion(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies') === '1') {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }

        session_destroy();
    }

    /**
     * Redirige al login guardando la ruta pedida (como AutenticacionFilter).
     */
    public static function exigirLogin(string $ruta): void
    {
        if (self::usuarioEnSesion() !== null) {
            return;
        }

        $consulta = $_SERVER['QUERY_STRING'] ?? '';
        $destino = $ruta . ($consulta !== '' ? '?' . $consulta : '');

        redirigir(url('/login') . '?destino=' . rawurlencode($destino));
    }

    /** 403 si la sesión no corresponde a un administrador. */
    public static function exigirAdmin(): void
    {
        $usuario = self::usuarioEnSesion();

        if ($usuario === null) {
            throw new ExcepcionHttp(403, 'Esta sección requiere permisos de administrador.');
        }
        if (!$usuario->esAdmin()) {
            throw new ExcepcionHttp(403, 'Esta sección requiere permisos de administrador.');
        }
    }
}
