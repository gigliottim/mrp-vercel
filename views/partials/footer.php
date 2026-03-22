<?php

use App\Core\View\View;

$appVersion = (string) config('app.version', '0.0.0');
$appBuild = (int) config('app.build', 0);
?>
<footer class="app-footer border-top bg-white py-2">
    <div class="container-fluid d-flex flex-column align-items-center justify-content-center text-center gap-1">
        <span class="text-muted small">&copy; <?= date('Y') ?> <?= View::escape(config('app.name', 'MRP')) ?> · Pensado para equipos de 1</span>
        <span class="text-muted small">
            Realizado por <a href="https://unik.ar" target="_blank" rel="noopener noreferrer" class="text-decoration-none fw-bold text-muted">UniK</a>
            · v<?= View::escape($appVersion) ?> build <?= View::escape((string) $appBuild) ?>
        </span>
    </div>
</footer>
