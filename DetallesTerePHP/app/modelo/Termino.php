<?php

namespace Modelo;

/**
 * Término/palabra clave para búsquedas del inventario.
 * ASOCIACIONES reproduce getAsociaciones() del modelo Java.
 */
class Termino
{
    public int $idTermino = 0;
    public int $idUsuario = 0;
    public string $palabra = '';
    public string $asociaciones = '';   // nombres de los artículos asociados
    public int $numProductos = 0;       // apoyo de lista (compatibilidad)
}
