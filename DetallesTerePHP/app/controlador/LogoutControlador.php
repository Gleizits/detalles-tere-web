<?php

namespace Controlador;

use Seguridad;

/** Cierra la sesión del usuario (equivalente a LogoutServlet). */
final class LogoutControlador extends ControladorBase
{
    public function procesar(): never
    {
        Seguridad::cerrarSesion();
        $this->ir('/login');
    }
}
