<?php

/**
 * Vista: Crear Orden de Producción
 */

use App\Core\Support\AssetHelper;

?>
<link rel="stylesheet" href="<?= AssetHelper::css('modules/SearchClient.css') ?>">

<div class="container-fluid py-4" x-data="ordenForm()">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Nueva Orden de Producción</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= url('produccion/ordenes') ?>" id="ordenForm">
                        <div class="row g-3">
                            <!-- Número de Orden -->
                            <div class="col-md-4">
                                <label for="numero_orden" class="form-label">Número de Orden</label>
                                <input type="text" class="form-control" id="numero_orden" name="numero_orden"
                                    value="<?= esc($numero_orden_sugerido ?? '') ?>" placeholder="Auto-generado si vacío">
                            </div>

                            <!-- Prioridad -->
                            <div class="col-md-4">
                                <label for="prioridad" class="form-label">Prioridad *</label>
                                <select class="form-select" id="prioridad" name="prioridad" required>
                                    <option value="baja">Baja</option>
                                    <option value="normal" selected>Normal</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                </select>
                            </div>

                            <!-- Estado (solo borrador en creación) -->
                            <div class="col-md-4">
                                <label for="estado" class="form-label">Estado</label>
                                <input type="text" class="form-control" value="Borrador" disabled>
                                <input type="hidden" name="estado" value="borrador">
                            </div>

                            <!-- Producto/Variante -->
                            <div class="col-12">
                                <label for="search-variante-input" class="form-label">
                                    <i class="fa-solid fa-magnifying-glass me-1"></i>
                                    Producto / Variante *
                                </label>
                                <div class="position-relative">
                                    <div class="input-group">
                                        <input type="text"
                                            class="form-control form-control-lg"
                                            id="search-variante-input"
                                            placeholder="Escriba al menos 2 caracteres para buscar producto o variante..."
                                            autocomplete="off">
                                        <button type="button" class="btn btn-outline-primary d-none" id="btn-cambiar-variante">
                                            <i class="fa-solid fa-rotate me-1"></i> Cambiar
                                        </button>
                                    </div>
                                    <div id="search-variante-results" class="search-results list-group mt-2"></div>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    <i class="fa-solid fa-info-circle me-1"></i>
                                    Los resultados incluyen productos y variantes. Seleccione uno para continuar.
                                </small>
                                <!-- Campo oculto para validación -->
                                <input type="hidden" id="variante_id" name="variante_id" x-model="varianteId" @change="cargarBoms" required>
                            </div>

                            <!-- BOM -->
                            <div class="col-md-6">
                                <label for="bom_id_utilizada" class="form-label">BOM a utilizar *</label>
                                <select class="form-select" id="bom_id_utilizada" name="bom_id_utilizada"
                                    x-model="bomId" :disabled="!varianteId" required>
                                    <option value="">Seleccione BOM...</option>
                                    <template x-for="bom in boms" :key="bom.id">
                                        <option :value="bom.id" x-text="bom.parte_codigo + ' - ' + bom.variante_codigo + ' (v' + bom.version + ')' + (bom.activa ? '' : ' [Inactiva]')"></option>
                                    </template>
                                </select>
                            </div>

                            <!-- Cantidad -->
                            <div class="col-md-6">
                                <label for="cantidad_planificada" class="form-label">Cantidad a Producir *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="cantidad_planificada"
                                        name="cantidad_planificada" step="0.01" min="0.01" required>
                                    <span class="input-group-text">unidades</span>
                                </div>
                            </div>

                            <!-- Fechas -->
                            <div class="col-md-6">
                                <label for="fecha_inicio_programada" class="form-label">Fecha Inicio Programada *</label>
                                <input type="datetime-local" class="form-control" id="fecha_inicio_programada"
                                    name="fecha_inicio_programada" required>
                            </div>

                            <div class="col-md-6">
                                <label for="fecha_fin_programada" class="form-label">Fecha Fin Programada *</label>
                                <input type="datetime-local" class="form-control" id="fecha_fin_programada"
                                    name="fecha_fin_programada" required>
                            </div>

                            <!-- Observaciones -->
                            <div class="col-12">
                                <label for="observaciones" class="form-label">Observaciones</label>
                                <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="<?= url('produccion/ordenes') ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Crear Orden
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Indicador de parte seleccionada */
    #search-variante-input.parte-seleccionada {
        background-color: #d1e7dd;
        border-color: #198754;
    }
