<?php

use App\Core\View\View;
?>
<!-- Modal Agregar -->
<div class="modal fade" id="modalAgregar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar Componentes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label d-block">Filtrar por tipo:</label>
                    <div class="btn-group btn-group-sm w-100 mb-2" role="group">
                        <button type="button"
                            class="btn btn-tipo-filter btn-outline-secondary"
                            :class="activeFilter === '' ? 'active' : ''"
                            @click="setAddModalFilter('')"
                            title="Mostrar todos los tipos">
                            <i class="fa-solid fa-border-all"></i> Todos
                        </button>
                        <?php
                        $colores = ['info', 'success', 'warning', 'danger', 'primary', 'secondary', 'dark'];
                        foreach ($tiposPartes as $index => $tipo) :
                            $color = $colores[$index % count($colores)];
                        ?>
                            <button type="button"
                                class="btn btn-tipo-filter btn-outline-<?= $color ?>"
                                :class="activeFilter === '<?= View::escape($tipo['codigo']) ?>' ? 'active' : ''"
                                @click="setAddModalFilter('<?= View::escape($tipo['codigo']) ?>')"
                                title="<?= View::escape($tipo['nombre']) ?>">
                                <strong><?= View::escape($tipo['codigo']) ?></strong>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Buscar en listado</label>
                        <input type="text"
                            class="form-control"
                            x-model.trim="addListQuery"
                            placeholder="Buscar por código o detalle..."
                            autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cantidad</label>
                        <input type="number"
                            step="<?= esc(app_decimal_step()) ?>"
                            min="<?= esc(app_decimal_step()) ?>"
                            class="form-control"
                            x-model="addQuantity">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Unidad</label>
                        <select class="form-select" x-model="addUnitId">
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= (int) $u['id'] ?>"><?= View::escape(($u['unidad'] ?? $u['nombre'] ?? '') . ' (' . $u['simbolo'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <template x-if="addModalStatus.message">
                    <div class="alert py-2"
                        :class="addModalStatus.type === 'error' ? 'alert-danger' : 'alert-success'"
                        x-text="addModalStatus.message"></div>
                </template>

                <template x-if="isValidatingCandidates">
                    <div class="alert alert-info py-2 mb-2">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Validando componentes permitidos para este nivel...
                    </div>
                </template>

                <div class="table-responsive border rounded">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 28%;">Código</th>
                                <th>Detalle</th>
                                <th style="width: 12%;" class="text-center">Tipo</th>
                                <th style="width: 16%;" class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="item in getAddModalVariants()" :key="item.id">
                                <tr>
                                    <td class="fw-semibold" x-text="[item.parte_codigo, item.codigo_variante].filter(Boolean).join('-') || 'N/A'"></td>
                                    <td x-text="[item.parte_detalle, item.detalle].filter(Boolean).join(' - ') || 'Sin detalle'"></td>
                                    <td class="text-center"><span class="badge bg-secondary" x-text="item.tipo_codigo || '-'"></span></td>
                                    <td class="text-end">
                                        <button type="button"
                                            class="btn btn-sm btn-success"
                                            :disabled="isAddingComponent(item.id) || isValidatingCandidates"
                                            @click="addComponentFromList(item)">
                                            <span x-show="!isAddingComponent(item.id)">
                                                <i class="fa-solid fa-plus"></i> Agregar
                                            </span>
                                            <span x-show="isAddingComponent(item.id)">
                                                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                            </span>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="getAddModalVariants().length === 0">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">
                                        No hay variantes disponibles con el filtro actual.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="small text-muted mt-2">
                    Puedes agregar varios componentes consecutivamente sin cerrar este modal.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-outline-primary" @click="reloadMaestroWithFocus()">
                    <i class="fa-solid fa-arrows-rotate"></i> Refrescar listado
                </button>
            </div>
        </div>
    </div>
</div>
