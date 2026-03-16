<?php

use App\Core\View\View;

/** @var array<int, array<string, mixed>> $sections */

$sectionMeta = [
    'taller'               => ['color' => 'warning',   'icon' => 'fa-solid fa-industry'],
    'catalogo_productos'   => ['color' => 'success',   'icon' => 'fa-solid fa-layer-group'],
    'planificacion_compras' => ['color' => 'info',      'icon' => 'fa-solid fa-calendar-days'],
    'reportes'             => ['color' => 'secondary', 'icon' => 'fa-solid fa-chart-line'],
    'administracion'       => ['color' => 'dark',      'icon' => 'fa-solid fa-sliders'],
];

$resolveHref = static function (array $item): string {
    $route = $item['route'] ?? null;
    if (!is_string($route) || trim($route) === '') {
        return '#';
    }
    if (str_starts_with($route, 'http://') || str_starts_with($route, 'https://')) {
        return $route;
    }
    return url(ltrim($route, '/'));
};
?>

<section class="mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <h1 class="h3 mb-1">
                <i class="fa-solid fa-grip me-2 text-primary"></i>Mapa del sistema
            </h1>
            <p class="text-muted mb-0">Acceso rápido a todos los módulos disponibles.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('dashboard') ?>">
            <i class="fa-solid fa-arrow-left me-1"></i> Panel inicial
        </a>
    </div>
</section>

<?php foreach ($sections as $section) :
    $sectionKey   = (string) ($section['section_key'] ?? 'otros');
    $sectionLabel = (string) ($section['section_label'] ?? 'Módulos');
    $items        = is_array($section['items'] ?? null) ? $section['items'] : [];
    $meta         = $sectionMeta[$sectionKey] ?? ['color' => 'primary', 'icon' => 'fa-solid fa-circle'];
    $color        = (string) $meta['color'];
    $sectionIcon  = (string) $meta['icon'];

    if ($items === []) {
        continue;
    }
?>
    <section class="mb-5">
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="menu-section__icon text-<?= View::escape($color) ?>">
                <i class="<?= View::escape($sectionIcon) ?>"></i>
            </span>
            <h2 class="h5 fw-semibold mb-0 text-<?= View::escape($color) ?>"><?= View::escape($sectionLabel) ?></h2>
            <span class="badge text-bg-light text-muted ms-1"><?= count($items) ?></span>
        </div>
        <div class="row g-3">
            <?php foreach ($items as $item) :
                $href  = $resolveHref($item);
                $icon  = (string) ($item['icon'] ?? 'fa-solid fa-circle');
                $label = (string) ($item['label'] ?? 'Sin título');
                $isDisabled = $href === '#';
            ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <a
                        class="menu-card<?= $isDisabled ? ' menu-card--disabled' : '' ?> text-decoration-none d-flex flex-column align-items-center text-center p-3 rounded-3 border bg-white h-100"
                        href="<?= View::escape($href) ?>"
                        aria-label="<?= View::escape($label) ?>"
                        <?= $isDisabled ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                        <div class="menu-card__icon mb-2 text-<?= View::escape($color) ?> bg-<?= View::escape($color) ?> bg-opacity-10 rounded-3">
                            <i class="<?= View::escape($icon) ?>"></i>
                        </div>
                        <div class="menu-card__label fw-medium small text-dark"><?= View::escape($label) ?></div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

<?php if (empty($sections)) : ?>
    <div class="text-center py-5 text-muted">
        <i class="fa-solid fa-circle-exclamation fs-2 mb-3"></i>
        <p>No hay módulos disponibles para tu rol.</p>
    </div>
<?php endif; ?>
