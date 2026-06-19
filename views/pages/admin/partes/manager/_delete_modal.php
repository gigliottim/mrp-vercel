<div class="modal fade" id="modalDeleteVarianteManager" tabindex="-1" aria-hidden="true" x-data="{show:false}" @show.bs.modal="show=true" @hidden.bs.modal="show=false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="border-radius:var(--pm-radius);border:none;box-shadow:0 25px 50px -12px rgba(0,0,0,.25)">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title" style="color:var(--pm-danger);font-weight:700;font-size:.85rem"><i class="fa-solid fa-triangle-exclamation me-2"></i>Eliminar variante</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0">
                <p x-html="deleteVarianteMessage" class="mb-0" style="font-size:.82rem"></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="pm-btn pm-btn-outline" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="pm-btn" style="background:var(--pm-danger);color:#fff" @click="confirmDeleteVariante()" :disabled="loading">
                    <span x-show="!loading"><i class="fa-solid fa-trash me-1"></i>Eliminar</span>
                    <span x-show="loading"><i class="fa-solid fa-spinner fa-spin me-1"></i>Eliminando...</span>
                </button>
            </div>
        </div>
    </div>
</div>