<?php

/**
 * Vista: Crear Operación de Ruta
 */
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Nueva Operación de Ruta</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= url('produccion/rutas') ?>" id="rutaForm">
                        <div class="row g-3">
                            <!-- BOM -->
                            <div class="col-md-6">
                                <label for="bom_id" class="form-label">BOM *</label>
                                <select class="form-select" id="bom_id" name="bom_id" required>
                                    <option value="">Seleccione un BOM...</option>
                                    <!-- Los BOMs se cargarían con AJAX o desde el controlador -->
                                </select>
                                <small class="text-muted">Producto al que pertenece esta operación</small>
                            </div>

                            <!-- Secuencia -->
                            <div class="col-md-6">
                                <label for="secuencia" class="form-label">Secuencia *</label>
                                <input type="number" class="form-control" id="secuencia" name="secuencia"
                                    min="1" step="1" value="10" required>
                                <small class="text-muted">Orden de ejecución (múltiplos de 10)</small>
                            </div>

                            <!-- Centro de Trabajo -->
                            <div class="col-md-6">
                                <label for="centro_trabajo_id" class="form-label">Centro de Trabajo *</label>
                                <select class="form-select" id="centro_trabajo_id" name="centro_trabajo_id" required>
                                    <option value="">Seleccione un centro...</option>
                                    <?php if (isset($centros) && !empty($centros)): ?>
                                        <?php foreach ($centros as $centro): ?>
                                            <option value="<?= esc($centro['id']) ?>">
                                                <?= esc($centro['codigo']) ?> - <?= esc($centro['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <!-- Descripción -->
                            <div class="col-md-6">
                                <label for="descripcion" class="form-label">Descripción *</label>
                                <input type="text" class="form-control" id="descripcion" name="descripcion"
                                    placeholder="Ej: Corte de piezas, Ensamblaje final..." required>
                            </div>

                            <!-- Tiempos -->
                            <div class="col-12">
                                <h6 class="mt-3 mb-2 text-primary">
                                    <i class="fas fa-clock"></i> Tiempos de Operación
                                </h6>
                            </div>

                            <div class="col-md-3">
                                <label for="tiempo_setup_mins" class="form-label">Setup (minutos)</label>
                                <input type="number" class="form-control" id="tiempo_setup_mins"
                                    name="tiempo_setup_mins" min="0" step="1" value="0">
                                <small class="text-muted">Preparación inicial</small>
                            </div>

                            <div class="col-md-3">
                                <label for="tiempo_proceso_unitario_mins" class="form-label">Proceso Unitario (min)</label>
                                <input type="number" class="form-control" id="tiempo_proceso_unitario_mins"
                                    name="tiempo_proceso_unitario_mins" min="0" step="0.01" value="0" required>
                                <small class="text-muted">Por cada unidad</small>
                            </div>

                            <div class="col-md-3">
                                <label for="tiempo_cola_mins" class="form-label">Cola (minutos)</label>
                                <input type="number" class="form-control" id="tiempo_cola_mins"
                                    name="tiempo_cola_mins" min="0" step="1" value="0">
                                <small class="text-muted">Espera en cola</small>
                            </div>

                            <div class="col-md-3">
                                <label for="tiempo_movimiento_mins" class="form-label">Movimiento (min)</label>
                                <input type="number" class="form-control" id="tiempo_movimiento_mins"
                                    name="tiempo_movimiento_mins" min="0" step="1" value="0">
                                <small class="text-muted">Traslado</small>
                            </div>

                            <!-- Costos -->
                            <div class="col-12">
                                <h6 class="mt-3 mb-2 text-success">
                                    <i class="fas fa-dollar-sign"></i> Costos
                                </h6>
                            </div>

                            <div class="col-md-4">
                                <label for="costo_operacion_fijo" class="form-label">Costo Fijo</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="costo_operacion_fijo"
                                        name="costo_operacion_fijo" min="0" step="0.01" value="0">
                                </div>
                                <small class="text-muted">Costo por ejecución</small>
                            </div>

                            <div class="col-md-4">
                                <label for="costo_operacion_variable" class="form-label">Costo Variable</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="costo_operacion_variable"
                                        name="costo_operacion_variable" min="0" step="0.01" value="0">
                                </div>
                                <small class="text-muted">Costo por unidad</small>
                            </div>

                            <div class="col-md-4">
                                <label for="capacidad_requerida" class="form-label">Capacidad Requerida</label>
                                <input type="number" class="form-control" id="capacidad_requerida"
                                    name="capacidad_requerida" min="0" step="0.01" value="1">
                                <small class="text-muted">Factor de uso del centro</small>
                            </div>

                            <!-- Instrucciones -->
                            <div class="col-12">
                                <label for="instrucciones" class="form-label">Instrucciones de Trabajo</label>
                                <textarea class="form-control" id="instrucciones" name="instrucciones"
                                    rows="4" placeholder="Detalle el procedimiento, herramientas necesarias, normas de seguridad..."></textarea>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="<?= url('produccion/rutas') ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Operación
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Validación adicional
        const form = document.getElementById('rutaForm');

        form.addEventListener('submit', function(e) {
            const tiempoProceso = parseFloat(document.getElementById('tiempo_proceso_unitario_mins').value);

            if (tiempoProceso <= 0) {
                e.preventDefault();
                alert('El tiempo de proceso unitario debe ser mayor a 0');
                return false;
            }
        });
    });
</script>
