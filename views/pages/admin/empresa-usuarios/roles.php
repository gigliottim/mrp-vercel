<?php

declare(strict_types=1);

use App\Core\View\View;

$old = $old ?? [];
$editing = $editing ?? null;
$errors = $errors ?? [];
$roles = $roles ?? [];

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

<?php $activeTab = 'roles';
include __DIR__ . '/_header.php'; ?>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="text-muted small text-uppercase mb-1">Formulario</p>
                        <h2 class="h5 mb-0"><?= $editing ? 'Editar rol' : 'Nuevo rol' ?></h2>
                    </div>
                    <?php if ($editing) : ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/empresa-usuarios/roles') ?>">Cancelar</a>
                    <?php endif; ?>
                </div>

                <?php if (isset($errors['general'])) : ?>
                    <div class="alert alert-danger"><?= View::escape($errors['general']) ?></div>
                <?php endif; ?>

                <form
                    method="post"
                    action="<?= $editing ? url('/empresa-usuarios/roles/' . (int) $editing['id']) : url('/empresa-usuarios/roles') ?>"
                    class="vstack gap-3">
                    <?php if ($editing) : ?>
                        <input type="hidden" name="_method" value="PUT">
                    <?php endif; ?>

                    <div>
                        <label class="form-label" for="nombre">Nombre</label>
                        <input
                            id="nombre"
                            class="form-control<?= isset($errors['nombre']) ? ' is-invalid' : '' ?>"
                            type="text"
                            name="nombre"
                            value="<?= View::escape($oldValue('nombre')) ?>"
                            required>
                        <?php if (isset($errors['nombre'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['nombre']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label" for="codigo">Codigo</label>
                        <input
                            id="codigo"
                            class="form-control<?= isset($errors['codigo']) ? ' is-invalid' : '' ?>"
                            type="text"
                            name="codigo"
                            value="<?= View::escape($oldValue('codigo')) ?>"
                            placeholder="operador"
                            required>
                        <?php if (isset($errors['codigo'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['codigo']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label" for="descripcion">Descripcion</label>
                        <textarea
                            id="descripcion"
                            class="form-control<?= isset($errors['descripcion']) ? ' is-invalid' : '' ?>"
                            name="descripcion"
                            rows="3"><?= View::escape($oldValue('descripcion')) ?></textarea>
                        <?php if (isset($errors['descripcion'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['descripcion']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-check form-switch">
                        <input
                            id="rol-activo"
                            class="form-check-input"
                            type="checkbox"
                            name="activo"
                            <?= (int) $oldValue('activo', 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="rol-activo">Rol activo</label>
                    </div>

                    <div class="d-grid">
                        <button class="btn btn-primary" type="submit">
                            <?= $editing ? 'Actualizar rol' : 'Crear rol' ?>
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
                    <h2 class="h5 mb-0">Listado de roles</h2>
                    <span class="text-muted small"><?= count($roles) ?> resultados</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Codigo</th>
                                <th>Descripcion</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $rol) : ?>
                                <?php $esSistema = !empty($rol['is_system']); ?>
                                <tr>
                                    <td class="fw-semibold">
                                        <?= View::escape((string) ($rol['nombre'] ?? '')) ?>
                                        <?php if ($esSistema) : ?>
                                            <span class="badge text-bg-secondary ms-1" title="Rol del sistema: no se puede eliminar ni editar">
                                                <i class="fa-solid fa-lock"></i> Sistema
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= View::escape((string) ($rol['codigo'] ?? '')) ?></td>
                                    <td><?= View::escape((string) ($rol['descripcion'] ?? '')) ?></td>
                                    <td>
                                        <?php $activo = (int) ($rol['activo'] ?? 0) === 1; ?>
                                        <span class="badge <?= $activo ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= $activo ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($esSistema) : ?>
                                            <span class="text-muted small" title="Rol del sistema protegido">
                                                <i class="fa-solid fa-shield-halved"></i>
                                            </span>
                                        <?php else : ?>
                                            <div class="btn-group btn-group-sm">
                                                <a class="btn btn-outline-secondary" href="<?= url('/empresa-usuarios/roles/' . (int) ($rol['id'] ?? 0) . '/editar') ?>">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                <form
                                                    method="post"
                                                    action="<?= url('/empresa-usuarios/roles/' . (int) ($rol['id'] ?? 0)) ?>"
                                                    onsubmit="return confirm('Eliminar rol?');">
                                                    <input type="hidden" name="_method" value="DELETE">
                                                    <button class="btn btn-outline-danger" type="submit">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if ($roles === []) : ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Sin roles para mostrar.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
