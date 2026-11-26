<?php

namespace Modelo;

/**
 * Caja. Apertura/cierre manual por usuario (ambos roles).
 * 1 caja abierta por usuario (FECHA_CIERRE IS NULL).
 */
class Caja
{
    public int $idCaja = 0;
    public int $idUsuario = 0;
    public ?float $montoInicial = null;
    public ?float $montoFinal = null;   // calculado al cierre
    public ?float $montoReal = null;    // conteo físico al cierre
    public ?float $diferencia = null;   // montoReal - montoFinal
    public ?string $fechaApertura = null;  // 'Y-m-d H:i:s' de la BD
    public ?string $fechaCierre = null;
    public ?string $nombreUsuario = null;  // para historial/reportes

    /** true si la caja sigue abierta (sin fecha de cierre). */
    public function esAbierta(): bool
    {
        return $this->fechaCierre === null;
    }
}
