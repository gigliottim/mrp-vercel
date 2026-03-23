<?php

declare(strict_types=1);

use App\Core\View\View;

$old = $old ?? [];
$editing = $editing ?? null;
$errors = $errors ?? [];
$empresas = $empresas ?? [];

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

<?php $activeTab = 'empresa';
include __DIR__ . '/_header.php'; ?>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="text-muted small text-uppercase mb-1">Formulario</p>
                        <h2 class="h5 mb-0">Editar empresa activa</h2>
                    </div>
                    <?php if ($editing) : ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/empresa-usuarios/empresa') ?>">Cancelar</a>
                    <?php endif; ?>
                </div>

                <?php if (isset($errors['general'])) : ?>
                    <div class="alert alert-danger"><?= View::escape($errors['general']) ?></div>
                <?php endif; ?>

                <form
                    method="post"
                    action="<?= $editing ? url('/empresa-usuarios/empresa/' . (int) $editing['id']) : url('/empresa-usuarios/empresa') ?>"
                    class="vstack gap-3">
                    <input type="hidden" name="_method" value="PUT">

                    <div>
                        <label class="form-label" for="nombre">Nombre legal</label>
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
                        <label class="form-label" for="slug">Slug</label>
                        <input
                            id="slug"
                            class="form-control<?= isset($errors['slug']) ? ' is-invalid' : '' ?>"
                            type="text"
                            name="slug"
                            value="<?= View::escape($oldValue('slug')) ?>"
                            placeholder="mi-empresa"
                            required>
                        <?php if (isset($errors['slug'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['slug']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label" for="cuit">CUIT</label>
                        <input
                            id="cuit"
                            class="form-control<?= isset($errors['cuit']) ? ' is-invalid' : '' ?>"
                            type="text"
                            name="cuit"
                            value="<?= View::escape($oldValue('cuit')) ?>"
                            placeholder="30-12345678-9">
                        <?php if (isset($errors['cuit'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['cuit']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label" for="email">Email de contacto</label>
                        <input
                            id="email"
                            class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                            type="email"
                            name="email"
                            value="<?= View::escape($oldValue('email')) ?>">
                        <?php if (isset($errors['email'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-check form-switch">
                        <?php $isOwnCompany = (isset($currentCompanyId) && isset($editing['id']) && $currentCompanyId === $editing['id']); ?>
                        <input
                            id="empresa-activa"
                            class="form-check-input"
                            type="checkbox"
                            name="activo"
                            <?= (int) $oldValue('activo', '1') === 1 ? 'checked' : '' ?>
                            <?= $isOwnCompany ? 'disabled' : '' ?>>
                        <label class="form-check-label" for="empresa-activa">Empresa activa</label>
                        <?php if ($isOwnCompany): ?>
                            <small class="text-muted d-block" style="font-size: 0.75rem;">No puede desactivar la empresa de su sesión actual.</small>
                            <input type="hidden" name="activo" value="<?= (int) $oldValue('activo', '1') ?>">
                        <?php endif; ?>
                    </div>

                    <div class="d-grid">
                        <button class="btn btn-primary" type="submit">Actualizar empresa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0"><?= (isset($isSuperAdmin) && $isSuperAdmin) ? 'Todas las empresas' : 'Empresa activa' ?></h2>
                    <span class="text-muted small"><?= count($empresas) ?> resultados</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Slug</th>
                                <th>CUIT</th>
                                <th>Email</th>
                                <th>Estado</th>
                                <th>Accion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($empresas as $empresa) : ?>
                                <tr>
                                    <td class="fw-semibold"><?= View::escape((string) ($empresa['nombre'] ?? '')) ?></td>
                                    <td><?= View::escape((string) ($empresa['slug'] ?? '')) ?></td>
                                    <td><?= View::escape((string) ($empresa['cuit'] ?? '')) ?></td>
                                    <td><?= View::escape((string) ($empresa['email'] ?? '')) ?></td>
                                    <td>
                                        <?php $activa = (int) ($empresa['activo'] ?? 0) === 1; ?>
                                        <span class="badge <?= $activa ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= $activa ? 'Activa' : 'Inactiva' ?>
                                        </span>
                                    </td>
                                    <td class="text-end d-flex gap-1 justify-content-end">
                                        <a
                                            class="btn btn-sm btn-outline-secondary"
                                            href="<?= url('/empresa-usuarios/empresa/' . (int) ($empresa['id'] ?? 0) . '/editar') ?>">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <?php if (isset($isSuperAdmin) && $isSuperAdmin) : ?>
                                            <?php $esPropia = (isset($currentCompanyId) && (int) ($empresa['id'] ?? -1) === $currentCompanyId); ?>
                                            <?php if ($esPropia) : ?>
                                                <button class="btn btn-sm btn-outline-danger" type="button" disabled title="No puedes eliminar tu propia empresa">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            <?php else : ?>
                                                <button
                                                    class="btn btn-sm btn-danger"
                                                    type="button"
                                                    data-delete-empresa="<?= (int) ($empresa['id'] ?? 0) ?>"
                                                    data-delete-nombre="<?= View::escape((string) ($empresa['nombre'] ?? '')) ?>"
                                                    title="Eliminar empresa">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if ($empresas === []) : ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        Sin empresas para mostrar.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($isSuperAdmin) && $isSuperAdmin) : ?>
    <div class="modal fade" id="modalEliminarEmpresa" tabindex="-1" aria-labelledby="modalEliminarEmpresaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalEliminarEmpresaLabel">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>Eliminar empresa
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1">Vas a eliminar permanentemente la empresa:</p>
                    <p class="fw-bold fs-5 mb-3" data-delete-nombre></p>
                    <div class="alert alert-danger py-2 mb-3">
                        <i class="fa-solid fa-database me-1"></i>
                        Esta accion eliminara la base de datos completa y todos sus datos. <strong>Es irreversible.</strong>
                    </div>
                    <p class="mb-1 text-muted small">Escribe el siguiente codigo para confirmar:</p>
                    <p class="font-monospace fw-bold fs-4 text-center letter-spacing-wide py-2 rounded bg-light mb-3" data-delete-codigo></p>
                    <input
                        class="form-control"
                        type="text"
                        maxlength="6"
                        autocomplete="off"
                        placeholder="Ingresa el codigo"
                        data-delete-input>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form
                        method="post"
                        data-delete-form
                        data-base-action="<?= url('/empresa-usuarios/empresa/__ID__') ?>">
                        <input type="hidden" name="_method" value="DELETE">
                        <button class="btn btn-danger" type="submit" data-delete-confirm disabled>
                            <i class="fa-solid fa-trash me-1"></i>Eliminar empresa
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= \App\Core\Support\AssetHelper::js('modules/empresa-usuarios/empresa-delete.js') ?>" defer></script>
<?php endif; ?>
