<?php

namespace Controlador;

/**
 * Panel de inicio (dashboard). Saludo según el momento del día.
 * El rol define qué ve: admin ve accesos a reportes y administración;
 * empleado solo saludo + accesos operativos.
 */
final class PanelControlador extends ControladorBase
{
    public function index(): never
    {
        $this->ver('panel', 'Inicio', '/panel', [
            'saludo' => saludoPorHorario(),
        ]);
    }
}
