<?php

use App\Core\View\View;
use App\Core\Support\AssetHelper;

$parte = $parte ?? null;
$parteVariants = $parteVariants ?? [];
$mode = $mode ?? ($parte ? 'view' : 'create');
$editingVariantId = $editingVariantId ?? null;
$selectedVariante = $editingVariant ?? null;

$tipos = $tipos ?? [];
$grupos = $grupos ?? [];
$unidadesLongitud = $unidadesLongitud ?? [];
$unidadesSuperficie = $unidadesSuperficie ?? [];
$unidadesVolumen = $unidadesVolumen ?? [];
$unidadesMasa = $unidadesMasa ?? [];
$unidadesTodas = $unidadesTodas ?? [];

$dimensionFields = [
    ['key' => 'largo_alto', 'label' => 'Largo/Alto', 'unit' => 'id_um_largo_alto', 'units' => $unidadesLongitud],
    ['key' => 'ancho', 'label' => 'Ancho', 'unit' => 'id_um_ancho', 'units' => $unidadesLongitud],
    ['key' => 'espesor_profundidad', 'label' => 'Espesor', 'unit' => 'id_um_espesor', 'units' => $unidadesLongitud],
];
?>
<link rel="stylesheet" href="<?= AssetHelper::css('modules/partes/manager.css') ?>">
<div x-data="parteManager(<?= View::escape(json_encode([
                                'parte' => $parte,
                                'variantes' => $parteVariants,
                                'mode' => $mode,
                                'editingVariantId' => $editingVariantId,
                                'editingVariant' => $editingVariant ?? null,
                            ])) ?>)" x-init="init()" class="pm-wrapper">

    <nav class="pm-navbar">
        <span class="pm-brand"><i class="fa-solid fa-cubes me-1"></i>MRP</span>
        <span class="pm-sep">|</span>
        <span class="pm-ctx">Productos</span>
        <div class="pm-nav-pills">
            <a class="pm-nav-link" href="<?= url('productos/partes') ?>"><i class="fa-solid fa-table-list me-1"></i><span class="d-none d-sm-inline">Partes</span></a>
            <a class="pm-nav-link active" href="<?= url('productos/partes/manager') ?>"><i class="fa-solid fa-sliders me-1"></i><span class="d-none d-sm-inline">Manager</span></a>
            <a class="pm-nav-link" href="<?= url('productos/partes/importar') ?>"><i class="fa-solid fa-file-import me-1"></i><span class="d-none d-sm-inline">Importar</span></a>
        </div>
    </nav>

    <div class="pm-search-bar">
        <template x-if="!hasValidParteSelection()">
            <div class="d-flex align-items-center gap-2 w-100">
                <select class="pm-search-select" id="tipo-filter-v3" onchange="updateTipoFilterV3(this.value, this)">
                    <option value="">Todos</option>
                    <?php foreach ($tipos as $tipo): ?>
                        <option value="<?= View::escape($tipo['codigo']) ?>"><?= View::escape($tipo['codigo']) ?> — <?= View::escape($tipo['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="pm-search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="parte-search-input-v3" class="pm-search-input" placeholder="Buscar parte o variante por código o descripción..." autocomplete="off">
                    <button class="pm-search-clear" x-show="document.getElementById('parte-search-input-v3')?.value" @click="document.getElementById('parte-search-input-v3').value=''; document.getElementById('parte-search-results-v3').style.display='none';"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
        </template>

        <template x-if="hasValidParteSelection()">
            <div class="pm-sel-banner">
                <div class="pm-sel-icon"><i class="fa-solid fa-box"></i></div>
                <div class="pm-sel-info">
                    <div class="pm-sel-code" x-text="(form.codigo||'N/A')+(form.id?' — ID:'+form.id:'')"></div>
                    <div class="pm-sel-det" x-text="form.detalle||'Sin detalle'"></div>
                </div>
                <span class="pm-b pm-b-suc" x-show="form.activo">Activa</span>
                <span class="pm-b pm-b-sec" x-show="!form.activo">Inactiva</span>
                <button class="pm-btn pm-btn-outline" style="padding:.2rem .5rem;font-size:.68rem;" @click="resetForm()"><i class="fa-solid fa-arrows-rotate me-1"></i>Cambiar</button>
            </div>
        </template>

        <div id="parte-search-results-v3" style="position:absolute;top:100%;left:0;right:0;z-index:1050;max-height:320px;overflow-y:auto;background:#fff;border:1px solid #e2e8f0;border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,.12);display:none;"></div>
    </div>

    <div class="pm-shell">
        <aside class="pm-panel pm-panel-left">
            <div class="pm-panel-head">
                <h6><i class="fa-solid fa-layer-group me-1"></i>Variantes</h6>
                <div class="d-flex align-items-center gap-2">
                    <span class="pm-b pm-b-pri" x-text="variantes.length" x-show="variantes.length > 0"></span>
                    <button class="pm-btn pm-btn-primary" style="padding:.12rem .35rem;font-size:.62rem;" x-show="form.id && !isVariantFormEnabled" @click="enableNewVariante()"><i class="fa-solid fa-plus"></i></button>
                </div>
            </div>
            <div class="pm-panel-body">
                <template x-if="!isEditing">
                    <div class="pm-empty pm-in">
                        <i class="fa-solid fa-inbox"></i>
                        <h6>Variantes</h6>
                        <p>Busca o crea una parte</p>
                    </div>
                </template>
                <template x-if="isEditing && variantes.length > 0">
                    <div>
                        <template x-for="(variante, idx) in variantes" :key="variante.id || idx">
                            <div class="pm-var-card" :class="{ 'active': variantForm && variantForm.id === variante.id }" @click="editVariante(variante)">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="pm-var-code" x-text="variante.codigo_variante || '—'"></div>
                                    <div class="pm-var-actions d-flex gap-1">
                                        <button class="btn btn-sm pm-btn-outline" style="padding:.1rem .25rem;font-size:.6rem;" @click.stop="editVariante(variante)" :disabled="isVariantFormEnabled" title="Editar"><i class="fa-solid fa-pen"></i></button>
                                        <a class="btn btn-sm pm-btn-outline" style="padding:.1rem .25rem;font-size:.6rem;" :href="'<?= url('productos/maestro') ?>?id_variante=' + variante.id" title="Maestro" target="_blank"><i class="fa-solid fa-network-wired"></i></a>
                                        <button class="btn btn-sm pm-btn-danger" style="padding:.1rem .25rem;" @click.stop="openDeleteVarianteModal(variante)" :disabled="isVariantFormEnabled" title="Eliminar"><i class="fa-solid fa-trash" style="font-size:.55rem"></i></button>
                                    </div>
                                </div>
                                <div class="pm-var-det" x-text="variante.detalle || 'Sin detalle'"></div>
                                <div class="pm-var-meta">
                                    <span class="pm-st" :class="{'pm-st-activa':variante.estado==='activa','pm-st-desarrollo':variante.estado==='desarrollo','pm-st-obsoleta':variante.estado==='obsoleta','pm-st-descontinuada':variante.estado==='descontinuada'}" x-text="getEstadoLabel(variante.estado)"></span>
                                    <span class="pm-b pm-b-sec" x-show="variante.stock_actual"><i class="fa-solid fa-box me-1" style="font-size:.5rem"></i><span x-text="formatNumberDisplay(variante.stock_actual,'0')"></span></span>
                                    <span class="pm-b pm-b-sec" x-show="variante.peso"><i class="fa-solid fa-weight-hanging me-1" style="font-size:.5rem"></i><span x-text="formatNumberDisplay(variante.peso,'--')"></span></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="isEditing && variantes.length === 0">
                    <div class="pm-empty pm-in"><i class="fa-solid fa-inbox"></i><h6>Sin variantes</h6><p>Agrega la primera variante</p></div>
                </template>
            </div>
        </aside>

        <section class="pm-panel pm-panel-center">
            <div class="pm-panel-body" style="max-width:860px;margin:0 auto;width:100%;">

                <div class="pm-sec pm-in" id="section-parte">
                    <div class="pm-sec-head" onclick="toggleSection(this)">
                        <h6><i class="fa-solid fa-box" style="color:var(--pm-primary)"></i> Datos de la Parte
                            <span class="pm-b pm-b-inf ms-1" x-show="mode==='create' && !isPartFormReadOnly && !form.id">Nueva</span>
                            <span class="pm-b pm-b-suc ms-1" x-show="mode==='edit' && !isPartFormReadOnly">Editando</span>
                            <span class="pm-b pm-b-sec ms-1" x-show="isPartFormReadOnly && form.id">Solo lectura</span>
                        </h6>
                        <i class="fa-solid fa-chevron-down pm-chevron"></i>
                    </div>
                    <div class="pm-sec-body">
                        <?php include __DIR__ . '/manager/_parte_form.php'; ?>
                    </div>
                </div>

                <div class="pm-sec pm-in" id="section-variante">
                    <div class="pm-sec-head" onclick="toggleSection(this)">
                        <h6><i class="fa-solid fa-pen-ruler" style="color:var(--pm-success)"></i> Variante
                            <span class="pm-b pm-b-inf ms-1" x-show="isVariantFormEnabled && variantForm && !variantForm.id">Nueva</span>
                            <span class="pm-b pm-b-suc ms-1" x-show="isVariantFormEnabled">Editando</span>
                            <span class="pm-b pm-b-sec ms-1" x-show="mode==='view' && !isVariantFormEnabled && variantForm && variantForm.id">Lectura</span>
                        </h6>
                        <i class="fa-solid fa-chevron-down pm-chevron"></i>
                    </div>
                    <div class="pm-sec-body">
                        <?php include __DIR__ . '/manager/_variante_form.php'; ?>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <?php include __DIR__ . '/manager/_delete_modal.php'; ?>
</div>

<script>
    window.MRP_BASE_PATH = '<?= url() ?>';
    window.unidadesMasaData = <?= json_encode($unidadesMasa) ?>;
    window.unidadesSuperficieData = <?= json_encode($unidadesSuperficie) ?>;
    window.unidadesVolumenData = <?= json_encode($unidadesVolumen) ?>;
    window.unidadesLongitudData = <?= json_encode($unidadesLongitud) ?>;
    window.unidadesTodasData = <?= json_encode($unidadesTodas) ?>;
</script>

<script src="<?= AssetHelper::js('modules/SearchClient.js') ?>"></script>
<script src="<?= AssetHelper::js('partes-manager.js') ?>" defer></script>
<script>
let currentTipoFilter = '';
function updateTipoFilterV3(tipo, sel) {
    currentTipoFilter = tipo;
    const si = document.getElementById('parte-search-input-v3');
    if (si && si.value.trim().length >= 2) si.dispatchEvent(new Event('input'));
}

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('parte-search-input-v3');
    const searchResults = document.getElementById('parte-search-results-v3');

    if (searchInput && searchResults) {
        parteSearchInstance = new SearchClient({
            endpoint: '<?= url('api/v1/search/variantes') ?>',
            inputElement: searchInput,
            resultsContainer: searchResults,
            minChars: 2,
            debounceDelay: 300,
            maxResults: 15,
            format: 'detailed',
            filters: {},
            onSelect: (item) => {
                const parteId = item.id_parte || item.id;
                const varianteId = item.id;
                const alpineComponent = Alpine.$data(document.querySelector('[x-data]'));
                if (alpineComponent && alpineComponent.loadVariante && parteId && varianteId) {
                    alpineComponent.loadVariante(parteId, varianteId);
                } else if (alpineComponent && alpineComponent.loadParte) {
                    alpineComponent.loadParte(parteId);
                }
                searchInput.value = '';
                searchResults.style.display = 'none';
            }
        });

        const origUpdateFilter = window.updateParteSearchFilter || null;

        window.updateTipoFilterV3 = function(tipo, sel) {
            currentTipoFilter = tipo;
            if (parteSearchInstance) {
                parteSearchInstance.filters = tipo ? { tipo_codigo: tipo } : {};
                if (searchInput && searchInput.value.trim().length >= 2) {
                    searchInput.dispatchEvent(new Event('input'));
                }
            }
        };
    }
});

function toggleSection(el) {
    const s = el.closest('.pm-sec');
    if (!s) return;
    const b = s.querySelector('.pm-sec-body');
    if (!b) return;
    b.style.display = b.style.display === 'none' ? '' : 'none';
    const chevron = el.querySelector('.pm-chevron');
    if (chevron) {
        chevron.classList.toggle('fa-chevron-down');
        chevron.classList.toggle('fa-chevron-right');
    }
}
</script>