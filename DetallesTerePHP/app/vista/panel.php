<?php
/** Panel de inicio. Variables: $saludo, $usuario, $csrfToken. */
?>
<!-- Saludo -->
<div class="mb-4">
    <h1 class="saludo">
        <?= e($saludo) ?>, <?= e($usuario->nombre) ?>.
    </h1>
    <p class="saludo-sub mb-0">¿Qué vamos a hacer hoy?</p>
</div>

<!-- Accesos rápidos -->
<div class="row g-3">

    <div class="col-12 col-md-6 col-xl-4">
        <a class="app-card seccion-tarjeta" href="<?= url('/caja') ?>">
            <div class="ico"><i class="bi bi-cash-coin"></i></div>
            <h3>Caja</h3>
            <p>Abrir caja, registrar ventas y cobrar en yape o efectivo.</p>
        </a>
    </div>

    <div class="col-12 col-md-6 col-xl-4">
        <a class="app-card seccion-tarjeta" href="<?= url('/inventario') ?>">
            <div class="ico"><i class="bi bi-box-seam"></i></div>
            <h3>Inventario</h3>
            <p>Ver productos, materiales y arreglos con su stock actual.</p>
        </a>
    </div>

    <div class="col-12 col-md-6 col-xl-4">
        <a class="app-card seccion-tarjeta" href="<?= url('/pedidos') ?>">
            <div class="ico"><i class="bi bi-calendar-check"></i></div>
            <h3>Pedidos</h3>
            <p>Gestionar pedidos reservados, adelantos y recogidas.</p>
        </a>
    </div>

    <?php if ($usuario->esAdmin()): ?>
        <div class="col-12 col-md-6 col-xl-4">
            <a class="app-card seccion-tarjeta" href="<?= url('/ingresos') ?>">
                <div class="ico"><i class="bi bi-truck"></i></div>
                <h3>Ingresos</h3>
                <p>Registrar proveedores y entradas de mercadería al inventario.</p>
            </a>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <a class="app-card seccion-tarjeta" href="<?= url('/terminos') ?>">
                <div class="ico"><i class="bi bi-tags"></i></div>
                <h3>Términos</h3>
                <p>Administrar palabras y sinónimos para encontrar artículos.</p>
            </a>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <a class="app-card seccion-tarjeta" href="<?= url('/reportes') ?>">
                <div class="ico"><i class="bi bi-graph-up"></i></div>
                <h3>Reportes</h3>
                <p>Resumen de ingresos al inventario, ventas y cajas del día.</p>
            </a>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <a class="app-card seccion-tarjeta" href="<?= url('/usuarios') ?>">
                <div class="ico"><i class="bi bi-people"></i></div>
                <h3>Usuarios</h3>
                <p>Administrar accesos, roles y usuarios activos.</p>
            </a>
        </div>
    <?php endif; ?>

</div>
