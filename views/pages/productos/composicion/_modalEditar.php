<!-- Modal Asignar Cantidad y UM de uso -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true" x-ref="modalEditar">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" :action="editActionUrl" @submit.prevent="submitAssignmentForm($event)">
                <div class="modal-header">
                    <h5 class="modal-title">Asignar cantidad y UM de uso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="id_variante" :value="getEditParentVariantId() || ''">
                    <input type="hidden" name="redirect_to" :value="getReturnUrl(getEditParentVariantId())">

                    <div class="alert alert-info py-2 mb-3">
                        <div class="small text-uppercase text-muted fw-bold" x-text="isAddAssignmentMode() ? 'Componente a agregar' : 'Componente a editar'"></div>
                        <div class="fw-semibold" x-text="getItemCode(getAssignmentItem())"></div>
                        <div class="text-muted" x-text="getItemDetail(getAssignmentItem())"></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label mb-0">Cantidad</label>
                            <input
                                x-show="!isAddAssignmentMode()"
                                type="number"
                                step="<?= esc(app_decimal_step()) ?>"
                                class="form-control"
                                name="cantidad"
                                x-model="editingItem.cantidad"
                                required>
                            <input
                                x-show="isAddAssignmentMode()"
                                type="number"
                                step="<?= esc(app_decimal_step()) ?>"
                                min="<?= esc(app_decimal_step()) ?>"
                                class="form-control"
                                x-model="pendingAddQuantity"
                                required>
                            <button
                                type="button"
                                class="btn btn-outline-info btn-sm w-100 mt-2"
                                x-show="!isAddAssignmentMode() && canAutoCalculateEditQuantity()"
                                @click="applyAutoCalculatedEditQuantity()">
                                <i class="fa-solid fa-calculator me-1"></i> Calcular por superficie
                            </button>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label mb-0">
                                UM de uso:
                                <span class="text-muted fw-normal" x-show="getAssignmentUsageUnitType()" x-text="getAssignmentUsageUnitType()"></span>
                            </label>
                            <select x-show="!isAddAssignmentMode()" class="form-select" name="id_unidad" x-model="editingItem.id_unidad" required>
                                <template x-for="unit in getAssignmentUnits()" :key="unit.id">
                                    <option :value="String(unit.id)" x-text="formatUnitLabel(unit)"></option>
                                </template>
                            </select>
                            <select x-show="isAddAssignmentMode()" class="form-select" x-model="pendingAddUnitId" required>
                                <template x-for="unit in getAssignmentUnits()" :key="unit.id">
                                    <option :value="String(unit.id)" x-text="formatUnitLabel(unit)"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="border-top mt-3 pt-2 small text-muted">
                        <div x-show="!isAddAssignmentMode() && canAutoCalculateEditQuantity()">
                            Sugerencia automatica: superficie del nodo padre
                            (<span x-text="formatQuantity(getParentSurfaceForEdit())"></span>). Igual puedes cargar cualquier cantidad manualmente.
                        </div>
                        <div x-show="getAssignmentUsageUnitType()" class="mt-1">
                            Se muestran solo unidades del tipo <span x-text="getAssignmentUsageUnitType()"></span>.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" x-show="!isAddAssignmentMode()">Guardar asignacion</button>
                    <button
                        type="button"
                        class="btn btn-success"
                        x-show="isAddAssignmentMode()"
                        :disabled="!pendingAddItem || getPendingAddUnits().length === 0"
                        @click="confirmAddSelectedComponent()">
                        <i class="fa-solid fa-check me-1"></i> Confirmar +Agregar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
