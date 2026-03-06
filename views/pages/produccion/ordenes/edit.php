<?php

/**
 * Vista: Editar Orden de Producción
 */
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Editar Orden: <?= esc($orden['numero_orden']) ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= url('produccion/ordenes/' . $orden['id']) ?>">
                        <input type="hidden" name="_method" value="PUT">

                        <div class="row g-3">
                            <!-- Número de Orden -->
                            <div class="col-md-6">
                                <label for="numero_orden" class="form-label">Número de Orden *</label>
                                <input type="text" class="form-control" id="numero_orden" name="numero_orden"
                                    value="<?= esc($orden['numero_orden']) ?>" required>
                            </div>

                            <!-- Prioridad -->
                            <div class="col-md-6">
                                <label for="prioridad" class="form-label">Prioridad *</label>
                                <select class="form-select" id="prioridad" name="prioridad" required>
                                    <option value="baja" <?= $orden['prioridad'] === 'baja' ? 'selected' : '' ?>>Baja</option>
                                    <option value="normal" <?= $orden['prioridad'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                                    <option value="alta" <?= $orden['prioridad'] === 'alta' ? 'selected' : '' ?>>Alta</option>
                                    <option value="urgente" <?= $orden['prioridad'] === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                                </select>
                            </div>

                            <!-- Producto/Variante (readonly) -->
                            <div class="col-md-6">
                                <label class="form-label">Producto / Variante</label>
                                <input type="text" class="form-control" value="<?= esc($orden['producto_nombre'] . ' - ' . $orden['variante_codigo']) ?>" disabled>
                                <input type="hidden" name="variante_id" value="<?= $orden['variante_id'] ?>">
                            </div>

                            <!-- BOM (readonly) -->
                            <div class="col-md-6">
                                <label class="form-label">BOM Utilizada</label>
                                <input type="text" class="form-control" value="<?= esc($orden['bom_nombre']) ?> (v<?= $orden['bom_version'] ?>)" disabled>
                                <input type="hidden" name="bom_id_utilizada" value="<?= $orden['bom_id_utilizada'] ?>">
                            </div>

                            <!-- Cantidades -->
                            <div class="col-md-4">
                                <label for="cantidad_planificada" class="form-label">Cantidad Planificada *</label>
                                <input type="number" class="form-control" id="cantidad_planificada" name="cantidad_planificada"
                                    value="<?= $orden['cantidad_planificada'] ?>" step="0.01" min="0.01" required>
                            </div>

                            <div class="col-md-4">
                                <label for="cantidad_producida" class="form-label">Cantidad Producida</label>
                                <input type="number" class="form-control" id="cantidad_producida" name="cantidad_producida"
                                    value="<?= $orden['cantidad_producida'] ?>" step="0.01" min="0">
                            </div>

                            <div class="col-md-4">
                                <label for="cantidad_desechada" class="form-label">Cantidad Desechada</label>
                                <input type="number" class="form-control" id="cantidad_desechada" name="cantidad_desechada"
                                    value="<?= $orden['cantidad_desechada'] ?>" step="0.01" min="0">
                            </div>

                            <!-- Fechas Programadas -->
                            <div class="col-md-6">
                                <label for="fecha_inicio_programada" class="form-label">Fecha Inicio Programada *</label>
                                <input type="datetime-local" class="form-control" id="fecha_inicio_programada"
                                    name="fecha_inicio_programada" value="<?= date('Y-m-d\TH:i', strtotime($orden['fecha_inicio_programada'])) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="fecha_fin_programada" class="form-label">Fecha Fin Programada *</label>
                                <input type="datetime-local" class="form-control" id="fecha_fin_programada"
                                    name="fecha_fin_programada" value="<?= date('Y-m-d\TH:i', strtotime($orden['fecha_fin_programada'])) ?>" required>
                            </div>

                            <!-- Observaciones -->
                            <div class="col-12">
                                <label for="observaciones" class="form-label">Observaciones</label>
                                <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?= esc($orden['observaciones']) ?></textarea>
                            </div>
                        </div>

                        <!-- Información de Estado -->
                        <div class="alert alert-info mt-3">
                            <strong>Estado actual:</strong> <?= ucfirst(str_replace('_', ' ', $orden['estado'])) ?>
                            <br>
                            <small>Para cambiar el estado, use las acciones en la vista de detalle.</small>
                        </div>

                        <!-- Botones -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="<?= url('produccion/ordenes/' . $orden['id']) ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
