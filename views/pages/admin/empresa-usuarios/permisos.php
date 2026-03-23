<?php

declare(strict_types=1);

use App\Core\Support\AssetHelper;
use App\Core\View\View;

// ── Datos, opciones y árbol de menú ──────────────────────────────────────────
require __DIR__ . '/_permisos_data.php';
?>

<link rel="stylesheet" href="<?= AssetHelper::css('modules/empresa-usuarios/permisos-tree.css') ?>">

<?php
$activeTab    = 'permisos';
$sectionClass = 'flex-shrink-0';
$navClass     = 'flex-shrink-0';
include __DIR__ . '/_header.php';
unset($sectionClass, $navClass);
?>

<div id="acl-outer-row" class="row g-4 flex-grow-1">
    <div id="acl-outer-col" class="col-12 d-flex flex-column">
        <div id="acl-outer-card" class="card flex-grow-1 d-flex flex-column">
            <div class="card-body d-flex flex-column">
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
                    id="acl-form"
                    method="post"
                    action="<?= url('/roles-permisos/acl/bulk') ?>"
                    class="vstack gap-3 flex-grow-1">
                    <div id="acl-inner-row" class="row g-4 flex-grow-1 mb-4">
                        <?php include __DIR__ . '/_permisos_tree.php'; ?>

                        <?php include __DIR__ . '/_permisos_table.php'; ?>
                    </div>
                </form>

                <script>
                    const aclData = <?= json_encode($aclRows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
                    const nodeTreeData = <?= json_encode($nodesById, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;
                    <?php
                    // Mapa userId → roleId para cascada visual en la tabla de permisos
                    $userRoleMap = [];
                    foreach ($usersList as $u) {
                        if (isset($u['id'], $u['role_id']) && $u['role_id'] !== null) {
                            $userRoleMap[(int) $u['id']] = (int) $u['role_id'];
                        }
                    }
                    ?>
                    const userRoleMap = <?= json_encode($userRoleMap, JSON_THROW_ON_ERROR) ?>;
                    const aclBulkUrl  = <?= json_encode(url('/roles-permisos/acl/bulk'), JSON_THROW_ON_ERROR) ?>;
                </script>

                <script src="<?= AssetHelper::js('modules/empresa-usuarios/permisos-tree.js') ?>" defer></script>
