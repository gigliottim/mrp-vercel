<!-- Modal Reemplazar -->
<div class="modal fade" id="modalReemplazar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" :action="replaceActionUrl">
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="action" value="replace_variant">
                <input type="hidden" name="id_variante" :value="getEditParentVariantId(replacementItem) || ''">
                <input type="hidden" name="redirect_to" :value="getReturnUrl(getEditParentVariantId(replacementItem))">
                <div class="modal-header">
                    <h5 class="modal-title">Reemplazar Variante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Reemplazar <strong><span x-text="replacementItem.variante_detalle"></span></strong> con:</p>
                    <div class="mb-3">
                        <select class="form-select" name="new_variante_id" required>
                            <template x-for="v in availableReplacements" :key="v.id">
                                <option :value="v.id" x-text="v.codigo_variante + ' - ' + v.detalle"></option>
                            </template>
                        </select>
                    </div>
                    <div class="alert alert-warning" x-show="availableReplacements.length === 0">
                        No hay otras variantes disponibles para esta parte.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" :disabled="availableReplacements.length === 0">Reemplazar</button>
                </div>
            </form>
        </div>
    </div>
</div>
