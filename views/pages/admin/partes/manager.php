<?php

use App\Core\View\View;
use App\Core\Support\AssetHelper;

// Variables del controlador
$parte = $parte ?? null;
$parteVariants = $parteVariants ?? [];

// Modo de operación: 'create', 'view', 'edit'
$mode = $mode ?? ($parte ? 'view' : 'create');
$editingVariantId = $editingVariantId ?? null;

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
<style>
    /* Estilos para el buscador de partes */
    #parte-search-container {
        position: relative;
    }

    #parte-search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 1050;
        max-height: 400px;
        overflow-y: auto;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        margin-top: 0.25rem;
        display: none;
    }

    #parte-search-results .search-result-item {
        padding: 0.75rem;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background-color 0.2s;
    }

    #parte-search-results .search-result-item:hover,
    #parte-search-results .search-result-item.active {
        background-color: #f8f9fa;
    }

    #parte-search-results .search-result-item:last-child {
        border-bottom: none;
    }

    #parte-search-input:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    /* Estilos para botones de filtro */
    .btn-tipo-filter {
        transition: all 0.3s ease;
        font-weight: 500;
        min-width: 60px;
    }

    .btn-tipo-filter:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .btn-tipo-filter.active {
        font-weight: bold;
        box-shadow: inset 0 3px 8px rgba(0, 0, 0, 0.2), 0 0 0 3px rgba(0, 0, 0, 0.1);
        transform: scale(1.05);
    }

    .btn-outline-secondary.active {
        background-color: #6c757d !important;
        color: white !important;
        border-color: #6c757d !important;
    }

    .btn-outline-info.active {
        background-color: #0dcaf0 !important;
        color: white !important;
        border-color: #0dcaf0 !important;
    }

    .btn-outline-success.active {
        background-color: #198754 !important;
        color: white !important;
        border-color: #198754 !important;
    }

    .btn-outline-warning.active {
        background-color: #ffc107 !important;
        color: #000 !important;
        border-color: #ffc107 !important;
    }

    .btn-outline-danger.active {
        background-color: #dc3545 !important;
        color: white !important;
        border-color: #dc3545 !important;
    }

    .btn-outline-primary.active {
        background-color: #0d6efd !important;
        color: white !important;
        border-color: #0d6efd !important;
    }

    .btn-outline-dark.active {
        background-color: #212529 !important;
        color: white !important;
        border-color: #212529 !important;
    }

    /* Estilos para badge de estado */
    .badge.ms-2 {
        font-size: 0.75rem;
        font-weight: 600;
    }

    /* Animación suave para el buscador */
    #parte-search-container {
        transition: all 0.3s ease;
    }

    #parte-search-input:focus+#parte-search-results {
        border-color: #0d6efd;
    }
</style>
<div x-data="parteManager(<?= View::escape(json_encode([
                                'parte' => $parte,
                                'variantes' => $parteVariants,
                                'mode' => $mode,
                                'editingVariantId' => $editingVariantId,
                            ])) ?>)" x-init="init()" class="pt-0 pb-4 px-0">

    <!-- Header -->
    <section class="mb-1">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
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
                    <a class="nav-link active" href="<?= url('productos/partes/manager') ?>">Manager</a>
                </li>
            </ul>
        </div>
    </section>

    <div class="d-flex justify-content-end mb-4">
        <div class="btn-group">
            <!-- Botón Cancelar Edición (modo edit) -->
            <a x-show="mode === 'edit' && form.id" :href="'/mrp/productos/partes/manager/' + form.id" class="btn btn-secondary">
                <i class="fa-solid fa-eye me-2"></i>Ver Solo Lectura
            </a>
        </div>
    </div>

    <!-- Buscador de Partes (siempre visible, ancho completo) -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h6 class="text-muted mb-3">
                <i class="fa-solid fa-search me-2"></i>
                Buscar parte o variante existente para cargar o editar
            </h6>

            <!-- Filtros por tipo -->
            <div class="btn-group btn-group-sm mb-3 w-100 flex-wrap" role="group" id="tipo-filters">
                <button type="button"
                    class="btn btn-tipo-filter btn-outline-secondary active"
                    data-tipo=""
                    onclick="updateParteSearchFilter('')">
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
                        onclick="updateParteSearchFilter('<?= View::escape($tipo['codigo']) ?>')"
                        title="<?= View::escape($tipo['nombre']) ?>">
                        <strong><?= View::escape($tipo['codigo']) ?></strong>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Buscador -->
            <div id="parte-search-container">
                <div class="input-group input-group-lg">
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
        </div>
    </div>

    <div class="row g-4">
        <!-- Columna Izquierda: Formulario de Parte -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-box text-primary me-2"></i>
                        Datos de la Parte
                        <span x-show="isEditing" class="badge bg-success ms-2">Editando</span>
                        <span x-show="form.id" class="text-muted ms-2 small">ID: <span x-text="form.id"></span></span>
                        <span x-show="!isEditing" class="badge bg-info ms-2">Nueva</span>
                    </h5>
                </div>
                <div class="card-body">
                    <fieldset :disabled="mode === 'view'">
                        <?php include __DIR__ . '/manager/_parte_form.php'; ?>
                    </fieldset>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Variantes -->
        <div class="col-lg-6" x-show="isEditing">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-layer-group text-success me-2"></i>
                            Variantes
                        </h5>
                        <span class="badge bg-primary rounded-pill" x-text="variantes.length"></span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Formulario de Variante -->
                    <div class="border rounded-3 p-3 mb-4 bg-light">
                        <fieldset :disabled="mode === 'view'">
                            <?php include __DIR__ . '/manager/_variante_form.php'; ?>
                        </fieldset>
                    </div>

                    <!-- Tabla de Variantes -->
                    <div x-show="variantes.length > 0">
                        <?php include __DIR__ . '/manager/_variantes_table.php'; ?>
                    </div>

                    <!-- Estado vacío -->
                    <div x-show="variantes.length === 0" class="text-center py-5">
                        <i class="fa-solid fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No hay variantes registradas</p>
                        <p class="small text-muted">Completa el formulario para agregar la primera variante</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensaje cuando no hay parte seleccionada (ocupa columna derecha) -->
        <div class="col-lg-6" x-show="!isEditing">
            <div class="card shadow-sm border-0 h-100 bg-light d-flex align-items-center justify-content-center">
                <div class="text-center p-5">
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
                    // item.id_parte viene del endpoint search/variantes (formato detailed)
                    const parteId = item.id_parte || item.id;
                    const alpineComponent = Alpine.$data(document.querySelector('[x-data]'));
                    if (alpineComponent && alpineComponent.loadParte) {
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
    function updateParteSearchFilter(tipoFilter) {
        // Actualizar botones activos
        document.querySelectorAll('#tipo-filters .btn-tipo-filter').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.closest('.btn-tipo-filter').classList.add('active');

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
