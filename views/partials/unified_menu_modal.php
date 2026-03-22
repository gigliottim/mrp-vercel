<?php

use App\Core\Auth\AuthManager;
use App\Core\View\View;

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

// Map specific section keys to colors
$sectionColors = [
    'taller' => ['text' => 'text-warning', 'bg' => 'bg-warning', 'icon' => 'fa-industry'],
    'catalogo_productos' => ['text' => 'text-success', 'bg' => 'bg-success', 'icon' => 'fa-layer-group'],
    'planificacion_compras' => ['text' => 'text-info', 'bg' => 'bg-info', 'icon' => 'fa-calendar-days'],
    'reportes' => ['text' => 'text-secondary', 'bg' => 'bg-secondary', 'icon' => 'fa-chart-line'],
    'administracion' => ['text' => 'text-dark', 'bg' => 'bg-dark', 'icon' => 'fa-sliders'],
    'empresa_usuarios' => ['text' => 'text-dark', 'bg' => 'bg-dark', 'icon' => 'fa-building-user'],
];

// Flat items to display inside the grid
$flattenItems = static function (array $items) use (&$flattenItems): array {
    $flat = [];
    foreach ($items as $item) {
        $flat[] = $item;
        $children = is_array($item['children'] ?? null) ? $item['children'] : [];
        if ($children !== []) {
            $flat = array_merge($flat, $flattenItems($children));
        }
    }
    return $flat;
};

