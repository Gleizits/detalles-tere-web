<?php

/**
 * Carga las variables de entorno desde el archivo .env del proyecto.
 *
 * Es un lector propio (sin dependencias externas) y SOLO lee el .env que
 * vive en la raíz de ESTE proyecto: nada de configuración global.
 *
 * Formato admitido:
 *   CLAVE=valor
 *   CLAVE="valor con espacios"
 *   # comentario
 *
 * Las variables ya definidas en el entorno del servidor (Render, Apache)
 * tienen prioridad sobre el archivo.
 */
final class Entorno
{
    /** @var array<string,string> */
    private static array $valores = [];

    private static bool $cargado = false;

    private function __construct()
    {
    }

    /** Lee el .env una sola vez por petición. */
    public static function cargar(): void
    {
        if (self::$cargado) {
            return;
        }
        self::$cargado = true;

        $archivo = APP_RUTA . '/.env';
        if (!is_file($archivo) || !is_readable($archivo)) {
            return;
        }

        $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lineas === false) {
            return;
        }

        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#')) {
                continue;
            }

            $igual = strpos($linea, '=');
            if ($igual === false) {
                continue;
            }

            $clave = trim(substr($linea, 0, $igual));
            $valor = trim(substr($linea, $igual + 1));

            // Quita comillas dobles o simples envolventes.
            if ($valor !== '' && ($valor[0] === '"' || $valor[0] === "'")) {
                $comilla = $valor[0];
                $ultimo = strlen($valor) - 1;
                if ($ultimo > 0 && $valor[$ultimo] === $comilla) {
                    $valor = substr($valor, 1, $ultimo - 1);
                }
            }

            if ($clave !== '') {
                self::$valores[$clave] = $valor;
            }
        }
    }

    /**
     * Devuelve el valor de la variable: entorno del sistema > .env > defecto.
     */
    public static function obtener(string $clave, ?string $defecto = null): ?string
    {
        self::cargar();

        $delSistema = getenv($clave);
        if ($delSistema !== false && $delSistema !== '') {
            return $delSistema;
        }

        if (array_key_exists($clave, self::$valores)) {
            return self::$valores[$clave];
        }

        return $defecto;
    }

    /** Versión booleana (acepta 1/0, true/false, sí/no). */
    public static function booleano(string $clave, bool $defecto = false): bool
    {
        $valor = self::obtener($clave);
        if ($valor === null) {
            return $defecto;
        }
        return in_array(strtolower($valor), ['1', 'true', 'yes', 'on', 'sí', 'si'], true);
    }

    /** Versión entera. */
    public static function entero(string $clave, int $defecto): int
    {
        $valor = self::obtener($clave);
        return $valor !== null && is_numeric($valor) ? (int) $valor : $defecto;
    }
}
