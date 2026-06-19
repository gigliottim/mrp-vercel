<?php

use App\Core\View\View;

$variantStates = [
    'activa' => 'Activa',
    'desarrollo' => 'Desarrollo',
    'obsoleta' => 'Obsoleta',
    'descontinuada' => 'Descontinuada',
];
?>
<form @submit.prevent="saveVariante()">
    <fieldset :disabled="!isVariantFormEnabled">
        <div class="pm-dlbl"><i class="fa-solid fa-hashtag"></i> Datos Básicos</div>
        <div class="row g-2">
            <div class="col-3"><div class="pm-fg"><label>Código <span class="req">*</span></label><input type="text" class="form-control" x-ref="variantCodigo" x-model="variantForm.codigo_variante" placeholder="V01" required style="text-transform:uppercase"></div></div>
            <div class="col-9"><div class="pm-fg"><label>Detalle <span class="req">*</span></label><textarea class="form-control" x-ref="variantDetalle" x-model="variantForm.detalle" rows="1" @input="adjustVariantDetalleHeight()" required></textarea></div></div>
        </div>
        <div class="pm-dlbl"><i class="fa-solid fa-boxes-stacked"></i> Inventario</div>
        <div class="row g-2">
            <div class="col-3"><div class="pm-fg"><label>Estado</label><select class="form-select" x-model="variantForm.estado"><?php foreach ($variantStates as $key => $label): ?><option value="<?= $key ?>"><?= $label ?></option><?php endforeach; ?></select></div></div>
            <div class="col-3"><div class="pm-fg"><label>Lote Mín.</label><div class="pm-dim"><input type="number" class="form-control" x-model.number="variantForm.lote_minimo" @blur="normalizeNumberInputValue($event,'variantForm.lote_minimo')" :step="numberInputStep" min="0"><span class="input-group-text" x-text="getUmUsoSimbolo()" style="width:38px;justify-content:center;font-size:.62rem"></span></div></div></div>
            <div class="col-3"><div class="pm-fg"><label>Pedir al</label><div class="pm-dim"><input type="number" class="form-control" x-model.number="variantForm.punto_pedido" @blur="normalizeNumberInputValue($event,'variantForm.punto_pedido')" :step="numberInputStep" min="0"><span class="input-group-text" x-text="getUmUsoSimbolo()" style="width:38px;justify-content:center;font-size:.62rem"></span></div></div></div>
            <div class="col-3"><div class="pm-fg"><label>Stock (Calc)</label><input type="text" class="form-control" :value="formatNumberForInput(variantForm.stock_actual)" readonly disabled style="background:#f1f5f9;color:#64748b"></div></div>
        </div>
        <div class="pm-dlbl"><i class="fa-solid fa-weight-hanging"></i> Peso y Ubicación</div>
        <div class="row g-2">
            <div class="col-4"><div class="pm-fg"><label>Peso Unit.</label><div class="pm-dim"><input type="number" class="form-control" x-model.number="variantForm.peso" @blur="normalizeNumberInputValue($event,'variantForm.peso')" :step="numberInputStep" placeholder="0"><select class="form-select" x-model="variantForm.id_um_peso"><option value="">UM</option><?php foreach ($unidadesMasa as $unidad): ?><option value="<?= (int) $unidad['id'] ?>"><?= View::escape($unidad['simbolo']) ?></option><?php endforeach; ?></select></div></div></div>
            <div class="col-8"><div class="pm-fg"><label>Ubicación</label><div class="d-flex gap-1"><div class="pm-dim flex-fill"><span class="input-group-text" style="font-size:.58rem">C</span><input type="text" class="form-control" x-model="variantForm.ubicacion_cuerpo" placeholder="A"></div><div class="pm-dim flex-fill"><span class="input-group-text" style="font-size:.58rem">P</span><input type="text" class="form-control" x-model="variantForm.ubicacion_pasillo" placeholder="01"></div><div class="pm-dim flex-fill"><span class="input-group-text" style="font-size:.58rem">E</span><input type="text" class="form-control" x-model="variantForm.ubicacion_estante" placeholder="3"></div></div></div></div>
        </div>
    </fieldset>
    <div class="pm-actions" style="background:transparent;border:none;padding:.25rem 0">
        <div class="pm-switch" x-show="isVariantFormEnabled"><input class="form-check-input" type="checkbox" id="variante-activa-v3" :checked="variantForm.estado==='activa'" @change="variantForm.estado=$event.target.checked?'activa':'descontinuada'"><label for="variante-activa-v3">Activa</label></div>
        <div class="ms-auto d-flex gap-1">
            <button type="button" class="pm-btn pm-btn-primary" @click="enableNewVariante()" x-show="form.id&&!isVariantFormEnabled"><i class="fa-solid fa-plus"></i> Nueva</button>
            <a class="pm-btn pm-btn-warning" x-show="mode==='view'&&form.id&&variantForm&&variantForm.id&&!isVariantFormEnabled" :href="'<?= url('productos/partes/manager') ?>/' + form.id + '/variantes/' + variantForm.id + '/editar'"><i class="fa-solid fa-pen"></i> Editar</a>
            <button type="submit" class="pm-btn" :class="variantForm.id?'pm-btn-success':'pm-btn-primary'" :disabled="loading" x-show="isVariantFormEnabled"><i class="fa-solid fa-check"></i> <span x-text="variantForm.id?'Actualizar':'Agregar'"></span></button>
            <button type="button" class="pm-btn pm-btn-outline" @click="cancelEditVariante()" x-show="isVariantFormEnabled"><i class="fa-solid fa-xmark"></i></button>
        </div>
    </div>
</form>