<?php

use App\Core\Support\AssetHelper;
use App\Core\View\View;

$appFormattingSettings = app_general_settings();

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
    <script>
        (function applyDesktopSidebarPreferenceEarly() {
            try {
                const storageKey = 'mrp.sidebar.desktop.visible';
                const isDesktop = window.matchMedia('(min-width: 992px)').matches;

                if (!isDesktop) {
                    return;
                }

                if (window.localStorage.getItem(storageKey) === '0') {
                    document.documentElement.classList.add('sidebar-desktop-hidden');
                }
            } catch (error) {
                // Si localStorage no esta disponible, se mantiene el estado por defecto.
            }
        })();
    </script>
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
    <script>
        window.appFormattingSettings = <?= json_encode($appFormattingSettings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        window.appFormatNumber = function(value, decimals) {
            const numericValue = Number.parseFloat(value);
            if (!Number.isFinite(numericValue)) {
                return String(value ?? '');
            }

            const settings = {
                decimal_places: 4,
                rounding_mode: 'half_up',
                thousand_separator: '.',
                decimal_separator: ',',
                ...(window.appFormattingSettings || {})
            };

            const usedDecimals = Number.isInteger(decimals) ?
                Math.max(0, Math.min(10, decimals)) :
                Math.max(1, Math.min(10, Number.parseInt(settings.decimal_places, 10) || 4));

            const factor = 10 ** usedDecimals;
            const mode = String(settings.rounding_mode || 'half_up');

            let rounded;
            if (mode === 'truncate') {
                rounded = numericValue >= 0 ?
                    Math.floor(numericValue * factor) / factor :
                    Math.ceil(numericValue * factor) / factor;
            } else {
                rounded = Math.round(numericValue * factor) / factor;
            }

            let fixed = rounded.toFixed(usedDecimals);
            fixed = fixed.replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');

            const [integerPartRaw, decimalPartRaw = ''] = fixed.split('.');
            const sign = integerPartRaw.startsWith('-') ? '-' : '';
            const integerDigits = sign ? integerPartRaw.slice(1) : integerPartRaw;
            const thousandSeparator = String(settings.thousand_separator ?? '.');
            const decimalSeparator = String(settings.decimal_separator ?? ',');
            const groupedInteger = integerDigits.replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);

            if (decimalPartRaw === '') {
                return sign + groupedInteger;
            }

            return sign + groupedInteger + decimalSeparator + decimalPartRaw;
        };
    </script>
    <script src="<?= AssetHelper::js('main.js') ?>" type="module"></script>
    <script src="<?= AssetHelper::js('sidebar-scroll.js') ?>" defer></script>
</body>

</html>
