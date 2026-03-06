<?php

use App\Core\Auth\AuthManager;
use App\Core\View\View;

$currentPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
$baseUrl = rtrim((string) config('app.url'), '/');
$normalizedCurrentPath = str_replace($baseUrl, '', $currentPath);
$normalizedCurrentPath = '/' . ltrim((string) $normalizedCurrentPath, '/');

$tenant = AuthManager::tenant();
$sections = AuthManager::sidebarTree();

$normalizePath = static function (string $path): string {
    $trimmed = '/' . ltrim($path, '/');
    if ($trimmed !== '/' && str_ends_with($trimmed, '/')) {
        return rtrim($trimmed, '/');
    }

    return $trimmed;
};

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

$isActiveItem = static function (array $item) use ($normalizedCurrentPath, $baseUrl, $resolveHref): bool {
    $href = $resolveHref($item);
    if ($href === '#') {
        return false;
    }

    $normalizedItemPath = str_replace($baseUrl, '', $href);
    $normalizedItemPath = '/' . ltrim((string) $normalizedItemPath, '/');

    return str_starts_with($normalizedCurrentPath, $normalizedItemPath);
};

$resolveItemPath = static function (array $item) use ($resolveHref, $baseUrl, $normalizePath): string {
    $href = $resolveHref($item);
    if ($href === '#') {
        return '#';
    }

    $itemPath = str_replace($baseUrl, '', $href);
    return $normalizePath((string) $itemPath);
};

$normalizedCurrentPath = $normalizePath($normalizedCurrentPath);
$selectedItemPath = null;
$selectedScore = -1;

$scanBestItemMatch = null;
$scanBestItemMatch = static function (array $items) use (&$scanBestItemMatch, $resolveItemPath, $normalizedCurrentPath, &$selectedItemPath, &$selectedScore): void {
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $itemPath = $resolveItemPath($item);
        if ($itemPath !== '#') {
            $score = -1;

            if ($normalizedCurrentPath === $itemPath) {
                $score = 10000 + strlen($itemPath);
            } elseif ($itemPath !== '/' && str_starts_with($normalizedCurrentPath, $itemPath . '/')) {
                $score = strlen($itemPath);
            }

            if ($score > $selectedScore) {
                $selectedScore = $score;
                $selectedItemPath = $itemPath;
            }
        }

        $children = is_array($item['children'] ?? null) ? $item['children'] : [];
        if ($children !== []) {
            $scanBestItemMatch($children);
        }
    }
};

foreach ($sections as $section) {
    $items = is_array($section['items'] ?? null) ? $section['items'] : [];
    $scanBestItemMatch($items);
}

$hasActiveChild = null;
$hasActiveChild = static function (array $item) use (&$hasActiveChild, $resolveItemPath, $selectedItemPath): bool {
    foreach (($item['children'] ?? []) as $child) {
        if (!is_array($child)) {
            continue;
        }

        if ($resolveItemPath($child) === $selectedItemPath || $hasActiveChild($child)) {
            return true;
        }
    }

    return false;
};

$renderSidebarItems = null;
$renderSidebarItems = static function (array $items) use (&$renderSidebarItems, $resolveHref, $resolveItemPath, $selectedItemPath, $hasActiveChild): void {
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $href = $resolveHref($item);
        $isActive = $resolveItemPath($item) === $selectedItemPath || $hasActiveChild($item);
        $children = is_array($item['children'] ?? null) ? $item['children'] : [];
        $icon = (string) ($item['icon'] ?? 'fa-solid fa-circle');
        $label = (string) ($item['label'] ?? 'Sin titulo');
?>
        <li>
            <a class="app-sidebar__link<?= $isActive ? ' is-active' : '' ?>" href="<?= View::escape($href) ?>">
                <i class="<?= View::escape($icon) ?>"></i>
                <span><?= View::escape($label) ?></span>
            </a>
            <?php if ($children !== []) : ?>
                <ul class="app-sidebar__nav list-unstyled mb-0 ps-3">
                    <?php $renderSidebarItems($children); ?>
                </ul>
            <?php endif; ?>
        </li>
<?php
    }
};

?>
<aside class="app-sidebar">
    <div class="app-sidebar__inner">
        <?php foreach ($sections as $section) : ?>
            <div class="app-sidebar__section">
                <p class="app-sidebar__section-title"><?= View::escape((string) ($section['section_label'] ?? 'Menu')) ?></p>
                <ul class="app-sidebar__nav list-unstyled mb-0">
                    <?php
                    $items = is_array($section['items'] ?? null) ? $section['items'] : [];
                    $renderSidebarItems($items);
                    ?>
                </ul>
            </div>
        <?php endforeach; ?>
        <div class="app-sidebar__section mt-4">
            <p class="app-sidebar__section-title text-info">
                <i class="fa-solid fa-building-user me-1"></i> Multiempresa
            </p>
            <div class="app-sidebar__card bg-gradient-info-subtle">
                <?php if ($tenant !== null) : ?>
                    <div class="d-flex align-items-start gap-2 mb-3">
                        <div class="bg-info bg-opacity-10 rounded-2 p-2">
                            <i class="fa-solid fa-building text-info fs-4"></i>
                        </div>
                        <div class="flex-grow-1">
                            <p class="mb-0 fw-bold text-dark"><?= View::escape($tenant['name'] ?? 'Tenant') ?></p>
                            <p class="text-muted small mb-0">
                                <i class="fa-solid fa-tag me-1"></i><?= View::escape($tenant['slug'] ?? 'n/d') ?>
                            </p>
                        </div>
                    </div>
                    <div class="border-top border-info border-opacity-25 pt-3">
                        <ul class="list-unstyled small mb-0 text-secondary">
                            <li class="mb-2">
                                <i class="fa-solid fa-database text-info me-2"></i>
                                <span class="fw-semibold text-dark"><?= View::escape($tenant['database']['name'] ?? 'n/d') ?></span>
                            </li>
                            <li class="mb-2">
                                <i class="fa-solid fa-server text-success me-2"></i>
                                <span><?= View::escape($tenant['database']['host'] ?? 'n/d') ?></span>
                            </li>
                            <li>
                                <i class="fa-solid fa-clock text-warning me-2"></i>
                                <span><?= date('d/m H:i') ?></span>
                            </li>
                        </ul>
                    </div>
                <?php else : ?>
                    <div class="text-center py-2">
                        <i class="fa-solid fa-circle-exclamation text-warning fs-3 mb-2"></i>
                        <p class="mb-2 fw-semibold text-dark">Sin sesión activa</p>
                        <p class="text-muted small mb-0">Inicia sesión para seleccionar un tenant.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</aside>
