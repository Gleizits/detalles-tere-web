<?php
/**
 * Caja: estado actual, apertura/cierre y cajas recientes.
 * Contenido dentro de <main>; la plantilla común pinta cabecera y pie.
 */
$error = $error ?? '';
$resumen = $resumen ?? null;
$ventasEfectivo = $ventasEfectivo ?? 0.0;
$esperado = $esperado ?? 0.0;
$ventasProductos = $ventasProductos ?? 0.0;
$pagosReservas = $pagosReservas ?? 0.0;
?>
<h1 class="titulo-seccion mb-4">
    <i class="bi bi-cash-coin me-2" aria-hidden="true"></i>Caja
</h1>

<?php if ($error !== ''): ?>
    <div class="alerta-error p-3 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
        <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- Resumen tras cerrar -->
<?php if ($resumen !== null): ?>
    <?php $ventasResumen = (float) ($resumen->montoFinal ?? 0) - (float) ($resumen->montoInicial ?? 0); ?>
    <?php $diferencia = (float) ($resumen->diferencia ?? 0); ?>
    <div class="app-card p-4 mb-4">
        <h2 class="h5 mb-3">
            <i class="bi bi-check-circle text-success me-1" aria-hidden="true"></i> Caja cerrada
        </h2>
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="dato-etiqueta">Monto inicial</div>
                <div class="dato-valor">S/ <?= e(num($resumen->montoInicial)) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="dato-etiqueta">Ventas en efectivo</div>
                <div class="dato-valor">S/ <?= e(num($ventasResumen)) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="dato-etiqueta">Esperado en caja</div>
                <div class="dato-valor">S/ <?= e(num($resumen->montoFinal)) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="dato-etiqueta">Contado</div>
                <div class="dato-valor">S/ <?= e(num($resumen->montoReal)) ?></div>
            </div>
        </div>
        <div class="mt-3">
            <span class="dato-etiqueta">Diferencia:</span>
            <?php if ($diferencia > 0): ?>
                <span class="badge-diferencia positivo">+ S/ <?= e(num($diferencia)) ?> (sobra)</span>
            <?php elseif ($diferencia < 0): ?>
                <span class="badge-diferencia negativo">- S/ <?= e(num(abs($diferencia))) ?> (falta)</span>
            <?php else: ?>
                <span class="badge-diferencia exacto">S/ 0.00 (cuadra)</span>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($caja !== null): ?>
    <div class="app-card p-4 mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge-punto-abierto">Caja abierta</span>
            <span class="text-muted">desde <?= e(fecha($caja->fechaApertura, 'd/m/Y H:i')) ?></span>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <div class="dato-etiqueta">Monto inicial</div>
                <div class="dato-valor">S/ <?= e(num($caja->montoInicial)) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="dato-etiqueta">Ventas de productos</div>
                <div class="dato-valor">S/ <?= e(num($ventasProductos)) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="dato-etiqueta">Pagos de reservas</div>
                <div class="dato-valor" style="color:var(--c-acento)">S/ <?= e(num($pagosReservas)) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="dato-etiqueta">Esperado en caja</div>
                <div class="dato-valor">S/ <?= e(num($esperado)) ?></div>
            </div>
        </div>

        <form action="<?= url('/caja') ?>" method="post" autocomplete="off">
            <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="accion" value="cerrar">
            <input type="hidden" name="idCaja" value="<?= (int) $caja->idCaja ?>">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label" for="montoReal">Cuéntalo: dinero físico en caja</label>
                    <div class="input-group">
                        <span class="input-group-text">S/</span>
                        <input type="number" step="0.01" min="0" class="form-control"
                               id="montoReal" name="montoReal" required>
                    </div>
                </div>
                <div class="col-12 col-md-5">
                    <button type="submit" class="btn btn-app btn-app-primary w-100"
                            data-confirm="¿Cerrar la caja? Esta acción no se puede deshacer.">
                        <i class="bi bi-lock me-1" aria-hidden="true"></i> Cerrar caja
                    </button>
                </div>
            </div>
        </form>
    </div>
<?php elseif ($resumen === null): ?>
    <div class="app-card p-4 mb-4">
        <p class="text-muted mb-3">No tienes una caja abierta. Abre una para empezar a registrar ventas.</p>
        <form action="<?= url('/caja') ?>" method="post" autocomplete="off">
            <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="accion" value="abrir">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label" for="montoInicial">Dinero inicial en caja</label>
                    <div class="input-group">
                        <span class="input-group-text">S/</span>
                        <input type="number" step="0.01" min="0" class="form-control"
                               id="montoInicial" name="montoInicial" required autofocus>
                    </div>
                </div>
                <div class="col-12 col-md-5">
                    <button type="submit" class="btn btn-app btn-app-primary w-100">
                        <i class="bi bi-unlock me-1" aria-hidden="true"></i> Abrir caja
                    </button>
                </div>
            </div>
        </form>
    </div>
<?php endif; ?>

<!-- Historial reciente -->
<?php if (count($historial) > 0): ?>
    <h2 class="h5 mt-4 mb-3">Cajas recientes</h2>
    <div class="app-card tabla-envoltorio">
        <table class="tabla-app">
            <thead>
                <tr>
                    <th>Apertura</th>
                    <th>Cierre</th>
                    <th class="num">Inicial</th>
                    <th class="num">Esperado</th>
                    <th class="num">Contado</th>
                    <th class="num">Diferencia</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($historial as $ch): ?>
                    <tr>
                        <td><?= e(fecha($ch->fechaApertura, 'd/m H:i')) ?></td>
                        <td><?= $ch->esAbierta() ? '—' : e(fecha($ch->fechaCierre, 'd/m H:i')) ?></td>
                        <td class="num">S/ <?= e(num($ch->montoInicial)) ?></td>
                        <td class="num">
                            <?= $ch->esAbierta() ? '—' : 'S/ ' . e(num($ch->montoFinal)) ?>
                        </td>
                        <td class="num">
                            <?= $ch->esAbierta() ? '—' : 'S/ ' . e(num($ch->montoReal)) ?>
                        </td>
                        <td class="num">
                            <?php if ($ch->esAbierta()): ?>
                                —
                            <?php elseif ((float) ($ch->diferencia ?? 0) < 0): ?>
                                <span class="texto-negativo">- S/ <?= e(num(abs($ch->diferencia))) ?></span>
                            <?php else: ?>
                                S/ <?= e(num($ch->diferencia)) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ch->esAbierta()): ?>
                                <span class="badge-estado abierto">Abierta</span>
                            <?php else: ?>
                                <span class="badge-estado cerrado">Cerrada</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- La CSP no permite scripts en línea: confirmaciones va en archivo externo. -->
<script src="<?= url('/assets/js/confirmaciones.js') ?>"></script>
