<?php

namespace Controlador;

use Dao\TerminoDAO;
use InvalidArgumentException;
use Throwable;

/**
 * Términos de búsqueda y sus asociaciones con el inventario (solo admin).
 * Acciones: crear, asociar y desasociar.
 */
final class TerminoControlador extends ControladorBase
{
    /** GET /terminos */
    public function index(): never
    {
        $this->cargarVista();
    }

    /** POST /terminos */
    public function procesar(): never
    {
        try {
            switch (valorParam('accion')) {
                case 'crear':
                    $this->crear();
                    break;
                case 'asociar':
                    (new TerminoDAO())->asociar(
                        $this->positivo(valorParam('idTermino')),
                        $this->positivo(valorParam('idInventario'))
                    );
                    break;
                case 'desasociar':
                    (new TerminoDAO())->desasociar(
                        $this->positivo(valorParam('idTermino')),
                        $this->positivo(valorParam('idInventario'))
                    );
                    break;
                default:
                    throw new InvalidArgumentException('Acción no válida.');
            }
        } catch (InvalidArgumentException $e) {
            $this->cargarVista($e->getMessage());
        } catch (Throwable $e) {
            error_log('Error en el módulo de términos: ' . $e->getMessage());
            $this->cargarVista('No pudimos guardar el cambio. Intenta de nuevo.');
        }

        flash('aviso', 'Cambios guardados.');
        $this->ir('/terminos');
    }

    private function crear(): void
    {
        $palabra = valorParam('palabra');
        if ($palabra === '' || mb_strlen($palabra) > 75) {
            throw new InvalidArgumentException(
                'El término es obligatorio y puede tener máximo 75 caracteres.'
            );
        }

        (new TerminoDAO())->crear($palabra, $this->usuario()->id);
    }

    private function cargarVista(?string $error = null): never
    {
        $dao = new TerminoDAO();
        $this->ver('terminos', 'Términos de búsqueda', '/terminos', [
            'terminos'   => $dao->listar(),
            'inventario' => $dao->listarInventarioActivo(),
            'error'      => $error,
        ]);
    }

    private function positivo(string $valor): int
    {
        if (preg_match('/^\d+$/', $valor) !== 1 || (int) $valor <= 0) {
            throw new InvalidArgumentException('Selecciona valores válidos.');
        }
        return (int) $valor;
    }
}
