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
                            <span x-text="variante.stock_actual || 0"></span>
                        </div>
                        <small class="text-muted text-nowrap">
                            Min: <span x-text="variante.lote_minimo || 0"></span> /
                            PP: <span x-text="variante.punto_pedido || 0"></span>
                        </small>
                    </td>
                    <td>
                        <span class="text-nowrap" x-text="variante.peso ? variante.peso : '--'"></span>
                        <small class="text-muted" x-show="variante.peso" x-text="getPesoUM(variante.id_um_peso)"></small>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm flex-nowrap" role="group">
                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                @click="editVariante(variante)"
                                :disabled="mode !== 'edit'"
                                :title="mode !== 'edit' ? 'Habilita la edición para editar variantes' : 'Editar'"
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
                                @click="deleteVariante(variante.id, index)"
                                :disabled="mode !== 'edit'"
                                :title="mode !== 'edit' ? 'Habilita la edición para eliminar variantes' : 'Eliminar'"
                                title="Eliminar">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</div>
