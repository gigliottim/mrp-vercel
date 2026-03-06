<?php

use App\Core\View\View;

$old = $old ?? [];
$editing = $editing ?? null;
$errors = $errors ?? [];
$filters = $filters ?? ['q' => ''];

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
            <p class="text-uppercase text-muted small mb-1">Producción</p>
            <h1 class="h3 mb-0">Centros de trabajo</h1>
        </div>
        <form class="d-flex gap-2" method="get" action="<?= url('produccion/centros') ?>">
            <input class="form-control" type="search" name="q" placeholder="Buscar por código o nombre" value="<?= View::escape($filters['q'] ?? '') ?>">
            <button class="btn btn-outline-secondary" type="submit">
                <i class="fa-solid fa-magnifying-glass me-1"></i>Buscar
            </button>
        </form>
    </div>
</section>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="text-muted small text-uppercase mb-1">Formulario</p>
                        <h2 class="h5 mb-0"><?= $editing ? 'Editar centro' : 'Nuevo centro' ?></h2>
                    </div>
                    <?php if ($editing) : ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('produccion/centros') ?>">
                            Cancelar
                        </a>
                    <?php endif; ?>
                </div>
                <?php if (isset($errors['general'])) : ?>
                    <div class="alert alert-danger"><?= View::escape($errors['general']) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= $editing ? url('produccion/centros/' . (int) $editing['id']) : url('produccion/centros') ?>" class="vstack gap-3">
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
                        <label class="form-label">Tipo de centro</label>
                        <select class="form-select<?= isset($errors['tipo']) ? ' is-invalid' : '' ?>" name="tipo">
                            <?php
                            $tipos = [
                                'manual' => 'Manual',
                                'semi_automatico' => 'Semi automático',
                                'automatico' => 'Automático',
                            ];
                            $selectedTipo = $oldValue('tipo', 'manual');
                            foreach ($tipos as $value => $label) :
                            ?>
                                <option value="<?= $value ?>" <?= $selectedTipo === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['tipo'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['tipo']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Horas/día</label>
                            <input class="form-control" type="number" step="0.25" name="capacidad_horas_dia" value="<?= View::escape($oldValue('capacidad_horas_dia', 8)) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Eficiencia %</label>
                            <input class="form-control" type="number" step="0.1" name="eficiencia_porcentaje" value="<?= View::escape($oldValue('eficiencia_porcentaje', 100)) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Costo por hora</label>
                            <input class="form-control" type="number" step="0.01" name="costo_hora" value="<?= View::escape($oldValue('costo_hora', 0)) ?>">
                        </div>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activo" id="centro-activo" <?= (int) $oldValue('activo', 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="centro-activo">Centro activo</label>
                    </div>
                    <div class="d-grid">
                        <button class="btn btn-primary" type="submit">
                            <?= $editing ? 'Actualizar centro' : 'Crear centro' ?>
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
                    <span class="text-muted small"><?= count($centros) ?> resultados</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Capacidad</th>
                                <th>Costo</th>
                                <th class="text-center">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($centros as $centro) : ?>
                                <tr>
                                    <td class="fw-semibold"><?= View::escape($centro['codigo']) ?></td>
                                    <td><?= View::escape($centro['nombre']) ?></td>
                                    <td class="text-capitalize"><?= str_replace('_', ' ', View::escape($centro['tipo'])) ?></td>
                                    <td><?= View::escape(number_format((float) $centro['capacidad_horas_dia'], 2)) ?> h</td>
                                    <td>$<?= View::escape(number_format((float) $centro['costo_hora'], 2)) ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= (int) $centro['activo'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= (int) $centro['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a class="btn btn-outline-secondary" href="<?= url('produccion/centros/' . (int) $centro['id'] . '/editar') ?>">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <form method="post" action="<?= url('produccion/centros/' . (int) $centro['id']) ?>" onsubmit="return confirm('¿Eliminar centro?');">
                                                <input type="hidden" name="_method" value="DELETE">
                                                <button class="btn btn-outline-danger" type="submit">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($centros === []) : ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Sin registros para mostrar.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
