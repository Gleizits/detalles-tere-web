<?php
/** Lista de pedidos reservados (solo contenido de <main>). */

$estadoBadge = static function (string $estado): array {
    return match ($estado) {
        'PENDIENTE' => ['pendiente', 'Pendiente'],
        'COMPLETO'  => ['completo', 'Listo'],
        'RECOGIDO'  => ['recogido', 'Recogido'],
        'CANCELADO' => ['cancelado', 'Cancelado'],
        default     => ['pendiente', $estado],
    };
};
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <h1 class="titulo-seccion mb-0">
        <i class="bi bi-calendar2-check me-2" aria-hidden="true"></i>Pedidos reservados
    </h1>
    <a class="btn btn-app btn-app-primary" href="<?= e(url('/pedidos?nuevo=1')) ?>">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nuevo pedido
    </a>
</div>

<form action="<?= e(url('/pedidos')) ?>" method="get" class="mb-4" role="search">
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
        <input type="search" class="form-control" name="q" value="<?= e($q) ?>"
               placeholder="Buscar por nombre, celular o quién recoge…" aria-label="Buscar pedidos">
        <button class="btn btn-app btn-app-primary" type="submit">Buscar</button>
    </div>
</form>

<?php if ($pedidos === []): ?>
    <div class="app-card p-4 text-center">
        <p class="text-muted mb-3">
            No hay pedidos reservados<?php if ($q !== ''): ?> que coincidan con «<?= e($q) ?>»<?php endif; ?>.
        </p>
        <a class="btn btn-app btn-app-primary" href="<?= e(url('/pedidos?nuevo=1')) ?>">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Crear el primer pedido
        </a>
    </div>
<?php else: ?>
    <div class="app-card tabla-envoltorio">
        <table class="tabla-app">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Cliente</th>
                    <th>Recojo</th>
                    <th class="num">Total</th>
                    <th class="num">Pagado</th>
                    <th class="num">Falta</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $p): ?>
                    <?php [$clase, $texto] = $estadoBadge($p->estado); ?>
                    <tr>
                        <td><strong><?= (int) $p->idPedido ?></strong></td>
                        <td>
                            <div class="nombre-item"><?= e($p->nombrePaga) ?></div>
                            <small class="text-muted"><?= e($p->celular) ?></small>
                        </td>
                        <td>
                            <?= e(fecha($p->fechaRecojo)) ?>
                            <?php if ($p->horaRecojo !== null && $p->horaRecojo !== ''): ?>
                                <small class="text-muted"><?= e($p->horaRecojo) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="num">S/ <?= num($p->total) ?></td>
                        <td class="num">S/ <?= num($p->adelanto) ?></td>
                        <td class="num">
                            <?php if ($p->faltante() > 0): ?>
                                <span class="texto-negativo">S/ <?= num($p->faltante()) ?></span>
                            <?php else: ?>
                                <span class="text-success fw-bold">S/ 0.00</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge-pedido <?= e($clase) ?>"><?= e($texto) ?></span>
                            <?php if ($p->estadoProduccion === 'HECHO'): ?>
                                <span class="badge-pedido hecho">Hecho</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-app btn-app-primary"
                               href="<?= e(url('/pedidos?id=' . (int) $p->idPedido)) ?>">Ver</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
