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
            <p class="text-uppercase text-muted small mb-1">Configuración</p>
            <h1 class="h3 mb-0">Tipos de depósito</h1>
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
                        <h2 class="h5 mb-0"><?= $editing ? 'Editar tipo' : 'Nuevo tipo' ?></h2>
                    </div>
                    <?php if ($editing) : ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/configuracion/tipos-depositos') ?>">
                            Cancelar
                        </a>
                    <?php endif; ?>
                </div>
                <?php if (isset($errors['general'])) : ?>
                    <div class="alert alert-danger"><?= View::escape($errors['general']) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= $editing ? url('/configuracion/tipos-depositos/' . (int) $editing['id']) : url('/configuracion/tipos-depositos') ?>" class="vstack gap-3">
                    <?php if ($editing) : ?>
                        <input type="hidden" name="_method" value="PUT">
                    <?php endif; ?>
                    <div>
                        <label class="form-label">Código</label>
                        <input class="form-control<?= isset($errors['codigo']) ? ' is-invalid' : '' ?>"
                            type="text"
                            name="codigo"
                            value="<?= View::escape($oldValue('codigo')) ?>"
                            required>
                        <?php if (isset($errors['codigo'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['codigo']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label">Nombre</label>
                        <input class="form-control<?= isset($errors['nombre']) ? ' is-invalid' : '' ?>"
                            type="text"
                            name="nombre"
                            value="<?= View::escape($oldValue('nombre')) ?>"
                            required>
                        <?php if (isset($errors['nombre'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['nombre']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label">Descripción</label>
                        <textarea class="form-control"
                            name="descripcion"
                            rows="3"><?= View::escape($oldValue('descripcion')) ?></textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activo" id="tipo-activo" <?= (int) $oldValue('activo', 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tipo-activo">Tipo activo</label>
                    </div>
                    <div class="d-grid">
                        <button class="btn btn-primary" type="submit">
                            <?= $editing ? 'Actualizar tipo' : 'Crear tipo' ?>
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
                    <span class="text-muted small"><?= count($tipos) ?> resultados</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Descripción</th>

                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tipos as $tipo) : ?>
                                <?php $esSistema = (bool) ($tipo['es_sistema'] ?? false); ?>
                                <tr>
                                    <td class="text-muted small font-monospace"><?= (int) $tipo['id'] ?></td>
                                    <td class="fw-semibold">
                                        <?= View::escape($tipo['codigo']) ?>
                                        <?php if ($esSistema) : ?>
                                            <i class="fa-solid fa-lock text-muted ms-1" title="Tipo del sistema"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= View::escape($tipo['nombre']) ?></td>
                                    <td class="text-muted small">
                                        <?= $tipo['descripcion'] ? View::escape($tipo['descripcion']) : '<span class="text-body-secondary">Sin descripción</span>' ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= (int) $tipo['activo'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $tipo['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?php if (!$esSistema) : ?>
                                            <div class="btn-group btn-group-sm">
                                                <a class="btn btn-outline-secondary" href="<?= url('/configuracion/tipos-depositos/' . (int) $tipo['id'] . '/editar') ?>">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                <form method="post" action="<?= url('/configuracion/tipos-depositos/' . (int) $tipo['id']) ?>" onsubmit="return confirm('¿Eliminar tipo de depósito?');">
                                                    <input type="hidden" name="_method" value="DELETE">
                                                    <button class="btn btn-outline-danger" type="submit">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else : ?>
                                            <span class="text-muted small" title="Los tipos del sistema no se pueden modificar">
                                                <i class="fa-solid fa-lock"></i> Protegido
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($tipos === []) : ?>
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
