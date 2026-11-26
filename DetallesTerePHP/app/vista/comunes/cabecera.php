<?php
/**
 * Cabecera común: <head>, menú lateral y barra superior.
 * Variables disponibles: $titulo, $seccion, $usuario, $csrfToken,
 *                       $mensajesFlash.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · DetallesTere</title>
    <link rel="icon" type="image/png" href="<?= url('/assets/img/logo.png') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/bootstrap-icons.min.css') ?>">
    <!-- ?v= fuerza al navegador a descargar el CSS tras cada cambio -->
    <link rel="stylesheet" href="<?= url('/assets/css/app.css?v=2026-11-24a') ?>">
</head>
<body>

<!-- Menú lateral: cajón superpuesto que se abre con la hamburguesa. -->
<aside class="app-sidebar" id="appSidebar" aria-label="Menú de secciones">
    <div class="sidebar-marca">
        <a class="marca marca-lateral" href="<?= url('/panel') ?>">
            <img src="<?= url('/assets/img/logo.png') ?>" alt="Logo" class="marca-logo">
            <span class="marca-completa">Detalles<span class="marca-punto">Tere</span></span>
        </a>
        <button type="button" class="sidebar-cerrar" id="sidebarClose"
                aria-label="Cerrar el menú de secciones">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <?php
        $enlaces = [
            ['/panel',      'Inicio',    'bi-house-door',   false],
            ['/caja',       'Caja',      'bi-cash-coin',    false],
            ['/venta',      'Venta',     'bi-cart-check',   false],
            ['/pedidos',    'Pedidos',   'bi-calendar2-check', false],
            ['/inventario', 'Inventario','bi-box-seam',     false],
            ['/ingresos',   'Ingresos',  'bi-truck',        true],
            ['/terminos',   'Términos',  'bi-tags',         true],
            ['/reportes',   'Reportes',  'bi-graph-up-arrow', true],
            ['/usuarios',   'Usuarios',  'bi-people',       true],
        ];
        $seccionAnterior = null;
        foreach ($enlaces as [$ruta, $texto, $icono, $esAdmin]) {
            if ($esAdmin && !$usuario->esAdmin()) {
                continue;
            }
            // Separador antes del primer enlace de administración.
            if ($esAdmin && $seccionAnterior === false) {
                echo '<div class="sidebar-separador" role="separator"></div>';
            }
            $activo = $seccion === $ruta;
            ?>
            <a href="<?= url($ruta) ?>" title="<?= e($texto) ?>"
               class="nav-app-link<?= $activo ? ' activo' : '' ?>"
               <?= $activo ? 'aria-current="page"' : '' ?>>
                <i class="bi <?= e($icono) ?>" aria-hidden="true"></i>
                <span class="sidebar-texto"><?= e($texto) ?></span>
            </a>
            <?php
            $seccionAnterior = $esAdmin;
        }
        ?>
    </nav>
</aside>

<!-- Fondo al abrir el menú -->
<div class="app-overlay" id="sidebarOverlay" aria-hidden="true"></div>

<div class="app-cuerpo">

    <!-- Barra superior: botón de menú + usuario -->
    <header class="app-topbar">
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="nav-menu-btn" id="sidebarToggle"
                    aria-controls="appSidebar" aria-expanded="false"
                    aria-label="Abrir el menú de secciones">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <a class="marca marca-top" href="<?= url('/panel') ?>">
                <img src="<?= url('/assets/img/logo.png') ?>" alt="Logo" class="marca-logo">
                <span>Detalles<span class="marca-punto">Tere</span></span>
            </a>
        </div>

        <div class="d-flex align-items-center gap-3">
            <span class="text-muted usuario-chip">
                <i class="bi bi-person-circle me-1" aria-hidden="true"></i>
                <span class="usuario-nombre"><?= e($usuario->nombreCompleto()) ?></span>
            </span>
            <form method="post" action="<?= url('/logout') ?>" class="m-0">
                <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
                <button type="submit" class="salir border-0 bg-transparent p-0">
                    <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Salir
                </button>
            </form>
        </div>
    </header>

    <main class="container py-4">

    <!-- Mensajes flash de la petición anterior -->
    <?php foreach ($mensajesFlash as $flash): ?>
        <div class="<?= $flash['error'] ? 'alerta-error' : 'alerta-exito' ?> mb-4"
             role="status" aria-live="polite">
            <?php if ($flash['titulo'] !== ''): ?>
                <strong><?= e($flash['titulo']) ?></strong><br>
            <?php endif; ?>
            <?= e($flash['mensaje']) ?>
        </div>
    <?php endforeach; ?>
