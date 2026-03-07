<?php

use App\Core\View\View;
use App\Core\Support\AssetHelper;

// ========================================
// INICIALIZACIÓN DE VARIABLES (desde Controller)
// ========================================
$parts = $parts ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 15];
$variants = $variants ?? [];
$partOptions = $partOptions ?? [];
$tipos = $tipos ?? [];
$grupos = $grupos ?? [];
$unidadesLongitud = $unidadesLongitud ?? [];
$unidadesSuperficie = $unidadesSuperficie ?? [];
$unidadesVolumen = $unidadesVolumen ?? [];
$unidadesMasa = $unidadesMasa ?? [];
$errors = $errors ?? [];
$variantErrors = $variantErrors ?? [];
$oldPart = $oldPart ?? [];
$oldVariant = $oldVariant ?? [];
$tab = $tab ?? 'partes';
$editingPartId = $editingPartId ?? null;
$editingVariantId = $editingVariantId ?? null;
$filteredParteId = $filteredParteId ?? null;
$search = $search ?? '';
$preselectedPartId = isset($_GET['id_parte']) ? (int) $_GET['id_parte'] : ($filteredParteId ?? 0);

// ========================================
// FUNCIONES DE DATOS
// ========================================
$partOldValue = static function (string $field, $default = '') use ($oldPart) {
    return $oldPart[$field] ?? $default;
};

$variantOldValue = static function (string $field, $default = '') use ($oldVariant, $preselectedPartId) {
    if (isset($oldVariant[$field])) {
        return $oldVariant[$field];
    }
    if ($field === 'id_parte' && $preselectedPartId > 0) {
        return $preselectedPartId;
    }
    return $default;
};

// ========================================
// PREPARACIÓN DE DATOS
// ========================================
$partItems = $parts['items'] ?? [];
$totalParts = $parts['total'] ?? count($partItems);

$partLookup = [];
foreach ($partOptions as $option) {
    $partLookup[(int) $option['id']] = $option;
}

$totalVariants = 0;
foreach ($variants as $chunk) {
    $totalVariants += count($chunk);
}

$masaLookup = [];
foreach ($unidadesMasa as $unidad) {
    $masaLookup[(int) $unidad['id']] = $unidad;
}

$unitSymbols = [];
foreach ([$unidadesLongitud, $unidadesSuperficie, $unidadesVolumen] as $group) {
    foreach ($group as $unidad) {
        $unitSymbols[(int) $unidad['id']] = $unidad['simbolo'];
    }
}

$dimensionFields = [
    'largo_alto' => [
        'label' => 'Largo / Alto',
        'unit' => 'id_um_largo_alto',
        'units' => $unidadesLongitud,
    ],
    'ancho' => [
        'label' => 'Ancho',
        'unit' => 'id_um_ancho',
        'units' => $unidadesLongitud,
    ],
    'espesor_profundidad' => [
        'label' => 'Espesor / Profundidad',
        'unit' => 'id_um_espesor',
        'units' => $unidadesLongitud,
    ],
    'superficie' => [
        'label' => 'Superficie',
        'unit' => 'id_um_superficie',
        'units' => $unidadesSuperficie,
    ],
    'volumen' => [
        'label' => 'Volumen',
        'unit' => 'id_um_volumen',
        'units' => $unidadesVolumen,
    ],
];

$variantStates = [
    'activa' => 'Activa',
    'desarrollo' => 'En desarrollo',
    'obsoleta' => 'Obsoleta',
    'descontinuada' => 'Descontinuada',
];

$clearUrlPartes = url('productos/partes?tab=partes');
$clearUrlVariantes = url('productos/partes?tab=variantes' . ($filteredParteId ? '&id_parte=' . $filteredParteId : ''));

?>
<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Productos</p>
            <h1 class="h3 mb-0">Partes y variantes</h1>
        </div>
        <ul class="nav nav-pills">
            <li class="nav-item">
                <a class="nav-link<?= $tab === 'partes' ? ' active' : '' ?>" href="<?= url('productos/partes?tab=partes') ?>">Partes</a>
            </li>
            <li class="nav-item">
                <a class="nav-link<?= $tab === 'variantes' ? ' active' : '' ?>" href="<?= url('productos/partes?tab=variantes') ?>">Variantes</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?= url('productos/partes/importar') ?>">Importar</a>
            </li>
        </ul>
    </div>
</section>

<?php if ($tab === 'partes') : ?>
    <?php include __DIR__ . '/_partes_tab.php'; ?>
<?php endif; ?>

<?php if ($tab === 'variantes') : ?>
    <?php include __DIR__ . '/_variantes_tab.php'; ?>
<?php endif; ?>

<script src="<?= AssetHelper::js('modules/SearchClient.js') ?>" defer></script>
<script src="<?= AssetHelper::js('partes-search.js') ?>" defer></script>