</style>

<script src="<?= AssetHelper::js('modules/SearchClient.js') ?>"></script>
<script>
    function ordenForm() {
        return {
            varianteId: '',
            bomId: '',
            boms: [],

            async cargarBoms() {
                if (!this.varianteId) {
                    this.boms = [];
                    this.bomId = '';
                    return;
                }

                try {
                    const response = await fetch(`/api/v1/bom/variantes/${this.varianteId}`);
                    const data = await response.json();
                    this.boms = data.boms ?? [];
                } catch (error) {
                    console.error('Error cargando BOMs:', error);
                    this.boms = [];
                }
            }
        };
    }

    // Cargar productos/variantes
    document.addEventListener('DOMContentLoaded', async function() {
        const searchInput = document.getElementById('search-variante-input');
        const searchResults = document.getElementById('search-variante-results');
        const inputVarianteHidden = document.getElementById('variante_id');
        const btnCambiar = document.getElementById('btn-cambiar-variante');

        function buildSearchSelectionLabel(item) {
            const parteCodigo = item.parte_codigo || 'N/A';
            const parteDetalle = item.parte_detalle || 'Sin detalle';
            const varianteCodigo = item.codigo_variante || 'N/A';
            const varianteDetalle = item.detalle || item.variante_detalle || 'Sin detalle';

            return `Producto: ${parteCodigo} - ${parteDetalle} | Variante: ${varianteCodigo} - ${varianteDetalle}`;
        }

        const searchClientInstance = new SearchClient({
            endpoint: '<?= url('api/v1/search/variantes') ?>',
            inputElement: searchInput,
            resultsContainer: searchResults,
            minChars: 2,
            debounceDelay: 300,
            maxResults: 20,
            format: 'detailed',
            onSelect: (item) => {
                const selectedLabel = buildSearchSelectionLabel(item);

                searchInput.value = selectedLabel;
                searchInput.readOnly = true;
                searchInput.disabled = true;
                searchInput.setAttribute('aria-readonly', 'true');
                searchInput.setAttribute('aria-disabled', 'true');
                searchInput.classList.add('parte-seleccionada');
                btnCambiar.classList.remove('d-none');

                inputVarianteHidden.value = item.id;
                inputVarianteHidden.dispatchEvent(new Event('input', {
                    bubbles: true
                })); // Para Alpine x-model
                inputVarianteHidden.dispatchEvent(new Event('change', {
                    bubbles: true
                })); // Para invocar cargarBoms
            },
            onError: (error) => {
                console.error('[SearchClient Error]', error);
                // Optional: Mostrar toast/notificación al usuario
            }
        });

        btnCambiar.addEventListener('click', () => {
            inputVarianteHidden.value = '';
            inputVarianteHidden.dispatchEvent(new Event('input', {
                bubbles: true
            })); // Para Alpine x-model
            inputVarianteHidden.dispatchEvent(new Event('change', {
                bubbles: true
            }));

            searchInput.value = '';
            searchInput.readOnly = false;
            searchInput.disabled = false;
            searchInput.setAttribute('aria-readonly', 'false');
            searchInput.setAttribute('aria-disabled', 'false');
            searchInput.classList.remove('parte-seleccionada');
            btnCambiar.classList.add('d-none');

            setTimeout(() => {
                searchInput.focus();
            }, 50);
        });

        // Configuración inicial
        searchInput.disabled = false;
    });
</script>
