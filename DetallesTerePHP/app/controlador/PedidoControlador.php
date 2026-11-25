<?php

namespace Controlador;

use Dao\CajaDAO;
use Dao\InventarioDAO;
use Dao\PedidoDAO;
use Modelo\DetalleVenta;
use Modelo\Pedido;
use Throwable;

/**
 * Controlador de pedidos reservados (PedidoServlet).
 *
 * Pantalla propia: el carrito de la reserva vive en la sesión bajo
 * "pedidoCarrito". Al crear se aparta stock y se cobra el adelanto (o el
 * total) contra la caja abierta del usuario.
 */
final class PedidoControlador extends ControladorBase
{
    private const K_CARRITO = 'pedidoCarrito';
    private const MILLON = 1000000.0;

    // ------------------------------------------------------------------
    // GET: lista / formulario nuevo / detalle
    // ------------------------------------------------------------------

    /** GET /pedidos */
    public function index(): never
    {
        if (isset($_GET['id'])) {
            $this->mostrarDetalle(trim((string) $_GET['id']));
        }
        if (isset($_GET['nuevo'])) {
            $this->mostrarFormulario();
        }
        $this->mostrarLista();
    }

    private function mostrarLista(): void
    {
        $q = valorParam('q');
        $usuario = $this->usuario();
        $idUsuario = $usuario->esAdmin() ? null : $usuario->id;

        $this->ver('pedidos', 'Pedidos reservados', '/pedidos', [
            'pedidos' => (new PedidoDAO())->listar($q, $idUsuario),
            'q'       => $q,
        ]);
    }

    private function mostrarFormulario(): void
    {
        $q = valorParam('q');
        $items = (new InventarioDAO())->listar($q);

        // Un empleado no ve costos de compra.
        if (!$this->esAdmin()) {
            foreach ($items as $item) {
                $item->precioCompra = null;
            }
        }

        $carrito = $this->carrito();
        $total = 0.0;
        foreach ($carrito as $linea) {
            $total += (float) ($linea->subtotal ?? 0.0);
        }

        $this->ver('pedido_form', 'Nuevo pedido reservado', '/pedidos', [
            'items'        => $items,
            'q'            => $q,
            'carrito'      => $carrito,
            'totalCarrito' => round($total, 2),
            'caja'         => (new CajaDAO())->cajaAbierta($this->usuario()->id),
            'hoy'          => date('Y-m-d'),
        ]);
    }

    private function mostrarDetalle(string $idTxt): void
    {
        if (preg_match('/^\d+$/', $idTxt) !== 1) {
            $this->ir('/pedidos');
        }
        $id = (int) $idTxt;
        $pedido = (new PedidoDAO())->buscarPorId($id);

        if ($pedido === null) {
            flash('error', 'Ese pedido no existe.', true);
            $this->ir('/pedidos');
        }
        if (!$this->esAdmin() && $pedido->idUsuario !== $this->usuario()->id) {
            flash('error', 'Solo puedes ver y modificar tus propios pedidos.', true);
            $this->ir('/pedidos');
        }

        $this->ver('pedido_detalle', 'Pedido #' . $pedido->idPedido, '/pedidos', [
            'pedido' => $pedido,
            'caja'   => (new CajaDAO())->cajaAbierta($this->usuario()->id),
        ]);
    }

    // ------------------------------------------------------------------
    // POST: acciones
    // ------------------------------------------------------------------

    /** POST /pedidos */
    public function procesar(): never
    {
        $accion = valorParam('accion');
        $destino = '/pedidos';

        try {
            switch ($accion) {
                case 'agregar':
                    $this->agregar();
                    $destino = '/pedidos?nuevo=1';
                    break;

                case 'eliminar':
                    $this->eliminar();
                    $destino = '/pedidos?nuevo=1';
                    break;

                case 'vaciar':
                    unset($_SESSION[self::K_CARRITO]);
                    $destino = '/pedidos?nuevo=1';
                    break;

                case 'crear':
                    // crear() termina siempre con una redirección.
                    $this->crear();
                    break;

                case 'abono': {
                    $id = enteroParam('idPedido', -1);
                    $this->validarPropiedadPedidoId($id);
                    $this->abono($id);
                    $destino = '/pedidos?id=' . $id;
                    break;
                }

                case 'hecho': {
                    $id = enteroParam('idPedido', -1);
                    $this->validarPropiedadPedidoId($id);
                    (new PedidoDAO())->marcarHecho($id);
                    flash('aviso', 'Pedido marcado como HECHO. Ya no se puede cancelar; se cobrará el total.');
                    $destino = '/pedidos?id=' . $id;
                    break;
                }

                case 'cancelar': {
                    $id = enteroParam('idPedido', -1);
                    $this->validarPropiedadPedidoId($id);
                    (new PedidoDAO())->cancelar($id, $this->usuario()->id);
                    flash('aviso', 'Pedido cancelado. El stock volvió al inventario y el adelanto '
                        . 'quedó como saldo a favor; no se devuelve en efectivo.');
                    $destino = '/pedidos?id=' . $id;
                    break;
                }

                case 'recoger': {
                    $id = enteroParam('idPedido', -1);
                    $this->validarPropiedadPedidoId($id);
                    (new PedidoDAO())->recoger($id);
                    flash('aviso', 'Pedido entregado (RECOGIDO).');
                    $destino = '/pedidos?id=' . $id;
                    break;
                }

                default:
                    break;
            }
        } catch (Throwable $e) {
            flash('error', $this->mensajeAmigable($e), true);
        }

        $this->ir($destino);
    }

