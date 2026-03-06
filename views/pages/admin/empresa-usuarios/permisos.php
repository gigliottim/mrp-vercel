<?php

declare(strict_types=1);

use App\Core\View\View;

$old = $old ?? [];
$editing = $editing ?? null;
$errors = $errors ?? [];
$aclRows = $aclRows ?? [];
$menuTree = $menuTree ?? [];
$subjects = $subjects ?? [];

$oldValue = static function (string $field, $default = '') use ($old, $editing) {
    if (array_key_exists($field, $old)) {
        return $old[$field];
    }

    if ($editing !== null && array_key_exists($field, $editing)) {
        return $editing[$field];
    }

    return $default;
};

$subjectTypeOptions = [
    'role' => 'Rol',
    'user' => 'Usuario',
];

$scopeOptions = [
    'item' => 'Item',
    'branch' => 'Branch',
];

$permissionOptions = [
    'read' => 'Read',
    'write' => 'Write',
    'deny' => 'Deny',
];

if ($menuTree === []) {
    $menuTree = [
        ['id' => 0, 'label' => 'Empresa', 'code' => 'empresa'],
        ['id' => 0, 'label' => 'Usuarios', 'code' => 'usuarios'],
        ['id' => 0, 'label' => 'Roles', 'code' => 'roles'],
        ['id' => 0, 'label' => 'Permisos', 'code' => 'permisos'],
    ];
}
?>

<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Empresa y Usuarios</p>
            <h1 class="h3 mb-0">Permisos</h1>
        </div>
    </div>
</section>

<nav class="nav nav-pills mb-4 flex-wrap gap-2">
    <a class="nav-link" href="<?= url('/empresa-usuarios/empresa') ?>">Empresa</a>
    <a class="nav-link" href="<?= url('/empresa-usuarios/usuarios') ?>">Usuarios</a>
    <a class="nav-link" href="<?= url('/empresa-usuarios/roles') ?>">Roles</a>
    <a class="nav-link active" href="<?= url('/empresa-usuarios/permisos') ?>">Permisos</a>
</nav>

