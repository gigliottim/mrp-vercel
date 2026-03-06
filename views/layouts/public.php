<?php

use App\Core\Auth\AuthManager;
use App\Core\Support\AssetHelper;
use App\Core\View\View;

$isAuthenticated = AuthManager::check();
$appVersion = (string) config('app.version', '0.0.0');
$appBuild = (int) config('app.build', 0);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= View::escape($title ?? config('app.name', 'MRP')) ?></title>
    <link rel="icon" href="<?= AssetHelper::image('favicon.svg') ?>" type="image/svg+xml">
    <link rel="preload" as="style" href="<?= AssetHelper::getBootstrap('css') ?>">
    <link rel="stylesheet" href="<?= AssetHelper::getBootstrap('css') ?>">
    <link rel="stylesheet" href="<?= AssetHelper::getFontAwesome() ?>">
    <link rel="stylesheet" href="<?= AssetHelper::css('global/main.css') ?>">
    <link rel="stylesheet" href="<?= AssetHelper::css('components/cards.css') ?>">
    <link rel="stylesheet" href="<?= AssetHelper::css('modules/public-site.css') ?>">
</head>

<body class="public-body">
    <header class="public-header border-bottom">
        <div class="container-fluid py-3 d-flex align-items-center justify-content-between gap-3">
            <a class="public-brand text-decoration-none" href="<?= url('/') ?>">
                <i class="fa-solid fa-industry me-2"></i><?= View::escape(config('app.name', 'MRP')) ?>
            </a>
            <nav class="d-flex align-items-center gap-2">
                <?php if ($isAuthenticated) : ?>
                    <a class="btn btn-outline-secondary" href="<?= url('dashboard') ?>">Dashboard</a>
                    <form method="post" action="<?= url('logout') ?>" class="d-inline">
                        <button class="btn btn-dark" type="submit">Salir</button>
                    </form>
                <?php else : ?>
                    <a class="btn btn-outline-secondary" href="<?= url('login') ?>">Ingresar</a>
                    <a class="btn btn-primary" href="<?= url('register') ?>">Registrarse</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="public-main">
        <div class="container-fluid py-4">
            <?= $content ?? '' ?>
        </div>
    </main>

    <footer class="public-footer border-top">
        <div class="container-fluid py-3 d-flex flex-column flex-md-row justify-content-between gap-2">
            <span class="small text-muted">&copy; <?= date('Y') ?> <?= View::escape(config('app.name', 'MRP')) ?></span>
            <span class="small text-muted">MRP para operaciones industriales y pymes en crecimiento · v<?= View::escape($appVersion) ?> build <?= View::escape((string) $appBuild) ?></span>
        </div>
    </footer>

    <script src="<?= AssetHelper::getBootstrap('js') ?>" defer></script>
    <script src="<?= AssetHelper::getAlpineJS() ?>" defer></script>
    <script src="<?= AssetHelper::js('main.js') ?>" type="module"></script>
</body>

</html>
