<?php

use App\Core\Auth\AuthManager;
use App\Core\View\View;

$appName = config('app.name', 'MRP');
$env = View::escape(config('app.env', 'production'));
$user = AuthManager::user();
$tenant = AuthManager::tenant();
?>
<header class="app-header bg-white border-bottom shadow-sm">
    <div class="container-fluid d-flex align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-primary d-lg-none" type="button" data-sidebar-toggle aria-label="Mostrar menú" aria-expanded="false">
                <i class="fa-solid fa-bars"></i>
            </button>
            <button
                class="btn btn-outline-secondary btn-sm d-none d-lg-inline-flex"
                type="button"
                data-sidebar-pin-toggle
                aria-label="Ocultar o fijar sidebar"
                aria-pressed="false"
                title="Ocultar/Fijar sidebar">
                <i class="fa-solid fa-thumbtack"></i>
            </button>
            <a class="navbar-brand fw-semibold text-decoration-none text-dark" href="<?= url('menu') ?>">
                <?= View::escape($appName) ?>
            </a>
            <span class="text-muted small d-none d-md-inline">MRP creado para emprendedores solos</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge text-bg-light text-uppercase small">Entorno: <?= $env ?></span>
            <?php if ($user !== null) : ?>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-user me-1"></i>
                        <?= View::escape($user['name'] ?? $user['email'] ?? 'Usuario') ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm p-3 user-menu-dropdown">
                        <p class="mb-1 fw-semibold"><?= View::escape($user['name'] ?? '') ?></p>
                        <p class="text-muted small mb-2"><?= View::escape($user['email'] ?? '') ?></p>
                        <?php if ($tenant !== null) : ?>
                            <p class="small mb-2"><i class="fa-solid fa-building me-1 text-primary"></i><?= View::escape($tenant['name'] ?? 'Tenant') ?></p>
                        <?php endif; ?>
                        <form method="post" action="<?= url('logout') ?>">
                            <button class="btn btn-danger btn-sm w-100" type="submit">
                                <i class="fa-solid fa-arrow-right-from-bracket me-1"></i>Cerrar sesión
                            </button>
                        </form>
                    </div>
                </div>
            <?php else : ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('login') ?>">
                    <i class="fa-solid fa-right-to-bracket me-1"></i> Login multiempresa
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>
