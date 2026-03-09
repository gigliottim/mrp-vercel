<?php

use App\Core\View\View;

$tiposDeposito = $tiposDeposito ?? [];
$destinosPorOrigen = $destinosPorOrigen ?? [];
$unidadesMedida = $unidadesMedida ?? [];

?>
<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Transacciones</p>
            <h1 class="h3 mb-0">Movimientos de Partes</h1>
            <p class="text-muted small mb-0">Registre movimientos de partes entre depósitos</p>
        </div>
    </div>
</section>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Nuevo Movimiento</h5>
            </div>
            <div class="card-body">
                <form id="formMovimiento" method="POST" action="<?= url('transacciones/movimientos-partes') ?>">
                    <!-- Fila 1: Fecha/Hora y Depósitos -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="fecha_hora" class="form-label">Fecha/Hora</label>
                            <div class="input-group">
                                <input type="datetime-local"
                                    class="form-control"
                                    id="fecha_hora"
                                    name="fecha_hora"
                                    value="<?= date('Y-m-d\TH:i') ?>"
                                    required>
                                <button class="btn btn-primary" type="button">
                                    <i class="fa-solid fa-calendar"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="deposito_origen" class="form-label">Depósito Origen</label>
                            <select class="form-select"
                                id="deposito_origen"
                                name="deposito_origen"
                                required>
                                <option value="">Seleccionar depósito origen</option>
                                <?php foreach ($tiposDeposito as $tipo) : ?>
                                    <option value="<?= View::escape($tipo['id']) ?>"
                                        data-codigo="<?= View::escape($tipo['codigo']) ?>">
                                        <?= View::escape($tipo['codigo']) ?> - <?= View::escape($tipo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="deposito_destino" class="form-label">Depósito Destino</label>
                            <select class="form-select"
                                id="deposito_destino"
                                name="deposito_destino"
                                required
                                disabled>
                                <option value="">Primero seleccione origen</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 2: Búsqueda de Parte -->
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label for="search-parte-input" class="form-label">
                                <i class="fa-solid fa-magnifying-glass me-1"></i>
                                Buscar Parte
                            </label>
                            <div class="position-relative">
                                <div class="input-group">
                                    <input type="text"
                                        class="form-control form-control-lg"
                                        id="search-parte-input"
                                        placeholder="Escriba al menos 2 caracteres para buscar parte o variante..."
                                        autocomplete="off">
                                    <button type="button" class="btn btn-outline-primary d-none" id="btn-cambiar-parte">
                                        <i class="fa-solid fa-rotate me-1"></i> Cambiar
                                    </button>
                                </div>
                                <div id="search-parte-results" class="search-results list-group mt-2"></div>
                            </div>
                            <small class="text-muted d-block mt-1">
                                <i class="fa-solid fa-info-circle me-1"></i>
                                Los resultados incluyen partes y variantes. Seleccione una para continuar.
                            </small>
                            <!-- Campo oculto para validación -->
                            <input type="hidden" id="parte" name="parte" required>
                        </div>
                    </div>

                    <!-- Fila 3: Orden No. y Venta No. -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-3 field-std">
                            <label for="orden_no" class="form-label">Orden No.</label>
                            <input type="text"
                                class="form-control"
                                id="orden_no"
                                name="orden_no"
                                placeholder="N/A">
                        </div>
                        <div class="col-md-3 field-std">
                            <label for="venta_no" class="form-label">Venta No.</label>
                            <input type="text"
                                class="form-control"
                                id="venta_no"
                                name="venta_no"
                                placeholder="N/A">
                        </div>
                        <div class="col-md-3">
                            <label for="cbte" class="form-label">Cbte</label>
                            <input type="text"
                                class="form-control"
                                id="cbte"
                                name="cbte">
                        </div>
                        <div class="col-md-3">
                            <label for="proveedor_cliente" class="form-label">Proveedor/Cliente</label>
                            <select class="form-select" id="proveedor_cliente" name="proveedor_cliente">
                                <option value="">Seleccionar</option>
                                <?php if (isset($entidades)): ?>
                                    <?php foreach ($entidades as $entidad): ?>
                                        <option value="<?= View::escape($entidad['id']) ?>">
                                            <?= View::escape($entidad['razon_social']) ?> (<?= View::escape($entidad['tipo']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 4: Cantidad, UM e Importe -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="cantidad" class="form-label">Cantidad</label>
                            <input type="number"
                                class="form-control"
                                id="cantidad"
                                name="cantidad"
                                step="any"
                                min="0.0001"
                                required>
                        </div>
                        <div class="col-md-4">
                            <label for="um" class="form-label">UM <span class="text-muted small" id="um_tipo_label"></span></label>
                            <select class="form-select" id="um" name="um" required disabled>
                                <option value="">Seleccione una parte primero</option>
                            </select>
                            <input type="hidden" id="um_compra_id" value="">
                            <input type="hidden" id="um_uso_id" value="">
                        </div>
                        <!-- Campos específicos para Compras (inicialmente ocultos) -->
                        <div class="col-md-4 field-compra d-none">
                            <label for="precio_unitario" class="form-label">Precio Unitario</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number"
                                    class="form-control"
                                    id="precio_unitario"
                                    name="precio_unitario"
                                    step="0.01"
                                    min="0">
                            </div>
                        </div>
                        <div class="col-md-4 field-compra d-none">
                            <label for="moneda" class="form-label">Moneda</label>
                            <select class="form-select" id="moneda" name="moneda">
                                <option value="USD">USD - Dólar Estadounidense</option>
                                <option value="ARS">ARS - Peso Argentino</option>
                                <option value="EUR">EUR - Euro</option>
                            </select>
                        </div>
                        <div class="col-md-4 field-compra d-none">
                            <label for="cotizacion" class="form-label">Cotización</label>
                            <input type="number" class="form-control" id="cotizacion" name="cotizacion" value="1" step="0.01">
                        </div>
                        <!-- Fin Campos Compras -->

                        <div class="col-md-4">
                            <label for="importe_total" class="form-label">Importe Total $</label>
                            <input type="number"
                                class="form-control"
                                id="importe_total"
                                name="importe_total"
                                step="0.01">
                        </div>
                    </div>

                    <!-- Panel de Información Calculada (Compras) -->
                    <div id="panel-calculos-compra" class="card bg-light mb-3 d-none field-compra">
                        <div class="card-body">
                            <h6 class="card-title text-primary"><i class="fas fa-calculator me-2"></i>Información Calculada</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Cantidad (UM Compra)</small>
                                    <strong id="calc-qty-compra">-</strong>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Factor Conversión</small>
                                    <strong id="calc-factor">-</strong>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Cantidad (UM Uso)</small>
                                    <strong id="calc-qty-uso" class="text-success">-</strong>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Costo Unitario (Base)</small>
                                    <strong id="calc-costo-base">-</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fila 5: Observaciones -->
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control"
                                id="observaciones"
                                name="observaciones"
                                rows="3"></textarea>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="btnCancelar">
                            <i class="fa-solid fa-xmark me-1"></i>
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fa-solid fa-check me-1"></i>
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de movimientos registrados -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Últimos 10 Movimientos Registrados</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Parte</th>
                                <th>Orden No.</th>
                                <th>Venta No.</th>
                                <th>De</th>
                                <th>A</th>
                                <th class="text-end">Cantidad</th>
                                <th>UM</th>
                                <th class="text-end">Importe</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($movimientos)): ?>
                                <?php foreach ($movimientos as $mov): ?>
                                    <tr data-origen-id="<?= View::escape($mov['id_tipo_deposito_origen']) ?>"
                                        data-destino-id="<?= View::escape($mov['id_tipo_deposito_destino']) ?>">
                                        <td><?= View::escape(app_format_datetime($mov['fecha'])) ?></td>
                                        <td>
                                            <div class="fw-bold"><?= View::escape($mov['parte_codigo']) ?></div>
                                            <small class="text-muted"><?= View::escape($mov['codigo_variante']) ?></small>
                                        </td>
                                        <td><?= View::escape($mov['referencia_id']) ?></td> <!-- Orden No / Ref -->
                                        <td>-</td> <!-- Venta No -->
                                        <td>
                                            <span class="badge bg-secondary"><?= View::escape($mov['origen_codigo']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?= View::escape($mov['destino_codigo']) ?></span>
                                        </td>
                                        <td class="text-end fw-bold">
                                            <?= View::escape(app_format_number((float) $mov['cantidad'])) ?>
                                        </td>
                                        <td>-</td> <!-- UM -->
                                        <td class="text-end">-</td> <!-- Importe -->
                                        <td>
                                            <!-- Acciones -->
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        No hay movimientos registrados
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Estilos para SearchClient inline */
    #search-parte-results {
        position: absolute;
        z-index: 1050;
        width: 100%;
        max-height: 400px;
        overflow-y: auto;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        display: none;
    }

    #search-parte-results.show {
        display: block;
    }

    #search-parte-results .list-group-item {
        cursor: pointer;
        border: none;
        border-bottom: 1px solid #f0f0f0;
        transition: background-color 0.15s ease;
    }

    #search-parte-results .list-group-item:hover,
    #search-parte-results .list-group-item.active {
        background-color: #f8f9fa;
    }

    #search-parte-results .list-group-item:last-child {
        border-bottom: none;
    }

    /* Indicador de parte seleccionada */
    #search-parte-input.parte-seleccionada {
        background-color: #d1e7dd;
        border-color: #198754;
    }
