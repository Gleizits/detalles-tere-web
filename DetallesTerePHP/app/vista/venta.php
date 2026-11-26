<?php
/**
 * Punto de venta: catálogo, carrito, pago y cotizaciones.
 * Contenido dentro de <main>; los mensajes flash los pinta la cabecera.
 */
$q = $q ?? '';
?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <h1 class="titulo-seccion mb-0">
        <i class="bi bi-cart-check me-2" aria-hidden="true"></i>Venta
    </h1>
    <?php if ($caja !== null): ?>
        <span class="badge-punto-abierto">Caja abierta</span>
    <?php else: ?>
        <span class="badge-punto-cerrado">Caja cerrada</span>
    <?php endif; ?>
</div>

<?php if ($caja === null): ?>
    <div class="alerta-error mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>
            No tienes la caja abierta. Ábrela para poder cobrar.
        </span>
        <a class="btn btn-app btn-app-primary" href="<?= url('/caja') ?>">Ir a Caja</a>
    </div>
<?php endif; ?>

<div class="venta-grid">

    <!-- ===================== CATÁLOGO ===================== -->
    <?php if ($cotizacionId === null): ?>
        <div class="app-card p-3">
            <h2 class="titulo-seccion mb-3" style="font-size:1.25rem">Agregar productos</h2>

            <form action="<?= url('/venta') ?>" method="get" class="mb-3" role="search">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" class="form-control" name="q" value="<?= e($q) ?>"
                           placeholder="Buscar producto o término…" aria-label="Buscar productos">
                    <button class="btn btn-app btn-app-primary" type="submit">Buscar</button>
                </div>
            </form>

            <?php if (count($items) === 0): ?>
                <p class="text-muted mb-0">Sin resultados en el inventario.</p>
            <?php else: ?>
                <div class="tabla-envoltorio">
                    <table class="tabla-app">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="num">P. venta</th>
                                <th class="num">Stock</th>
                                <th>Agregar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td class="nombre-item"><?= e($it->nombre) ?></td>
                                    <td class="num">
                                        <?php if ($it->precioVenta !== null): ?>
                                            S/ <?= e(num($it->precioVenta)) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td class="num">
                                        <?php if ($it->esStockBajo()): ?>
                                            <span class="badge-estado bajo"><?= (int) $it->stockAct ?></span>
                                        <?php else: ?>
                                            <?= (int) $it->stockAct ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form class="catalogo-form" method="post" action="<?= url('/venta') ?>">
                                            <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="accion" value="agregar">
                                            <input type="hidden" name="idInventario" value="<?= (int) $it->idInventario ?>">
                                            <input type="number" class="form-control cant-input"
                                                   name="cantidad" value="1" min="1"
                                                   <?php if ($it->stockAct > 0): ?>max="<?= (int) $it->stockAct ?>"<?php endif; ?>
                                                   aria-label="Cantidad">
                                            <button class="btn btn-app btn-app-primary btn-sm"
                                                    <?php if ($it->stockAct === 0 || $it->precioVenta === null): ?>disabled<?php endif; ?>>
                                                Agregar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- ===================== CARRITO ===================== -->
    <div class="app-card p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="titulo-seccion mb-0" style="font-size:1.25rem">Carrito</h2>
            <?php if ($cotizacionId !== null): ?>
                <span class="badge-estado abierto">Cotización #<?= (int) $cotizacionId ?></span>
            <?php endif; ?>
        </div>

        <?php if ($cotizacionId !== null): ?>
            <div class="alerta-info mb-3">
                <i class="bi bi-info-circle me-2" aria-hidden="true"></i>
                Cotización cargada. Al cobrar se convertirá en venta y se descontará su stock.
            </div>
        <?php endif; ?>

        <?php if (count($carrito) === 0): ?>
            <p class="text-muted mb-0">El carrito está vacío. Agrega productos desde el catálogo.</p>
        <?php else: ?>
            <div class="tabla-envoltorio">
                <table class="tabla-app">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="num">Cant.</th>
                            <th class="num">P. unit.</th>
                            <th class="num">Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($carrito as $indice => $linea): ?>
                            <tr>
                                <td><?= e($linea->nombre) ?></td>
                                <td class="num"><?= (int) $linea->cantidad ?></td>
                                <td class="num">S/ <?= e(num($linea->costoUnitario)) ?></td>
                                <td class="num">S/ <?= e(num($linea->subtotal)) ?></td>
                                <td class="text-end">
                                    <?php if ($cotizacionId === null): ?>
                                        <form method="post" action="<?= url('/venta') ?>">
                                            <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="indice" value="<?= (int) $indice ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    title="Quitar del carrito" aria-label="Quitar del carrito">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="carrito-total">
                <span class="etq">Total</span>
                <span class="val">S/ <?= e(num($totalCarrito)) ?></span>
            </div>

            <!-- ====== PAGO ====== -->
            <form method="post" action="<?= url('/venta') ?>" class="pago-zona mt-3"
                  data-total="<?= e(numJs($totalCarrito)) ?>">
                <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="accion" value="cobrar">

                <div class="mb-3">
                    <label class="form-label fw-bold">Método de pago</label>
                    <div class="d-flex gap-4 mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipoPago"
                                   id="pagoEfectivo" value="EFECTIVO" checked>
                            <label class="form-check-label fs-5" for="pagoEfectivo">
                                <i class="bi bi-cash me-1" aria-hidden="true"></i>Efectivo
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipoPago"
                                   id="pagoYape" value="YAPE">
                            <label class="form-check-label fs-5" for="pagoYape">
                                <i class="bi bi-phone me-1" aria-hidden="true"></i>Yape
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipoPago"
                                   id="pagoCredito" value="CREDITO">
                            <label class="form-check-label fs-5" for="pagoCredito">
                                <i class="bi bi-wallet2 me-1" aria-hidden="true"></i>Saldo a favor
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mb-3" id="zonaCredito" style="display:none">
                    <label class="form-label fw-bold" for="celularCredito">
                        Celular del cliente con saldo a favor
                    </label>
                    <input type="tel" class="form-control" name="celularCredito"
                           id="celularCredito" maxlength="12" inputmode="tel"
                           placeholder="Ej: 999888777">
                    <small class="text-muted">
                        El total debe coincidir exactamente con todo su saldo vigente.
                    </small>
                </div>

                <div class="mb-3" id="zonaEfectivo">
                    <label class="form-label fw-bold" for="montoRecibido">Monto recibido (S/)</label>
                    <input type="number" step="0.01" min="0" inputmode="decimal"
                           class="form-control" name="montoRecibido" id="montoRecibido"
                           placeholder="Ej: 50">
                    <div class="vuelto-linea mt-2">
                        <span>Vuelto</span>
                        <span id="vuelto">S/ 0.00</span>
                    </div>
                </div>

                <button type="submit" class="btn btn-app btn-app-primary w-100 py-2">
                    <i class="bi bi-cash-stack me-1" aria-hidden="true"></i>
                    Cobrar S/ <span id="totalVisible"><?= e(numJs($totalCarrito)) ?></span>
                </button>
            </form>

            <!-- ====== ACCIONES ====== -->
            <?php if ($cotizacionId !== null): ?>
                <form method="post" action="<?= url('/venta') ?>" class="mt-2">
                    <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="accion" value="cancelar_carga">
                    <button type="submit" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancelar carga de cotización
                    </button>
                </form>
            <?php else: ?>
                <div class="d-flex gap-2 mt-2 flex-wrap">
                    <form method="post" action="<?= url('/venta') ?>" class="flex-fill">
                        <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="accion" value="cotizar">
                        <button type="submit" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>Guardar cotización (24 h)
                        </button>
                    </form>
                    <form method="post" action="<?= url('/venta') ?>" class="flex-fill">
                        <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="accion" value="vaciar">
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Vaciar
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ===================== COTIZACIONES ===================== -->
<div class="app-card mt-4">
    <div class="p-3 pb-2">
        <h2 class="titulo-seccion mb-1" style="font-size:1.25rem">
            <i class="bi bi-clock-history me-2" aria-hidden="true"></i>Cotizaciones activas (24 h)
        </h2>
        <p class="text-muted mb-0">Se borran solas al vencer. Carga una para cobrarla.</p>
    </div>

    <?php if (count($cotizaciones) === 0): ?>
        <p class="text-muted p-3 mb-0">No hay cotizaciones activas.</p>
    <?php else: ?>
        <?php foreach ($cotizaciones as $co): ?>
            <div class="cotiz-tarjeta">
                <div>
                    <strong>Cotización #<?= (int) $co->idVenta ?></strong>
                    <div class="cotiz-resumen"><?= e($co->resumen) ?></div>
                    <small class="text-muted">
                        Creada: <?= e(fecha($co->fechaVenta, 'd/m/Y H:i')) ?>
                        · <?= (int) $co->numLineas ?> producto(s)
                    </small>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <strong class="fs-5">S/ <?= e(num($co->total)) ?></strong>
                    <form method="post" action="<?= url('/venta') ?>">
                        <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="accion" value="cargar_cotizacion">
                        <input type="hidden" name="idVenta" value="<?= (int) $co->idVenta ?>">
                        <button type="submit" class="btn btn-app btn-app-primary btn-sm">Cargar</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- La CSP no permite scripts en línea: venta.js va en archivo externo. -->
<script src="<?= url('/assets/js/venta.js') ?>"></script>
