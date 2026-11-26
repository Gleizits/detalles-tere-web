<?php

namespace Modelo;

/**
 * Pedido reservado. Reglas de negocio:
 *  - ADELANTO mínimo 50% del TOTAL (solo admin puede bajarlo).
 *  - ADELANTO = monto pagado hasta ahora (se acumula con abonos).
 *  - Faltante = TOTAL - ADELANTO.
 *  - 3 meses para canjear, 2 meses para recoger, 1 mes para pagar el total.
 *  - ESTADO_PRODUCCION='HECHO' es punto de no retorno.
 *  - Al crear, el stock de los ítems se descuenta (se apartan).
 */
class Pedido
{
    public int $idPedido = 0;
    public ?int $idCliente = null;
    public string $nombrePaga = '';
    public ?string $nombreRecoge = null;
    public string $celular = '';
    public ?string $fechaRecojo = null;      // 'Y-m-d'
    public ?string $horaRecojo = null;       // 'HH:mm'
    public ?float $adelanto = null;
    public ?float $total = null;
    public string $estado = '';              // PENDIENTE / COMPLETO / RECOGIDO / CANCELADO
    public string $estadoProduccion = '';    // PENDIENTE / HECHO / CANCELADO
    public int $idUsuario = 0;
    public ?string $fechaCreacion = null;
    public ?string $fechaLimiteCanje = null;
    public ?string $fechaLimiteRecojo = null;
    public ?string $fechaLimitePago = null;
    public ?string $fechaPagoTotal = null;

    // Datos de apoyo (no son columnas de la tabla)
    public string $nombreCliente = '';
    /** @var DetalleVenta[] */
    public array $lineas = [];

    /** Cuánto falta por pagar: TOTAL - pagado. */
    public function faltante(): float
    {
        if ($this->total === null) {
            return 0.0;
        }
        $pagado = $this->adelanto ?? 0.0;
        return round($this->total - $pagado, 2);
    }

    /** true si ya se pagó el total. */
    public function pagadoTotal(): bool
    {
        return $this->total !== null
            && $this->adelanto !== null
            && $this->adelanto >= $this->total;
    }
}
