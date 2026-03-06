<?php

use App\Core\Auth\AuthManager;
use App\Core\View\View;

$currentPath = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
$currentQueryString = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
parse_str($currentQueryString ?? '', $queryParams);

$user = AuthManager::user();
$tenant = AuthManager::tenant();

$sections = [
    [
        'title' => 'Panel',
        'items' => array_filter([
            [
                'href' => url('dashboard'),
                'label' => 'Panel inicial',
                'icon' => 'fa-solid fa-gauge',
                'tables' => [],
            ],
            $user === null ? [
                'href' => url('login'),
                'label' => 'Login multiempresa',
                'icon' => 'fa-solid fa-right-to-bracket',
                'tables' => [],
            ] : null,
        ]),
    ],
    [
        'title' => 'Planeamiento MRP',
        'items' => [
            [
                'href' => url('planeamiento/sugerencias'),
                'label' => 'Sugerencias MRP',
                'icon' => 'fa-solid fa-list-check',
                'tables' => ['mrp_sugerencias', 'vista_mrp_resumen'],
            ],
            [
                'href' => url('planeamiento/ordenes'),
                'label' => 'Órdenes planificadas',
                'icon' => 'fa-solid fa-calendar-check',
                'tables' => ['ordenes_produccion', 'vista_ordenes_completas'],
            ],
        ],
    ],
    [
        'title' => 'Producción',
        'items' => [
            [
                'href' => url('produccion'),
                'label' => 'Dashboard de Operaciones',
                'icon' => 'fa-solid fa-gauge-high',
                'tables' => ['ordenes_produccion', 'centros_trabajo'],
            ],
            [
                'href' => url('produccion/centros-trabajo'),
                'label' => 'Centros de Trabajo',
                'icon' => 'fa-solid fa-industry',
                'tables' => ['centros_trabajo'],
            ],
            [
                'href' => url('produccion/rutas'),
                'label' => 'Rutas de Producción',
                'icon' => 'fa-solid fa-route',
                'tables' => ['rutas_produccion'],
            ],
            [
                'href' => url('produccion/ordenes'),
                'label' => 'Órdenes de Producción',
                'icon' => 'fa-solid fa-clipboard-list',
                'tables' => ['ordenes_produccion'],
            ],
            [
                'href' => url('produccion/planificacion'),
                'label' => 'Planificación de Recursos',
                'icon' => 'fa-solid fa-calendar-alt',
                'tables' => ['planificacion_recursos'],
            ],
            [
                'href' => url('produccion/planificacion/gantt'),
                'label' => 'Vista Gantt',
                'icon' => 'fa-solid fa-chart-gantt',
                'tables' => ['planificacion_recursos'],
            ],
        ],
    ],
    [
        'title' => 'Productos y BOM',
        'items' => [
            [
                'href' => url('productos/partes'),
                'label' => 'Listado de Partes',
                'icon' => 'fa-solid fa-puzzle-piece',
                'tables' => ['partes', 'tipos_partes'],
            ],
            [
                'href' => url('productos/partes/manager'),
                'label' => 'Gestor de partes',
                'icon' => 'fa-solid fa-wrench',
                'tables' => ['partes', 'tipos_partes'],
            ],
            [
                'href' => url('productos/bom'),
                'label' => 'BOM activas',
                'icon' => 'fa-solid fa-diagram-project',
                'tables' => ['bom_cabecera', 'bom_detalle', 'vista_boms_activas'],
            ],
            [
                'href' => url('productos/maestro'),
                'label' => 'Composición de variantes',
                'icon' => 'fa-solid fa-layer-group',
                'tables' => ['composicion_variantes'],
            ],
        ],
    ],
    [
        'title' => 'Inventario y stock',
        'items' => [
            [
                'href' => url('inventario/critico'),
                'label' => 'Stock crítico',
                'icon' => 'fa-solid fa-triangle-exclamation',
                'tables' => ['vista_stock_critico'],
            ],
        ],
    ],
    [
        'title' => 'Transacciones',
        'items' => [
            [
                'href' => url('transacciones/movimientos-partes'),
                'label' => 'Movimientos de Partes',
                'icon' => 'fa-solid fa-arrow-right-arrow-left',
                'tables' => ['movimientos_partes'],
            ],
            [
                'href' => url('compras'),
                'label' => 'Gestión de Compras',
                'icon' => 'fa-solid fa-shopping-cart',
                'tables' => ['compras'],
            ],
        ],
    ],
    [
        'title' => 'Reportes',
        'items' => [
            [
                'href' => url('reportes/destino-partes'),
                'label' => 'Destino de Partes',
                'icon' => 'fa-solid fa-sitemap',
                'tables' => ['bom_detalle', 'composicion_variantes'],
            ],
            [
                'href' => url('reportes/listado-ingenieria'),
                'label' => 'Listado de Ingeniería',
                'icon' => 'fa-solid fa-list-check',
                'tables' => ['bom_detalle', 'bom_cabecera', 'variantes'],
            ],
            [
                'href' => url('reportes/planificacion-produccion'),
                'label' => 'Planificación de Producción',
                'icon' => 'fa-solid fa-calendar-days',
                'tables' => ['bom_detalle', 'bom_cabecera', 'variantes'],
            ],
            [
                'href' => url('reportes/resumen-grupos'),
                'label' => 'Resumen por grupos',
                'icon' => 'fa-solid fa-layer-group',
                'tables' => ['grupos_partes', 'partes', 'variantes'],
            ],
        ],
    ],
    [
        'title' => 'Parámetros y catálogos',
        'items' => [
            [
                'href' => url('configuracion/general'),
                'label' => 'Configuración',
                'icon' => 'fa-solid fa-sliders',
                'tables' => ['configuracion'],
            ],
            [
                'href' => url('configuracion/unidades'),
                'label' => 'Unidades de medida',
                'icon' => 'fa-solid fa-ruler-combined',
                'tables' => ['unidades_medida'],
            ],
            [
                'href' => url('configuracion/tipos-partes'),
                'label' => 'Tipos de partes',
                'icon' => 'fa-solid fa-tags',
                'tables' => ['tipos_partes'],
            ],
            [
                'href' => url('configuracion/tipos-depositos'),
                'label' => 'Tipos de depósito',
                'icon' => 'fa-solid fa-warehouse',
                'tables' => ['tipos_depositos'],
            ],
            [
                'href' => url('configuracion/depositos-validaciones'),
                'label' => 'Validaciones de movimientos',
                'icon' => 'fa-solid fa-arrow-right-arrow-left',
                'tables' => ['tipos_depositos_movimientos'],
            ],
            [
                'href' => url('configuracion/grupos-partes'),
                'label' => 'Grupos de partes',
                'icon' => 'fa-solid fa-layer-group',
                'tables' => ['grupos_partes'],
            ],
        ],
    ],
];

