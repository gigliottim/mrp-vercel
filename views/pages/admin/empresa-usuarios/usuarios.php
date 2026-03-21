<?php

declare(strict_types=1);

use App\Core\View\View;

$old = $old ?? [];
$editing = $editing ?? null;
$errors = $errors ?? [];
$usuarios = $usuarios ?? [];
$roles = $roles ?? [];
$isAdminCompany = (bool) ($isAdminCompany ?? true);
$currentUserId = (int) ($currentUserId ?? 0);

$oldValue = static function (string $field, $default = '') use ($old, $editing) {
    if (array_key_exists($field, $old)) {
        return $old[$field];
    }

    if ($editing !== null && array_key_exists($field, $editing)) {
        return $editing[$field];
    }

    return $default;
};

$roleOptions = [];
foreach ($roles as $role) {
    $roleOptions[(string) ($role['id'] ?? '')] = (string) ($role['nombre'] ?? '');
}
if ($roleOptions === []) {
    $roleOptions = [
        'admin_empresa' => 'Admin empresa',
        'operador' => 'Operador',
        'consulta' => 'Consulta',
    ];
}
?>

<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Empresa y Usuarios</p>
            <h1 class="h3 mb-0">Usuarios</h1>
        </div>
    </div>
</section>

<nav class="nav nav-pills mb-4 flex-wrap gap-2">
    <a class="nav-link" href="<?= url('/empresa-usuarios/empresa') ?>">Empresa</a>
    <a class="nav-link active" href="<?= url('/empresa-usuarios/usuarios') ?>">Usuarios</a>
    <a class="nav-link" href="<?= url('/empresa-usuarios/roles') ?>">Roles</a>
    <a class="nav-link" href="<?= url('/empresa-usuarios/permisos') ?>">Permisos</a>
</nav>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <p class="text-muted small text-uppercase mb-1">Formulario</p>
                        <h2 class="h5 mb-0">
                            <?= $isAdminCompany ? ($editing ? 'Editar usuario' : 'Nuevo usuario') : 'Cambiar mi password' ?>
                        </h2>
                    </div>
                    <?php if ($editing) : ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('/empresa-usuarios/usuarios') ?>">Cancelar</a>
                    <?php endif; ?>
                </div>

                <?php if (isset($errors['general'])) : ?>
                    <div class="alert alert-danger"><?= View::escape($errors['general']) ?></div>
                <?php endif; ?>

                <?php
                $formAction = $isAdminCompany
                    ? ($editing ? url('/empresa-usuarios/usuarios/' . (int) $editing['id']) : url('/empresa-usuarios/usuarios'))
                    : url('/empresa-usuarios/usuarios/' . $currentUserId);
                ?>
                <form method="post" action="<?= $formAction ?>" class="vstack gap-3">
                    <?php if ($editing || !$isAdminCompany) : ?>
                        <input type="hidden" name="_method" value="PUT">
                    <?php endif; ?>

                    <?php if ($isAdminCompany) : ?>
                        <div>
                            <label class="form-label" for="nombre">Nombre completo</label>
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
                            <label class="form-label" for="email">Email</label>
                            <input
                                id="email"
                                class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>"
                                type="email"
                                name="email"
                                value="<?= View::escape($oldValue('email')) ?>"
                                required>
                            <?php if (isset($errors['email'])) : ?>
                                <div class="invalid-feedback"><?= View::escape($errors['email']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label class="form-label" for="password">Password <?= ($editing && $isAdminCompany) ? '(dejar vacio para mantener)' : '' ?></label>
                        <input
                            id="password"
                            class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>"
                            type="password"
                            name="password"
                            <?= ($editing && $isAdminCompany) ? '' : 'required' ?>>
                        <?php if (isset($errors['password'])) : ?>
                            <div class="invalid-feedback"><?= View::escape($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($isAdminCompany) : ?>
                        <div>
                            <label class="form-label" for="role_id">Rol</label>
                            <select
                                id="role_id"
                                class="form-select<?= isset($errors['role_id']) ? ' is-invalid' : '' ?>"
                                name="role_id"
                                required>
                                <?php $selectedRole = (string) $oldValue('role_id', array_key_first($roleOptions)); ?>
                                <?php foreach ($roleOptions as $roleId => $roleName) : ?>
                                    <option value="<?= View::escape((string) $roleId) ?>" <?= $selectedRole === (string) $roleId ? 'selected' : '' ?>>
                                        <?= View::escape((string) $roleName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['role_id'])) : ?>
                                <div class="invalid-feedback"><?= View::escape($errors['role_id']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <div>
                            <label class="form-label" for="password_confirmation">Confirmar password</label>
                            <input
                                id="password_confirmation"
                                class="form-control<?= isset($errors['password_confirmation']) ? ' is-invalid' : '' ?>"
                                type="password"
                                name="password_confirmation"
                                required>
                            <?php if (isset($errors['password_confirmation'])) : ?>
                                <div class="invalid-feedback"><?= View::escape($errors['password_confirmation']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($isAdminCompany) : ?>
                        <div class="form-check form-switch">
                            <input
                                id="usuario-activo"
                                class="form-check-input"
                                type="checkbox"
                                name="activo"
                                <?= (int) $oldValue('activo', '1') === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="usuario-activo">Usuario activo</label>
                        </div>
                    <?php endif; ?>

                    <div class="d-grid">
                        <button class="btn btn-primary" type="submit">
                            <?= $isAdminCompany ? ($editing ? 'Actualizar usuario' : 'Crear usuario') : 'Actualizar password' ?>
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
                    <h2 class="h5 mb-0">Listado de usuarios</h2>
                    <span class="text-muted small"><?= count($usuarios) ?> resultados</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Ultimo acceso</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $usuario) : ?>
                                <tr>
                                    <td class="fw-semibold"><?= View::escape((string) ($usuario['nombre'] ?? '')) ?></td>
                                    <td><?= View::escape((string) ($usuario['email'] ?? '')) ?></td>
                                    <td><?= View::escape((string) ($usuario['rol_nombre'] ?? '')) ?></td>
                                    <td><?= View::escape((string) ($usuario['ultimo_acceso'] ?? '-')) ?></td>
                                    <td>
                                        <?php $activo = (int) ($usuario['activo'] ?? 0) === 1; ?>
                                        <span class="badge <?= $activo ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                            <?= $activo ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($isAdminCompany) : ?>
                                            <?php $isSuper = strtolower((string) ($usuario['rol_nombre'] ?? '')) === 'super administrador' || strtolower((string) ($usuario['rol_nombre'] ?? '')) === 'super_admin'; ?>
                                            <div class="btn-group btn-group-sm">
                                                <a
                                                    class="btn btn-outline-secondary"
                                                    href="<?= url('/empresa-usuarios/usuarios/' . (int) ($usuario['id'] ?? 0) . '/editar') ?>">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                                <?php if (!$isSuper) : ?>
                                                    <form
                                                        method="post"
                                                        action="<?= url('/empresa-usuarios/usuarios/' . (int) ($usuario['id'] ?? 0)) ?>"
                                                        onsubmit="return confirm('Eliminar usuario?');">
                                                        <input type="hidden" name="_method" value="DELETE">
                                                        <button class="btn btn-outline-danger" type="submit">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php else : ?>
                                            <span class="text-muted small">Solo lectura</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if ($usuarios === []) : ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        Sin usuarios para mostrar.
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
