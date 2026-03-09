<?php

use App\Core\View\View;
use App\Core\Support\AssetHelper;

// Variables del controlador
$parte = $parte ?? null;
$parteVariants = $parteVariants ?? [];

// Modo de operación: 'create', 'view', 'edit'
$mode = $mode ?? ($parte ? 'view' : 'create');
$editingVariantId = $editingVariantId ?? null;
$selectedVariante = $editingVariant ?? null;

// Listas de datos
$partesList = $partesList ?? [];
$tipos = $tipos ?? [];
$grupos = $grupos ?? [];
$unidadesLongitud = $unidadesLongitud ?? [];
$unidadesSuperficie = $unidadesSuperficie ?? [];
$unidadesVolumen = $unidadesVolumen ?? [];
$unidadesMasa = $unidadesMasa ?? [];
$unidadesTodas = $unidadesTodas ?? [];

// UI flags
$isEditing = $mode !== 'create'; // El nombre de variable original 'isEditing' controlaba la visibilidad de variantes. Mantenemos eso.
$isFormEnabled = $mode !== 'view'; // Solo 'create' y 'edit' habilitan form

$dimensionFields = [
    ['key' => 'largo_alto', 'label' => 'Largo / Alto', 'unit' => 'id_um_largo_alto', 'units' => $unidadesLongitud],
    ['key' => 'ancho', 'label' => 'Ancho', 'unit' => 'id_um_ancho', 'units' => $unidadesLongitud],
    ['key' => 'espesor_profundidad', 'label' => 'Espesor / Profundidad', 'unit' => 'id_um_espesor', 'units' => $unidadesLongitud],
];

