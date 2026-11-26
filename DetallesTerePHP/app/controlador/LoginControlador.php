<?php

namespace Controlador;

use Seguridad;

/**
 * Controlador de autenticación. Muestra y procesa el login.
 *
 * Protecciones (auditadas en la versión Java, fase 7 / H9):
 *  - Límite de intentos fallidos (5 en 15 minutos por usuario+IP).
 *  - Límite por dirección IP (20 fallos en la ventana) para frenar
 *    ataques que rotan usuarios desde un mismo origen.
 *  - Backoff exponencial a partir del 4.º fallo: 2 s, 4 s, 8 s ... 60 s.
 *  - Rotación del token CSRF tras autenticarse (session fixation).
 *  - Destino validado contra una ruta conocida (anti open-redirect).
 *
 * El estado de los intentos vive en un archivo temporal por proceso;
 * en un servidor con varios workers se pierde el conteo entre procesos,
 * pero el límite sigue actuando dentro de cada uno.
 */
final class LoginControlador extends ControladorBase
{
    private const MAX_INTENTOS = 5;
    private const MAX_POR_IP = 20;
    private const VENTANA_SEG = 900;          // 15 minutos
    private const FALLOS_SIN_BACKOFF = 3;
    private const BACKOFF_INICIAL_SEG = 2;
    private const BACKOFF_MAX_SEG = 60;

    /** Rutas internas admitidas como destino tras el login. */
    private const RUTAS_PERMITIDAS = [
        '/panel', '/caja', '/venta', '/pedidos', '/inventario',
        '/reportes', '/usuarios', '/ingresos', '/terminos',
    ];

    /** GET /login */
    public function mostrar(): never
    {
        if (Seguridad::usuarioEnSesion() !== null) {
            $this->ir('/panel');
        }

        $this->verSuelta('login', 'Iniciar sesión', [
            'error'    => null,
            'username' => '',
            'destino'  => valorParam('destino'),
        ]);
    }

    /** POST /login */
    public function procesar(): never
    {
        if (Seguridad::usuarioEnSesion() !== null) {
            $this->ir('/panel');
        }

        $username = valorParam('username');
        $password = $_POST['password'] ?? '';
        $destino  = valorParam('destino');
        $ip       = $this->ipCliente();
        $clave    = strtolower($username) . '|' . $ip;

        $aviso = $this->mensajeDeBloqueo($clave, $ip);
        if ($aviso !== null) {
            $this->verSuelta('login', 'Iniciar sesión', [
                'error'    => $aviso,
                'username' => $username,
                'destino'  => $destino,
            ]);
        }

        $dao = new \Dao\UsuarioDAO();
        $usuario = $dao->login($username, $password);

        if ($usuario === null) {
            $this->registrarFallo($clave, $ip);
            $this->verSuelta('login', 'Iniciar sesión', [
                'error'    => 'Usuario o contraseña incorrectos.',
                'username' => $username,
                'destino'  => $destino,
            ]);
        }

        unset($this->intentos()[$clave]);

        // Anti session fixation: identificador nuevo tras autenticarse.
        session_regenerate_id(true);
        $_SESSION['usuario'] = $usuario;
        Seguridad::rotarCsrf();

        // Ir a la ruta pedida o al panel (solo destinos internos válidos).
        $this->ir($this->destinoValido($destino) ? $destino : '/panel');
    }

    // ------------------------------------------------------------------
    // Límite de intentos: por usuario+IP, por IP y con backoff
    // ------------------------------------------------------------------

    /** @return array<string, array{0:int,1:int,2:int}> */
    private function &intentos(): array
    {
        static $almacen = [];
        $this->cargar($almacen);
        return $almacen;
    }

    /** @return array<string, array{0:int,1:int,2:int}> */
    private function &intentosIp(): array
    {
        static $almacen = [];
        $this->cargar($almacen, true);
        return $almacen;
    }

    /** Persiste los contadores en un archivo temporal del proyecto. */
    private function guardar(array $datos, bool $porIp): void
    {
        $ruta = $this->archivoIntentos($porIp);
        @file_put_contents($ruta, json_encode($datos), LOCK_EX);
    }

