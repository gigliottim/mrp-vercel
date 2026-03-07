<?php

use App\Core\View\View;
?>
<form @submit.prevent="saveParte()">
    <fieldset :disabled="mode === 'view'">
        <div class="row g-2 g-xl-3">
            <!-- Fila 1: Datos Generales (Compacto) -->
            <div class="col-sm-6 col-xl-4">
                <label class="form-label fw-semibold mb-1">Código <span class="text-danger">*</span></label>
                <input
                    type="text"
                    class="form-control form-control-sm"
                    x-model="form.codigo"
                    placeholder=""
                    required
                    style="text-transform: uppercase">
            </div>
            <div class="col-sm-6 col-xl-4">
                <label class="form-label fw-semibold mb-1">Tipo <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" x-model.number="form.id_tipo" required>
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($tipos as $tipo) : ?>
                        <option value="<?= (int) $tipo['id'] ?>"><?= View::escape($tipo['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-xl-4">
                <label class="form-label fw-semibold mb-1">Grupo <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" x-model.number="form.id_grupo" required>
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($grupos as $grupo) : ?>
                        <option value="<?= (int) $grupo['id'] ?>"><?= View::escape($grupo['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Fila 2: Detalle -->
            <div class="col-12">
                <label class="form-label fw-semibold mb-1">Detalle <span class="text-danger">*</span></label>
                <textarea
                    class="form-control form-control-sm"
                    x-model="form.detalle"
                    rows="2"
                    placeholder="Descripción de la parte"
                    required></textarea>
            </div>

            <!-- Fila 3: Unidades de Medida -->
            <div class="col-sm-6 col-xl-3">
                <label class="form-label fw-semibold mb-1">
                    UM Compra
                    <i class="fa-solid fa-circle-info text-muted"
                        data-bs-toggle="tooltip"
                        title="Unidad en la que se compra el ítem"></i>
                </label>
                <select class="form-select form-select-sm" x-model.number="form.id_um_compra">
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($unidadesTodas as $unidad) : ?>
                        <option value="<?= (int) $unidad['id'] ?>">
                            <?= View::escape($unidad['unidad']) ?> (<?= View::escape($unidad['simbolo']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-xl-3">
                <label class="form-label fw-semibold mb-1">
                    UM Uso
                    <i class="fa-solid fa-circle-info text-muted"
                        data-bs-toggle="tooltip"
                        title="Unidad en la que se usa el ítem en producción"></i>
                </label>
                <select class="form-select form-select-sm" x-model.number="form.id_um_uso">
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($unidadesTodas as $unidad) : ?>
                        <option value="<?= (int) $unidad['id'] ?>">
                            <?= View::escape($unidad['unidad']) ?> (<?= View::escape($unidad['simbolo']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Factor de conversión (visible si UM Compra != UM Uso) -->
            <div class="col-12 col-xl-6" x-show="form.id_um_compra && form.id_um_uso && form.id_um_compra != form.id_um_uso" x-transition>
                <label class="form-label fw-semibold mb-1 text-primary">
                    Factor de Conversión
                    <i class="fa-solid fa-circle-question" title="Cuántas unidades de USO equivale 1 unidad de COMPRA"></i>
                </label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted">1 UM Compra =</span>
                    <input type="number" step="0.000001" min="0" class="form-control"
                        x-model.number="form.factor_conversion"
                        placeholder="Ej: 10">
                    <span class="input-group-text bg-light text-muted">UM Uso</span>
                </div>
                <div class="form-text text-muted my-0" style="font-size: 0.7em;">
                    Ejemplo: Si compra Cajas de 10 Unidades, ingrese 10.
                </div>
            </div>

            <!-- Separator -->
            <div class="col-12">
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-muted text-uppercase small mb-0">
                        <i class="fa-solid fa-ruler-combined me-2"></i>Dimensiones físicas
                    </h6>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary py-0"
                        @click="calculateDimensions()"
                        style="font-size: 0.8rem;">
                        <i class="fa-solid fa-calculator me-1"></i>
                        Recalcular
                    </button>
                </div>
            </div>

            <!-- Fila 4: Dimensiones (3 por fila en escritorio) -->
            <?php foreach ($dimensionFields as $field) : ?>
                <div class="col-sm-6 col-xl-4 pm-dimension-col">
                    <label class="form-label small mb-0 text-truncate"><?= View::escape($field['label']) ?></label>
                    <div class="input-group input-group-sm pm-dimension-input-group">
                        <input
                            type="number"
                            class="form-control px-2"
                            x-model.number="form.<?= $field['key'] ?>"
                            step="0.01"
                            placeholder="0.00">
                        <select class="form-select px-1" x-model.number="form.<?= $field['unit'] ?>" style="max-width: 65px;">
                            <option value="">UM</option>
                            <?php foreach ($field['units'] as $unidad) : ?>
                                <option value="<?= (int) $unidad['id'] ?>"><?= View::escape($unidad['simbolo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Fila 5: Superficie y Volumen -->
            <div class="col-sm-6 col-xl-6 pm-dimension-col">
                <label class="form-label small mb-0">Superficie</label>
                <div class="input-group input-group-sm pm-dimension-input-group">
                    <input type="number" class="form-control px-2" x-model.number="form.superficie" step="0.0001">
                    <select class="form-select px-1" x-model.number="form.id_um_superficie" style="max-width: 65px;">
                        <option value="">UM</option>
                        <?php foreach ($unidadesSuperficie as $unidad) : ?>
                            <option value="<?= (int) $unidad['id'] ?>"><?= View::escape($unidad['simbolo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-6 col-xl-6 pm-dimension-col">
                <label class="form-label small mb-0">Volumen</label>
                <div class="input-group input-group-sm pm-dimension-input-group">
                    <input type="number" class="form-control px-2" x-model.number="form.volumen" step="0.01">
                    <select class="form-select px-1" x-model.number="form.id_um_volumen" style="max-width: 65px;">
                        <option value="">UM</option>
                        <?php foreach ($unidadesVolumen as $unidad) : ?>
                            <option value="<?= (int) $unidad['id'] ?>"><?= View::escape($unidad['simbolo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </fieldset>

    <!-- Fila 8: Botones de acción -->
    <div class="mt-3">
        <hr class="my-2">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center pm-form-actions">
            <div class="form-check form-switch mb-0 pm-active-toggle" x-show="mode !== 'view'">
                <input
                    class="form-check-input"
                    type="checkbox"
                    x-model="form.activo"
                    id="parte-activa">
                <label class="form-check-label small fw-semibold" for="parte-activa">Activa</label>
            </div>

            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <a
                    class="btn btn-sm btn-warning"
                    x-show="mode === 'view' && form.id"
                    :href="'/mrp/productos/partes/manager/' + form.id + '/editar'">
                    <i class="fa-solid fa-pen me-2"></i>
                    Habilitar Edicion
                </a>
                <button
                    type="button"
                    class="btn btn-sm btn-primary"
                    @click="resetForm()"
                    x-show="mode !== 'create'">
                    <i class="fa-solid fa-plus me-2"></i>
                    Nueva Parte
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary"
                    @click="resetForm()"
                    x-show="mode !== 'view' && isEditing">
                    <i class="fa-solid fa-times me-2"></i>
                    Cancelar
                </button>
                <button
                    type="submit"
                    class="btn btn-sm"
                    :class="isEditing ? 'btn-success' : 'btn-primary'"
                    :disabled="loading"
                    x-show="mode !== 'view'">
                    <i class="fa-solid me-2" :class="isEditing ? 'fa-save' : 'fa-plus'"></i>
                    <span x-text="isEditing ? 'Actualizar Parte' : 'Crear Parte'"></span>
                </button>
            </div>
        </div>
    </div>
</form>
