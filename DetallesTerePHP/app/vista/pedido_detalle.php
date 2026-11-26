<?php
/** Detalle de un pedido reservado (solo contenido de <main>). */

$estadoBadge = static function (string $estado): array {
    return match ($estado) {
        'PENDIENTE' => ['pendiente', 'Pendiente de pago'],
        'COMPLETO'  => ['completo', 'Pagado por completo'],
        'RECOGIDO'  => ['recogido', 'Recogido'],
        'CANCELADO' => ['cancelado', 'Cancelado'],
        default     => ['pendiente', $estado],
    };
};

$produccionBadge = static function (string $estado): array {
    return match ($estado) {
        'HECHO'      => ['hecho', 'Producción: HECHO'],
        'CANCELADO'  => ['cancelado', 'Producción: cancelada'],
        default      => ['pendiente', 'Producción: pendiente'],
    };
};

// Las líneas pueden venir como objetos DetalleVenta o como arrays del DAO.
$valorLinea = static function ($linea, string $clave): mixed {
    return is_array($linea) ? ($linea[$clave] ?? null) : ($linea->$clave ?? null);
};

[$claseEstado, $textoEstado] = $estadoBadge($pedido->estado);
[$claseProduccion, $textoProduccion] = $produccionBadge($pedido->estadoProduccion);
$faltante = $pedido->faltante();
$activo = $pedido->estado !== 'CANCELADO' && $pedido->estado !== 'RECOGIDO';
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <h1 class="titulo-seccion mb-0">
        <i class="bi bi-calendar2-check me-2" aria-hidden="true"></i>Pedido #<?= (int) $pedido->idPedido ?>
    </h1>
    <a class="btn btn-outline-secondary" href="<?= e(url('/pedidos')) ?>">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver
    </a>
</div>

<!-- ====== ESTADOS ====== -->
<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
    <span class="badge-pedido <?= e($claseEstado) ?>"><?= e($textoEstado) ?></span>
    <span class="badge-pedido <?= e($claseProduccion) ?>"><?= e($textoProduccion) ?></span>
</div>

<?php if ($pedido->estado === 'CANCELADO' && (float) ($pedido->adelanto ?? 0) > 0): ?>
    <div class="alerta-info mb-4">
        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
        El adelanto quedó como saldo a favor del cliente, vigente durante 3 meses.
    </div>
<?php endif; ?>

<!-- ====== DINERO ====== -->
<div class="app-card p-4 mb-4">
    <div class="row g-3 text-center">
        <div class="col-4">
            <div class="dato-etiqueta">Total del pedido</div>
            <div class="dato-valor">S/ <?= num($pedido->total) ?></div>
        </div>
        <div class="col-4">
            <div class="dato-etiqueta">Pagado</div>
            <div class="dato-valor">S/ <?= num($pedido->adelanto) ?></div>
        </div>
        <div class="col-4">
            <div class="dato-etiqueta">Falta por pagar</div>
            <div class="dato-valor <?= $faltante > 0 ? 'texto-negativo' : '' ?>"
                 <?= $faltante <= 0 ? 'style="color:var(--c-exito)"' : '' ?>>
                S/ <?= $faltante > 0 ? num($faltante) : '0.00' ?>
            </div>
        </div>
    </div>
    <?php if ($faltante > 0 && $pedido->estado !== 'CANCELADO'): ?>
        <div class="alerta-info mt-3 mb-0">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
            Falta cobrar <strong>S/ <?= num($faltante) ?></strong>.
            No se entrega hasta pagar el total.
        </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <!-- ====== CLIENTE ====== -->
    <div class="col-12 col-lg-6">
        <div class="app-card p-4 h-100">
            <h2 class="titulo-seccion mb-3" style="font-size:1.25rem">
                <i class="bi bi-person-vcard me-2" aria-hidden="true"></i>Cliente
            </h2>
            <dl class="row mb-0 detalle-lista">
                <dt>Quien paga</dt>
                <dd><?= e($pedido->nombrePaga) ?></dd>
                <dt>Celular</dt>
                <dd><?= e($pedido->celular) ?></dd>
                <dt>Quien recoge</dt>
                <dd><?= $pedido->nombreRecoge !== null && $pedido->nombreRecoge !== ''
                    ? e($pedido->nombreRecoge) : '—' ?></dd>
                <dt>Día de recojo</dt>
                <dd>
                    <?= e(fecha($pedido->fechaRecojo)) ?>
                    <?php if ($pedido->horaRecojo !== null && $pedido->horaRecojo !== ''): ?>
                        a las <?= e($pedido->horaRecojo) ?>
                    <?php endif; ?>
                </dd>
            </dl>
        </div>
    </div>

    <!-- ====== PLAZOS ====== -->
    <div class="col-12 col-lg-6">
        <div class="app-card p-4 h-100">
            <h2 class="titulo-seccion mb-3" style="font-size:1.25rem">
                <i class="bi bi-hourglass-split me-2" aria-hidden="true"></i>Plazos
            </h2>
            <dl class="row mb-0 detalle-lista">
                <dt>Recoger antes de</dt>
                <dd><?= e(fecha($pedido->fechaLimiteRecojo)) ?></dd>
                <dt>Pagar el total antes de</dt>
                <dd><?= e(fecha($pedido->fechaLimitePago)) ?></dd>
                <dt>Canjear antes de</dt>
                <dd><?= e(fecha($pedido->fechaLimiteCanje)) ?></dd>
                <dt>Creado</dt>
                <dd><?= e(fecha($pedido->fechaCreacion, 'd/m/Y H:i')) ?></dd>
            </dl>
        </div>
    </div>
