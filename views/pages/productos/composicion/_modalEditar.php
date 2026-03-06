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
                    <input type="hidden" name="id_variante" value="<?= $selectedVarianteId ?>">

                    <div class="mb-3">
                        <label class="form-label">Cantidad</label>
                        <input type="number" step="0.01" class="form-control" name="cantidad" x-model="editingItem.cantidad" required>
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