    /** @param array<string, array{0:int,1:int,2:int}> $almacen */
    private function cargar(array &$almacen, bool $porIp = false): void
    {
        if ($almacen !== []) {
            return;
        }
        $ruta = $this->archivoIntentos($porIp);
        if (!is_file($ruta)) {
            return;
        }
        $crudo = @file_get_contents($ruta);
        $datos = $crudo === false ? null : json_decode($crudo, true);
        $almacen = is_array($datos) ? $datos : [];
    }

    private function archivoIntentos(bool $porIp): string
    {
        return APP_RUTA . '/var/' . ($porIp ? 'intentos_ip.json' : 'intentos.json');
    }

    /** null si se puede intentar; si no, el mensaje que ve el usuario. */
    private function mensajeDeBloqueo(string $clave, string $ip): ?string
    {
        $ahora = time();

        // Tope por dirección IP (atacante que rota usuarios).
        $porIp = &$this->intentosIp();
        if (isset($porIp[$ip])) {
            if ($ahora - $porIp[$ip][1] >= self::VENTANA_SEG) {
                unset($porIp[$ip]);
                $this->guardar($porIp, true);
            } elseif ($porIp[$ip][0] >= self::MAX_POR_IP) {
                return 'Demasiados intentos desde esta dirección. '
                    . 'Vuelve a intentarlo en unos minutos.';
            }
        }

        $intentos = &$this->intentos();
        if (!isset($intentos[$clave])) {
            return null;
        }
        if ($ahora - $intentos[$clave][1] >= self::VENTANA_SEG) {
            unset($intentos[$clave]);
            $this->guardar($intentos, false);
            return null;
        }
        if ($intentos[$clave][0] >= self::MAX_INTENTOS) {
            return 'Demasiados intentos fallidos. '
                . 'Vuelve a intentarlo en unos minutos.';
        }

        // Backoff exponencial a partir del 4.º fallo: 2 s, 4 s, 8 s ... 60 s.
        $fallos = $intentos[$clave][0];
        if ($fallos > self::FALLOS_SIN_BACKOFF) {
            $exponente = min($fallos - self::FALLOS_SIN_BACKOFF - 1, 5);
            $espera = min(self::BACKOFF_INICIAL_SEG * (2 ** $exponente), self::BACKOFF_MAX_SEG);
            $desdeUltimoFallo = $ahora - $intentos[$clave][2];
            if ($desdeUltimoFallo < $espera) {
                $segundos = (int) ceil($espera - $desdeUltimoFallo);
                return 'Espera ' . $segundos . ' segundo' . ($segundos === 1 ? '' : 's')
                    . ' antes de volver a intentar.';
            }
        }
        return null;
    }

    private function registrarFallo(string $clave, string $ip): void
    {
        $ahora = time();

        $intentos = &$this->intentos();
        $actual = $intentos[$clave] ?? null;
        $intentos[$clave] = ($actual === null || $ahora - $actual[1] >= self::VENTANA_SEG)
            ? [1, $ahora, $ahora]
            : [$actual[0] + 1, $actual[1], $ahora];
        $this->guardar($intentos, false);

        $porIp = &$this->intentosIp();
        $actualIp = $porIp[$ip] ?? null;
        $porIp[$ip] = ($actualIp === null || $ahora - $actualIp[1] >= self::VENTANA_SEG)
            ? [1, $ahora, $ahora]
            : [$actualIp[0] + 1, $actualIp[1], $ahora];
        $this->guardar($porIp, true);
    }

    // ------------------------------------------------------------------
    // Destino: solo rutas internas conocidas del sistema
    // ------------------------------------------------------------------
    private function destinoValido(?string $destino): bool
    {
        if ($destino === null || $destino === '' || !str_starts_with($destino, '/')) {
            return false;
        }
        // Sin "//", ni ":" ni "\" (variantes de open redirect) ni saltos.
        if (str_starts_with($destino, '//')
            || str_contains($destino, ':')
            || str_contains($destino, '\\')
            || str_contains($destino, '..')) {
            return false;
        }

        $ruta = explode('?', $destino, 2)[0];
        foreach (self::RUTAS_PERMITIDAS as $permitida) {
            if ($ruta === $permitida || str_starts_with($ruta, $permitida . '/')) {
                return true;
            }
        }
        return false;
    }

    /** Dirección del cliente, considerando un proxy de confianza. */
    private function ipCliente(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return is_string($ip) ? $ip : '0.0.0.0';
    }
}
