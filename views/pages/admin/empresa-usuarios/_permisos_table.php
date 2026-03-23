<?php

declare(strict_types=1);

use App\Core\View\View;

/**
 * Parcial: columna derecha con la tabla de asignación de permisos ACL.
 *
 * Variables de entrada esperadas:
 *   $rolesList, $usersList
 */
?>
<div id="acl-col-right" class="col-12 col-md-8 d-flex flex-column">
    <div class="card d-flex flex-column flex-grow-1">
        <div class="card-header bg-light d-flex justify-content-between align-items-center sticky-top">
            <div>
                <h3 class="h6 mb-0">Asignación de Permisos</h3>
                <small class="text-muted">Para: <strong id="selected-node-title" class="text-primary">-</strong>
                    <span id="selected-node-type" class="badge bg-secondary ms-1">Seleccione un elemento</span></small>
            </div>
            <div class="input-group input-group-sm" style="width: 200px;">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input type="text" class="form-control" placeholder="Buscar sujeto...">
            </div>
        </div>

        <div class="card-body p-0 flex-grow-1" style="overflow-y: auto; min-height: 0;">
            <div class="table-responsive h-100">
                <table class="table table-hover align-middle mb-0 border-top-0">
                    <thead class="table-light sticky-top" style="top: 0px; z-index: 10;">
                        <tr>
                            <th class="ps-4">Sujeto (Rol / Usuario)</th>
                            <th style="width: 35%">Nivel de Permiso</th>
                            <th style="width: 15%" class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- ROLES -->
                        <tr>
                            <td colspan="3" class="p-0">
                                <div class="role-group-header ms-3 me-3"><i class="fa-solid fa-shield-halved text-secondary me-2"></i>Roles</div>
                            </td>
                        </tr>
                        <?php foreach ($rolesList as $role) : ?>
                            <tr class="permission-row">
                                <td class="ps-4 fw-medium"><?= View::escape((string) ($role['label'] ?? '')) ?></td>
                                <td>
                                    <select name="perms[role][<?= (int) ($role['id'] ?? 0) ?>]" class="form-select form-select-sm border-0 bg-transparent shadow-none" onchange="updateRowState(this)">
                                        <option value="none" selected>Heredada (Acceder)</option>
                                        <option value="allow">Acceder</option>
                                        <option value="deny">Denegar</option>
                                    </select>
                                </td>
                                <td class="text-center status-indicator">
                                    <span class="text-muted"><i class="fa-solid fa-minus"></i></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if ($rolesList === []) : ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted small py-2">No hay roles definidos</td>
                            </tr>
                        <?php endif; ?>

                        <!-- USUARIOS -->
                        <tr>
                            <td colspan="3" class="p-0">
                                <div class="user-group-header ms-3 me-3"><i class="fa-solid fa-user text-secondary me-2"></i>Usuarios (Excepciones específicas)</div>
                            </td>
                        </tr>
                        <?php foreach ($usersList as $user) : ?>
                            <?php
                            $userName = (string) ($user['label'] ?? '');
                            $initials = mb_substr($userName, 0, 2);
                            ?>
                            <tr class="permission-row">
                                <td class="ps-4 fw-medium d-flex align-items-center gap-2">
                                    <div class="bg-secondary text-white rounded-circle d-flex justify-content-center align-items-center"
                                        style="width: 24px; height: 24px; font-size: 10px;">
                                        <?= View::escape(strtoupper($initials)) ?>
                                    </div>
                                    <?= View::escape($userName) ?>
                                </td>
                                <td>
                                    <select name="perms[user][<?= (int) ($user['id'] ?? 0) ?>]" class="form-select form-select-sm border-0 bg-transparent shadow-none text-muted" onchange="updateRowState(this)">
                                        <option value="none" selected>Heredada (Acceder)</option>
                                        <option value="allow">Acceder</option>
                                        <option value="deny">Denegar</option>
                                    </select>
                                </td>
                                <td class="text-center status-indicator">
                                    <span class="text-muted"><i class="fa-solid fa-minus"></i></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if ($usersList === []) : ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted small py-2">No hay usuarios definidos</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-light d-flex justify-content-end gap-2 py-3">
            <button class="btn btn-outline-secondary" type="button">Descartar Cambios</button>
            <button class="btn btn-primary d-flex align-items-center gap-2" type="submit">
                <i class="fa-solid fa-save"></i> Guardar Permisos
            </button>
        </div>
    </div>
</div>
