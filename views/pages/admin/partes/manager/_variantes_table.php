<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 18%">Código</th>
                <th style="width: 30%">Detalle</th>
                <th style="width: 15%">Estado</th>
                <th style="width: 15%">Stock</th>
                <th style="width: 12%">Peso</th>
                <th style="width: 10%" class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <template x-for="(variante, index) in variantes" :key="variante.id || index">
                <tr>
                    <td>
                        <span class="badge bg-secondary" x-text="variante.codigo_variante"></span>
                    </td>
                    <td>
                        <span x-text="variante.detalle"></span>
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
                        <div>
                            <span x-text="variante.stock_actual || 0"></span>
                        </div>
                        <small class="text-muted">
                            Min: <span x-text="variante.lote_minimo || 0"></span> /
                            PP: <span x-text="variante.punto_pedido || 0"></span>
                        </small>
                    </td>
                    <td>
                        <span x-text="variante.peso ? variante.peso : '--'"></span>
                        <small class="text-muted" x-show="variante.peso" x-text="getPesoUM(variante.id_um_peso)"></small>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm" role="group">
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
