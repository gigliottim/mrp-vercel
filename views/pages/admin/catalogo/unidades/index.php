<?php

use App\Core\View\View;

$old = $old ?? [];
$editing = $editing ?? null;
$errors = $errors ?? [];
$tipos = [
    'longitud' => 'Longitud',
    'masa' => 'Masa',
    'superficie' => 'Superficie',
    'temperatura' => 'Temperatura',
    'tiempo' => 'Tiempo',
    'unidad' => 'Unidad',
    'volumen' => 'Volumen',
];

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
            <h1 class="h3 mb-0">Unidades de medida</h1>
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
                        <h2 class="h5 mb-0"><?= $editing ? 'Editar unidad' : 'Nueva unidad' ?></h2>
                    </div>
                    <?php if ($editing) : ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/configuracion/unidades') ?>">
                            Cancelar
                        </a>
                    <?php endif; ?>
                </div>
                <?php if (isset($errors['general'])) : ?>
                    <div class="alert alert-danger"><?= View::escape($errors['general']) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= $editing ? url('/configuracion/unidades/' . (int) $editing['id']) : url('/configuracion/unidades') ?>" class="vstack gap-3">
                    <?php if ($editing) : ?>
                        <input type="hidden" name="_method" value="PUT">
                    <?php endif; ?>
                    <div>
                        <label class="form-label">Tipo</label>
                        <select class="form-select<?= isset($errors['tipo']) ? ' is-invalid' : '' ?>" name="tipo">
                            <?php $selectedTipo = $oldValue('tipo', 'longitud'); ?>
                            <?php foreach ($tipos as $value => $label) : ?>
                                <option value="<?= $value ?>" <?= $selectedTipo === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['tipo'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['tipo']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label">Nombre de la unidad</label>
                        <input class="form-control<?= isset($errors['unidad']) ? ' is-invalid' : '' ?>" type="text" name="unidad" value="<?= View::escape($oldValue('unidad')) ?>" required>
                        <?php if (isset($errors['unidad'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['unidad']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="form-label">Símbolo</label>
                        <input class="form-control<?= isset($errors['simbolo']) ? ' is-invalid' : '' ?>" type="text" name="simbolo" value="<?= View::escape($oldValue('simbolo')) ?>" maxlength="10" required>
                        <?php if (isset($errors['simbolo'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['simbolo']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Equivalencia base</label>
                            <input class="form-control<?= isset($errors['equivalencia_base']) ? ' is-invalid' : '' ?>" type="number" step="0.0001" name="equivalencia_base" value="<?= View::escape((string) (float) $oldValue('equivalencia_base', 1)) ?>">
                            <?php if (isset($errors['equivalencia_base'])) : ?>
                                <div class="invalid-feedback"><?= View::escape($errors['equivalencia_base']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Unidad base</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input<?= isset($errors['es_base']) ? ' is-invalid' : '' ?>" type="checkbox" name="es_base" id="unidad-base" <?= (int) $oldValue('es_base', 0) === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="unidad-base">Marcar como unidad base</label>
                            </div>
                            <?php if (isset($errors['es_base'])) : ?>
                                <div class="text-danger small mt-1"><?= View::escape($errors['es_base']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activo" id="unidad-activa" <?= (int) $oldValue('activo', 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="unidad-activa">Unidad activa</label>
                    </div>
                    <div class="d-grid">
                        <button class="btn btn-primary" type="submit">
                            <?= $editing ? 'Actualizar unidad' : 'Crear unidad' ?>
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
                    <span class="text-muted small"><?= count($unidades) ?> resultados</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tipo</th>
                                <th>Unidad</th>
                                <th>Símbolo</th>
                                <th>Equivalencia</th>
                                <th>Base</th>
                                <th>Sistema</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unidades as $unidad) : ?>
                                <?php
                                $isSystemEffective = ((int) ($unidad['_is_system_effective'] ?? 0) === 1);
                                $isLockedEffective = ((int) ($unidad['_is_locked_effective'] ?? 0) === 1);
                                $isProtected = $isSystemEffective || $isLockedEffective;
                                ?>
                                <tr>
                                    <td class="text-capitalize"><?= View::escape(str_replace('_', ' ', $unidad['tipo'])) ?></td>
                                    <td><?= View::escape($unidad['unidad']) ?></td>
                                    <td><?= View::escape($unidad['simbolo']) ?></td>
                                    <td><?= View::escape(app_format_number($unidad['equivalencia_base'], 8)) ?></td>
                                    <td>
                                        <span class="badge <?= (int) $unidad['es_base'] === 1 ? 'text-bg-info' : 'text-bg-light' ?>">
                                            <?= (int) $unidad['es_base'] === 1 ? 'Sí' : 'No' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($isSystemEffective) : ?>
                                            <span class="badge text-bg-primary">Sistema</span>
                                        <?php elseif ($isLockedEffective) : ?>
                                            <span class="badge text-bg-dark">Bloqueada</span>
                                        <?php else : ?>
                                            <span class="badge text-bg-light">Custom</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= (int) $unidad['activo'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= (int) $unidad['activo'] === 1 ? 'Activa' : 'Inactiva' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($isProtected) : ?>
                                            <span class="badge text-bg-secondary">Protegida</span>
                                        <?php else : ?>
                                            <div class="btn-group btn-group-sm">
                                                <a class="btn btn-outline-secondary" href="<?= url('/configuracion/unidades/' . (int) $unidad['id'] . '/editar') ?>">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                <form method="post" action="<?= url('/configuracion/unidades/' . (int) $unidad['id']) ?>" onsubmit="return confirm('¿Eliminar unidad de medida?');">
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
                            <?php if ($unidades === []) : ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Sin registros para mostrar.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
