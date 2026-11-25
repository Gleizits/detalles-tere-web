<?php

namespace Controlador;

use Dao\IngresoDAO;
use Dao\InventarioDAO;
use Dao\ProveedorDAO;
use DateTime;
use InvalidArgumentException;
use Modelo\IngresoLinea;
use Throwable;

/**
 * Ingresos de mercadería (solo admin).
 * Acciones: crearProveedor y registrar, con sus líneas de inventario.
 */
final class IngresoControlador extends ControladorBase
{
    private const MAX_PRECIO = 1000000.0;

    /** GET /ingresos */
    public function index(): never
    {
        $this->cargarVista();
    }

    /** POST /ingresos */
    public function procesar(): never
    {
        try {
            switch (valorParam('accion')) {
                case 'crearProveedor':
                    $this->crearProveedor();
                    flash('aviso', 'Proveedor guardado.');
                    break;

                case 'registrar':
                    $this->registrarIngreso();
                    flash('aviso', 'Ingreso registrado y stock actualizado.');
                    break;

                default:
                    flash('error', 'Acción no válida.');
            }
        } catch (InvalidArgumentException $e) {
            $this->cargarVista($e->getMessage());
        } catch (Throwable $e) {
            error_log('Error en el módulo de ingresos: ' . $e->getMessage());
            $this->cargarVista('No pudimos guardar la información. Intenta de nuevo.');
        }

        $this->ir('/ingresos');
    }

    private function crearProveedor(): void
    {
        $nombre = $this->obligatorio(valorParam('nombreProveedor'), 50,
            'Indica el nombre del proveedor.');
        $numero = $this->obligatorio(valorParam('numeroProveedor'), 12,
            'Indica el número del proveedor.');

        (new ProveedorDAO())->crear($nombre, $numero);
    }

    private function registrarIngreso(): void
    {
        $idProveedor = enteroParam('idProveedor');
        if ($idProveedor <= 0) {
            throw new InvalidArgumentException('Selecciona un proveedor.');
        }

        $fecha = valorParam('fechaIngreso');
        $fechaDate = DateTime::createFromFormat('!Y-m-d', $fecha);
        if ($fechaDate === false || $fechaDate->format('Y-m-d') !== $fecha) {
            throw new InvalidArgumentException('Selecciona una fecha válida.');
        }

        (new IngresoDAO())->registrar(
            $idProveedor,
            $this->usuario()->id,
            $fecha,
            $this->leerLineas()
        );
    }

    /** @return IngresoLinea[] */
    private function leerLineas(): array
    {
        $ids = $_POST['idInventario'] ?? [];
        $cantidades = $_POST['cantidad'] ?? [];
        $precios = $_POST['precioCompra'] ?? [];
        $observaciones = $_POST['observaciones'] ?? [];

        if (!is_array($ids) || !is_array($cantidades) || !is_array($precios)
            || !is_array($observaciones)
            || count($ids) !== count($cantidades)
            || count($ids) !== count($precios)) {
            throw new InvalidArgumentException('Agrega al menos un artículo válido.');
        }

        $lineas = [];
        foreach ($ids as $i => $idTxt) {
            if (trim((string) $idTxt) === '') {
                continue; // Las filas vacías se ignoran.
            }

            $id = $this->positivo((string) $idTxt, 'Selecciona un artículo válido.');
            $cantidad = $this->positivo((string) ($cantidades[$i] ?? ''),
                'La cantidad debe ser un entero positivo.');
            $precio = $this->decimal((string) ($precios[$i] ?? ''),
                'Captura un precio de compra válido.');

            $linea = new IngresoLinea();
            $linea->idInventario = $id;
            $linea->cantidad = $cantidad;
            $linea->precioCompraUnitario = $precio;
            $linea->recalcularSubtotal();
            $obs = trim((string) ($observaciones[$i] ?? ''));
            $linea->observaciones = $obs === '' ? null : mb_substr($obs, 0, 100);
            $lineas[] = $linea;
        }

        if ($lineas === []) {
            throw new InvalidArgumentException('Agrega al menos un artículo al ingreso.');
        }
        return $lineas;
    }

    private function cargarVista(?string $error = null): never
    {
        $this->ver('ingresos', 'Ingresos', '/ingresos', [
            'proveedores' => (new ProveedorDAO())->listar(),
            'inventario'  => (new InventarioDAO())->listarActivos(),
            'ingresos'    => (new IngresoDAO())->listarRecientes(10),
            'fechaActual' => date('Y-m-d'),
            'error'       => $error,
        ]);
    }

    private function obligatorio(string $valor, int $maximo, string $mensaje): string
    {
        $resultado = mb_substr(trim($valor), 0, $maximo);
        if ($resultado === '') {
            throw new InvalidArgumentException($mensaje);
        }
        return $resultado;
    }

    private function positivo(string $valor, string $mensaje): int
    {
        if (preg_match('/^\d+$/', trim($valor)) !== 1 || (int) $valor <= 0) {
            throw new InvalidArgumentException($mensaje);
        }
        return (int) $valor;
    }

    private function decimal(string $valor, string $mensaje): float
    {
        $v = str_replace(',', '.', trim($valor));
        if (preg_match('/^\d+(?:\.\d+)?$/', $v) !== 1) {
            throw new InvalidArgumentException($mensaje);
        }
        $numero = (float) $v;
        if ($numero > self::MAX_PRECIO) {
            throw new InvalidArgumentException($mensaje);
        }
        return $numero;
    }
}
