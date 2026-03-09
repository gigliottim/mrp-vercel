<?php

use App\Core\View\View;
use App\Core\Support\AssetHelper;

// Inicializar variables para evitar notices de undefined variable
$tiposDeposito = $tiposDeposito ?? [];
$destinosPorOrigen = $destinosPorOrigen ?? [];
$unidadesMedida = $unidadesMedida ?? [];
$entidades = $entidades ?? [];
$movimientos = $movimientos ?? [];
$tipoMovimiento = $tipoMovimiento ?? null;

// Helper para encontrar ID de depósitos por código
$idProveedor = null;
$idAlmacen = null;

foreach ($tiposDeposito as $tipo) {
    if (strtoupper($tipo['codigo']) === 'PROVEEDOR') $idProveedor = $tipo['id'];
    if (strtoupper($tipo['codigo']) === 'ALMACEN') $idAlmacen = $tipo['id'];
}
?>

<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Compras</p>
            <h1 class="h3 mb-0">Registrar Compra</h1>
            <p class="text-muted small mb-0">Ingrese una nueva compra de stock</p>
        </div>
        <div>
            <a href="<?= url('compras') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Volver al listado
            </a>
        </div>
    </div>
</section>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Nueva Compra</h5>
            </div>
            <div class="card-body">
                <form id="formMovimiento" method="POST" action="<?= url('transacciones/movimientos-partes') ?>">
                    <!-- Fila 1: Fecha/Hora y Depósitos -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="fecha_hora" class="form-label">Fecha Compra</label>
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
                            <label for="deposito_origen" class="form-label">Origen (Proveedor)</label>
                            <!-- Input visible disabled y hidden con el valor real -->
                            <select class="form-select bg-light" disabled>
                                <?php foreach ($tiposDeposito as $tipo) : ?>
                                    <option value="<?= View::escape($tipo['id']) ?>"
                                        <?= $tipo['id'] == $idProveedor ? 'selected' : '' ?>>
                                        <?= View::escape($tipo['codigo']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="deposito_origen" id="deposito_origen" value="<?= $idProveedor ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="deposito_destino" class="form-label">Destino</label>
                            <select class="form-select bg-light" disabled>
                                <?php foreach ($tiposDeposito as $tipo) : ?>
                                    <option value="<?= View::escape($tipo['id']) ?>"
                                        <?= $tipo['id'] == $idAlmacen ? 'selected' : '' ?>>
                                        <?= View::escape($tipo['codigo']) ?> - <?= View::escape($tipo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="deposito_destino" id="deposito_destino" value="<?= $idAlmacen ?>">
                        </div>
                    </div>

                    <!-- Fila 2: Búsqueda de Parte -->
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label for="search-parte-input" class="form-label">
                                <i class="fa-solid fa-magnifying-glass me-1"></i>
                                Buscar Parte / Ítem
                            </label>
                            <div class="position-relative">
                                <input type="text"
                                    class="form-control form-control-lg"
                                    id="search-parte-input"
                                    placeholder="Escriba el código o nombre..."
                                    autocomplete="off">
                                <div id="search-parte-results" class="search-results list-group mt-2"></div>
                            </div>
                            <input type="hidden" id="parte" name="parte" required>
                        </div>
                    </div>

                    <!-- Calculated Info Section -->
                    <div class="card bg-secondary bg-opacity-10 border-0 mb-4" id="calculatedInfo" style="display:none;">
                        <div class="card-body">
                            <h6 class="card-title text-muted mb-3"><i class="fas fa-calculator me-2"></i>Información del Ítem</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="small text-muted fw-bold">Stock Actual</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control bg-white fw-bold text-dark" id="calc_stock_actual" readonly>
                                        <span class="input-group-text">Uso</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="small text-muted">Precio Unitario (Est.)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control bg-white" id="calc_precio_unitario" readonly>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="small text-muted">Costo Lote Mín (<span id="lote_min_display">0</span>)</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control bg-white" id="calc_costo_lote" readonly>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="small text-muted">Factor Conversión</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control bg-white" id="calc_factor" readonly>
                                        <span class="input-group-text">Uso/Compra</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fila 3: Orden No. y Venta No. -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label for="orden_no" class="form-label">Orden No.</label>
                            <input type="text"
                                class="form-control bg-light"
                                id="orden_no"
                                name="orden_no"
                                placeholder="N/A"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label for="venta_no" class="form-label">Venta No.</label>
                            <input type="text"
                                class="form-control bg-light"
                                id="venta_no"
                                name="venta_no"
                                placeholder="N/A"
                                readonly>
                        </div>
                        <div class="col-md-3">
                            <label for="cbte" class="form-label">Cbte / Factura</label>
                            <input type="text"
                                class="form-control"
                                id="cbte"
                                name="cbte"
                                placeholder="Nro factura">
                        </div>
                        <div class="col-md-3">
                            <label for="proveedor_cliente" class="form-label">Proveedor <span class="text-danger">*</span></label>
                            <select class="form-select" id="proveedor_cliente" name="proveedor_cliente" required>
                                <option value="">Seleccionar Proveedor</option>
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
                            <label for="cantidad" class="form-label">Cantidad (Compra)</label>
                            <input type="number"
                                class="form-control form-control-lg"
                                id="cantidad"
                                name="cantidad"
                                step="<?= esc(app_decimal_step()) ?>"
                                placeholder="0.00"
                                required>
                        </div>
                        <div class="col-md-4">
                            <label for="um" class="form-label">Unidad Medida</label>
                            <select class="form-select form-select-lg" id="um" name="um" required disabled>
                                <option value="">-</option>
                            </select>
                            <input type="hidden" id="um_compra_id" value="">
                            <input type="hidden" id="um_uso_id" value="">
                        </div>
                        <div class="col-md-4">
                            <label for="importe_total" class="form-label">Importe Total $</label>
                            <input type="number"
                                class="form-control form-control-lg"
                                id="importe_total"
                                name="importe_total"
                                step="<?= esc(app_decimal_step()) ?>"
                                placeholder="0.00"
                                required>
                        </div>
                    </div>

                    <!-- Fila 5: Observaciones -->
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control"
                                id="observaciones"
                                name="observaciones"
                                rows="2"></textarea>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="btnCancelar">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fa-solid fa-save me-2"></i>
                            Registrar Compra
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de movimientos recientes (Opcional, reutilizada) -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Últimos Movimientos</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Parte</th>
                                <th>Ref</th>
                                <th>De</th>
                                <th>A</th>
                                <th class="text-end">Cant.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($movimientos)): ?>
                                <?php foreach ($movimientos as $mov): ?>
                                    <tr>
                                        <td><?= View::escape(app_format_datetime($mov['fecha'])) ?></td>
                                        <td><?= View::escape($mov['codigo_variante']) ?></td>
                                        <td><?= View::escape($mov['referencia_id']) ?></td>
                                        <td><?= View::escape($mov['origen_codigo']) ?></td>
                                        <td><?= View::escape($mov['destino_codigo']) ?></td>
                                        <td class="text-end"><?= View::escape(app_format_number((float) $mov['cantidad'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Sin movimientos recientes</td>
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
    /* Estilos para SearchClient */
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
    }

    #search-parte-results .list-group-item:hover {
        background-color: #f8f9fa;
    }

    #search-parte-input.parte-seleccionada {
        background-color: #d1e7dd;
        border-color: #198754;
    }
</style>

<?php if (class_exists('App\Core\Support\AssetHelper')): ?>
    <script src="<?= AssetHelper::js('modules/SearchClient.js') ?>"></script>
<?php else: ?>
    <script src="/assets/js/modules/SearchClient.js"></script>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const unidadesMedida = <?= json_encode($unidadesMedida) ?>;
        const idProveedor = "<?= $idProveedor ?>";
        const idAlmacen = "<?= $idAlmacen ?>";

        const elements = {
            form: document.getElementById('formMovimiento'),
            fechaHora: document.getElementById('fecha_hora'),
            searchInput: document.getElementById('search-parte-input'),
            searchResults: document.getElementById('search-parte-results'),
            inputParteHidden: document.getElementById('parte'),
            selectUM: document.getElementById('um'),
            cantidad: document.getElementById('cantidad'),
            importeTotal: document.getElementById('importe_total'),

            // Calculated Fields
            calculatedInfo: document.getElementById('calculatedInfo'),
            calcStockActual: document.getElementById('calc_stock_actual'),
            calcPrecioUnitario: document.getElementById('calc_precio_unitario'),
            calcCostoLote: document.getElementById('calc_costo_lote'),
            calcFactor: document.getElementById('calc_factor'),
            loteMinLabel: document.getElementById('lote_min_display')
        };

        const argentinaTimezone = 'America/Argentina/Buenos_Aires';

        function getBuenosAiresNowDateTimeLocal() {
            const formatter = new Intl.DateTimeFormat('en-CA', {
                timeZone: argentinaTimezone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hourCycle: 'h23'
            });

            const parts = formatter.formatToParts(new Date()).reduce((acc, part) => {
                if (part.type !== 'literal') {
                    acc[part.type] = part.value;
                }
                return acc;
            }, {});

            return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
        }

        function syncFechaHoraLimits(resetValue = false) {
            if (!elements.fechaHora) {
                return;
            }

            const maxNow = getBuenosAiresNowDateTimeLocal();
            elements.fechaHora.max = maxNow;

            if (resetValue || !elements.fechaHora.value || elements.fechaHora.value > maxNow) {
                elements.fechaHora.value = maxNow;
            }
        }

        let parteSeleccionada = null;

        syncFechaHoraLimits(true);
        setInterval(() => syncFechaHoraLimits(false), 30000);
        if (elements.fechaHora) {
            elements.fechaHora.addEventListener('change', () => syncFechaHoraLimits(false));
        }

        // Inicializar SearchClient
        if (elements.searchInput && elements.searchResults) {
            // Asegurarse de que SearchClient esté cargado
            if (typeof SearchClient !== 'undefined') {
                new SearchClient({
                    endpoint: '<?= url('api/v1/search/variantes') ?>',
                    inputElement: elements.searchInput,
                    resultsContainer: elements.searchResults,
                    minChars: 2,
                    debounceDelay: 300,
                    format: 'detailed',
                    // Pasar campos adicionales que queramos recibir si el backend soporta seleccion de campos
                    onSelect: (item) => {
                        parteSeleccionada = {
                            id: item.id,
                            codigo: item.codigo_variante || item.parte_codigo,
                            detalle: item.detalle || item.variante_detalle,
                            id_um_compra: item.id_um_compra,
                            id_um_uso: item.id_um_uso,

                            // Nuevos campos
                            stock_actual: parseFloat(item.stock_actual || 0),
                            lote_minimo: parseFloat(item.lote_minimo || 0),
                            factor_conversion: parseFloat(item.factor_conversion || 1),
                            um_uso_codigo: item.um_uso_codigo || 'Unid',
                            um_compra_codigo: item.um_compra_codigo || 'Unid',
                        };

                        elements.searchInput.value = parteSeleccionada.codigo + ' - ' + parteSeleccionada.detalle;
                        elements.searchInput.classList.add('parte-seleccionada');
                        elements.inputParteHidden.value = parteSeleccionada.id;

                        actualizarUI();
                    },
                    customItemRender: (item) => {
                        return `
                            <div class="d-flex justify-content-between align-items-start w-100 p-2">
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-primary">${item.codigo_variante || item.parte_codigo}</div>
                                    <small class="text-secondary">${item.detalle || ''}</small>
                                </div>
                                <span class="badge bg-light text-dark ms-2">Stock: ${item.stock_actual || 0}</span>
                            </div>
                        `;
                    }
                });
            } else {
                console.error('SearchClient no está definido. Verifique que el script JS se cargue correctamente.');
            }
        }

        // Reset busqueda
        elements.searchInput.addEventListener('input', function() {
            if (parteSeleccionada && this.value !== (parteSeleccionada.codigo + ' - ' + parteSeleccionada.detalle)) {
                parteSeleccionada = null;
                elements.inputParteHidden.value = '';
                this.classList.remove('parte-seleccionada');
                actualizarUI();
            }
        });

        function formatMoney(amount) {
            return '$ ' + window.appFormatNumber(amount);
        }

        function actualizarUI() {
            if (!parteSeleccionada) {
                elements.calculatedInfo.style.display = 'none';
                elements.selectUM.innerHTML = '<option value="">-</option>';
                elements.selectUM.disabled = true;
                return;
            }

            // Mostrar sección calculada
            elements.calculatedInfo.style.display = 'block';

            // UM Compra (select)
            // En compras siempre usamos la UM de Compra
            const umId = parteSeleccionada.id_um_compra;
            const um = unidadesMedida.find(u => u.id == umId);
            if (um) {
                elements.selectUM.innerHTML = `<option value="${um.id}" selected>${um.codigo} - ${um.nombre}</option>`;
            } else {
                elements.selectUM.innerHTML = `<option value="">UM Compra no config.</option>`;
            }

            // Stock Actual (Uso)
            elements.calcStockActual.value = window.appFormatNumber(parteSeleccionada.stock_actual) + ' ' + parteSeleccionada.um_uso_codigo;

            // Factor
            elements.calcFactor.value = parteSeleccionada.factor_conversion;

            // Trigger calc updates
            updateCalculations();
        }

        function updateCalculations() {
            if (!parteSeleccionada) return;

            const total = parseFloat(elements.importeTotal.value) || 0;
            const cant = parseFloat(elements.cantidad.value) || 0;
            const factor = parteSeleccionada.factor_conversion || 1;

            // Precio Unitario (Por unidad de USO)
            // Precio Unitario Compra = Total / Cantidad Compra
            // Precio Unitario Uso = Precio Unitario Compra / Factor
            let unitPriceUso = 0;
            if (cant > 0 && factor > 0) {
                unitPriceUso = (total / cant) / factor;
            }

            elements.calcPrecioUnitario.value = formatMoney(unitPriceUso) + ` / ${parteSeleccionada.um_uso_codigo}`;

            // Costo Lote Minimo (en unidades de uso)
            const loteMin = parteSeleccionada.lote_minimo;
            elements.loteMinLabel.textContent = window.appFormatNumber(loteMin) + ' ' + parteSeleccionada.um_uso_codigo;

            const costLoteMin = unitPriceUso * loteMin;
            elements.calcCostoLote.value = formatMoney(costLoteMin);
        }

        elements.cantidad.addEventListener('input', updateCalculations);
        elements.importeTotal.addEventListener('input', updateCalculations);

        // Cancelar action
        document.getElementById('btnCancelar').addEventListener('click', () => {
            if (confirm('¿Cancelar carga?')) window.location.href = '<?= url("compras") ?>';
        });

        // Submit Action
        elements.form.addEventListener('submit', function(e) {
            e.preventDefault();
            syncFechaHoraLimits(false);

            if (elements.fechaHora && elements.fechaHora.value > elements.fechaHora.max) {
                alert('La Fecha/Hora no puede ser superior al momento actual de Buenos Aires.');
                elements.fechaHora.value = elements.fechaHora.max;
                elements.fechaHora.focus();
                return;
            }

            if (!parteSeleccionada) {
                alert('Seleccione un ítem');
                return;
            }
            if (!elements.cantidad.value) {
                alert('Ingrese cantidad');
                return;
            }

            const btn = elements.form.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            // Preparar datos
            const formData = new FormData(elements.form);
            const data = Object.fromEntries(formData);

            // Agregar campos fijos que podrían no enviarse si están disabled
            data.deposito_origen = idProveedor;
            data.deposito_destino = idAlmacen;
            data.parte = parteSeleccionada.id;

            fetch(elements.form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        alert('Compra registrada correctamente!');
                        window.location.reload();
                    } else {
                        alert('Error: ' + (res.message || 'Error desconocido'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error de conexión');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        });
    });
</script>
