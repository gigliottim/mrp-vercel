<?php

/**
 * Vista: Crear Orden de Producción
 */
?>

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
                                <label for="variante_id" class="form-label">Producto / Variante *</label>
                                <select class="form-select" id="variante_id" name="variante_id"
                                    x-model="varianteId" @change="cargarBoms" required>
                                    <option value="">Seleccione un producto...</option>
                                    <!-- Cargar con AJAX o desde PHP -->
                                </select>
                            </div>

                            <!-- BOM -->
                            <div class="col-md-6">
                                <label for="bom_id_utilizada" class="form-label">BOM a utilizar *</label>
                                <select class="form-select" id="bom_id_utilizada" name="bom_id_utilizada"
                                    x-model="bomId" :disabled="!varianteId" required>
                                    <option value="">Seleccione BOM...</option>
                                    <template x-for="bom in boms" :key="bom.id">
                                        <option :value="bom.id" x-text="bom.nombre + ' (v' + bom.version + ')'"></option>
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

<script>
    function ordenForm() {
        return {
            varianteId: '',
            bomId: '',
            boms: [],

            async cargarBoms() {
                if (!this.varianteId) {
                    this.boms = [];
                    return;
                }

                try {
                    const response = await fetch(`/api/variantes/${this.varianteId}/boms`);
                    this.boms = await response.json();
                } catch (error) {
                    console.error('Error cargando BOMs:', error);
                }
            }
        };
    }

    // Cargar productos/variantes
    document.addEventListener('DOMContentLoaded', async function() {
        try {
            const response = await fetch('<?= url('api/variantes') ?>?activo=1');
            const variantes = await response.json();

            const select = document.getElementById('variante_id');
            variantes.forEach(v => {
                const option = document.createElement('option');
                option.value = v.id;
                option.textContent = `${v.producto_nombre} - ${v.codigo}`;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Error cargando variantes:', error);
        }
    });
</script>
