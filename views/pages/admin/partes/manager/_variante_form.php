<?php

use App\Core\View\View;

$variantStates = [
    'activa' => 'Activa',
    'desarrollo' => 'En desarrollo',
    'obsoleta' => 'Obsoleta',
    'descontinuada' => 'Descontinuada',
];
?>
<form @submit.prevent="saveVariante()" class="vstack gap-2">
    <fieldset :disabled="mode === 'view'">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <h6 class="mb-0 small d-flex flex-wrap align-items-center gap-2">
                <i class="fa-solid" :class="variantForm.id ? 'fa-edit' : 'fa-plus-circle'" class="me-1"></i>
                <span class="fw-semibold">Variante</span>
                <span x-show="variantForm.id" class="text-muted">ID: <span x-text="variantForm.id"></span></span>
                <span x-show="!variantForm.id" class="badge bg-info">Nueva</span>
                <span x-show="mode === 'view'" class="badge bg-secondary">Solo lectura</span>
                <span x-show="mode === 'edit'" class="badge bg-success">Editando</span>
            </h6>
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                @click="cancelEditVariante()"
                x-show="variantForm.id">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>

        <div class="row g-2 align-items-end">
            <!-- Fila 1: Datos Básicos -->
            <div class="col-sm-4 col-xl-3">
                <label class="form-label small fw-semibold mb-0">Código <span class="text-danger">*</span></label>
                <input
                    type="text"
                    class="form-control form-control-sm"
                    x-model="variantForm.codigo_variante"
                    placeholder=""
                    required
                    style="text-transform: uppercase">
            </div>
            <div class="col-sm-8 col-xl-9">
                <label class="form-label small fw-semibold mb-0">Detalle <span class="text-danger">*</span></label>
                <textarea
                    class="form-control form-control-sm"
                    x-model="variantForm.detalle"
                    rows="2"
                    required></textarea>
            </div>

            <!-- Fila 2: Gestión de Inventario (compacto 2x3 o 3x2) -->
            <div class="col-sm-6 col-xl-3">
                <label class="form-label small fw-semibold mb-0">Status</label>
                <select class="form-select form-select-sm" x-model="variantForm.estado">
                    <?php foreach ($variantStates as $key => $label) : ?>
                        <option value="<?= $key ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-xl-3">
                <label class="form-label small fw-semibold mb-0 text-truncate">Lote Mín.</label>
                <div class="input-group input-group-sm">
                    <input
                        type="number"
                        class="form-control"
                        x-model.number="variantForm.lote_minimo"
                        step="0.01"
                        min="0">
                    <span class="input-group-text px-2" x-text="getUmUsoSimbolo()" style="font-size: 0.8rem; min-width: 40px; justify-content: center;"></span>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <label class="form-label small fw-semibold mb-0 text-truncate">Pedir al</label>
                <div class="input-group input-group-sm">
                    <input
                        type="number"
                        class="form-control"
                        x-model.number="variantForm.punto_pedido"
                        step="0.01"
                        min="0">
                    <span class="input-group-text px-2" x-text="getUmUsoSimbolo()" style="font-size: 0.8rem; min-width: 40px; justify-content: center;"></span>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <label class="form-label small fw-semibold mb-0">Stock (Calc)</label>
                <div class="input-group input-group-sm">
                    <input
                        type="text"
                        class="form-control bg-light"
                        x-model="variantForm.stock_actual"
                        readonly
                        disabled>
                    <span class="input-group-text px-2 bg-light" x-text="getUmUsoSimbolo()" style="font-size: 0.8rem; min-width: 40px; justify-content: center;"></span>
                </div>
            </div>

            <!-- Fila 3 y 4: Peso y Ubicación -->
            <div class="col-sm-6 col-xl-4">
                <label class="form-label small fw-semibold mb-0">Peso Unit.</label>
                <div class="input-group input-group-sm">
                    <input
                        type="number"
                        class="form-control px-2"
                        x-model.number="variantForm.peso"
                        step="0.001"
                        placeholder="0.000">
                    <select class="form-select px-1" x-model="variantForm.id_um_peso" style="max-width: 65px;">
                        <option value="">UM</option>
                        <?php foreach ($unidadesMasa as $unidad) : ?>
                            <option value="<?= (int) $unidad['id'] ?>"><?= View::escape($unidad['simbolo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="col-sm-6 col-xl-8">
                <label class="form-label small fw-semibold mb-0">Ubicación Física</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text px-2" style="font-size: 0.8rem;">Cuerpo</span>
                    <input type="text" class="form-control px-2" x-model="variantForm.ubicacion_cuerpo" placeholder="Ej: A">

                    <span class="input-group-text px-2 border-start-0" style="font-size: 0.8rem;">Pasillo</span>
                    <input type="text" class="form-control px-2" x-model="variantForm.ubicacion_pasillo" placeholder="Ej: 01">

                    <span class="input-group-text px-2 border-start-0" style="font-size: 0.8rem;">Estante</span>
                    <input type="text" class="form-control px-2" x-model="variantForm.ubicacion_estante" placeholder="Ej: 3">
                </div>
            </div>
        </div>
    </fieldset>

    <!-- Botones -->
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mt-1 pm-variant-actions">
        <div class="form-check form-switch mb-0 pm-active-toggle" x-show="mode === 'edit'">
            <input
                class="form-check-input"
                type="checkbox"
                id="variante-activa"
                :checked="variantForm.estado === 'activa'"
                @change="variantForm.estado = $event.target.checked ? 'activa' : 'descontinuada'">
            <label class="form-check-label small fw-semibold" for="variante-activa">Activa</label>
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
                type="submit"
                class="btn btn-sm"
                :class="variantForm.id ? 'btn-success' : 'btn-primary'"
                :disabled="loading"
                x-show="mode !== 'view'">
                <i class="fa-solid fa-check me-2"></i>
                <span x-text="variantForm.id ? 'Actualizar' : 'Agregar'"></span>
            </button>
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                @click="cancelEditVariante()"
                x-show="mode !== 'view' && variantForm.id">
                <i class="fa-solid fa-times me-2"></i>
                Cancelar
            </button>
        </div>
    </div>
</form>
