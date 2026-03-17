<?php

use App\Core\View\View;

$appVersion = (string) config('app.version', '0.0.0');
$appBuild = (int) config('app.build', 0);
?>
<footer class="app-footer border-top bg-white py-2">
    <div class="container-fluid d-flex flex-column flex-md-row justify-content-between align-items-start gap-2">
        <span class="text-muted small">&copy; <?= date('Y') ?> <?= View::escape(config('app.name', 'MRP')) ?> · Pensado para equipos de 1</span>
        <span class="text-muted small">Stack PHP 8 · Bootstrap 5 · Multiempresa · v<?= View::escape($appVersion) ?> build <?= View::escape((string) $appBuild) ?></span>
    </div>
</footer>
