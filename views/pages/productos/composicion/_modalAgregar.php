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
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="small text-muted">
                            Al presionar <strong>+Agregar</strong> se abrirá un modal para definir cantidad y UM de uso.
                        </div>
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
                                            @click="openAddQuantityModal(item)">
                                            <span x-show="!isAddingComponent(item.id)">
                                                <i class="fa-solid fa-plus"></i> +Agregar
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

<!-- Modal Asignar Cantidad y UM al agregar -->
<div class="modal fade" id="modalAgregarCantidad" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Asignar cantidad y UM de uso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 mb-3" x-show="pendingAddItem">
                    <div class="small text-uppercase text-muted fw-bold">Componente a agregar</div>
                    <div class="fw-semibold" x-text="getItemCode(pendingAddItem)"></div>
                    <div class="text-muted" x-text="getItemDetail(pendingAddItem)"></div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cantidad</label>
                        <input type="number"
                            step="<?= esc(app_decimal_step()) ?>"
                            min="<?= esc(app_decimal_step()) ?>"
                            class="form-control"
                            x-model="pendingAddQuantity"
                            required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label mb-0">
                            UM de uso:
                            <span class="text-muted fw-normal" x-show="pendingAddUnitType" x-text="pendingAddUnitType"></span>
                        </label>
                        <select class="form-select" x-model="pendingAddUnitId" required>
                            <template x-for="unit in getPendingAddUnits()" :key="unit.id">
                                <option :value="String(unit.id)" x-text="formatUnitLabel(unit)"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div class="border-top mt-3 pt-2 small text-muted">
                    <div x-show="pendingAddUnitType">
                        Se muestran solo unidades del tipo <span x-text="pendingAddUnitType"></span>.
                    </div>
                </div>

                <div class="alert alert-warning py-2 mt-3 mb-0" x-show="getPendingAddUnits().length === 0">
                    No hay unidades disponibles para el tipo requerido. Revisa el catálogo de unidades.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button"
                    class="btn btn-success"
                    :disabled="!pendingAddItem || getPendingAddUnits().length === 0"
                    @click="confirmAddSelectedComponent()">
                    <i class="fa-solid fa-check me-1"></i> Confirmar +Agregar
                </button>
            </div>
        </div>
    </div>
</div>
