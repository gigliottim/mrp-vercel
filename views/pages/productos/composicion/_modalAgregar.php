<?php

use App\Core\View\View;
?>
<!-- Modal Agregar -->
<div class="modal fade" id="modalAgregar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" :action="addActionUrl">
                <input type="hidden" name="id_variante" :value="addItemParentId">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Componente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label d-block">Filtrar por tipo:</label>
                        <div class="btn-group btn-group-sm w-100 mb-2" role="group" id="modal-tipo-filters">
                            <button type="button"
                                class="btn btn-tipo-filter btn-outline-secondary active"
                                data-tipo=""
                                onclick="updateModalSearchFilter('')"
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
                                    data-tipo="<?= View::escape($tipo['codigo']) ?>"
                                    onclick="updateModalSearchFilter('<?= View::escape($tipo['codigo']) ?>')"
                                    title="<?= View::escape($tipo['nombre']) ?>">
                                    <strong><?= View::escape($tipo['codigo']) ?></strong>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <label class="form-label">Material / Componente</label>

                        <!-- Campo de búsqueda con SearchClient -->
                        <div class="position-relative">
                            <input type="text"
                                id="modal-search-input"
                                class="form-control"
                                placeholder="Buscar por código o detalle..."
                                autocomplete="off">
                            <input type="hidden" name="id_material" id="modal-id-material" required>

                            <!-- Resultados de búsqueda (SearchClient) -->
                            <div id="modal-search-results" class="search-results" style="position: absolute; z-index: 1050; max-height: 300px; overflow-y: auto; display: none;"></div>

                            <small class="text-muted d-block mt-1">Escribe al menos 2 caracteres para buscar</small>

                            <!-- Mostrar selección actual -->
                            <div id="modal-selected-display" class="alert alert-success mt-2" style="display: none;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong id="modal-selected-codigo"></strong>
                                        <br><small class="text-muted" id="modal-selected-detalle"></small>
                                    </div>
                                    <button type="button" class="btn-close btn-sm" onclick="clearModalSelection()"></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cantidad</label>
                            <input type="number" step="0.01" class="form-control" name="cantidad" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unidad</label>
                            <select class="form-select" name="id_unidad" required>
                                <?php foreach ($unidades as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= View::escape(($u['unidad'] ?? $u['nombre'] ?? '') . ' (' . $u['simbolo'] . ')') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Agregar</button>
                </div>
            </form>
        </div>
    </div>
</div>
