<?php

use App\Core\View\View;
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?= url('/') ?>">MRP</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= url('/') ?>">Inicio</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= url('api/v1/health') ?>" target="_blank" rel="noopener">API Health</a></li>
            </ul>
            <span class="navbar-text text-white small">Entorno: <?= View::escape(config('app.env', 'production')) ?></span>
        </div>
    </div>
</nav>