</div>

<!-- ====== PRODUCTOS ====== -->
<div class="app-card tabla-envoltorio mt-4">
    <table class="tabla-app">
        <thead>
            <tr>
                <th>Producto</th>
                <th class="num">Cant.</th>
                <th class="num">P. unit.</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pedido->lineas as $linea): ?>
                <tr>
                    <td><?= e($valorLinea($linea, 'nombre')) ?></td>
                    <td class="num"><?= (int) $valorLinea($linea, 'cantidad') ?></td>
                    <td class="num">S/ <?= num($valorLinea($linea, 'costoUnitario')) ?></td>
                    <td class="num">S/ <?= num($valorLinea($linea, 'subtotal')) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ====== ACCIONES ====== -->
<?php if ($activo): ?>
    <?php if ($faltante > 0): ?>
        <div class="app-card p-4 mt-4">
            <h2 class="titulo-seccion mb-3" style="font-size:1.25rem">
                <i class="bi bi-cash-coin me-2" aria-hidden="true"></i>Registrar abono
            </h2>
            <?php if ($caja === null): ?>
                <div class="alerta-error mb-0">
                    <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
                    Abre la caja para registrar pagos.
                </div>
            <?php else: ?>
                <form method="post" action="<?= e(url('/pedidos')) ?>" class="row g-3 align-items-end">
                    <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="accion" value="abono">
                    <input type="hidden" name="idPedido" value="<?= (int) $pedido->idPedido ?>">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="abonoMonto">Monto a abonar (S/)</label>
                        <input type="number" step="0.01" min="0.01" max="<?= e(numJs($faltante)) ?>"
                               inputmode="decimal" class="form-control" name="monto"
                               id="abonoMonto" required placeholder="Máx. <?= e(numJs($faltante)) ?>">
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-bold mb-1">Método</label>
                        <div class="d-flex gap-4">
                            <div class="form-check fs-5">
                                <input class="form-check-input" type="radio" name="metodo"
                                       id="aEfectivo" value="EFECTIVO" checked>
                                <label class="form-check-label" for="aEfectivo">Efectivo</label>
                            </div>
                            <div class="form-check fs-5">
                                <input class="form-check-input" type="radio" name="metodo"
                                       id="aYape" value="YAPE">
                                <label class="form-check-label" for="aYape">Yape</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <button type="submit" class="btn btn-app btn-app-primary w-100">
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Abonar
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="app-card p-4 mt-4">
        <h2 class="titulo-seccion mb-3" style="font-size:1.25rem">
            <i class="bi bi-gear me-2" aria-hidden="true"></i>Acciones
        </h2>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($pedido->estadoProduccion === 'PENDIENTE'): ?>
                <form method="post" action="<?= e(url('/pedidos')) ?>" class="flex-fill">
                    <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="accion" value="hecho">
                    <input type="hidden" name="idPedido" value="<?= (int) $pedido->idPedido ?>">
                    <button type="submit" class="btn btn-app btn-app-primary w-100"
                            data-confirm="¿Marcar como HECHO? A partir de este punto no se puede cancelar: se cobrará el total.">
                        <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Marcar HECHO
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($pedido->estado === 'COMPLETO'): ?>
                <form method="post" action="<?= e(url('/pedidos')) ?>" class="flex-fill">
                    <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="accion" value="recoger">
                    <input type="hidden" name="idPedido" value="<?= (int) $pedido->idPedido ?>">
                    <button type="submit" class="btn btn-app btn-app-primary w-100"
                            data-confirm="¿Entregar el pedido al cliente?">
                        <i class="bi bi-box-seam me-1" aria-hidden="true"></i>Entregar (recogido)
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($pedido->estadoProduccion === 'PENDIENTE'): ?>
                <form method="post" action="<?= e(url('/pedidos')) ?>" class="flex-fill">
                    <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="accion" value="cancelar">
                    <input type="hidden" name="idPedido" value="<?= (int) $pedido->idPedido ?>">
                    <button type="submit" class="btn btn-outline-danger w-100"
                            data-confirm="¿Cancelar el pedido? El stock volverá al inventario y el adelanto quedará como saldo a favor, sin devolución en efectivo.">
                        <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar pedido
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script src="<?= e(url('/assets/js/confirmaciones.js')) ?>"></script>
