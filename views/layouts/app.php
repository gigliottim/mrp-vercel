<?php

use App\Core\Support\AssetHelper;
use App\Core\View\View;

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

<body class="app-body">
    <?php include base_path('views/partials/header.php'); ?>
    <div class="app-shell">
        <?php include base_path('views/partials/sidebar.php'); ?>
        <main class="app-shell__content">
            <div class="app-shell__content-inner">
                <?= $content ?? '' ?>
            </div>
        </main>
    </div>
    <?php include base_path('views/partials/footer.php'); ?>
    <script src="<?= AssetHelper::getBootstrap('js') ?>" defer></script>
    <script src="<?= AssetHelper::getAlpineJS() ?>" defer></script>
    <script src="<?= AssetHelper::js('main.js') ?>" type="module"></script>
    <script src="<?= AssetHelper::js('sidebar-scroll.js') ?>" defer></script>
</body>

</html>
