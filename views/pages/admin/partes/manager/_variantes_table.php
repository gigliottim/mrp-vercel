<div class="table-responsive">
    <table class="table table-hover table-sm align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th class="text-nowrap" style="width: 16%">Código</th>
                <th style="width: 30%">Detalle</th>
                <th class="text-nowrap" style="width: 14%">Estado</th>
                <th class="text-nowrap" style="width: 16%">Stock</th>
                <th class="text-nowrap" style="width: 12%">Peso</th>
                <th class="text-end text-nowrap" style="width: 12%">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <template x-for="(variante, index) in variantes" :key="variante.id || index">
                <tr>
                    <td>
                        <span class="badge bg-secondary text-truncate" x-text="variante.codigo_variante"></span>
                    </td>
                    <td>
                        <span class="d-inline-block text-truncate" style="max-width: 260px;" x-text="variante.detalle"></span>
                    </td>
                    <td>
                        <span
                            class="badge"
                            :class="{
                                'bg-success': variante.estado === 'activa',
                                'bg-warning': variante.estado === 'desarrollo',
                                'bg-secondary': variante.estado === 'obsoleta',
                                'bg-danger': variante.estado === 'descontinuada'
                            }"
                            x-text="getEstadoLabel(variante.estado)"></span>
                    </td>
                    <td>
                        <div class="text-nowrap">
                            <span x-text="formatNumberDisplay(variante.stock_actual, '0')"></span>
                        </div>
                    </td>
                    <td>
                        <span class="text-nowrap" x-text="formatNumberDisplay(variante.peso, '--')"></span>
                        <small class="text-muted" x-show="variante.peso !== null && variante.peso !== ''" x-text="getPesoUM(variante.id_um_peso)"></small>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm flex-nowrap" role="group">
                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                @click="editVariante(variante)"
                                :disabled="isVariantFormEnabled"
                                :title="isVariantFormEnabled ? 'Guarda o cancela el formulario variante antes de cambiar de acción' : 'Editar'"
                                title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <a :href="'<?= url('productos/maestro') ?>?id_variante=' + variante.id"
                                class="btn btn-outline-primary"
                                title="Cargar como Maestro"
                                target="_blank">
                                <i class="fa-solid fa-network-wired"></i>
                            </a>
                            <button
                                type="button"
                                class="btn btn-outline-danger"
                                @click="openDeleteVarianteModal(variante)"
                                :disabled="isVariantFormEnabled"
                                :title="isVariantFormEnabled ? 'Guarda o cancela el formulario variante antes de eliminar' : 'Eliminar'">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</div>

<!-- Modal Eliminar Variante -->
<div class="modal fade" id="modalDeleteVarianteManager" tabindex="-1" aria-hidden="true"
     x-data="{ show: false }"
     @show.bs.modal="show = true"
     @hidden.bs.modal="show = false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>Eliminar variante
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-0">
                <p x-html="deleteVarianteMessage" class="mb-0"></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" @click="confirmDeleteVariante()" :disabled="loading">
                    <span x-show="!loading"><i class="fa-solid fa-trash me-1"></i> Eliminar</span>
                    <span x-show="loading"><i class="fa-solid fa-spinner fa-spin me-1"></i> Eliminando...</span>
                </button>
            </div>
        </div>
    </div>
</div>
