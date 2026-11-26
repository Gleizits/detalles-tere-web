<?php

namespace Controlador;

use Dao\CajaDAO;
use Dao\ClienteDAO;
use Dao\InventarioDAO;
use Dao\VentaDAO;
use Modelo\DetalleVenta;
use Throwable;

/**
 * Controlador de Venta (VentaServlet): punto de venta y cotizaciones.
 * El carrito vive en la sesión; el stock solo se descuenta al cobrar.
 * Si hay una cotización cargada, el cobro la convierte en venta.
 */
final class VentaControlador extends ControladorBase
{
    private const K_CARRITO = 'carrito';
    private const K_COTIZACION = 'cotizacionId';

    /** GET /venta */
    public function index(): never
    {
        $usuario = $this->usuario();
        $caja = (new CajaDAO())->cajaAbierta($usuario->id);

        $q = valorParam('q');
        $items = (new InventarioDAO())->listar($q === '' ? null : $q);

        // El empleado no ve precio de costo (coherencia con el servlet).
        if (!$usuario->esAdmin()) {
            foreach ($items as $item) {
                $item->precioCompra = null;
            }
        }

        $carrito = $this->carrito();
        $totalCarrito = 0.0;
        foreach ($carrito as $linea) {
            $totalCarrito += (float) ($linea->subtotal ?? 0);
        }

        $this->ver('venta', 'Venta', '/venta', [
            'caja'         => $caja,
            'items'        => $items,
            'q'            => $q,
            'carrito'      => $carrito,
            'totalCarrito' => round($totalCarrito, 2),
            'cotizacionId' => $this->cotizacionId(),
            'cotizaciones' => (new VentaDAO())->listarCotizaciones(),
        ]);
    }

    /** POST /venta: todas las acciones del carrito y del cobro. */
    public function procesar(): never
    {
        $accion = valorParam('accion');

        try {
            switch ($accion) {
                case 'agregar':
                    $this->agregar();
                    break;
                case 'eliminar':
                    $this->eliminar();
                    break;
                case 'vaciar':
                case 'cancelar_carga':
                    unset($_SESSION[self::K_CARRITO], $_SESSION[self::K_COTIZACION]);
                    break;
                case 'cargar_cotizacion':
                    $this->cargarCotizacion();
                    break;
                case 'cotizar':
                    $this->guardarCotizacion();
                    break;
                case 'cobrar':
                    $this->cobrar();
                    break;
                default:
                    break;
            }
        } catch (\LogicException $e) {
            // IllegalStateException / IllegalArgumentException del servlet.
            $this->aviso($e->getMessage(), true);
        }

        $this->ir('/venta');
    }

    /** Agrega un producto del inventario al carrito validando stock. */
    private function agregar(): void
    {
        $idInventario = enteroParam('idInventario', -1);
        $cantidad = enteroParam('cantidad', 1);

        if ($idInventario < 0 || $cantidad < 1) {
            $this->aviso('Cantidad inválida.', true);
            return;
        }
        if ($this->cotizacionId() !== null) {
            $this->aviso('Tienes una cotización cargada. Cancela su carga antes de agregar productos.', true);
            return;
        }

        $item = (new InventarioDAO())->buscarPorId($idInventario);
        if ($item === null || $item->precioVenta === null) {
            $this->aviso('Ese producto no está disponible para venta.', true);
            return;
        }

        $carrito = $this->carrito();
        $enCarrito = 0;
        $existente = null;
        foreach ($carrito as $linea) {
            if ($linea->idInventario === $idInventario) {
                $enCarrito += $linea->cantidad;
                $existente = $linea;
            }
        }

        if ($enCarrito + $cantidad > $item->stockAct) {
            $this->aviso('Stock insuficiente de «' . $item->nombre
                . '». Disponible: ' . $item->stockAct . '.', true);
            return;
        }

        if ($existente !== null) {
            $existente->cantidad += $cantidad;
            $existente->recalcularSubtotal();
        } else {
            $carrito[] = new DetalleVenta(
                $idInventario,
                $item->nombre,
                $cantidad,
                $item->precioVenta,
                $item->stockAct
            );
        }
        $this->guardarCarrito($carrito);

        $this->aviso('Agregado: ' . $item->nombre . ' x' . $cantidad . '.');
    }

    /** Quita una línea del carrito por su índice. */
    private function eliminar(): void
    {
        if ($this->cotizacionId() !== null) {
            $this->aviso('Usa «Cancelar carga» para salir de la cotización.', true);
            return;
        }

        $indice = enteroParam('indice', -1);
        $carrito = $this->carrito();
        if ($indice >= 0 && $indice < count($carrito)) {
            $nombre = $carrito[$indice]->nombre;
            array_splice($carrito, $indice, 1);
            $this->guardarCarrito($carrito);
            $this->aviso('Quitado: ' . $nombre . '.');
        }
    }

    /** Carga las líneas de una cotización en el carrito. */
    private function cargarCotizacion(): void
    {
        $idVenta = enteroParam('idVenta', -1);
        if ($idVenta < 0) {
            return;
        }

        $lineas = (new VentaDAO())->obtenerLineas($idVenta);
        if (count($lineas) === 0) {
            $this->aviso('Esa cotización ya no existe.', true);
            return;
        }

        $_SESSION[self::K_CARRITO] = array_values($lineas);
        $_SESSION[self::K_COTIZACION] = $idVenta;
        $this->aviso('Cotización #' . $idVenta . ' cargada. Elige el pago y cobra.');
    }