    // ------------------------------------------------------------------
    // Acciones del carrito
    // ------------------------------------------------------------------

    private function agregar(): void
    {
        $idInventario = enteroParam('idInventario', -1);
        $cantidad = enteroParam('cantidad', 1);

        if ($idInventario < 0 || $cantidad < 1) {
            flash('error', 'Cantidad inválida.', true);
            return;
        }

        $item = (new InventarioDAO())->buscarPorId($idInventario);
        if ($item === null || $item->precioVenta === null) {
            flash('error', 'Ese producto no está disponible para reserva.', true);
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
            flash('error', 'Stock insuficiente de «' . $item->nombre . '». Disponible: '
                . $item->stockAct . '.', true);
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
        $_SESSION[self::K_CARRITO] = $carrito;

        flash('aviso', 'Agregado: ' . $item->nombre . ' x' . $cantidad . '.');
    }

    private function eliminar(): void
    {
        $indice = enteroParam('indice', -1);
        $carrito = $this->carrito();

        if ($indice >= 0 && $indice < count($carrito)) {
            $nombre = $carrito[$indice]->nombre;
            array_splice($carrito, $indice, 1);
            $_SESSION[self::K_CARRITO] = $carrito;
            flash('aviso', 'Quitado: ' . $nombre . '.');
        }
    }

    /** Crea la reserva: Validaciones, stock, pago y vencimientos. */
    private function crear(): void
    {
        $usuario = $this->usuario();
        $caja = (new CajaDAO())->cajaAbierta($usuario->id);
        if ($caja === null) {
            flash('error', 'Abre la caja antes de registrar un pedido.', true);
            $this->ir('/pedidos?nuevo=1');
        }

        $carrito = $this->carrito();
        if ($carrito === []) {
            flash('error', 'Agrega al menos un producto a la reserva.', true);
            $this->ir('/pedidos?nuevo=1');
        }

        $celular = valorParam('celular');
        $nombrePaga = valorParam('nombrePaga');
        $nombreRecoge = valorParam('nombreRecoge');
        $fechaRecojoTxt = valorParam('fechaRecojo');
        $horaRecojo = valorParam('horaRecojo');
        $tipoReserva = valorParam('tipoReserva');
        $metodo = valorParam('metodo');

        if ($celular === '' || $nombrePaga === '') {
            flash('error', 'Ingresa el celular y el nombre de quien paga.', true);
            $this->ir('/pedidos?nuevo=1');
        }

        $fechaRecojo = $this->fechaValida($fechaRecojoTxt);
        if ($fechaRecojo === null) {
            flash('error', 'Elige la fecha de recojo.', true);
            $this->ir('/pedidos?nuevo=1');
        }

        if ($metodo !== 'YAPE' && $metodo !== 'EFECTIVO') {
            flash('error', 'Elige un método de pago (Yape o Efectivo).', true);
            $this->ir('/pedidos?nuevo=1');
        }

        $total = 0.0;
        foreach ($carrito as $linea) {
            $total += (float) ($linea->subtotal ?? 0.0);
        }
        $total = round($total, 2);

        if ($tipoReserva === 'completo') {
            $monto = $total;
        } else {
            $monto = decimalParam('monto');
            if ($monto === null) {
                flash('error', 'Ingresa el adelanto o elige pago completo.', true);
                $this->ir('/pedidos?nuevo=1');
            }
        }

        if ($monto < 0 || $monto > $total) {
            flash('error', 'El monto debe estar entre S/ 0.00 y el total (S/ '
                . numJs($total) . ').', true);
            $this->ir('/pedidos?nuevo=1');
        }

        // Adelanto mínimo del 50%; solo el administrador puede bajarlo.
        $minimo = round($total * 0.5, 2);
        if ($monto < $minimo && !$usuario->esAdmin()) {
            flash('error', 'El adelanto mínimo es 50% (S/ ' . numJs($minimo)
                . '). Solo el administrador puede modificarlo.', true);
            $this->ir('/pedidos?nuevo=1');
        }

        $completo = $monto >= $total;
        $pedido = new Pedido();
        $pedido->nombrePaga = $nombrePaga;
        $pedido->nombreRecoge = $nombreRecoge !== '' ? $nombreRecoge : null;
        $pedido->celular = $celular;
        $pedido->fechaRecojo = $fechaRecojo;
        $pedido->horaRecojo = $horaRecojo !== '' ? $horaRecojo : null;
        $pedido->adelanto = $monto;
        $pedido->total = $total;
        $pedido->estado = $completo ? 'COMPLETO' : 'PENDIENTE';
        $pedido->estadoProduccion = 'PENDIENTE';
        $pedido->idUsuario = $usuario->id;

        // Vencimientos (3 meses canje, 2 desde el recojo, 1 mes pago).
        // El DAO puede recalcularlos en SQL; se inicializan aquí para que la
        // cabecera sea correcta también si los inserta tal cual.
        $ahora = date('Y-m-d H:i:s');
        $pedido->fechaCreacion = $ahora;
        $pedido->fechaLimiteCanje = date('Y-m-d H:i:s', strtotime('+3 months'));
        $pedido->fechaLimiteRecojo = date('Y-m-d H:i:s', strtotime($fechaRecojo . ' +2 months'));
        $pedido->fechaLimitePago = date('Y-m-d H:i:s', strtotime('+1 month'));
        $pedido->fechaPagoTotal = $completo ? $ahora : null;

        try {
            $idPedido = (new PedidoDAO())->crear($pedido, $carrito, $caja->idCaja, $metodo);
        } catch (Throwable $e) {
            error_log('[DetallesTere] Error al crear pedido: ' . $e->getMessage());
            flash('error', $this->mensajeAmigable($e), true);
            $this->ir('/pedidos?nuevo=1');
        }

        unset($_SESSION[self::K_CARRITO]);
        flash('aviso', 'Pedido #' . $idPedido . ' reservado. Stock apartado. '
            . ($completo ? 'Pagado por completo.'
                         : 'Falta por pagar: S/ ' . numJs(round($total - $monto, 2)) . '.'));
        $this->ir('/pedidos?id=' . $idPedido);
    }

    // ------------------------------------------------------------------
    // Acciones sobre un pedido existente
    // ------------------------------------------------------------------

    private function abono(int $idPedido): void
    {
        $caja = (new CajaDAO())->cajaAbierta($this->usuario()->id);
        if ($caja === null) {
            flash('error', 'Abre la caja antes de registrar un pago.', true);
            return;
        }

        $monto = decimalParam('monto');
        $metodo = valorParam('metodo');
        if ($idPedido < 0 || $monto === null || $monto <= 0 || $monto > self::MILLON) {
            flash('error', 'Ingresa un monto válido para el abono.', true);
            return;
        }
        if ($metodo !== 'YAPE' && $metodo !== 'EFECTIVO') {
            flash('error', 'Elige un método de pago (Yape o Efectivo).', true);
            return;
        }

        try {
            (new PedidoDAO())->abono($idPedido, $monto, $caja->idCaja, $metodo, $this->usuario()->id);
            flash('aviso', 'Abono de S/ ' . numJs($monto) . ' registrado para el pedido #' . $idPedido . '.');
        } catch (Throwable $e) {
            flash('error', $this->mensajeAmigable($e), true);
        }
    }

    /** H4: existe el pedido y, si no eres admin, solo puedes tocar los tuyos. */
    private function validarPropiedadPedidoId(int $idPedido): void
    {
        $this->validarPropiedadPedido((new PedidoDAO())->buscarPorId($idPedido));
    }

    private function validarPropiedadPedido(?Pedido $pedido): void
    {
        if ($pedido === null) {
            throw new \ExcepcionNegocio('Ese pedido no existe.');
        }
        if (!$this->esAdmin() && $pedido->idUsuario !== $this->usuario()->id) {
            throw new \ExcepcionNegocio('Solo puedes ver y modificar tus propios pedidos.');
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @return DetalleVenta[] */
    private function carrito(): array
    {
        $carrito = $_SESSION[self::K_CARRITO] ?? null;
        if (!is_array($carrito)) {
            $carrito = [];
            $_SESSION[self::K_CARRITO] = $carrito;
        }
        return $carrito;
    }

    private function fechaValida(string $texto): ?string
    {
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
        return $fecha !== false && $fecha->format('Y-m-d') === $texto ? $texto : null;
    }

    /** Traduce excepciones de negocio a mensajes amigables (PedidoServlet.mensajeDe). */
    private function mensajeAmigable(Throwable $e): string
    {
        $t = $e;
        while ($t->getPrevious() !== null) {
            $t = $t->getPrevious();
        }
        $m = $t->getMessage();

        if (stripos($m, 'stock') !== false) {
            return 'No hay stock suficiente para apartar ese ítem. Quita o reduce la cantidad.';
        }
        if (str_contains($m, 'pedido (revísalo')) {
            return 'No se pudo actualizar el pedido. Puede que ya no esté en ese estado.';
        }
        if (str_contains($m, 'Solo se puede cancelar')) {
            return $m;
        }
        if (str_contains($m, 'propios pedidos') || str_contains($m, 'no existe')) {
            return $m;
        }
        if (str_contains($m, 'supera el saldo')) {
            return 'El abono supera el saldo pendiente del pedido.';
        }
        if (str_contains($m, 'no admite abonos')) {
            return 'El pedido ya no admite abonos (cancelado o entregado).';
        }
        if (str_contains($m, 'ya no puede cancelarse')) {
            return $m;
        }
        return 'Ocurrió un error. Revisa los datos e inténtalo de nuevo.';
    }
}