?>
<link rel="stylesheet" href="<?= AssetHelper::css('modules/partes/manager.css') ?>">
<div x-data="parteManager(<?= View::escape(json_encode([
                                'parte' => $parte,
                                'variantes' => $parteVariants,
                                'mode' => $mode,
                                'editingVariantId' => $editingVariantId,
                                'editingVariant' => $editingVariant ?? null,
                            ])) ?>)" x-init="init()" class="partes-manager-page pt-0 pb-4 px-0">

    <!-- Header -->
    <section class="mb-1">
        <div class="d-flex flex-wrap justify-content-between align-items-center pm-page-header">
            <div>
                <p class="text-uppercase text-muted small mb-1">Productos</p>
                <h1 class="h3 mb-0">Gestión completa de partes y sus variantes</h1>
            </div>
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('productos/partes?tab=partes') ?>">Partes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('productos/partes?tab=variantes') ?>">Variantes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= url('productos/partes/importar') ?>">Importar</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="<?= url('productos/partes/manager') ?>">Manager</a>
                </li>
            </ul>
        </div>
    </section>

    <!-- Buscador de Partes (siempre visible, ancho completo) -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h6 class="text-muted mb-3">
                <i class="fa-solid fa-search me-2"></i>
                Buscar parte o variante existente para cargar o editar
            </h6>

            <?php if ($selectedVariante): ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="flex-grow-1">
                        <div class="alert alert-success mb-0 py-2 px-3">
                            <i class="fa-solid fa-check-circle me-2"></i>
                            <span class="fw-semibold">Parte:</span>
                            <strong><?= View::escape((string) ($parte['codigo'] ?? 'N/A')) ?></strong>
                            <span class="text-muted mx-1">-</span>
                            <span><?= View::escape((string) ($parte['detalle'] ?? '')) ?></span>
                            <span class="text-muted mx-2">|</span>
                            <span class="fw-semibold">Variante:</span>
                            <strong><?= View::escape((string) ($selectedVariante['codigo_variante'] ?? 'N/A')) ?></strong>
                            <span class="text-muted mx-1">-</span>
                            <span><?= View::escape((string) ($selectedVariante['detalle'] ?? '')) ?></span>
                        </div>
                    </div>
                    <a href="<?= url('productos/partes/manager/' . (int) ($parte['id'] ?? 0)) ?>" class="btn btn-outline-primary">
                        <i class="fa-solid fa-exchange-alt"></i> Cambiar
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!$selectedVariante): ?>
                <!-- Filtros por tipo -->
                <div class="btn-group btn-group-sm mb-3 w-100 flex-wrap" role="group" id="tipo-filters">
                    <button type="button"
                        class="btn btn-tipo-filter btn-outline-secondary active"
                        data-tipo=""
                        onclick="updateParteSearchFilter('', this)">
                        <i class="fa-solid fa-border-all"></i> Todos
                    </button>
                    <?php
                    $colores = ['info', 'success', 'warning', 'danger', 'primary', 'dark'];
                    foreach ($tipos as $index => $tipo):
                        $color = $colores[$index % count($colores)];
                    ?>
                        <button type="button"
                            class="btn btn-tipo-filter btn-outline-<?= $color ?>"
                            data-tipo="<?= View::escape($tipo['codigo']) ?>"
                            onclick="updateParteSearchFilter('<?= View::escape($tipo['codigo']) ?>', this)"
                            title="<?= View::escape($tipo['nombre']) ?>">
                            <strong><?= View::escape($tipo['codigo']) ?></strong>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Buscador -->
                <div id="parte-search-container">
                    <div class="input-group pm-search-group">
                        <span class="input-group-text bg-white">
                            <i class="fa-solid fa-search text-muted"></i>
                        </span>
                        <input type="text"
                            id="parte-search-input"
                            class="form-control"
                            placeholder="Buscar parte o variante por código o descripción..."
                            autocomplete="off">
                    </div>
                    <div id="parte-search-results" class="search-results"></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 pm-main-grid">
        <!-- Columna Izquierda: Formulario de Parte -->
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 h-100 pm-part-card">
                <div class="card-header bg-white border-bottom pm-card-header">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-box text-primary me-2"></i>
                        Datos de la Parte
                        <span x-show="mode === 'edit' && !isPartFormReadOnly" class="badge bg-success ms-2">Editando</span>
                        <span x-show="isPartFormReadOnly && form.id" class="badge bg-secondary ms-2">Solo lectura</span>
                        <span x-show="form.id" class="text-muted ms-2 small">ID: <span x-text="form.id"></span></span>
                        <span x-show="mode === 'create' && isPartFormReadOnly && !form.id" class="badge bg-secondary ms-2">Esperando Nueva Parte</span>
                        <span x-show="mode === 'create' && !isPartFormReadOnly && !form.id" class="badge bg-info ms-2">Nueva</span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php include __DIR__ . '/manager/_parte_form.php'; ?>
                </div>
            </div>
        </div>

        <!-- Columna Centro: Formulario de Variante -->
        <div class="col-12 col-md-4" x-show="isEditing">
            <div class="card shadow-sm border-0 h-100 pm-scroll-card">
                <div class="card-header bg-white border-bottom pm-card-header">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-pen-ruler text-success me-2"></i>
                            Formulario Variante
                        </h5>
                        <span x-show="variantForm && variantForm.id" class="text-muted small">ID: <span x-text="variantForm.id"></span></span>
                        <span x-show="isVariantFormEnabled && variantForm && !variantForm.id" class="badge bg-info">Nueva</span>
                        <span x-show="mode === 'view' && !isVariantFormEnabled && variantForm && variantForm.id" class="badge bg-secondary">Solo lectura</span>
                        <span x-show="isVariantFormEnabled" class="badge bg-success">Editando</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="pm-variant-form mb-0">
                        <?php include __DIR__ . '/manager/_variante_form.php'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Tabla de Variantes -->
        <div class="col-12 col-md-4" x-show="isEditing">
            <div class="card shadow-sm border-0 h-100 pm-scroll-card">
                <div class="card-header bg-white border-bottom pm-card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-layer-group text-success me-2"></i>
                            Tabla de Variantes
                        </h5>
                        <span class="badge bg-primary rounded-pill" x-text="variantes.length"></span>
                    </div>
                </div>
                <div class="card-body">

                    <!-- Tabla de Variantes -->
                    <div x-show="variantes.length > 0" class="pm-variants-table">
                        <?php include __DIR__ . '/manager/_variantes_table.php'; ?>
                    </div>

                    <!-- Estado vacío -->
                    <div x-show="variantes.length === 0" class="text-center py-5 pm-empty-state">
                        <i class="fa-solid fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No hay variantes registradas</p>
                        <p class="small text-muted">Completa el formulario para agregar la primera variante</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensaje cuando no hay parte seleccionada (ocupa columnas centro+derecha) -->
        <div class="col-12 col-md-8" x-show="!isEditing">
            <div class="card shadow-sm border-0 h-100 bg-light d-flex align-items-center justify-content-center">
                <div class="text-center p-4 p-lg-5 pm-empty-state">
                    <i class="fa-solid fa-arrow-left fa-3x text-muted mb-3 d-none d-lg-block"></i>
                    <i class="fa-solid fa-arrow-up fa-3x text-muted mb-3 d-lg-none"></i>
                    <h5 class="text-muted">Gestión de Variantes</h5>
                    <p class="text-muted mb-0">Selecciona una parte existente o crea una nueva para gestionar sus variantes.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Datos para JavaScript -->
