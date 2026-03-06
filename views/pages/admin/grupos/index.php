<?php

use App\Core\View\View;

$old = $old ?? [];
$editing = $editing ?? null;
$errors = $errors ?? [];
$oldValue = static function (string $field, $default = '') use ($old, $editing) {
    if (array_key_exists($field, $old)) {
        return $old[$field];
    }
    if ($editing !== null && array_key_exists($field, $editing)) {
        return $editing[$field];
    }
    return $default;
};

?>
<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Inventario</p>
            <h1 class="h3 mb-0">Grupos de partes</h1>
        </div>
    </div>
</section>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="text-muted small text-uppercase mb-1">Formulario</p>
                        <h2 class="h5 mb-0"><?= $editing ? 'Editar grupo' : 'Nuevo grupo' ?></h2>
                    </div>
                    <?php if ($editing) : ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('configuracion/grupos-partes') ?>">
                            Cancelar
                        </a>
                    <?php endif; ?>
                </div>
                <?php if (isset($errors['general'])) : ?>
                    <div class="alert alert-danger"><?= View::escape($errors['general']) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= $editing ? url('configuracion/grupos-partes/' . (int) $editing['id']) : url('configuracion/grupos-partes') ?>" class="vstack gap-3">
                    <?php if ($editing) : ?>
                        <input type="hidden" name="_method" value="PUT">
                    <?php endif; ?>
                    <div>
                        <label class="form-label">Código</label>
                        <input class="form-control<?= isset($errors['codigo']) ? ' is-invalid' : '' ?>" type="text" name="codigo" value="<?= View::escape($oldValue('codigo')) ?>" required>
                        <?php if (isset($errors['codigo'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['codigo']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label">Nombre</label>
                        <input class="form-control<?= isset($errors['nombre']) ? ' is-invalid' : '' ?>" type="text" name="nombre" value="<?= View::escape($oldValue('nombre')) ?>" required>
                        <?php if (isset($errors['nombre'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['nombre']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control" name="descripcion" rows="3"><?= View::escape($oldValue('descripcion')) ?></textarea>
                    </div>
                    <div>
                        <label class="form-label">Color</label>
                        <input class="form-control form-control-color<?= isset($errors['color']) ? ' is-invalid' : '' ?>" type="color" name="color" value="<?= View::escape($oldValue('color', '#0d6efd')) ?>">
                        <?php if (isset($errors['color'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['color']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activo" id="grupo-activo" <?= (int) $oldValue('activo', 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="grupo-activo">Grupo activo</label>
                    </div>
                    <div class="d-grid">
                        <button class="btn btn-primary" type="submit">
                            <?= $editing ? 'Actualizar grupo' : 'Crear grupo' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Listado</h2>
                    <span class="text-muted small"><?= count($grupos) ?> resultados</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Color</th>
                                <th class="text-center">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grupos as $grupo) : ?>
                                <tr>
                                    <td class="fw-semibold"><?= View::escape($grupo['codigo']) ?></td>
                                    <td><?= View::escape($grupo['nombre']) ?></td>
                                    <td class="text-muted small">
                                        <?= $grupo['descripcion'] ? View::escape($grupo['descripcion']) : '<span class="text-body-secondary">Sin descripción</span>' ?>
                                    </td>
                                    <td>
                                        <span class="badge border" style="background-color: <?= View::escape($grupo['color'] ?? '#0d6efd') ?>;">&nbsp;</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= (int) $grupo['activo'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= (int) $grupo['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a class="btn btn-outline-secondary" href="<?= url('configuracion/grupos-partes/' . (int) $grupo['id'] . '/editar') ?>">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <form method="post" action="<?= url('configuracion/grupos-partes/' . (int) $grupo['id']) ?>" onsubmit="return confirm('¿Eliminar grupo?');">
                                                <input type="hidden" name="_method" value="DELETE">
                                                <button class="btn btn-outline-danger" type="submit">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($grupos === []) : ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Sin registros para mostrar.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
