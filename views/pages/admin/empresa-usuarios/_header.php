<?php

declare(strict_types=1);

/**
 * Header compartido: sección Empresa y Usuarios
 *
 * Variables esperadas (definidas en la página que hace include):
 *   $activeTab    (string) — 'empresa' | 'usuarios' | 'roles' | 'permisos'
 *   $sectionClass (string) — clases CSS extra para <section> (opcional)
 *   $navClass     (string) — clases CSS extra para <nav>     (opcional)
 */

$activeTab    = $activeTab    ?? 'empresa';
$sectionClass = $sectionClass ?? '';
$navClass     = $navClass     ?? '';

$tabs = [
    'empresa'  => ['label' => 'Empresa',  'url' => url('/empresa-usuarios/empresa')],
    'usuarios' => ['label' => 'Usuarios', 'url' => url('/empresa-usuarios/usuarios')],
    'roles'    => ['label' => 'Roles',    'url' => url('/empresa-usuarios/roles')],
    'permisos' => ['label' => 'Permisos', 'url' => url('/empresa-usuarios/permisos')],
];

$pageTitle = $tabs[$activeTab]['label'] ?? ucfirst($activeTab);
?>

<section class="mb-4<?= $sectionClass ? ' ' . $sectionClass : '' ?>">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Empresa y Usuarios</p>
            <h1 class="h3 mb-0"><?= $pageTitle ?></h1>
        </div>
    </div>
</section>

<nav class="nav nav-pills mb-4 flex-wrap gap-2<?= $navClass ? ' ' . $navClass : '' ?>">
    <?php foreach ($tabs as $key => $tab) : ?>
        <a
            class="nav-link<?= $key === $activeTab ? ' active' : '' ?>"
            href="<?= $tab['url'] ?>">
            <?= $tab['label'] ?>
        </a>
    <?php endforeach; ?>
</nav>
