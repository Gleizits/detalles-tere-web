<?php

namespace Controlador;

use ExcepcionHttp;
use Modelo\Usuario;
use Seguridad;
use Vista;

/**
 * Controlador base: ofrece las operaciones comunes a todos los módulos
 * (pintar vista, redirigir, leer parámetros y comprobar el rol).
 *
 * Cada controlador concreto implementa dos métodos:
 *   index()    -> muestra la vista (GET)
 *   procesar() -> procesa el formulario (POST)
 */
abstract class ControladorBase
{
    /** Pinta una vista con la plantilla común (cabecera + pie). */
    protected function ver(string $vista, string $titulo, string $seccion, array $datos = []): never
    {
        Vista::mostrar($vista, $titulo, $seccion, $datos);
        exit;
    }

    /** Pinta una página suelta (login): sin cabecera ni pie de la aplicación. */
    protected function verSuelta(string $vista, string $titulo, array $datos = []): never
    {
        Vista::suelta($vista, $titulo, $datos);
        exit;
    }

    /** Redirige a otra ruta y termina. */
    protected function ir(string $ruta): never
    {
        redirigir(url($ruta));
    }

    /** Redirige a la misma ruta conservando la query string. */
    protected function volver(): never
    {
        redirigir(url($_SERVER['REQUEST_URI'] ?? '/panel'));
    }

    /** true si el usuario en sesión es administrador. */
    protected function esAdmin(): bool
    {
        $u = Seguridad::usuarioEnSesion();
        return $u !== null && $u->esAdmin();
    }

    /** Usuario en sesión (siempre existe: las rutas exigen login). */
    protected function usuario(): Usuario
    {
        $u = Seguridad::usuarioEnSesion();
        if ($u === null) {
            throw new ExcepcionHttp(403);
        }
        return $u;
    }
}
