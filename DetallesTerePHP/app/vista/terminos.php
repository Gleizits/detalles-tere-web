<?php /** Términos de búsqueda y asociación con artículos (solo admin). */ ?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <h1 class="titulo-seccion mb-0">
        <i class="bi bi-tags me-2" aria-hidden="true"></i>Términos de búsqueda
    </h1>
    <a class="btn btn-outline-secondary" href="<?= e(url('/panel')) ?>">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Inicio
    </a>
</div>

<?php if (!empty($error)): ?>
    <div class="alerta-error mb-4" role="alert">
        <i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i><?= e($error) ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-5">
        <div class="app-card p-4">
            <h2 class="h4 mb-3">Agregar término</h2>
            <form method="post" action="<?= e(url('/terminos')) ?>" class="mb-4">
                <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="accion" value="crear">
                <label class="form-label" for="palabra">Palabra o sinónimo *</label>
                <div class="input-group">
                    <input class="form-control" id="palabra" name="palabra"
                           maxlength="75" placeholder="Ej: limpiapipas" required>
                    <button class="btn btn-app btn-app-primary" type="submit">Guardar</button>
                </div>
            </form>

            <h2 class="h4 mb-3">Asociar artículo</h2>
            <form method="post" action="<?= e(url('/terminos')) ?>">
                <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="accion" value="asociar">
                <div class="mb-3">
                    <label class="form-label" for="idTermino">Término *</label>
                    <select class="form-select" id="idTermino" name="idTermino" required>
                        <option value="">— Selecciona un término —</option>
                        <?php foreach ($terminos as $termino): ?>
                            <option value="<?= (int) $termino->idTermino ?>"><?= e($termino->palabra) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="idInventario">Artículo *</label>
                    <select class="form-select" id="idInventario" name="idInventario" required>
                        <option value="">— Selecciona un artículo —</option>
                        <?php foreach ($inventario as $it): ?>
                            <option value="<?= (int) $it->idInventario ?>">
                                <?= e($it->nombre) ?> · <?= e($it->clase) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-outline-primary" type="submit">
                    <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>Asociar
                </button>
            </form>

            <form method="post" action="<?= e(url('/terminos')) ?>" class="mt-3">
                <input type="hidden" name="csrfToken" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="accion" value="desasociar">
                <div class="mb-3">
                    <label class="form-label" for="idTerminoQuitar">Término *</label>
                    <select class="form-select" id="idTerminoQuitar" name="idTermino" required>
                        <option value="">— Selecciona un término —</option>
                        <?php foreach ($terminos as $termino): ?>
                            <option value="<?= (int) $termino->idTermino ?>"><?= e($termino->palabra) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="idInventarioQuitar">Artículo *</label>
                    <select class="form-select" id="idInventarioQuitar" name="idInventario" required>
                        <option value="">— Selecciona un artículo —</option>
                        <?php foreach ($inventario as $it): ?>
                            <option value="<?= (int) $it->idInventario ?>">
                                <?= e($it->nombre) ?> · <?= e($it->clase) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-outline-secondary" type="submit">
                    <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>Quitar asociación
                </button>
            </form>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="app-card p-4">
            <h2 class="h4 mb-3">Términos registrados</h2>
            <?php if ($terminos === []): ?>
                <p class="text-muted mb-0">Todavía no hay términos registrados.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="tabla-app">
                        <thead>
                            <tr><th>Término</th><th>Artículos asociados</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($terminos as $termino): ?>
                                <tr>
                                    <td class="nombre-item"><?= e($termino->palabra) ?></td>
                                    <td>
                                        <?php
                                        $asociaciones = $termino->asociaciones ?? '';
                                        echo e($asociaciones !== ''
                                            ? $asociaciones
                                            : sprintf('%d artículo(s)', (int) ($termino->numProductos ?? 0)));
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
