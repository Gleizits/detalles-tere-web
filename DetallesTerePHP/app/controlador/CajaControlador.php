<?php

namespace Controlador;

use Dao\CajaDAO;
use Modelo\Caja;

/**
 * Controlador de Caja (CajaServlet): apertura y cierre manual, ambos roles.
 * GET muestra el estado (abierta / cerrada) y el historial reciente.
 * POST abre o cierra según el parámetro "accion".
 */
final class CajaControlador extends ControladorBase
{
    /** Límite superior del monto, igual que en el servlet. */
    private const MONTO_MAXIMO = 1000000.0;

    /** GET /caja */
    public function index(): never
    {
        $this->estado();
    }

    /** POST /caja (abrir o cerrar) */
    public function procesar(): never
    {
        $accion = valorParam('accion');

        if ($accion === 'abrir') {
            $this->abrir();
        } elseif ($accion === 'cerrar') {
            $this->cerrar();
        }

        $this->ir('/caja');
    }

    /** Abre una caja con el monto inicial indicado. */
    private function abrir(): void
    {
        $monto = decimalParam('montoInicial');

        if ($monto === null) {
            $this->estado('Ingresa un monto válido.');
        }
        if ($monto < 0 || $monto > self::MONTO_MAXIMO) {
            $this->estado('El monto inicial debe estar entre 0 y 1,000,000.00.');
        }

        $dao = new CajaDAO();
        $idUsuario = $this->usuario()->id;

        if ($dao->cajaAbierta($idUsuario) !== null) {
            $this->estado('Ya tienes una caja abierta.');
        }

        $dao->abrir($idUsuario, $monto);
    }

    /** Cierra la caja abierta, guardando esperado, contado y diferencia. */
    private function cerrar(): void
    {
        $idCajaTxt = valorParam('idCaja');
        $idCaja = enteroParam('idCaja', -1);
        $montoReal = decimalParam('montoReal');

        if (preg_match('/^-?\d+$/', $idCajaTxt) !== 1 || $montoReal === null) {
            $this->estado('Ingresa un monto válido.');
        }
        if ($montoReal < 0 || $montoReal > self::MONTO_MAXIMO) {
            $this->estado('El monto contado debe estar entre 0 y 1,000,000.00.');
        }

        $dao = new CajaDAO();
        $abierta = $dao->cajaAbierta($this->usuario()->id);

        // El servlet redirige sin mensaje si la caja ya no es la abierta.
        if ($abierta === null || $abierta->idCaja !== $idCaja) {
            return;
        }

        $ventasEfectivo = $dao->ventasEfectivo($idCaja);
        $abierta->montoFinal = round(($abierta->montoInicial ?? 0.0) + $ventasEfectivo, 2);
        $abierta->montoReal = $montoReal;
        $dao->cerrar($abierta);

        // Equivale al reenvío del servlet con el atributo "resumen".
        $this->estado(null, $abierta);
    }

    /**
     * Pinta la vista con el estado actual (reenviarEstado del servlet).
     * Los errores y el resumen se pasan como datos de la vista, no como flash.
     */
    private function estado(?string $error = null, ?Caja $resumen = null): never
    {
        $dao = new CajaDAO();
        $idUsuario = $this->usuario()->id;
        $abierta = $dao->cajaAbierta($idUsuario);

        $datos = [
            'caja'      => $abierta,
            'historial' => $dao->historial($idUsuario, 7),
        ];

        if ($abierta !== null) {
            $ventasEfectivo = $dao->ventasEfectivo($abierta->idCaja);
            $datos['ventasEfectivo'] = $ventasEfectivo;
            $datos['esperado'] = round(($abierta->montoInicial ?? 0.0) + $ventasEfectivo, 2);
            $datos['ventasProductos'] = $dao->ventasProductosEfectivo($abierta->idCaja);
            $datos['pagosReservas'] = $dao->pagosReservasEfectivo($abierta->idCaja);
        }

        if ($error !== null) {
            $datos['error'] = $error;
        }
        if ($resumen !== null) {
            $datos['resumen'] = $resumen;
        }

        $this->ver('caja', 'Caja', '/caja', $datos);
    }
}
