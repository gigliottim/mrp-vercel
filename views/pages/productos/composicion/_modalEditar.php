<!-- Modal Editar Cantidad -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true" x-ref="modalEditar">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" :action="editActionUrl">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Cantidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_method" value="PUT">
                    <input type="hidden" name="id_variante" :value="getEditParentVariantId() || ''">

                    <div class="alert alert-info py-2 mb-3">
                        <div class="small text-uppercase text-muted fw-bold">Componente a editar</div>
                        <div class="fw-semibold" x-text="getItemCode(editingItem)"></div>
                        <div class="text-muted" x-text="getItemDetail(editingItem)"></div>
                        <div class="mt-1">
                            <span class="badge bg-light text-dark border">
                                UM: <span x-text="editingItem.unidad || 'N/A'"></span>
                            </span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <label class="form-label mb-0">Cantidad</label>
                            <button
                                type="button"
                                class="btn btn-outline-info btn-sm"
                                x-show="canAutoCalculateEditQuantity()"
                                @click="applyAutoCalculatedEditQuantity()">
                                <i class="fa-solid fa-calculator me-1"></i> Calcular por superficie
                            </button>
                        </div>
                        <input type="number" step="<?= esc(app_decimal_step()) ?>" class="form-control" name="cantidad" x-model="editingItem.cantidad" required>
                        <small class="text-muted d-block mt-1" x-show="canAutoCalculateEditQuantity()">
                            Sugerencia automatica: superficie del nodo padre
                            (<span x-text="formatQuantity(getParentSurfaceForEdit())"></span>). Igual puedes cargar cualquier cantidad manualmente.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