?>
<style>
    /* CSS del Menú Unificado */
    .unified-menu-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(4px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.2s ease-out;
    }

    .unified-menu-overlay.active {
        display: flex;
        opacity: 1;
    }

    .unified-menu-container {
        background: white;
        width: 90%;
        max-width: 1100px;
        height: 85vh;
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        transform: translateY(20px) scale(0.98);
        transition: transform 0.2s ease-out;
    }

    .unified-menu-overlay.active .unified-menu-container {
        transform: translateY(0) scale(1);
    }

    .unified-menu-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 16px;
        background: #ffffff;
    }

    .unified-search-wrapper {
        flex: 1;
        position: relative;
    }

    .unified-search-wrapper i {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
        font-size: 1.1rem;
    }

    .unified-search-input {
        width: 100%;
        padding: 12px 16px 12px 48px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 1rem;
        color: #1e293b;
        background: #f8fafc;
        transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }

    .unified-search-input:focus {
        outline: none;
        border-color: #3b82f6;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }

    .unified-menu-close-btn {
        background: #f1f5f9;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        font-size: 1.25rem;
        cursor: pointer;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s, color 0.2s;
    }

    .unified-menu-close-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    .unified-menu-body {
        display: flex;
        flex: 1;
        overflow: hidden;
    }

    .unified-menu-sidebar {
        width: 260px;
        background: #f8fafc;
        border-right: 1px solid #e2e8f0;
        padding: 20px 12px;
        overflow-y: auto;
    }

    .unified-nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        margin-bottom: 4px;
        font-weight: 500;
        color: #475569;
        cursor: pointer;
        border-radius: 8px;
        transition: background 0.15s, color 0.15s;
        user-select: none;
    }

    .unified-nav-item i {
        width: 20px;
        text-align: center;
        font-size: 1.1rem;
        color: #94a3b8;
    }

    .unified-nav-item:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    .unified-nav-item:hover i {
        color: #64748b;
    }

    .unified-nav-item.active {
        background: #eff6ff;
        color: #2563eb;
        font-weight: 600;
    }

    .unified-nav-item.active i {
        color: #3b82f6;
    }

    .unified-menu-content {
        flex: 1;
        padding: 24px 32px;
        overflow-y: auto;
        background: #ffffff;
        scroll-behavior: smooth;
    }

    .unified-category-block {
        margin-bottom: 40px;
    }

    .unified-category-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .unified-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
    }

    .unified-menu-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 16px;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        display: flex;
        align-items: flex-start;
        gap: 16px;
        text-decoration: none;
        color: inherit;
    }

    .unified-menu-card:hover {
        text-decoration: none;
        color: inherit;
    }

    .unified-menu-card:hover {
        border-color: #cbd5e1;
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    .unified-menu-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .unified-icon-warning {
        background: #fffbeb;
        color: #d97706;
    }

    .unified-icon-success {
        background: #f0fdf4;
        color: #16a34a;
    }

    .unified-icon-info {
        background: #eff6ff;
        color: #2563eb;
    }

    .unified-icon-secondary {
        background: #f8fafc;
        color: #475569;
    }

    .unified-icon-dark {
        background: #f1f5f9;
        color: #0f172a;
    }

    .unified-menu-text {
        flex: 1;
        overflow: hidden;
    }

    .unified-menu-card-title {
        font-weight: 600;
        color: #1e293b;
        margin: 0 0 4px 0;
        font-size: 0.95rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .unified-menu-card-desc {
        font-size: 0.8rem;
        color: #64748b;
        margin: 0;
        line-height: 1.4;
    }

    .unified-hidden {
        display: none !important;
    }

    .unified-no-results {
        text-align: center;
        padding: 60px 20px;
        color: #64748b;
    }

    .unified-no-results i {
        font-size: 3rem;
        color: #cbd5e1;
        margin-bottom: 16px;
    }

    @media (max-width: 768px) {
        .unified-menu-body {
            flex-direction: column;
        }

        .unified-menu-sidebar {
            width: 100%;
            border-right: none;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            overflow-x: auto;
            padding: 12px;
        }

        .unified-nav-item {
            margin-bottom: 0;
            margin-right: 8px;
            white-space: nowrap;
        }

        .unified-menu-container {
            width: 100%;
            height: 100%;
            max-height: 100vh;
            border-radius: 0;
        }
    }
</style>

<div class="unified-menu-overlay" id="unifiedMenuModal" onclick="closeUnifiedMenuOnOutsideClick(event)">
    <div class="unified-menu-container">

        <div class="unified-menu-header">
            <div class="unified-search-wrapper">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="unifiedSearchInput" class="unified-search-input" placeholder="Buscar módulos, reportes o configuraciones... (Ej: Producción, Stock, BOM)" oninput="filterUnifiedMenu()">
            </div>
            <button class="unified-menu-close-btn" onclick="closeUnifiedMenu()" title="Cerrar (Esc)"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <div class="unified-menu-body">
            <div class="unified-menu-sidebar">
                <div class="unified-nav-item active" onclick="scrollToUnifiedCategory('todos')">
                    <i class="fa-solid fa-layer-group"></i> Todos los módulos
                </div>
                <?php foreach ($sections as $section) :
                    $secKey = $section['section_key'];
                    $colorData = $sectionColors[$secKey] ?? $sectionColors['administracion'];
                    $iconClass = $colorData['icon'];
                    $textClass = $colorData['text'];
                ?>
                    <div class="unified-nav-item" onclick="scrollToUnifiedCategory('<?= View::escape($secKey) ?>')">
                        <i class="fa-solid <?= View::escape($iconClass) ?> <?= View::escape($textClass) ?>"></i>
                        <?= View::escape($section['section_label']) ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="unified-menu-content" id="unifiedMenuContent">

                <div id="unifiedNoResults" class="unified-no-results unified-hidden">
                    <i class="fa-regular fa-face-frown-open d-block"></i>
                    <h4 class="mt-3">No encontramos coincidencias</h4>
                    <p>Intenta buscar con otros términos.</p>
                </div>

                <?php foreach ($sections as $section) :
                    $secKey = $section['section_key'];
                    $colorData = $sectionColors[$secKey] ?? [
                        'text' => 'text-dark',
                        'bg' => 'bg-dark',
                        'icon' => 'fa-bars'
                    ];

                    // The icon mapping class for unified-menu-icon
                    $iconBgClass = match ($colorData['text']) {
                        'text-warning' => 'unified-icon-warning',
                        'text-success' => 'unified-icon-success',
                        'text-info' => 'unified-icon-info',
                        'text-secondary' => 'unified-icon-secondary',
                        default => 'unified-icon-dark',
                    };

                    $itemsFlat = $flattenItems($section['items'] ?? []);
                    if (empty($itemsFlat)) continue;
                ?>
                    <div class="unified-category-block" id="cat-<?= View::escape($secKey) ?>">
                        <h3 class="unified-category-title">
                            <i class="fa-solid <?= View::escape($colorData['icon']) ?> <?= View::escape($colorData['text']) ?>"></i>
                            <?= View::escape($section['section_label']) ?>
                        </h3>
                        <div class="unified-grid">
                            <?php foreach ($itemsFlat as $item) :
                                $href = $resolveHref($item);
                                $searchTitle = strtolower($item['label'] . ' ' . $item['code'] ?? '');
                                $isWip = str_starts_with((string) ($item['code'] ?? ''), 'produccion.');
                            ?>
                                <a href="<?= View::escape($href) ?>" class="unified-menu-card" data-title="<?= View::escape($searchTitle) ?>">
                                    <div class="unified-menu-icon <?= View::escape($iconBgClass) ?>">
                                        <i class="<?= View::escape($item['icon'] ?? 'fa-solid fa-circle') ?>"></i>
                                    </div>
                                    <div class="unified-menu-text">
                                        <h4 class="unified-menu-card-title">
                                            <?= View::escape($item['label'] ?? 'Sin titulo') ?>
                                            <?php if ($isWip): ?>
                                                <span class="badge text-bg-warning ms-1" style="font-size: 0.6rem;">WIP</span>
                                            <?php endif; ?>
                                        </h4>
                                        <p class="unified-menu-card-desc">Ir al módulo de <?= View::escape(strtolower($item['label'])) ?>.</p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>
</div>

<script>
    function openUnifiedMenu() {
        const modal = document.getElementById("unifiedMenuModal");
        const searchInput = document.getElementById("unifiedSearchInput");
        if (!modal) return;

        modal.classList.add("active");
        if (searchInput) {
            searchInput.value = "";
            filterUnifiedMenu();
            setTimeout(() => searchInput.focus(), 150);
        }
    }

    function closeUnifiedMenu() {
        const modal = document.getElementById("unifiedMenuModal");
        if (modal) modal.classList.remove("active");
    }

    function closeUnifiedMenuOnOutsideClick(event) {
        const modal = document.getElementById("unifiedMenuModal");
        if (event.target === modal) closeUnifiedMenu();
    }

    document.addEventListener("keydown", (e) => {
        const modal = document.getElementById("unifiedMenuModal");
        if (!modal) return;

        if (e.key === "Escape" && modal.classList.contains("active")) {
            closeUnifiedMenu();
        }

        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") {
            e.preventDefault();
            openUnifiedMenu();
        }
    });

    function filterUnifiedMenu() {
        const searchInput = document.getElementById("unifiedSearchInput");
        if (!searchInput) return;

        const query = searchInput.value.toLowerCase().trim();
        const cards = document.querySelectorAll(".unified-menu-card");
        const sections = document.querySelectorAll(".unified-category-block");
        let hasResults = false;

        if (query !== "") {
            document.querySelectorAll(".unified-nav-item").forEach(el => el.classList.remove("active"));
        }

        cards.forEach(card => {
            const searchTerms = card.getAttribute("data-title") || "";
            const title = card.querySelector(".unified-menu-card-title").textContent.toLowerCase();

            if (searchTerms.includes(query) || title.includes(query)) {
                card.classList.remove("unified-hidden");
            } else {
                card.classList.add("unified-hidden");
            }
        });

        sections.forEach(sec => {
            const visibleCards = sec.querySelectorAll(".unified-menu-card:not(.unified-hidden)");
            if (visibleCards.length === 0) {
                sec.classList.add("unified-hidden");
            } else {
                sec.classList.remove("unified-hidden");
                hasResults = true;
            }
        });

        const noResults = document.getElementById("unifiedNoResults");
        if (noResults) {
            if (!hasResults && query !== "") {
                noResults.classList.remove("unified-hidden");
            } else {
                noResults.classList.add("unified-hidden");
            }
        }
    }

    function scrollToUnifiedCategory(catId) {
        const searchInput = document.getElementById("unifiedSearchInput");
        const menuContent = document.getElementById("unifiedMenuContent");
        if (!menuContent) return;

        if (searchInput && searchInput.value !== '') {
            searchInput.value = "";
            filterUnifiedMenu();
        }

        document.querySelectorAll(".unified-nav-item").forEach(el => el.classList.remove("active"));

        if (window.event && window.event.currentTarget) {
            window.event.currentTarget.classList.add("active");
        }

        if (catId === "todos") {
            menuContent.scrollTo({
                top: 0,
                behavior: "smooth"
            });
            return;
        }

        const targetSection = document.getElementById("cat-" + catId);
        if (targetSection) {
            const topPos = targetSection.offsetTop - menuContent.offsetTop - 10;
            menuContent.scrollTo({
                top: topPos,
                behavior: "smooth"
            });
        }
    }
</script>