<div class="row g-4">
    <div class="col-12 col-xl-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="text-muted small text-uppercase mb-1">Formulario ACL</p>
                        <h2 class="h5 mb-0"><?= $editing ? 'Editar permiso' : 'Nuevo permiso' ?></h2>
                    </div>
                    <?php if ($editing) : ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/empresa-usuarios/permisos') ?>">Cancelar</a>
                    <?php endif; ?>
                </div>

                <?php if (isset($errors['general'])) : ?>
                    <div class="alert alert-danger"><?= View::escape($errors['general']) ?></div>
                <?php endif; ?>

                <form
                    method="post"
                    action="<?= $editing ? url('/roles-permisos/acl/' . (int) $editing['id']) : url('/roles-permisos/acl') ?>"
                    class="vstack gap-3">
                    <?php if ($editing) : ?>
                        <input type="hidden" name="_method" value="PUT">
                    <?php endif; ?>

                    <div>
                        <label class="form-label" for="menu_item_id">Arbol</label>
                        <select
                            id="menu_item_id"
                            class="form-select<?= isset($errors['menu_item_id']) ? ' is-invalid' : '' ?>"
                            name="menu_item_id"
                            required>
                            <?php $selectedMenu = (string) $oldValue('menu_item_id', ''); ?>
                            <?php foreach ($menuTree as $node) : ?>
                                <?php
                                $nodeId = (string) ($node['id'] ?? '');
                                $nodeLabel = (string) ($node['label'] ?? $nodeId);
                                $nodeCode = (string) ($node['code'] ?? '');
                                ?>
                                <option value="<?= View::escape($nodeId) ?>" <?= $selectedMenu === $nodeId ? 'selected' : '' ?>>
                                    <?= View::escape($nodeLabel . ($nodeCode !== '' ? ' (' . $nodeCode . ')' : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['menu_item_id'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['menu_item_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label" for="subject_type">Sujeto</label>
                        <select
                            id="subject_type"
                            class="form-select<?= isset($errors['subject_type']) ? ' is-invalid' : '' ?>"
                            name="subject_type"
                            required>
                            <?php $selectedSubjectType = (string) $oldValue('subject_type', 'role'); ?>
                            <?php foreach ($subjectTypeOptions as $key => $label) : ?>
                                <option value="<?= $key ?>" <?= $selectedSubjectType === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['subject_type'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['subject_type']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="form-label" for="subject_id">ID Rol/Usuario</label>
                        <input
                            id="subject_id"
                            class="form-control<?= isset($errors['subject_id']) ? ' is-invalid' : '' ?>"
                            type="number"
                            min="1"
                            step="1"
                            name="subject_id"
                            value="<?= View::escape((string) $oldValue('subject_id')) ?>"
                            required>
                        <?php if (isset($errors['subject_id'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['subject_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" for="scope">Scope</label>
                            <select
                                id="scope"
                                class="form-select<?= isset($errors['scope']) ? ' is-invalid' : '' ?>"
                                name="scope"
                                required>
                                <?php $selectedScope = (string) $oldValue('scope', 'item'); ?>
                                <?php foreach ($scopeOptions as $key => $label) : ?>
                                    <option value="<?= $key ?>" <?= $selectedScope === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['scope'])) : ?>
                                <div class="invalid-feedback"><?= View::escape($errors['scope']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="permission_level">Permiso</label>
                            <select
                                id="permission_level"
                                class="form-select<?= isset($errors['permission_level']) ? ' is-invalid' : '' ?>"
                                name="permission_level"
                                required>
                                <?php $selectedPerm = (string) $oldValue('permission_level', 'read'); ?>
                                <?php foreach ($permissionOptions as $key => $label) : ?>
                                    <option value="<?= $key ?>" <?= $selectedPerm === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['permission_level'])) : ?>
                                <div class="invalid-feedback"><?= View::escape($errors['permission_level']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button class="btn btn-primary" type="submit">
                            <?= $editing ? 'Actualizar permiso' : 'Crear permiso' ?>
                        </button>
                    </div>
                </form>

                <?php if ($subjects !== []) : ?>
                    <div class="mt-4">
                        <p class="text-muted small text-uppercase mb-2">Referencia sujetos</p>
                        <ul class="list-group list-group-flush border rounded">
                            <?php foreach ($subjects as $subject) : ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?= View::escape((string) ($subject['label'] ?? '')) ?></span>
                                    <span class="badge text-bg-light">ID <?= View::escape((string) ($subject['id'] ?? '')) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-8">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Tabla ACL (Arbol / Sujeto / Permiso)</h2>
                    <span class="text-muted small"><?= count($aclRows) ?> resultados</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Arbol</th>
                                <th>Sujeto</th>
                                <th>Permiso</th>
                                <th>Scope</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aclRows as $row) : ?>
                                <?php
                                $subjectText = (string) (($row['subject_type'] ?? '') . ':' . ($row['subject_id'] ?? ''));
                                $permission = (string) ($row['permission_level'] ?? 'read');
                                $badgeClass = 'text-bg-secondary';
                                if ($permission === 'write') {
                                    $badgeClass = 'text-bg-primary';
                                }
                                if ($permission === 'deny') {
                                    $badgeClass = 'text-bg-danger';
                                }
                                ?>
                                <tr>
                                    <td><?= View::escape((string) ($row['menu_label'] ?? '')) ?></td>
                                    <td><?= View::escape($subjectText) ?></td>
                                    <td><span class="badge <?= $badgeClass ?>"><?= View::escape($permission) ?></span></td>
                                    <td><?= View::escape((string) ($row['scope'] ?? 'item')) ?></td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a class="btn btn-outline-secondary" href="<?= url('/roles-permisos/acl/' . (int) ($row['id'] ?? 0) . '/editar') ?>">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <form
                                                method="post"
                                                action="<?= url('/roles-permisos/acl/' . (int) ($row['id'] ?? 0)) ?>"
                                                onsubmit="return confirm('Eliminar permiso ACL?');">
                                                <input type="hidden" name="_method" value="DELETE">
                                                <button class="btn btn-outline-danger" type="submit">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if ($aclRows === []) : ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Sin reglas ACL para mostrar.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