</style>

<?php

use App\Core\Support\AssetHelper;
?>
<script src="<?= AssetHelper::js('modules/SearchClient.js') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Datos de configuración desde PHP
        const tiposDeposito = <?= json_encode($tiposDeposito) ?>;
        const destinosPorOrigen = <?= json_encode($destinosPorOrigen) ?>;
        const unidadesMedida = <?= json_encode($unidadesMedida) ?>;

        // Elementos del DOM
        const selectOrigen = document.getElementById('deposito_origen');
        const selectDestino = document.getElementById('deposito_destino');
        const selectUM = document.getElementById('um');
        const umTipoLabel = document.getElementById('um_tipo_label');
        const btnCancelar = document.getElementById('btnCancelar');
        const form = document.getElementById('formMovimiento');
        const inputParteHidden = document.getElementById('parte');
        const btnCambiarParte = document.getElementById('btn-cambiar-parte');

        // Elementos adicionales para Compras
        const fieldsCompra = document.querySelectorAll('.field-compra');
        const fieldsStd = document.querySelectorAll('.field-std');
        const inputPrecio = document.getElementById('precio_unitario');
        const inputMoneda = document.getElementById('moneda');
        const inputCotizacion = document.getElementById('cotizacion');
        const panelCalculos = document.getElementById('panel-calculos-compra');
        const inputCantidad = document.getElementById('cantidad');
        const inputImporteTotal = document.getElementById('importe_total');

        // Tabla de movimientos
        const movimientosTableBody = document.querySelector('table tbody');
        const movimientosRows = movimientosTableBody ? movimientosTableBody.querySelectorAll('tr') : [];

        // Estado de la parte seleccionada
        let parteSeleccionada = null;

        function buildSearchSelectionLabel(item) {
            const parteCodigo = item.parte_codigo || 'N/A';
            const parteDetalle = item.parte_detalle || 'Sin detalle';
            const varianteCodigo = item.codigo_variante || 'N/A';
            const varianteDetalle = item.detalle || item.variante_detalle || 'Sin detalle';

            return `Parte: ${parteCodigo} - ${parteDetalle} | Variante: ${varianteCodigo} - ${varianteDetalle}`;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function setSearchLockedState(isLocked) {
            searchInput.readOnly = isLocked;
            searchInput.disabled = isLocked;
            searchInput.setAttribute('aria-readonly', isLocked ? 'true' : 'false');
            searchInput.setAttribute('aria-disabled', isLocked ? 'true' : 'false');

            if (isLocked) {
                searchInput.blur();
                searchInput.classList.add('parte-seleccionada');
                searchResults.style.display = 'none';
                if (btnCambiarParte) btnCambiarParte.classList.remove('d-none');
            } else {
                searchInput.classList.remove('parte-seleccionada');
                if (btnCambiarParte) btnCambiarParte.classList.add('d-none');
            }
        }

        // Mapa de tipos de depósito para determinar si es compra o uso
        const depositosCompra = ['PROVEEDOR', 'PROVEED'];
        const depositosCliente = ['CLIENTE', 'VENTA', 'PT'];

        // Función para verificar si es una compra (Origen = Proveedor)
        function esCompra() {
            const origenId = parseInt(selectOrigen.value);
            if (!origenId) return false;

            const origenOption = selectOrigen.options[selectOrigen.selectedIndex];
            const codigo = origenOption.dataset.codigo || '';

            return depositosCompra.some(c => codigo.includes(c));
        }

        // Función para filtrar la tabla de movimientos
        function filtrarTabla() {
            const origenId = selectOrigen.value;
            const destinoId = selectDestino.value;

            if (!movimientosRows.length) return;

            // Si no hay filtros, mostrar todo
            if (!origenId && !destinoId) {
                movimientosRows.forEach(row => row.style.display = '');
                return;
            }

            movimientosRows.forEach(row => {
                // Si es fila de "no hay datos", ignorar
                if (row.cells.length === 1) return;

                const rowOrigenId = row.dataset.origenId;
                const rowDestinoId = row.dataset.destinoId;

                let mostrar = true;

                // Filtrar por origen si está seleccionado
                if (origenId && rowOrigenId !== origenId) {
                    mostrar = false;
                }

                // Filtrar por destino si está seleccionado
                if (destinoId && rowDestinoId !== destinoId) {
                    mostrar = false;
                }

                row.style.display = mostrar ? '' : 'none';
            });
        }

        // Función para mostrar/ocultar campos de compra
        function toggleCamposCompra() {
            const isPurchase = esCompra();

            // Toggle campos de compra
            fieldsCompra.forEach(field => {
                if (isPurchase) {
                    field.classList.remove('d-none');
                } else {
                    field.classList.add('d-none');
                }
            });

            // Toggle campos estándar (Orden No, Venta No)
            fieldsStd.forEach(field => {
                if (isPurchase) {
                    field.classList.add('d-none');
                } else {
                    field.classList.remove('d-none');
                }
            });

            if (isPurchase) {
                inputPrecio.required = true;
                actualizarCalculosCompra();
            } else {
                inputPrecio.required = false;
                inputPrecio.value = '';
                // Limpiar otros campos compra si se desea
            }
        }

        // Función para actualizar cálculos de compra
        function actualizarCalculosCompra() {
            if (!esCompra() || !parteSeleccionada) return;

            const cantidad = parseFloat(inputCantidad.value) || 0;
            const precio = parseFloat(inputPrecio.value) || 0;
            const cotizacion = parseFloat(inputCotizacion.value) || 1;

            // Calcular importe total si no hay override manual (opcional)
            // Aquí asumimos que Precio * Cantidad * Cotización = Total
            const total = cantidad * precio * cotizacion;
            if (precio > 0) {
                inputImporteTotal.value = window.appFormatNumber(total);
            }

            // Actualizar panel informativo
            document.getElementById('calc-qty-compra').textContent = window.appFormatNumber(cantidad) + ' ' + (selectUM.options[selectUM.selectedIndex]?.text || '');

            // Factor de conversión (simulado o real si viniera de API)
            // NOTA: Si la API 'SearchClient' devuelve factor_conversion, usarlo.
            // Por ahora usaremos 1 si son iguales, o placeholder.
            const factor = parteSeleccionada.factor_conversion || 1;
            document.getElementById('calc-factor').textContent = factor;

            const qtyUso = cantidad * factor;
            document.getElementById('calc-qty-uso').textContent = window.appFormatNumber(qtyUso) + ' (Estimado)';

            const costoBase = (precio * cotizacion) / factor;
            document.getElementById('calc-costo-base').textContent = '$ ' + window.appFormatNumber(costoBase);
        }

        // Listeners para cálculos
        [inputCantidad, inputPrecio, inputCotizacion, inputMoneda].forEach(input => {
            if (input) input.addEventListener('input', actualizarCalculosCompra);
        });

        // Inicializar SearchClient para búsqueda de partes inline
        const searchInput = document.getElementById('search-parte-input');
        const searchResults = document.getElementById('search-parte-results');

        if (searchInput && searchResults) {
            const searchClientInstance = new SearchClient({
                endpoint: '<?= url('api/v1/search/variantes') ?>',
                inputElement: searchInput,
                resultsContainer: searchResults,
                minChars: 2,
                debounceDelay: 300,
                maxResults: 20,
                format: 'detailed',
                // Usar renderizado por defecto actualizado en SearchClient.js
                onSelect: (item) => {
                    const selectedLabel = buildSearchSelectionLabel(item);

                    // Guardar la parte seleccionada
                    parteSeleccionada = {
                        id: item.id,
                        id_parte: item.id_parte,
                        codigo: item.codigo_variante || item.parte_codigo,
                        detalle: item.detalle || item.variante_detalle,
                        selectedLabel: selectedLabel,
                        id_um_compra: item.id_um_compra,
                        id_um_uso: item.id_um_uso,
                        factor_conversion: item.factor_conversion || 1 // Asumimos 1 si no viene
                    };

                    // Actualizar el input visible con el texto de la parte
                    searchInput.value = selectedLabel;
                    setSearchLockedState(true);

                    // Actualizar el campo oculto para validación
                    inputParteHidden.value = parteSeleccionada.id;

                    // Actualizar UM según los depósitos seleccionados
                    actualizarUM();

                    // Actualizar precios si es compra (quizás traer último precio)
                    if (esCompra()) {
                        actualizarCalculosCompra();
                    }
                },
                customItemRender: (item) => {
                    const parteCodigo = escapeHtml(item.parte_codigo || 'N/A');
                    const parteDetalle = escapeHtml(item.parte_detalle || 'Sin detalle');
                    const varianteCodigo = escapeHtml(item.codigo_variante || 'N/A');
                    const varianteDetalle = escapeHtml(item.detalle || item.variante_detalle || 'Sin descripción');
                    const tipoCodigo = escapeHtml(item.tipo_codigo || 'N/A');

                    return `
                        <div class="d-flex justify-content-between align-items-start w-100 p-2">
                            <div class="flex-grow-1">
                                <div class="small text-secondary">
                                    <span class="fw-semibold">Parte:</span>
                                    <strong class="text-primary">${parteCodigo}</strong>
                                    <span class="text-muted mx-1">|</span>
                                    <span class="fw-semibold">Variante:</span>
                                    <strong class="text-primary">${varianteCodigo}</strong>
                                </div>
                                <small class="text-secondary d-block">${parteDetalle} | ${varianteDetalle}</small>
                            </div>
                            <span class="badge bg-secondary ms-2 align-self-start">${tipoCodigo}</span>
                        </div>
                    `;
                }
            });

            // Limpiar selección cuando el usuario empieza a escribir de nuevo
            searchInput.addEventListener('input', function() {
                if (parteSeleccionada && this.value !== parteSeleccionada.selectedLabel) {
                    parteSeleccionada = null;
                    inputParteHidden.value = '';
                    setSearchLockedState(false);
                    actualizarUM();
                }
            });
        }

        if (btnCambiarParte) {
            btnCambiarParte.addEventListener('click', function() {
                parteSeleccionada = null;
                inputParteHidden.value = '';
                searchInput.value = '';
                setSearchLockedState(false);
                actualizarUM();
                searchInput.focus();
            });
        }

        // Evento: cambio en depósito origen
        selectOrigen.addEventListener('change', function() {
            const origenId = parseInt(this.value);

            // Limpiar y deshabilitar select de destino
            selectDestino.innerHTML = '<option value="">Seleccione un destino</option>';

            if (!origenId) {
                selectDestino.disabled = true;
                return;
            }

            // Obtener destinos permitidos para este origen
            const destinosPermitidos = destinosPorOrigen[origenId] || [];

            if (destinosPermitidos.length === 0) {
                selectDestino.innerHTML = '<option value="">No hay destinos configurados</option>';
                selectDestino.disabled = true;
                return;
            }

            // Llenar select de destino con opciones permitidas
            destinosPermitidos.forEach(function(destinoId) {
                const deposito = tiposDeposito.find(t => t.id == destinoId);
                if (deposito) {
                    const option = document.createElement('option');
                    option.value = deposito.id;
                    option.textContent = deposito.codigo + ' - ' + deposito.nombre;
                    option.dataset.codigo = deposito.codigo;
                    selectDestino.appendChild(option);
                }
            });

            selectDestino.disabled = false;

            // Actualizar UM si hay parte seleccionada
            actualizarUM();

            // Actualizar visibilidad de campos compra
            toggleCamposCompra();

            // Filtrar tabla
            filtrarTabla();
        });

        // Evento: cambio en depósito destino
        selectDestino.addEventListener('change', function() {
            actualizarUM();
            // Filtrar tabla
            filtrarTabla();
        });

        // Función para determinar si debe usar UM Compra o UM Uso
        function determinarTipoUM() {
            const origenId = parseInt(selectOrigen.value);
            const destinoId = parseInt(selectDestino.value);

            if (!origenId || !destinoId) return null;

            const origen = tiposDeposito.find(t => t.id == origenId);
            const destino = tiposDeposito.find(t => t.id == destinoId);

            if (!origen || !destino) return null;

            // Si viene de PROVEEDOR → usar UM Compra
            if (depositosCompra.some(codigo => origen.codigo.includes(codigo))) {
                return 'compra';
            }

            // Si va a CLIENTE o PT → usar UM Uso (venta)
            if (depositosCliente.some(codigo => destino.codigo.includes(codigo))) {
                return 'uso';
            }

            // Por defecto, usar UM Uso para movimientos internos
            return 'uso';
        }

        // Función para actualizar el select de UM
        function actualizarUM() {
            if (!parteSeleccionada) {
                selectUM.disabled = true;
                selectUM.innerHTML = '<option value="">Seleccione una parte primero</option>';
                umTipoLabel.textContent = '';
                return;
            }

            const tipoUM = determinarTipoUM();

            if (!tipoUM) {
                selectUM.disabled = true;
                return;
            }

            // Obtener la UM correspondiente
            const umId = tipoUM === 'compra' ? parteSeleccionada.id_um_compra : parteSeleccionada.id_um_uso;
            const um = unidadesMedida.find(u => u.id == umId);

            if (!um) {
                selectUM.innerHTML = '<option value="">UM no configurada</option>';
                selectUM.disabled = true;
                umTipoLabel.textContent = '';
                return;
            }

            // Configurar el select con la UM correspondiente
            selectUM.innerHTML = `<option value="${um.id}" selected>${um.simbolo} - ${um.unidad}</option>`;
            selectUM.disabled = false;

            // Actualizar label
            umTipoLabel.textContent = tipoUM === 'compra' ? '(UM Compra)' : '(UM Uso)';
        }

        // Botón cancelar
        btnCancelar.addEventListener('click', function() {
            if (confirm('¿Está seguro que desea cancelar? Se perderán los datos ingresados.')) {
                form.reset();
                document.getElementById('fecha_hora').value = '<?= date('Y-m-d\TH:i') ?>';
                parteSeleccionada = null;
                inputParteHidden.value = '';
                searchInput.value = '';
                setSearchLockedState(false);
                selectDestino.disabled = true;
                selectDestino.innerHTML = '<option value="">Primero seleccione origen</option>';
                selectUM.disabled = true;
                selectUM.innerHTML = '<option value="">Seleccione una parte primero</option>';
                umTipoLabel.textContent = '';
            }
        });

        // Procesa parámetros URL al cargar (después de definir listeners)
        const urlParams = new URLSearchParams(window.location.search);
        const origenParam = urlParams.get('origen');
        const destinoParam = urlParams.get('destino');

        if (origenParam) {
            let found = false;
            // Buscar coincidencia en selectOrigen
            Array.from(selectOrigen.options).forEach(opt => {
                const optText = (opt.textContent || '').toUpperCase();
                const optCode = (opt.dataset.codigo || '').toUpperCase();
                const param = origenParam.toUpperCase();

                if (opt.value && (optCode.includes(param) || optText.includes(param))) {
                    selectOrigen.value = opt.value;
                    found = true;
                }
            });

            if (found) {
                // Disparar evento change para cargar destinos
                selectOrigen.dispatchEvent(new Event('change'));

                // Intentar seleccionar destino después de que se llene el select
                if (destinoParam) {
                    setTimeout(() => {
                        let destFound = false;
                        const param = destinoParam.toUpperCase();

                        Array.from(selectDestino.options).forEach(opt => {
                            const optText = (opt.textContent || '').toUpperCase();
                            const optCode = (opt.dataset.codigo || '').toUpperCase();

                            if (opt.value && (optCode.includes(param) || optText.includes(param))) {
                                selectDestino.value = opt.value;
                                selectDestino.disabled = false;
                                destFound = true;
                            }
                        });

                        if (destFound) {
                            selectDestino.dispatchEvent(new Event('change'));
                        }
                    }, 200); // 200ms para asegurar renderizado
                }
            }
        }

        // Submit del formulario
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!parteSeleccionada) {
                alert('Debe seleccionar una parte');
                return;
            }

            const formData = new FormData(form);
            const data = Object.fromEntries(formData);

            // Validar campos requeridos
            if (!data.deposito_origen || !data.deposito_destino || !data.cantidad || parseFloat(data.cantidad) <= 0) {
                alert('Por favor complete todos los campos requeridos correctamente.');
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Guardando...';

            fetch('<?= url("transacciones/movimientos-partes") ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Movimiento registrado exitosamente');
                        // Resetear formulario
                        form.reset();
                        document.getElementById('fecha_hora').value = '<?= date('Y-m-d\TH:i') ?>';
                        parteSeleccionada = null;
                        inputParteHidden.value = '';
                        searchInput.value = '';
                        setSearchLockedState(false);
                        selectDestino.disabled = true;
                        selectDestino.innerHTML = '<option value="">Primero seleccione origen</option>';
                        selectUM.disabled = true;
                        selectUM.innerHTML = '<option value="">Seleccione una parte primero</option>';
                        umTipoLabel.textContent = '';
                        // Recargar página o actualizar lista de movimientos si estuviera implementada
                        window.location.reload();
                    } else {
                        alert('Error: ' + (result.message || 'Ocurrió un error desconocido'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error de conexión al guardar el movimiento');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
        });
    });
</script>
