<?php

namespace Modelo;

/**
 * Usuario del sistema. Roles: admin | empleado.
 * Ambos roles pueden abrir caja; solo admin ve costos/proveedor.
 */
class Usuario
{
    public int $id;
    public string $username;
    public string $nombre;
    public string $apellido;
    public string $rol;
    public ?string $correo = null;
    public bool $estado = true;

    public function __construct(
        int $id = 0,
        string $username = '',
        string $nombre = '',
        string $apellido = '',
        string $rol = '',
        ?string $correo = null,
        bool $estado = true
    ) {
        $this->id = $id;
        $this->username = $username;
        $this->nombre = $nombre;
        $this->apellido = $apellido;
        $this->rol = $rol;
        $this->correo = $correo;
        $this->estado = $estado;
    }

    /** Solo el administrador accede a costos, proveedor y reportes. */
    public function esAdmin(): bool
    {
        return strtolower($this->rol) === 'admin';
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombre . ' ' . $this->apellido);
    }
}
