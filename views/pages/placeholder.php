<?php

/**
 * @var string $title
 */
?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><?= $title ?? 'Página en construcción' ?></h5>
    </div>
    <div class="card-body">
        <p class="text-muted">Esta sección está en desarrollo.</p>
        <a href="<?= url('/') ?>" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Volver al inicio
        </a>
    </div>
</div>
