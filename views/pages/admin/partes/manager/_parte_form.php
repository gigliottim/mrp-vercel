<?php

use App\Core\View\View;
?>
<form @submit.prevent="saveParte()">
    <fieldset :disabled="isPartFormReadOnly">
        <div class="pm-dlbl"><i class="fa-solid fa-circle-info"></i> General</div>
        <div class="row g-2">
            <div class="col-4"><div class="pm-fg"><label>Código <span class="req">*</span></label><input type="text" class="form-control" x-model="form.codigo" placeholder="CHAPA-01" required style="text-transform:uppercase"></div></div>
            <div class="col-4"><div class="pm-fg"><label>Tipo <span class="req">*</span></label><select class="form-select" x-model.number="form.id_tipo" required><option value="">—</option><?php foreach ($tipos as $tipo): ?><option value="<?= (int) $tipo['id'] ?>"><?= View::escape($tipo['nombre']) ?></option><?php endforeach; ?></select></div></div>
            <div class="col-4"><div class="pm-fg"><label>Grupo <span class="req">*</span></label><select class="form-select" x-model.number="form.id_grupo" required><option value="">—</option><?php foreach ($grupos as $grupo): ?><option value="<?= (int) $grupo['id'] ?>"><?= View::escape($grupo['nombre']) ?></option><?php endforeach; ?></select></div></div>
            <div class="col-12"><div class="pm-fg"><label>Detalle <span class="req">*</span></label><textarea class="form-control" x-model="form.detalle" rows="1" placeholder="Descripción de la parte" required></textarea></div></div>
        </div>
        <div class="pm-dlbl"><i class="fa-solid fa-ruler"></i> Unidades de Medida</div>
        <div class="row g-2">
            <div class="col-4"><div class="pm-fg"><label>UM Compra</label><select class="form-select" x-model.number="form.id_um_compra"><option value="">—</option><?php foreach ($unidadesTodas as $unidad): ?><option value="<?= (int) $unidad['id'] ?>"><?= View::escape($unidad['unidad']) ?> (<?= View::escape($unidad['simbolo']) ?>)</option><?php endforeach; ?></select></div></div>
            <div class="col-4"><div class="pm-fg"><label>UM Uso</label><select class="form-select" x-model.number="form.id_um_uso"><option value="">—</option><?php foreach ($unidadesTodas as $unidad): ?><option value="<?= (int) $unidad['id'] ?>"><?= View::escape($unidad['unidad']) ?> (<?= View::escape($unidad['simbolo']) ?>)</option><?php endforeach; ?></select></div></div>
            <div class="col-4" x-show="form.id_um_compra && form.id_um_uso && form.id_um_compra != form.id_um_uso" x-transition>
                <div class="pm-fg"><label class="text-primary">Factor Conv.</label>
                    <div class="pm-factor-box"><div class="input-group input-group-sm"><span class="input-group-text" style="font-size:.66rem;background:var(--pm-primary-bg);border-color:rgba(79,70,229,.15)">1 =</span><input type="number" class="form-control" :step="numberInputStep" min="0" x-model.number="form.factor_conversion" @blur="normalizeNumberInputValue($event,'form.factor_conversion')" placeholder="10"><span class="input-group-text" style="font-size:.66rem;background:var(--pm-primary-bg);border-color:rgba(79,70,229,.15)">UM Uso</span></div></div>
                </div>
            </div>
        </div>
        <div class="pm-dlbl">
            <i class="fa-solid fa-ruler-combined"></i> Dimensiones
            <button type="button" class="pm-btn pm-btn-outline ms-auto" style="padding:.08rem .35rem;font-size:.58rem;" @click.prevent="calculateDimensions()"><i class="fa-solid fa-calculator"></i></button>
        </div>
        <div class="d-flex g-2" style="gap:.4rem;flex-wrap:nowrap;">
            <?php foreach ($dimensionFields as $field): ?>
            <div style="flex:1;min-width:0"><div class="pm-fg"><label><?= View::escape($field['label']) ?></label><div class="pm-dim"><input type="number" class="form-control" x-model.number="form.<?= $field['key'] ?>" @blur="normalizeNumberInputValue($event,'form.<?= $field['key'] ?>')" :step="numberInputStep" placeholder="0"><select class="form-select" x-model.number="form.<?= $field['unit'] ?>"><option value="">UM</option><?php foreach ($field['units'] as $unidad): ?><option value="<?= (int) $unidad['id'] ?>"><?= View::escape($unidad['simbolo']) ?></option><?php endforeach; ?></select></div></div></div>
            <?php endforeach; ?>
            <div style="flex:1;min-width:0"><div class="pm-fg"><label>Superficie</label><div class="pm-dim"><input type="number" class="form-control" x-model.number="form.superficie" @blur="normalizeNumberInputValue($event,'form.superficie')" :step="numberInputStep" placeholder="0"><select class="form-select" x-model.number="form.id_um_superficie"><option value="">UM</option><?php foreach ($unidadesSuperficie as $unidad): ?><option value="<?= (int) $unidad['id'] ?>"><?= View::escape($unidad['simbolo']) ?></option><?php endforeach; ?></select></div></div></div>
            <div style="flex:1;min-width:0"><div class="pm-fg"><label>Volumen</label><div class="pm-dim"><input type="number" class="form-control" x-model.number="form.volumen" @blur="normalizeNumberInputValue($event,'form.volumen')" :step="numberInputStep" placeholder="0"><select class="form-select" x-model.number="form.id_um_volumen"><option value="">UM</option><?php foreach ($unidadesVolumen as $unidad): ?><option value="<?= (int) $unidad['id'] ?>"><?= View::escape($unidad['simbolo']) ?></option><?php endforeach; ?></select></div></div></div>
        </div>
    </fieldset>
    <div class="pm-actions">
        <div class="pm-switch" x-show="!isPartFormReadOnly"><input class="form-check-input" type="checkbox" x-model="form.activo" id="parte-activa-v2"><label for="parte-activa-v2">Activa</label></div>
        <div class="ms-auto d-flex gap-1">
            <a class="pm-btn pm-btn-warning" x-show="isPartFormReadOnly && form.id" :href="'<?= url('productos/partes/manager') ?>/' + form.id + '/editar'"><i class="fa-solid fa-pen"></i> Editar</a>
            <button type="button" class="pm-btn pm-btn-primary" @click="resetForm()" x-show="mode==='view'||(mode==='create'&&isPartFormReadOnly)"><i class="fa-solid fa-plus"></i> Nueva</button>
            <button type="button" class="pm-btn pm-btn-danger" @click="resetForm()" x-show="mode==='create'&&!isPartFormReadOnly"><i class="fa-solid fa-xmark"></i> Cancelar</button>
            <button type="submit" class="pm-btn" :class="isEditing?'pm-btn-success':'pm-btn-primary'" :disabled="loading" x-show="!isPartFormReadOnly"><i class="fa-solid" :class="isEditing?'fa-save':'fa-plus'"></i> <span x-text="isEditing?'Actualizar':'Crear'"></span></button>
        </div>
    </div>
</form>