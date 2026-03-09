<?php

use App\Core\View\View;

$old = $old ?? [];
$editing = $editing ?? null;
$errors = $errors ?? [];
$entidades = $entidades ?? [];

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
            <p class="text-uppercase text-muted small mb-1">Configuracion</p>
            <h1 class="h3 mb-0">Clientes y proveedores</h1>
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
                        <h2 class="h5 mb-0"><?= $editing ? 'Editar entidad' : 'Nueva entidad' ?></h2>
                    </div>
                    <?php if ($editing) : ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/configuracion/entidades') ?>">Cancelar</a>
                    <?php endif; ?>
                </div>

                <?php if (isset($errors['general'])) : ?>
                    <div class="alert alert-danger"><?= View::escape((string) $errors['general']) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= $editing ? url('/configuracion/entidades/' . (int) $editing['id']) : url('/configuracion/entidades') ?>" class="vstack gap-3">
                    <?php if ($editing) : ?>
                        <input type="hidden" name="_method" value="PUT">
                    <?php endif; ?>

                    <div>
                        <label class="form-label">Razon social</label>
                        <input class="form-control<?= isset($errors['razon_social']) ? ' is-invalid' : '' ?>"
                            type="text"
                            name="razon_social"
                            value="<?= View::escape((string) $oldValue('razon_social')) ?>"
                            required>
                        <?php if (isset($errors['razon_social'])) : ?>
                            <div class="invalid-feedback"><?= View::escape((string) $errors['razon_social']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label">Tipo</label>
                        <?php $tipo = strtoupper((string) $oldValue('tipo', 'PROVEEDOR')); ?>
                        <select class="form-select<?= isset($errors['tipo']) ? ' is-invalid' : '' ?>" name="tipo" required>
                            <option value="PROVEEDOR" <?= $tipo === 'PROVEEDOR' ? 'selected' : '' ?>>Proveedor</option>
                            <option value="CLIENTE" <?= $tipo === 'CLIENTE' ? 'selected' : '' ?>>Cliente</option>
                            <option value="AMBOS" <?= $tipo === 'AMBOS' ? 'selected' : '' ?>>Ambos</option>
                        </select>
                        <?php if (isset($errors['tipo'])) : ?>
                            <div class="invalid-feedback"><?= View::escape((string) $errors['tipo']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label">Identificacion tributaria</label>
                        <input class="form-control"
                            type="text"
                            name="identificacion_tributaria"
                            value="<?= View::escape((string) $oldValue('identificacion_tributaria')) ?>">
                    </div>

                    <div>
                        <label class="form-label">Email</label>
                        <input class="form-control<?= isset($errors['contacto_email']) ? ' is-invalid' : '' ?>"
                            type="email"
                            name="contacto_email"
                            value="<?= View::escape((string) $oldValue('contacto_email')) ?>">
                        <?php if (isset($errors['contacto_email'])) : ?>
                            <div class="invalid-feedback"><?= View::escape((string) $errors['contacto_email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label">Telefono</label>
                        <input class="form-control"
                            type="text"
                            name="contacto_telefono"
                            value="<?= View::escape((string) $oldValue('contacto_telefono')) ?>">
                    </div>

                    <div>
                        <label class="form-label">Direccion</label>
                        <textarea class="form-control" name="direccion" rows="2"><?= View::escape((string) $oldValue('direccion')) ?></textarea>
                    </div>

                    <div class="d-grid">
                        <button class="btn btn-primary" type="submit">
                            <?= $editing ? 'Actualizar entidad' : 'Crear entidad' ?>
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
                    <span class="text-muted small"><?= count($entidades) ?> resultados</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Razon social</th>
                                <th>Tipo</th>
                                <th>Email</th>
                                <th>Telefono</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entidades as $entidad) : ?>
                                <tr>
                                    <td class="fw-semibold"><?= View::escape((string) ($entidad['razon_social'] ?? '')) ?></td>
                                    <td><span class="badge text-bg-info"><?= View::escape((string) ($entidad['tipo'] ?? '')) ?></span></td>
                                    <td class="small text-muted"><?= View::escape((string) ($entidad['contacto_email'] ?? '-')) ?></td>
                                    <td class="small text-muted"><?= View::escape((string) ($entidad['contacto_telefono'] ?? '-')) ?></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a class="btn btn-outline-secondary" href="<?= url('/configuracion/entidades/' . (int) $entidad['id'] . '/editar') ?>">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <form method="post" action="<?= url('/configuracion/entidades/' . (int) $entidad['id']) ?>" onsubmit="return confirm('¿Eliminar entidad?');">
                                                <input type="hidden" name="_method" value="DELETE">
                                                <button class="btn btn-outline-danger" type="submit">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($entidades === []) : ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Sin registros para mostrar.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
