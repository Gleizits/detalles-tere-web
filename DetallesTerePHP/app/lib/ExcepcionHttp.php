<?php

/**
 * Excepción que lleva un código HTTP asociado (404, 405, 403...).
 * El front controller la captura y pinta la página de error correspondiente.
 */
final class ExcepcionHttp extends RuntimeException
{
    public function __construct(
        private readonly int $codigo,
        string $mensaje = ''
    ) {
        parent::__construct($mensaje !== '' ? $mensaje : self::textoPorDefecto($codigo), $codigo);
    }

    public function codigo(): int
    {
        return $this->codigo;
    }

    private static function textoPorDefecto(int $codigo): string
    {
        return match ($codigo) {
            403 => 'No tienes permiso para acceder a esta sección.',
            404 => 'No encontramos la página que buscas.',
            405 => 'Método no permitido para esta ruta.',
            default => 'No pudimos completar la operación.',
        };
    }
}