?>
<aside class="app-sidebar">
    <div class="app-sidebar__inner">
        <?php foreach ($sections as $section) : ?>
            <div class="app-sidebar__section">
                <p class="app-sidebar__section-title"><?= View::escape($section['title']) ?></p>
                <ul class="app-sidebar__nav list-unstyled mb-0">
                    <?php foreach ($section['items'] as $item) :
                        // Normalizar paths para comparación (remover base URL)
                        $baseUrl = rtrim(config('app.url'), '/');
                        $normalizedCurrentPath = str_replace($baseUrl, '', $currentPath);
                        $normalizedItemHref = str_replace($baseUrl, '', $item['href']);

                        // Lógica especial: si estamos en productos/partes?tab=variantes
                        $isActive = false;

                        // Si estamos en partes, activar Listado de Partes incluso con tab=variantes
                        // Lógica normal: comparar paths
                        $isActive = $item['href'] !== '#' && str_starts_with($normalizedCurrentPath, $normalizedItemHref);
                        $tablesAttr = '';
                        if (!empty($item['tables'])) {
                            $tablesAttr = ' data-tables="' . View::escape(implode(',', $item['tables'])) . '"';
                        }
                    ?>
                        <li>
                            <a class="app-sidebar__link<?= $isActive ? ' is-active' : '' ?>" href="<?= View::escape($item['href']) ?>" <?= $tablesAttr ?>>
                                <i class="<?= View::escape($item['icon']) ?>"></i>
                                <span><?= View::escape($item['label']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
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
