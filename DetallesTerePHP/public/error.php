<?php /** Página de error sin plantilla */ ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? 'Error') ?> · DetallesTere</title>
    <link rel="stylesheet" href="<?= url('/assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('/assets/css/app.css?v=2026-11-24a') ?>">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card mt-5">
                <div class="card-body p-4">
                    <h1 class="h3"><?= e($titulo ?? 'Error') ?></h1>
                    <p><?= e($mensaje ?? 'Ocurrió un error inesperado.') ?></p>
                    <a href="<?= url('/login') ?>" class="btn btn-primary">Ir al login</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