    /** Guarda el carrito como cotización de 24 h (sin descontar stock). */
    private function guardarCotizacion(): void
    {
        $carrito = $this->carrito();
        if (count($carrito) === 0) {
            $this->aviso('El carrito está vacío.', true);
            return;
        }
        if ($this->cotizacionId() !== null) {
            $this->aviso('Ya hay una cotización cargada. Cancela su carga antes de guardar otra.', true);
            return;
        }

        $id = (new VentaDAO())->guardarCotizacion($carrito);
        unset($_SESSION[self::K_CARRITO]);
        $this->aviso('Cotización #' . $id . ' guardada. Vence en 24 h (no reserva stock).');
    }

    /** Cobra el carrito: efectivo, Yape o canje de saldo a favor. */
    private function cobrar(): void
    {
        $usuario = $this->usuario();
        $caja = (new CajaDAO())->cajaAbierta($usuario->id);
        if ($caja === null) {
            $this->aviso('Abre la caja antes de cobrar.', true);
            return;
        }

        $carrito = $this->carrito();
        if (count($carrito) === 0) {
            $this->aviso('El carrito está vacío.', true);
            return;
        }

        $tipoPago = valorParam('tipoPago');
        if ($tipoPago !== 'YAPE' && $tipoPago !== 'EFECTIVO' && $tipoPago !== 'CREDITO') {
            $this->aviso('Elige un método de pago.', true);
            return;
        }

        $total = 0.0;
        foreach ($carrito as $linea) {
            $total += (float) ($linea->subtotal ?? 0);
        }
        $total = round($total, 2);

        if ($tipoPago === 'CREDITO') {
            $cliente = (new ClienteDAO())->buscarPorWsp(valorParam('celularCredito'));
            if ($cliente === null) {
                $this->aviso('No encontramos un cliente con ese celular.', true);
                return;
            }
            if ($this->cotizacionId() !== null) {
                $this->aviso('El canje de crédito no se puede aplicar a una cotización cargada.', true);
                return;
            }

            try {
                (new VentaDAO())->confirmarVentaConCredito(
                    $carrito,
                    $caja->idCaja,
                    $usuario->id,
                    $cliente->idCliente
                );
            } catch (Throwable $e) {
                $this->aviso($this->mensajeDeCredito($e), true);
                return;
            }

            unset($_SESSION[self::K_CARRITO]);
            $this->aviso('Canje registrado. Se utilizó todo el saldo vigente del cliente.');
            return;
        }

        if ($tipoPago === 'YAPE') {
            // En Yape se paga el total exacto; no hay vuelto.
            $montoRecibido = $total;
        } else {
            $montoRecibido = decimalParam('montoRecibido');
            if ($montoRecibido === null) {
                $this->aviso('Ingresa el monto recibido en efectivo.', true);
                return;
            }
            if ($montoRecibido < $total) {
                $this->aviso('El monto recibido es menor que el total.', true);
                return;
            }
        }

        $cotizacionId = $this->cotizacionId();
        $dao = new VentaDAO();
        if ($cotizacionId !== null) {
            $dao->convertirCotizacion($cotizacionId, $caja->idCaja, $usuario->id, $tipoPago);
        } else {
            $dao->confirmarVenta($carrito, $caja->idCaja, $usuario->id, $tipoPago);
        }

        unset($_SESSION[self::K_CARRITO], $_SESSION[self::K_COTIZACION]);

        $vuelto = '';
        if ($tipoPago === 'EFECTIVO') {
            $vuelto = ' Vuelto: S/ ' . numJs($montoRecibido - $total) . '.';
        }
        $this->aviso('Venta registrada. Total: S/ ' . numJs($total) . '.' . $vuelto);
    }

    /** Devuelve el carrito de la sesión, creándolo si no existe. */
    private function carrito(): array
    {
        $carrito = $_SESSION[self::K_CARRITO] ?? null;
        if (!is_array($carrito)) {
            $carrito = [];
            $_SESSION[self::K_CARRITO] = $carrito;
        }
        return $carrito;
    }

    /** Los arrays de la sesión se guardan por valor: hay que reasignarlos. */
    private function guardarCarrito(array $carrito): void
    {
        $_SESSION[self::K_CARRITO] = $carrito;
    }

    /** ID de la cotización cargada, o null. */
    private function cotizacionId(): ?int
    {
        $id = $_SESSION[self::K_COTIZACION] ?? null;
        if (is_int($id)) {
            return $id;
        }
        return is_string($id) && ctype_digit($id) ? (int) $id : null;
    }

    /** Mensaje para el usuario tras un error al canjear crédito. */
    private function mensajeDeCredito(Throwable $e): string
    {
        $causa = $e;
        while ($causa->getPrevious() !== null) {
            $causa = $causa->getPrevious();
        }
        $mensaje = $causa->getMessage();

        if ($mensaje !== null && str_contains($mensaje, 'saldo a favor vigente')) {
            return $mensaje;
        }
        if ($mensaje !== null && stripos($mensaje, 'stock') !== false) {
            return 'No hay stock suficiente para completar el canje.';
        }
        return 'No se pudo completar el canje. Revisa el saldo y vuelve a intentarlo.';
    }

    /** Equivale al guardado de mensaje en atributos de sesión del servlet. */
    private function aviso(string $mensaje, bool $error = false): void
    {
        flash($error ? 'error' : 'aviso', $mensaje, $error);
    }
}