<script>
    window.unidadesMasaData = <?= json_encode($unidadesMasa) ?>;
    window.unidadesSuperficieData = <?= json_encode($unidadesSuperficie) ?>;
    window.unidadesVolumenData = <?= json_encode($unidadesVolumen) ?>;
    window.unidadesLongitudData = <?= json_encode($unidadesLongitud) ?>;
    window.unidadesTodasData = <?= json_encode($unidadesTodas) ?>;
</script>

<script src="<?= AssetHelper::js('modules/SearchClient.js') ?>"></script>
<script src="<?= AssetHelper::js('partes-manager.js') ?>" defer></script>
<script>
    // Inicializar buscador de partes
    let currentParteFilters = {};
    let parteSearchInstance = null;

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('parte-search-input');
        const searchResults = document.getElementById('parte-search-results');

        if (searchInput && searchResults) {
            parteSearchInstance = new SearchClient({
                endpoint: '<?= url('api/v1/search/variantes') ?>',
                inputElement: searchInput,
                resultsContainer: searchResults,
                minChars: 2,
                debounceDelay: 300,
                maxResults: 15,
                format: 'detailed',
                filters: currentParteFilters,
                onSelect: (item) => {
                    // item.id es id_variante, item.id_parte es la parte asociada
                    const parteId = item.id_parte || item.id;
                    const varianteId = item.id;
                    const alpineComponent = Alpine.$data(document.querySelector('[x-data]'));
                    if (alpineComponent && alpineComponent.loadVariante && parteId && varianteId) {
                        alpineComponent.loadVariante(parteId, varianteId);
                    } else if (alpineComponent && alpineComponent.loadParte) {
                        alpineComponent.loadParte(parteId);
                    }
                    // Limpiar el input
                    searchInput.value = '';
                    searchResults.style.display = 'none';
                }
            });
        }
    });

    // Función para actualizar filtro de tipo
    function updateParteSearchFilter(tipoFilter, clickedButton = null) {
        // Actualizar botones activos
        document.querySelectorAll('#tipo-filters .btn-tipo-filter').forEach(btn => {
            btn.classList.remove('active');
        });
        if (clickedButton) {
            clickedButton.classList.add('active');
        }

        // Actualizar filtros
        currentParteFilters = tipoFilter ? {
            tipo_codigo: tipoFilter
        } : {};

        // Actualizar filtros en SearchClient
        if (parteSearchInstance) {
            parteSearchInstance.filters = currentParteFilters;
            // Si hay texto, reejecutar búsqueda
            const searchInput = document.getElementById('parte-search-input');
            if (searchInput && searchInput.value.trim().length >= 2) {
                searchInput.dispatchEvent(new Event('input'));
            }
        }
    }
</script>
