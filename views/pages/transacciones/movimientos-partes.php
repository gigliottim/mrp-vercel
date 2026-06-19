<?php

use App\Core\View\View;

$tiposDeposito = $tiposDeposito ?? [];
$destinosPorOrigen = $destinosPorOrigen ?? [];
$unidadesMedida = $unidadesMedida ?? [];

?>
<section class="mb-2">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1" style="font-size:.8rem">
                    <li class="breadcrumb-item"><a href="<?= url('') ?>">MRP</a></li>
                    <li class="breadcrumb-item"><a href="#">Transacciones</a></li>
                    <li class="breadcrumb-item active">Movimientos de Partes</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">Movimientos de Partes</h1>
            <p class="text-muted small mb-0">Registre y administre movimientos de partes entre dep&oacute;sitos</p>
        </div>
    </div>
</section>

<!-- Stats Row -->
<div class="row g-2 mb-3" id="statsRow">
    <div class="col-6 col-md-3">
        <div class="mrp-stat-card">
            <div class="stat-icon text-primary"><i class="fa-solid fa-arrow-right-arrow-left"></i></div>
            <div class="stat-number" id="statTotal"><?= count($movimientos ?? []) ?></div>
            <div class="stat-desc">Total Movimientos</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="mrp-stat-card">
            <div class="stat-icon text-success"><i class="fa-solid fa-truck-ramp-box"></i></div>
            <div class="stat-number" id="statCompras">0</div>
            <div class="stat-desc">Compras</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="mrp-stat-card">
            <div class="stat-icon text-info"><i class="fa-solid fa-warehouse"></i></div>
            <div class="stat-number" id="statInternos">0</div>
            <div class="stat-desc">Internos</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="mrp-stat-card">
            <div class="stat-icon text-warning"><i class="fa-solid fa-truck"></i></div>
            <div class="stat-number" id="statVentas">0</div>
            <div class="stat-desc">Salidas</div>
        </div>
    </div>
</div>

<div class="row g-3 movimientos-layout">
    <!-- LEFT: Formulario -->
    <div class="col-lg-5 d-flex flex-column">
        <div class="mrp-card flex-fill">
            <div class="mrp-card-header">
                <h5 id="formTitle">
                    <i class="fa-solid fa-plus-circle"></i>
                    <span id="formTitleText">Nuevo Movimiento</span>
                </h5>
                <span class="badge-counter d-none" id="editingBadge">Editando #<span id="editingId"></span></span>
            </div>
            <div class="mrp-card-body mrp-scroll-form">
                <form id="formMovimiento" method="POST" action="<?= url('transacciones/movimientos-partes') ?>">
                    <input type="hidden" id="movimiento_id" name="movimiento_id" value="">

                    <!-- Section: Fecha y Flujo -->
                    <div class="mrp-form-section">
                        <div class="mrp-form-section-title">
                            <i class="fa-solid fa-calendar-day"></i> Fecha y Flujo
                        </div>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label for="fecha_hora" class="form-label">Fecha/Hora</label>
                                <input type="datetime-local"
                                    class="form-control"
                                    id="fecha_hora"
                                    name="fecha_hora"
                                    value="<?= date('Y-m-d\TH:i') ?>"
                                    required>
                            </div>
                            <div class="col-md-3">
                                <label for="deposito_origen" class="form-label">Origen</label>
                                <select class="form-select" id="deposito_origen" name="deposito_origen" required>
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($tiposDeposito as $tipo) : ?>
                                    <option value="<?= View::escape($tipo['id']) ?>"
                                        data-codigo="<?= View::escape($tipo['codigo']) ?>">
                                        <?= View::escape($tipo['codigo']) ?> - <?= View::escape($tipo['nombre']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex align-items-end justify-content-center pb-1">
                                <i class="fa-solid fa-arrow-right text-primary" style="font-size:1.2rem"></i>
                            </div>
                            <div class="col-md-3">
                                <label for="deposito_destino" class="form-label">Destino</label>
                                <select class="form-select" id="deposito_destino" name="deposito_destino" required disabled>
                                    <option value="">Seleccione origen</option>
                                </select>
                            </div>
                        </div>
                        <!-- Flow visual indicator -->
                        <div class="mrp-flow-visual d-none" id="flowVisual">
                            <div class="mrp-flow-step" id="flowOrigen">
                                <i class="fa-solid fa-box"></i>
                                <span id="flowOrigenLabel">-</span>
                            </div>
                            <i class="fa-solid fa-arrow-right mrp-flow-arrow-icon"></i>
                            <div class="mrp-flow-step" id="flowDestino">
                                <i class="fa-solid fa-boxes-stacked"></i>
                                <span id="flowDestinoLabel">-</span>
                            </div>
                            <span class="ms-auto" id="flowTypeBadge"></span>
                        </div>
                    </div>

                    <!-- Section: Parte -->
                    <div class="mrp-form-section">
                        <div class="mrp-form-section-title">
                            <i class="fa-solid fa-magnifying-glass"></i> Parte / Variante
                        </div>
                        <div class="mrp-search-wrapper">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-search"></i></span>
                                <input type="text"
                                    class="form-control form-control-lg"
                                    id="search-parte-input"
                                    placeholder="Escriba al menos 2 caracteres para buscar parte o variante..."
                                    autocomplete="off"
                                    disabled>
                                <button type="button" class="btn btn-mrp btn-mrp-outline d-none" id="btn-cambiar-parte">
                                    <i class="fa-solid fa-rotate me-1"></i> Cambiar
                                </button>
                            </div>
                            <div id="search-parte-results" class="mrp-search-results"></div>
                        </div>
                        <input type="hidden" id="parte" name="parte" required>
                        <small class="text-muted d-block mt-1" id="parteInfo">
                            <i class="fa-solid fa-info-circle me-1"></i>Seleccione un dep&oacute;sito destino para habilitar la b&uacute;squeda.
                        </small>
                        <div class="d-none mt-2 p-2 bg-light rounded-3" id="parteSelectedDetail">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-check-circle text-success"></i>
                                <div>
                                    <strong id="parteSelectedCode" class="text-primary"></strong>
                                    <span id="parteSelectedVariante" class="text-muted small ms-1"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Referencia (std) -->
                    <div class="mrp-form-section field-std">
                        <div class="mrp-form-section-title">
                            <i class="fa-solid fa-hashtag"></i> Referencia
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="orden_no" class="form-label">Orden No.</label>
                                <input type="text" class="form-control" id="orden_no" name="orden_no" placeholder="N/A">
                            </div>
                            <div class="col-md-4">
                                <label for="venta_no" class="form-label">Venta No.</label>
                                <input type="text" class="form-control" id="venta_no" name="venta_no" placeholder="N/A">
                            </div>
                            <div class="col-md-4">
                                <label for="proveedor_cliente" class="form-label">Prov./Cliente</label>
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
                    </div>

                    <!-- Section: Cantidad y UM -->
                    <div class="mrp-form-section">
                        <div class="mrp-form-section-title">
                            <i class="fa-solid fa-scale-balanced"></i> Cantidad
                        </div>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label for="cantidad" class="form-label">Cantidad</label>
                                <input type="number" class="form-control mrp-qty-input" id="cantidad" name="cantidad" step="any" min="0.0001" required>
                            </div>
                            <div class="col-md-4">
                                <label for="um" class="form-label">UM <span class="text-muted small" id="um_tipo_label"></span></label>
                                <select class="form-select" id="um" name="um" required disabled>
                                    <option value="">Seleccione una parte primero</option>
                                </select>
                                <input type="hidden" id="um_compra_id" value="">
                                <input type="hidden" id="um_uso_id" value="">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Compra (hidden by default) -->
                    <div class="mrp-form-section field-compra d-none">
                        <div class="mrp-form-section-title">
                            <i class="fa-solid fa-cart-shopping"></i> Datos de Compra
                            <span class="mrp-purchase-tag ms-2"><i class="fa-solid fa-tag"></i> Compra</span>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="proveedor_compra" class="form-label">Proveedor *</label>
                                <select class="form-select" id="proveedor_compra" name="proveedor_cliente">
                                    <option value="">Seleccionar proveedor</option>
                                    <?php if (isset($entidades)): ?>
                                        <?php foreach ($entidades as $entidad): ?>
                                            <?php if (($entidad['tipo'] ?? '') === 'PROVEEDOR'): ?>
                                    <option value="<?= View::escape($entidad['id']) ?>">
                                        <?= View::escape($entidad['razon_social']) ?>
                                    </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="moneda" class="form-label">Moneda</label>
                                <select class="form-select" id="moneda" name="moneda">
                                    <option value="ARS" selected>ARS - Peso Argentino</option>
                                    <option value="USD">USD - D&oacute;lar Estadounidense</option>
                                    <option value="EUR">EUR - Euro</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="cotizacion" class="form-label">Cotizaci&oacute;n</label>
                                <input type="number" class="form-control" id="cotizacion" name="cotizacion" value="1" step="0.01">
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="cbte_compra" class="form-label">Comprobante</label>
                                <input type="text" class="form-control" id="cbte_compra" name="cbte">
                            </div>
                            <div class="col-md-4">
                                <label for="importe_total" class="form-label">Importe Total *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="importe_total" name="importe_total" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="precio_unitario" class="form-label">Precio Unit. (Auto)</label>
                                <input type="number" class="form-control" id="precio_unitario" name="precio_unitario" step="0.000001" readonly tabindex="-1">
                            </div>
                        </div>

                        <!-- Calculated info panel -->
                        <div class="mrp-calc-panel" id="panel-calculos-compra">
                            <h6><i class="fa-solid fa-calculator me-2"></i>Resumen Calculado</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="mrp-calc-stat">
                                        <div class="stat-value" id="calc-qty-compra">-</div>
                                        <div class="stat-label">Cant. Compra</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mrp-calc-stat">
                                        <div class="stat-value" id="calc-factor">-</div>
                                        <div class="stat-label">Factor Conv.</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mrp-calc-stat">
                                        <div class="stat-value text-success" id="calc-qty-uso">-</div>
                                        <div class="stat-label">Cant. Uso (Est.)</div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mrp-calc-stat">
                                        <div class="stat-value" id="calc-costo-base">-</div>
                                        <div class="stat-label">Costo Unit. Base</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Observaciones -->
                    <div class="mrp-form-section">
                        <div class="mrp-form-section-title">
                            <i class="fa-solid fa-message"></i> Observaciones
                        </div>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2" placeholder="Notas adicionales..."></textarea>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex justify-content-between align-items-center gap-2 pt-2">
                        <button type="button" class="btn btn-mrp btn-mrp-outline" id="btnCancelar">
                            <i class="fa-solid fa-xmark me-1"></i> Cancelar
                        </button>
                        <button type="submit" class="btn btn-mrp btn-mrp-success" id="btnSubmit">
                            <i class="fa-solid fa-check me-1"></i> <span id="btnSubmitText">Guardar Movimiento</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT: Tabla de movimientos -->
    <div class="col-lg-7 d-flex flex-column">
        <div class="mrp-card flex-fill">
            <div class="mrp-card-header">
                <h5>
                    <i class="fa-solid fa-list-check me-1"></i>
                    &Uacute;ltimos Movimientos
                </h5>
                <span class="badge-counter" id="movCountBadge"><?= count($movimientos ?? []) ?></span>
            </div>
            <div class="mrp-card-body p-0">
                <!-- Filter chips -->
                <div class="px-3 pt-3 pb-2">
                    <div class="mrp-filters-bar" id="filtersBar">
                        <span class="text-muted small fw-semibold me-1">Filtrar:</span>
                    </div>
                </div>
                <div class="mrp-table-wrapper">
                    <table class="mrp-table" id="movimientosTable">
                        <thead>
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Parte</th>
                                <th>Flujo</th>
                                <th>Orden</th>
                                <th class="text-end">Cant. Uso</th>
                                <th class="text-end">Cant. Compra</th>
                                <th class="text-end">Importe</th>
                                <th class="text-center" style="width:70px">Acci&oacute;n</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($movimientos)): ?>
                                <?php
                                $formatCompactQty = static function (float $value): string {
                                    $formatted = app_format_number($value);
                                    if (strpos($formatted, ',') === false) {
                                        return $formatted;
                                    }

                                    [$integerPart, $decimalPart] = explode(',', $formatted, 2);
                                    $decimalPart = rtrim($decimalPart, '0');

                                    return $decimalPart === '' ? $integerPart : $integerPart . ',' . $decimalPart;
                                };

                                $depositosCompra = ['PROVEEDOR', 'PROVEED'];
                                $depositosCliente = ['CLIENTE', 'VENTA', 'PT'];
                                ?>
                                <?php foreach ($movimientos as $mov): ?>
                                    <?php
                                    $precioUnitarioCompra = isset($mov['compra_precio_unitario']) ? (float) $mov['compra_precio_unitario'] : 0.0;
                                    $importeMovimiento = $precioUnitarioCompra > 0 ? ((float) $mov['cantidad'] * $precioUnitarioCompra) : null;
                                    $factorConversion = isset($mov['factor_conversion']) ? (float) $mov['factor_conversion'] : 1.0;
                                    if ($factorConversion <= 0) {
                                        $factorConversion = 1.0;
                                    }
                                    $cantidadUso = (float) $mov['cantidad'];
                                    $cantidadCompra = $cantidadUso / $factorConversion;
                                    $umUso = (string) ($mov['um_uso_simbolo'] ?? '-');
                                    $umCompra = (string) ($mov['um_compra_simbolo'] ?? '-');

                                    $origenCodigo = strtoupper((string) ($mov['origen_codigo'] ?? ''));
                                    $destinoCodigo = strtoupper((string) ($mov['destino_codigo'] ?? ''));
                                    $esCompraMov = false;
                                    $esSalidaMov = false;
                                    foreach ($depositosCompra as $dc) {
                                        if (strpos($origenCodigo, $dc) !== false) {
                                            $esCompraMov = true;
                                            break;
                                        }
                                    }
                                    foreach ($depositosCliente as $dc) {
                                        if (strpos($destinoCodigo, $dc) !== false) {
                                            $esSalidaMov = true;
                                            break;
                                        }
                                    }
                                    ?>
                                    <tr data-origen-id="<?= View::escape($mov['id_tipo_deposito_origen']) ?>"
                                        data-destino-id="<?= View::escape($mov['id_tipo_deposito_destino']) ?>">
                                        <td><span class="small"><?= View::escape(app_format_datetime($mov['fecha'])) ?></span></td>
                                        <td>
                                            <div class="fw-bold" style="font-size:.85rem"><?= View::escape($mov['parte_codigo']) ?></div>
                                            <small class="text-muted"><?= View::escape($mov['codigo_variante']) ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-1">
                                                <div class="d-flex align-items-center gap-1">
                                                    <span class="deposit-badge origen"><i class="fa-solid fa-box fa-xs"></i> <?= View::escape($mov['origen_codigo']) ?></span>
                                                    <i class="fa-solid fa-arrow-right fa-xs text-primary"></i>
                                                    <span class="deposit-badge destino"><i class="fa-solid fa-boxes-stacked fa-xs"></i> <?= View::escape($mov['destino_codigo']) ?></span>
                                                </div>
                                                <?php if ($esCompraMov): ?>
                                                    <span class="mrp-purchase-tag"><i class="fa-solid fa-cart-shopping"></i> Compra</span>
                                                <?php elseif ($esSalidaMov): ?>
                                                    <span class="badge bg-warning text-dark" style="font-size:.7rem"><i class="fa-solid fa-truck me-1"></i>Salida</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info text-dark" style="font-size:.7rem"><i class="fa-solid fa-arrows-rotate me-1"></i>Interno</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?= View::escape($mov['referencia_id'] ?? '-') ?></td>
                                        <td class="text-end fw-bold" style="font-size:.85rem"><?= View::escape($formatCompactQty($cantidadUso) . ' ' . $umUso) ?></td>
                                        <td class="text-end" style="font-size:.85rem"><?= View::escape($formatCompactQty($cantidadCompra) . ' ' . $umCompra) ?></td>
                                        <td class="text-end" style="font-size:.85rem"><?= $importeMovimiento !== null ? View::escape('$ ' . $formatCompactQty($importeMovimiento)) : '<span class="text-muted">-</span>' ?></td>
                                        <td class="text-center">
                                            <button type="button"
                                                class="btn btn-sm btn-mrp-outline btn-edit-row btn-editar-movimiento"
                                                data-id="<?= View::escape($mov['id']) ?>"
                                                data-fecha="<?= View::escape((string) $mov['fecha']) ?>"
                                                data-variante-id="<?= View::escape($mov['id_variante']) ?>"
                                                data-parte-codigo="<?= View::escape((string) ($mov['parte_codigo'] ?? '')) ?>"
                                                data-parte-detalle="<?= View::escape((string) ($mov['parte_detalle'] ?? '')) ?>"
                                                data-variante-codigo="<?= View::escape((string) ($mov['codigo_variante'] ?? '')) ?>"
                                                data-variante-detalle="<?= View::escape((string) ($mov['variante_detalle'] ?? '')) ?>"
                                                data-cantidad="<?= View::escape((string) $mov['cantidad']) ?>"
                                                data-origen-id="<?= View::escape($mov['id_tipo_deposito_origen']) ?>"
                                                data-destino-id="<?= View::escape($mov['id_tipo_deposito_destino']) ?>"
                                                data-id-um-compra="<?= View::escape((string) ($mov['id_um_compra'] ?? '')) ?>"
                                                data-id-um-uso="<?= View::escape((string) ($mov['id_um_uso'] ?? '')) ?>"
                                                data-factor-conversion="<?= View::escape((string) ($mov['factor_conversion'] ?? '1')) ?>"
                                                data-id-entidad="<?= View::escape((string) ($mov['id_entidad'] ?? '')) ?>"
                                                data-cbte="<?= View::escape((string) ($mov['nro_comprobante'] ?? '')) ?>"
                                                data-compra-precio-unitario="<?= View::escape((string) ($mov['compra_precio_unitario'] ?? '')) ?>"
                                                data-observaciones="<?= View::escape((string) ($mov['observaciones'] ?? '')) ?>"
                                                title="Editar movimiento">
                                                <i class="fa-solid fa-pen-to-square me-1"></i>Editar
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <div class="mrp-empty-state">
                                            <i class="fa-solid fa-inbox d-block"></i>
                                            <p>No hay movimientos registrados</p>
                                        </div>
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

<!-- Toast Container -->
<div class="mrp-toast-container" id="toastContainer"></div>
<!-- Modal Container -->
<div id="modalContainer"></div>

<style>
    :root {
        --mrp-primary: #4f46e5;
        --mrp-primary-light: #6366f1;
        --mrp-primary-dark: #3730a3;
        --mrp-success: #059669;
        --mrp-success-light: #d1fae5;
        --mrp-warning: #d97706;
        --mrp-danger: #dc2626;
        --mrp-info: #0891b2;
        --mrp-bg: #f8fafc;
        --mrp-card-bg: #ffffff;
        --mrp-border: #e2e8f0;
        --mrp-text: #1e293b;
        --mrp-text-muted: #64748b;
        --mrp-radius: 12px;
        --mrp-shadow: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
        --mrp-shadow-lg: 0 10px 25px rgba(0,0,0,.08), 0 4px 10px rgba(0,0,0,.04);
        --mrp-transition: all .2s cubic-bezier(.4,0,.2,1);
    }

    [data-bs-theme="dark"] {
        --mrp-bg: #0f172a;
        --mrp-card-bg: #1e293b;
        --mrp-border: #334155;
        --mrp-text: #f1f5f9;
        --mrp-text-muted: #94a3b8;
        --mrp-shadow: 0 1px 3px rgba(0,0,0,.3);
        --mrp-shadow-lg: 0 10px 25px rgba(0,0,0,.3);
        --mrp-success-light: #064e3b;
    }

    .mrp-stat-card {
        background: var(--mrp-card-bg);
        border: 1px solid var(--mrp-border);
        border-radius: 10px;
        padding: .85rem;
        text-align: center;
        transition: var(--mrp-transition);
    }
    .mrp-stat-card:hover { transform: translateY(-2px); box-shadow: var(--mrp-shadow); }
    .mrp-stat-card .stat-icon { font-size: 1.2rem; margin-bottom: .25rem; }
    .mrp-stat-card .stat-number { font-size: 1.3rem; font-weight: 700; color: var(--mrp-text); }
    .mrp-stat-card .stat-desc { font-size: .7rem; color: var(--mrp-text-muted); text-transform: uppercase; letter-spacing: .04em; }

    .mrp-card {
        background: var(--mrp-card-bg);
        border: 1px solid var(--mrp-border);
        border-radius: var(--mrp-radius);
        box-shadow: var(--mrp-shadow);
        overflow: hidden;
        transition: var(--mrp-transition);
    }
    .mrp-card:hover { box-shadow: var(--mrp-shadow-lg); }

    .mrp-card-header {
        padding: .85rem 1.25rem;
        border-bottom: 1px solid var(--mrp-border);
        background: linear-gradient(135deg, var(--mrp-primary) 0%, var(--mrp-primary-light) 100%);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .mrp-card-header h5 { margin: 0; font-size: .95rem; font-weight: 600; display: flex; align-items: center; gap: .5rem; }
    .mrp-card-header .badge-counter { background: rgba(255,255,255,.25); color: #fff; font-size: .75rem; padding: .2em .6em; border-radius: 9999px; }

    .mrp-card-body { padding: 1.25rem; }

    .mrp-form-section { margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px dashed var(--mrp-border); }
    .mrp-form-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .mrp-form-section-title { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--mrp-primary); margin-bottom: .75rem; display: flex; align-items: center; gap: .4rem; }
    .mrp-form-section-title i { font-size: .7rem; opacity: .7; }

    .form-control, .form-select { border-radius: 8px; border-color: var(--mrp-border); font-size: .88rem; transition: var(--mrp-transition); padding: .55rem .85rem; }
    .form-control:focus, .form-select:focus { border-color: var(--mrp-primary); box-shadow: 0 0 0 3px rgba(79,70,229,.15); }
    .form-control.is-locked { background-color: var(--mrp-success-light) !important; border-color: var(--mrp-success) !important; font-weight: 600; }

    .input-group-text { background: var(--mrp-bg); border-color: var(--mrp-border); font-size: .85rem; }

    .mrp-search-wrapper { position: relative; }
    .mrp-search-results {
        position: absolute; top: 100%; left: 0; right: 0; z-index: 1050;
        background: var(--mrp-card-bg); border: 1px solid var(--mrp-border); border-radius: 10px;
        box-shadow: var(--mrp-shadow-lg); max-height: 340px; overflow-y: auto; display: none; margin-top: 4px;
    }
    .mrp-search-results.show { display: block; }

    .mrp-flow-visual { display: flex; align-items: center; gap: .5rem; padding: .75rem; background: var(--mrp-bg); border-radius: 10px; margin-top: .5rem; }
    .mrp-flow-step { display: flex; align-items: center; gap: .4rem; padding: .4rem .75rem; background: var(--mrp-card-bg); border: 1px solid var(--mrp-border); border-radius: 8px; font-size: .82rem; font-weight: 600; }
    .mrp-flow-arrow-icon { color: var(--mrp-primary); font-size: 1.1rem; }

    .mrp-purchase-tag { display: inline-flex; align-items: center; gap: .3rem; background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; padding: .2rem .6rem; border-radius: 6px; font-size: .75rem; font-weight: 700; }
    [data-bs-theme="dark"] .mrp-purchase-tag { background: linear-gradient(135deg, #78350f, #92400e); color: #fcd34d; }

    .mrp-calc-panel { background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%); border: 1px solid #bbf7d0; border-radius: 10px; padding: 1rem; }
    [data-bs-theme="dark"] .mrp-calc-panel { background: linear-gradient(135deg, #064e3b 0%, #065f46 100%); border-color: #059669; }
    .mrp-calc-panel h6 { color: var(--mrp-success); font-size: .82rem; font-weight: 700; margin-bottom: .75rem; }
    .mrp-calc-stat .stat-value { font-size: 1rem; font-weight: 700; color: var(--mrp-text); }
    .mrp-calc-stat .stat-value.text-success { color: var(--mrp-success) !important; }
    .mrp-calc-stat .stat-label { font-size: .7rem; color: var(--mrp-text-muted); text-transform: uppercase; letter-spacing: .05em; }

    .mrp-table-wrapper { overflow-y: auto; max-height: calc(100vh - 230px); }
    .mrp-table { width: 100%; font-size: .82rem; margin-bottom: 0; }
    .mrp-table thead th { position: sticky; top: 0; background: var(--mrp-bg); font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--mrp-text-muted); border-bottom: 2px solid var(--mrp-border); padding: .65rem .75rem; white-space: nowrap; z-index: 2; }
    .mrp-table tbody td { padding: .6rem .75rem; vertical-align: middle; border-bottom: 1px solid var(--mrp-border); }
    .mrp-table tbody tr { transition: var(--mrp-transition); }
    .mrp-table tbody tr:hover { background: rgba(79,70,229,.04); }

    .deposit-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .2em .55em; border-radius: 6px; font-size: .75rem; font-weight: 600; }
    .deposit-badge.origen { background: #fef3c7; color: #92400e; }
    .deposit-badge.destino { background: #dbeafe; color: #1e40af; }
    [data-bs-theme="dark"] .deposit-badge.origen { background: #78350f; color: #fcd34d; }
    [data-bs-theme="dark"] .deposit-badge.destino { background: #1e3a5f; color: #93c5fd; }

    .btn-mrp { border-radius: 8px; font-weight: 600; font-size: .85rem; padding: .5rem 1.2rem; transition: var(--mrp-transition); display: inline-flex; align-items: center; gap: .4rem; }
    .btn-mrp-success { background: var(--mrp-success); border-color: var(--mrp-success); color: #fff; }
    .btn-mrp-success:hover { background: #047857; border-color: #047857; color: #fff; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(5,150,105,.3); }
    .btn-mrp-outline { background: transparent; border: 1px solid var(--mrp-border); color: var(--mrp-text-muted); }
    .btn-mrp-outline:hover { background: var(--mrp-bg); border-color: var(--mrp-primary); color: var(--mrp-primary); }
    .btn-edit-row { padding: .25rem .55rem; font-size: .78rem; border-radius: 6px; }

    .mrp-empty-state { text-align: center; padding: 3rem 1rem; color: var(--mrp-text-muted); }
    .mrp-empty-state i { font-size: 2.5rem; opacity: .3; margin-bottom: .75rem; }

    .mrp-toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 9999; display: flex; flex-direction: column; gap: .5rem; }
    .mrp-toast { padding: .85rem 1.2rem; border-radius: 10px; background: var(--mrp-card-bg); border: 1px solid var(--mrp-border); box-shadow: var(--mrp-shadow-lg); display: flex; align-items: center; gap: .6rem; font-size: .88rem; min-width: 300px; animation: slideIn .3s ease; }
    .mrp-toast.success { border-left: 4px solid var(--mrp-success); }
    .mrp-toast.error { border-left: 4px solid var(--mrp-danger); }
    .mrp-toast.warning { border-left: 4px solid var(--mrp-warning); }

    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }

    .mrp-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,.45); z-index: 9998; display: flex; align-items: center; justify-content: center; animation: fadeIn .2s ease; }
    .mrp-modal { background: var(--mrp-card-bg); border-radius: var(--mrp-radius); box-shadow: var(--mrp-shadow-lg); max-width: 480px; width: 95%; animation: scaleIn .2s ease; }
    .mrp-modal-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--mrp-border); display: flex; align-items: center; justify-content: space-between; }
    .mrp-modal-body { padding: 1.25rem; }
    .mrp-modal-footer { padding: .75rem 1.25rem; border-top: 1px solid var(--mrp-border); display: flex; justify-content: flex-end; gap: .5rem; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes scaleIn { from { transform: scale(.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

    .mrp-filters-bar { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
    .mrp-filter-chip { display: inline-flex; align-items: center; gap: .3rem; padding: .3rem .7rem; border-radius: 9999px; font-size: .78rem; font-weight: 600; cursor: pointer; border: 1px solid var(--mrp-border); background: var(--mrp-card-bg); color: var(--mrp-text-muted); transition: var(--mrp-transition); }
    .mrp-filter-chip:hover, .mrp-filter-chip.active { background: var(--mrp-primary); border-color: var(--mrp-primary); color: #fff; }

    .mrp-scroll-form { overflow-y: auto; max-height: calc(100vh - 200px); padding-right: .25rem; }
    .mrp-scroll-form::-webkit-scrollbar { width: 5px; }
    .mrp-scroll-form::-webkit-scrollbar-track { background: transparent; }
    .mrp-scroll-form::-webkit-scrollbar-thumb { background: var(--mrp-border); border-radius: 10px; }
    .mrp-table-wrapper::-webkit-scrollbar { width: 5px; }
    .mrp-table-wrapper::-webkit-scrollbar-track { background: transparent; }
    .mrp-table-wrapper::-webkit-scrollbar-thumb { background: var(--mrp-border); border-radius: 10px; }

    .movimientos-layout { min-height: 0; }
    .movimientos-layout > div[class*="col"] { display: flex; flex-direction: column; }
    .movimientos-layout .mrp-card { flex: 1; min-height: 0; }

    .mrp-qty-input { font-size: 1.1rem; font-weight: 700; text-align: center; }

    @media (max-width: 991.98px) {
        .mrp-scroll-form { max-height: none; }
        .mrp-table-wrapper { max-height: 400px; }
    }
</style>

<?php

use App\Core\Support\AssetHelper;
?>
<script src="<?= AssetHelper::js('modules/SearchClient.js') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Datos de configuraci&oacute;n desde PHP
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
        const inputFechaHora = document.getElementById('fecha_hora');
        const inputMovimientoId = document.getElementById('movimiento_id');
        const argentinaTimezone = 'America/Argentina/Buenos_Aires';
        const submitBtn = document.getElementById('btnSubmit');
        const submitTextCreate = '<i class="fa-solid fa-check me-1"></i> <span id="btnSubmitText">Guardar Movimiento</span>';
        const submitTextEdit = '<i class="fa-solid fa-floppy-disk me-1"></i> <span id="btnSubmitText">Actualizar Movimiento</span>';

        const searchInput = document.getElementById('search-parte-input');
        const searchResults = document.getElementById('search-parte-results');
        const flowVisual = document.getElementById('flowVisual');
        const parteSelectedDetail = document.getElementById('parteSelectedDetail');

        // Elementos de Compra
        const fieldsCompra = document.querySelectorAll('.field-compra');
        const fieldsStd = document.querySelectorAll('.field-std');
        const inputPrecio = document.getElementById('precio_unitario');
        const inputMoneda = document.getElementById('moneda');
        const inputCotizacion = document.getElementById('cotizacion');
        const inputCantidad = document.getElementById('cantidad');
        const inputImporteTotal = document.getElementById('importe_total');

        // Tabla
        const movimientosTableBody = document.querySelector('#movimientosTable tbody');
        const movimientosRows = movimientosTableBody ? movimientosTableBody.querySelectorAll('tr') : [];

        // Mapa de tipos de dep&oacute;sito
        const depositosCompra = ['PROVEEDOR', 'PROVEED'];
        const depositosCliente = ['CLIENTE', 'VENTA', 'PT'];

        function getBuenosAiresNowDateTimeLocal() {
            const now = new Date();
            const formatter = new Intl.DateTimeFormat('en-CA', {
                timeZone: argentinaTimezone,
                year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
            });
            const parts = formatter.formatToParts(now).reduce((acc, part) => {
                if (part.type !== 'literal') { acc[part.type] = part.value; }
                return acc;
            }, {});
            return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
        }

        function syncFechaHoraLimits(resetValue = false) {
            if (!inputFechaHora) return;
            const maxNow = getBuenosAiresNowDateTimeLocal();
            inputFechaHora.max = maxNow;
            if (resetValue || !inputFechaHora.value || inputFechaHora.value > maxNow) {
                inputFechaHora.value = maxNow;
            }
        }

        syncFechaHoraLimits(true);
        setInterval(() => syncFechaHoraLimits(false), 30000);
        if (inputFechaHora) {
            inputFechaHora.addEventListener('change', () => syncFechaHoraLimits(false));
        }

        // Estado de la parte seleccionada
        let parteSeleccionada = null;

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function setSearchLockedState(isLocked) {
            const hasDestinoSelected = !!selectDestino.value;
            if (!hasDestinoSelected) {
                parteSeleccionada = null;
                inputParteHidden.value = '';
                searchInput.value = '';
                searchInput.readOnly = false;
                searchInput.disabled = true;
                searchInput.classList.remove('is-locked');
                searchInput.placeholder = 'Seleccione un dep&oacute;sito destino para habilitar la b&uacute;squeda...';
                searchResults.classList.remove('show');
                if (btnCambiarParte) btnCambiarParte.classList.add('d-none');
                if (parteSelectedDetail) parteSelectedDetail.classList.add('d-none');
                actualizarUM();
                return;
            }

            searchInput.placeholder = 'Escriba al menos 2 caracteres para buscar parte o variante...';
            searchInput.readOnly = isLocked;
            searchInput.disabled = isLocked;

            if (isLocked) {
                searchInput.blur();
                searchInput.classList.add('is-locked');
                searchResults.classList.remove('show');
                if (btnCambiarParte) btnCambiarParte.classList.remove('d-none');
            } else {
                searchInput.classList.remove('is-locked');
                if (btnCambiarParte) btnCambiarParte.classList.add('d-none');
                if (parteSelectedDetail) parteSelectedDetail.classList.add('d-none');
            }
        }

        function setFormModeEditing(isEditing) {
            const formTitle = document.getElementById('formTitleText');
            const badge = document.getElementById('editingBadge');
            const editingId = document.getElementById('editingId');

            if (isEditing) {
                formTitle.textContent = 'Editar Movimiento';
                document.querySelector('#formTitle i').className = 'fa-solid fa-pen-to-square';
                submitBtn.innerHTML = submitTextEdit;
                badge.classList.remove('d-none');
                editingId.textContent = inputMovimientoId.value;
            } else {
                formTitle.textContent = 'Nuevo Movimiento';
                document.querySelector('#formTitle i').className = 'fa-solid fa-plus-circle';
                submitBtn.innerHTML = submitTextCreate;
                badge.classList.add('d-none');
            }
        }

        function toDateTimeLocalValue(value) {
            if (!value) return getBuenosAiresNowDateTimeLocal();
            return String(value).replace(' ', 'T').slice(0, 16);
        }

        function parseNumber(value, fallback = 0) {
            const n = parseFloat(value);
            return Number.isFinite(n) ? n : fallback;
        }

        function esCompra() {
            const origenId = parseInt(selectOrigen.value);
            if (!origenId) return false;
            const origenOption = selectOrigen.options[selectOrigen.selectedIndex];
            const codigo = origenOption.dataset.codigo || '';
            return depositosCompra.some(c => codigo.includes(c));
        }

        function esSalidaCliente() {
            const destinoId = parseInt(selectDestino.value);
            if (!destinoId) return false;
            const destinoOption = selectDestino.options[selectDestino.selectedIndex];
            const codigo = destinoOption.dataset.codigo || '';
            return depositosCliente.some(c => codigo.includes(c));
        }

        function determinarTipoUM() {
            const origenId = parseInt(selectOrigen.value);
            const destinoId = parseInt(selectDestino.value);
            if (!origenId || !destinoId) return null;
            if (esCompra()) return 'compra';
            if (esSalidaCliente()) return 'uso';
            return 'uso';
        }

        // Flow visual
        function updateFlowVisual() {
            const origenId = parseInt(selectOrigen.value);
            const destinoId = parseInt(selectDestino.value);
            if (!origenId && !destinoId) { flowVisual.classList.add('d-none'); return; }
            flowVisual.classList.remove('d-none');
            const origen = origenId ? tiposDeposito.find(t => t.id == origenId) : null;
            const destino = destinoId ? tiposDeposito.find(t => t.id == destinoId) : null;
            document.getElementById('flowOrigenLabel').textContent = origen ? origen.codigo : '---';
            document.getElementById('flowDestinoLabel').textContent = destino ? destino.codigo : '---';
            const flowTypeBadge = document.getElementById('flowTypeBadge');
            if (esCompra()) {
                flowTypeBadge.innerHTML = '<span class="mrp-purchase-tag"><i class="fa-solid fa-cart-shopping me-1"></i>Compra</span>';
            } else if (esSalidaCliente()) {
                flowTypeBadge.innerHTML = '<span class="badge bg-warning text-dark" style="font-size:.75rem"><i class="fa-solid fa-truck me-1"></i>Salida</span>';
            } else {
                flowTypeBadge.innerHTML = '<span class="badge bg-info text-dark" style="font-size:.75rem"><i class="fa-solid fa-arrows-rotate me-1"></i>Interno</span>';
            }
        }

        // Filtrar tabla
        function filtrarTabla() {
            const origenId = selectOrigen.value;
            const destinoId = selectDestino.value;
            if (!movimientosRows.length) return;
            if (!origenId && !destinoId) { movimientosRows.forEach(row => row.style.display = ''); return; }
            movimientosRows.forEach(row => {
                if (row.cells.length === 1) return;
                const rowOrigenId = row.dataset.origenId;
                const rowDestinoId = row.dataset.destinoId;
                let mostrar = true;
                if (origenId && rowOrigenId !== origenId) mostrar = false;
                if (destinoId && rowDestinoId !== destinoId) mostrar = false;
                row.style.display = mostrar ? '' : 'none';
            });
        }

        // Toggle campos compra
        function toggleCamposCompra() {
            const isPurchase = esCompra();
            fieldsCompra.forEach(field => {
                if (isPurchase) field.classList.remove('d-none');
                else field.classList.add('d-none');
            });
            fieldsStd.forEach(field => {
                if (isPurchase) field.classList.add('d-none');
                else field.classList.remove('d-none');
            });

            if (isPurchase) {
                inputPrecio.required = false;
                inputPrecio.readOnly = true;
                inputImporteTotal.required = true;
                actualizarCalculosCompra();
            } else {
                inputPrecio.required = false;
                inputPrecio.value = '';
                inputImporteTotal.required = false;
            }
        }

        function actualizarCalculosCompra() {
            if (!esCompra() || !parteSeleccionada) return;
            const cantidad = parseFloat(inputCantidad.value) || 0;
            const importeTotal = parseFloat(inputImporteTotal.value) || 0;
            const cotizacion = parseFloat(inputCotizacion.value) || 1;
            const factor = parteSeleccionada.factor_conversion || 1;

            const precioUnitarioCompra = cantidad > 0 ? (importeTotal / cantidad) : 0;
            inputPrecio.value = precioUnitarioCompra > 0 ? precioUnitarioCompra.toFixed(6) : '';

            const umText = selectUM.options[selectUM.selectedIndex]?.text || '';
            document.getElementById('calc-qty-compra').textContent = window.appFormatNumber ? appFormatNumber(cantidad) : cantidad.toLocaleString('es-AR') + ' ' + umText.split(' - ')[0];
            document.getElementById('calc-factor').textContent = factor;

            const qtyUso = cantidad * factor;
            document.getElementById('calc-qty-uso').textContent = (window.appFormatNumber ? appFormatNumber(qtyUso) : qtyUso.toLocaleString('es-AR')) + ' (Estimado)';

            const costoBase = qtyUso > 0 ? ((importeTotal * cotizacion) / qtyUso) : 0;
            document.getElementById('calc-costo-base').textContent = '$ ' + (window.appFormatNumber ? appFormatNumber(costoBase) : costoBase.toLocaleString('es-AR'));
        }

        // Actualizar UM
        function actualizarUM() {
            if (!parteSeleccionada) {
                selectUM.disabled = true;
                selectUM.innerHTML = '<option value="">Seleccione una parte primero</option>';
                umTipoLabel.textContent = '';
                return;
            }

            const tipoUM = determinarTipoUM();
            if (!tipoUM) { selectUM.disabled = true; return; }

            const umId = tipoUM === 'compra' ? parteSeleccionada.id_um_compra : parteSeleccionada.id_um_uso;
            const um = unidadesMedida.find(u => u.id == umId);
            if (!um) {
                selectUM.innerHTML = '<option value="">UM no configurada</option>';
                selectUM.disabled = true;
                umTipoLabel.textContent = '';
                return;
            }

            selectUM.innerHTML = `<option value="${um.id}" selected>${um.simbolo} - ${um.unidad}</option>`;
            selectUM.disabled = false;
            umTipoLabel.textContent = tipoUM === 'compra' ? '(UM Compra)' : '(UM Uso)';
        }

        // SearchClient
        if (searchInput && searchResults) {
            setSearchLockedState(false);

            const searchClientInstance = new SearchClient({
                endpoint: '<?= url('api/v1/search/variantes') ?>',
                inputElement: searchInput,
                resultsContainer: searchResults,
                minChars: 2,
                debounceDelay: 300,
                maxResults: 20,
                format: 'detailed',
                onSelect: (item) => {
                    const selectedLabel = `Parte: ${item.parte_codigo || 'N/A'} - ${item.parte_detalle || 'Sin detalle'} | Variante: ${item.codigo_variante || 'N/A'} - ${item.detalle || item.variante_detalle || 'Sin detalle'}`;

                    parteSeleccionada = {
                        id: item.id,
                        id_parte: item.id_parte,
                        codigo: item.codigo_variante || item.parte_codigo,
                        detalle: item.detalle || item.variante_detalle,
                        selectedLabel: selectedLabel,
                        id_um_compra: item.id_um_compra,
                        id_um_uso: item.id_um_uso,
                        factor_conversion: item.factor_conversion || 1,
                        parte_codigo: item.parte_codigo,
                        parte_detalle: item.parte_detalle,
                        codigo_variante: item.codigo_variante,
                        variante_detalle: item.variante_detalle || item.detalle
                    };

                    searchInput.value = selectedLabel;
                    setSearchLockedState(true);
                    inputParteHidden.value = parteSeleccionada.id;

                    if (parteSelectedDetail) {
                        document.getElementById('parteSelectedCode').textContent = parteSeleccionada.parte_codigo || '';
                        document.getElementById('parteSelectedVariante').textContent = parteSeleccionada.codigo_variante || '';
                        parteSelectedDetail.classList.remove('d-none');
                    }

                    actualizarUM();
                    if (esCompra()) actualizarCalculosCompra();
                },
                customItemRender: (item) => {
                    const parteCodigo = escapeHtml(item.parte_codigo || 'N/A');
                    const parteDetalle = escapeHtml(item.parte_detalle || 'Sin detalle');
                    const varianteCodigo = escapeHtml(item.codigo_variante || 'N/A');
                    const varianteDetalle = escapeHtml(item.detalle || item.variante_detalle || 'Sin descripci&oacute;n');
                    const tipoCodigo = escapeHtml(item.tipo_codigo || 'N/A');

                    const tipoColors = { 'MP': '#059669', 'PZ': '#4f46e5', 'PROD': '#d97706', 'CONJ': '#dc2626', 'TER': '#0891b2', 'MO': '#64748b' };
                    const tipoBgs = { 'MP': '#ecfdf5', 'PZ': '#eef2ff', 'PROD': '#fffbeb', 'CONJ': '#fef2f2', 'TER': '#ecfeff', 'MO': '#f8fafc' };
                    const tc = tipoColors[tipoCodigo] || '#64748b';
                    const tb = tipoBgs[tipoCodigo] || '#f1f5f9';

                    return `
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;padding:.6rem .85rem;">
                            <div style="flex:1;min-width:0">
                                <div style="display:flex;align-items:center;gap:.35rem;margin-bottom:.15rem">
                                    <span style="background:${tb};color:${tc};padding:.1em .4em;border-radius:9999px;font-size:.6rem;font-weight:700">${tipoCodigo}</span>
                                    <span style="font-weight:700;color:#4f46e5;font-size:.75rem">${parteCodigo}</span>
                                    <span style="color:#64748b;font-size:.72rem">|</span>
                                    <span style="font-weight:600;color:#6366f1;font-size:.75rem">${varianteCodigo}</span>
                                </div>
                                <small style="color:#64748b;display:block;font-size:.76rem">${parteDetalle} | ${varianteDetalle}</small>
                            </div>
                        </div>`;
                }
            });

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

        // Origen change
        selectOrigen.addEventListener('change', function() {
            const origenId = parseInt(this.value);
            selectDestino.innerHTML = '<option value="">Seleccione un destino</option>';

            if (!origenId) {
                selectDestino.disabled = true;
                updateFlowVisual();
                return;
            }

            const destinosPermitidos = destinosPorOrigen[origenId] || [];
            if (destinosPermitidos.length === 0) {
                selectDestino.innerHTML = '<option value="">No hay destinos configurados</option>';
                selectDestino.disabled = true;
                updateFlowVisual();
                return;
            }

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
            actualizarUM();
            toggleCamposCompra();
            updateFlowVisual();
            filtrarTabla();
        });

        // Destino change
        selectDestino.addEventListener('change', function() {
            setSearchLockedState(!!parteSeleccionada);
            actualizarUM();
            updateFlowVisual();
            filtrarTabla();
        });

        // Listeners para c&aacute;lculos
        [inputCantidad, inputImporteTotal, inputCotizacion, inputMoneda].forEach(input => {
            if (input) input.addEventListener('input', actualizarCalculosCompra);
        });

        // Editar movimiento
        function cargarMovimientoParaEdicion(button) {
            const movimientoId = button.dataset.id;
            if (!movimientoId) return;

            const origenId = button.dataset.origenId || '';
            const destinoId = button.dataset.destinoId || '';
            const varianteId = button.dataset.varianteId || '';
            const factorConversion = parseNumber(button.dataset.factorConversion, 1);
            const cantidadUso = parseNumber(button.dataset.cantidad, 0);
            const precioUnitarioCompraUso = parseNumber(button.dataset.compraPrecioUnitario, 0);
            const importeTotalEstimado = precioUnitarioCompraUso > 0 ? (precioUnitarioCompraUso * cantidadUso) : 0;

            inputMovimientoId.value = movimientoId;
            inputFechaHora.value = toDateTimeLocalValue(button.dataset.fecha);
            selectOrigen.value = origenId;
            selectOrigen.dispatchEvent(new Event('change'));

            setTimeout(() => {
                selectDestino.value = destinoId;
                selectDestino.dispatchEvent(new Event('change'));

                const isPurchaseFlow = esCompra();
                const cantidadForInput = isPurchaseFlow ?
                    (factorConversion > 0 ? (cantidadUso / factorConversion) : cantidadUso) :
                    cantidadUso;

                parteSeleccionada = {
                    id: varianteId,
                    id_parte: null,
                    codigo: button.dataset.varianteCodigo || '',
                    detalle: button.dataset.varianteDetalle || '',
                    selectedLabel: `Parte: ${button.dataset.parteCodigo || ''} - ${button.dataset.parteDetalle || ''} | Variante: ${button.dataset.varianteCodigo || ''} - ${button.dataset.varianteDetalle || ''}`,
                    id_um_compra: button.dataset.idUmCompra || '',
                    id_um_uso: button.dataset.idUmUso || '',
                    factor_conversion: factorConversion,
                    parte_codigo: button.dataset.parteCodigo || '',
                    parte_detalle: button.dataset.parteDetalle || '',
                    codigo_variante: button.dataset.varianteCodigo || '',
                    variante_detalle: button.dataset.varianteDetalle || ''
                };

                inputParteHidden.value = varianteId;
                searchInput.value = parteSeleccionada.selectedLabel;
                setSearchLockedState(true);

                if (parteSelectedDetail) {
                    document.getElementById('parteSelectedCode').textContent = parteSeleccionada.parte_codigo;
                    document.getElementById('parteSelectedVariante').textContent = parteSeleccionada.codigo_variante;
                    parteSelectedDetail.classList.remove('d-none');
                }

                inputCantidad.value = cantidadForInput > 0 ? cantidadForInput.toFixed(6) : '';
                inputImporteTotal.value = importeTotalEstimado > 0 ? importeTotalEstimado.toFixed(2) : '';

                const entidadId = button.dataset.idEntidad || '';
                if (entidadId) {
                    const entidadSelect = document.getElementById('proveedor_cliente');
                    if (entidadSelect) entidadSelect.value = entidadId;
                    const provSelect = document.getElementById('proveedor_compra');
                    if (provSelect) provSelect.value = entidadId;
                }

                const cbteInput = document.getElementById('cbte_compra');
                if (cbteInput) cbteInput.value = button.dataset.cbte || '';

                const observacionesInput = document.getElementById('observaciones');
                if (observacionesInput) observacionesInput.value = button.dataset.observaciones || '';

                actualizarUM();
                toggleCamposCompra();
                actualizarCalculosCompra();
                setFormModeEditing(true);

                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 120);
        }

        // Bot&oacute;n cancelar
        btnCancelar.addEventListener('click', function() {
            showModal('Cancelar', '&iquest;Est&aacute; seguro que desea cancelar? Se perder&aacute;n los datos ingresados.', function() {
                form.reset();
                inputMovimientoId.value = '';
                setFormModeEditing(false);
                syncFechaHoraLimits(true);
                parteSeleccionada = null;
                inputParteHidden.value = '';
                searchInput.value = '';
                setSearchLockedState(false);
                selectDestino.disabled = true;
                selectDestino.innerHTML = '<option value="">Primero seleccione origen</option>';
                selectUM.disabled = true;
                selectUM.innerHTML = '<option value="">Seleccione una parte primero</option>';
                umTipoLabel.textContent = '';
                if (parteSelectedDetail) parteSelectedDetail.classList.add('d-none');
                flowVisual.classList.add('d-none');
                fieldsCompra.forEach(f => f.classList.add('d-none'));
                fieldsStd.forEach(f => f.classList.remove('d-none'));
            });
        });

        // Toast notifications
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `mrp-toast ${type}`;
            const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-times-circle' : 'fa-exclamation-triangle';
            const color = type === 'success' ? 'text-success' : type === 'error' ? 'text-danger' : 'text-warning';
            toast.innerHTML = `<i class="fa-solid ${icon} ${color}"></i><span>${message}</span>`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'slideOut .3s ease forwards';
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }

        // Modal
        function showModal(title, message, onConfirm) {
            const container = document.getElementById('modalContainer');
            container.innerHTML = `
                <div class="mrp-modal-overlay" id="modalOverlay">
                    <div class="mrp-modal">
                        <div class="mrp-modal-header">
                            <h6 class="mb-0 fw-bold">${title}</h6>
                            <button class="btn-close" id="modalClose"></button>
                        </div>
                        <div class="mrp-modal-body">
                            <p class="mb-0">${message}</p>
                        </div>
                        <div class="mrp-modal-footer">
                            <button class="btn btn-mrp btn-mrp-outline btn-sm" id="modalCancel">Cancelar</button>
                            <button class="btn btn-mrp btn-mrp-success btn-sm" id="modalConfirm">Confirmar</button>
                        </div>
                    </div>
                </div>`;
            document.getElementById('modalClose').addEventListener('click', () => container.innerHTML = '');
            document.getElementById('modalCancel').addEventListener('click', () => container.innerHTML = '');
            document.getElementById('modalConfirm').addEventListener('click', () => { container.innerHTML = ''; if (onConfirm) onConfirm(); });
            document.getElementById('modalOverlay').addEventListener('click', (e) => { if (e.target === e.currentTarget) container.innerHTML = ''; });
        }

        // Procesa par&aacute;metros URL
        const urlParams = new URLSearchParams(window.location.search);
        const origenParam = urlParams.get('origen');
        const destinoParam = urlParams.get('destino');

        if (origenParam) {
            let found = false;
            Array.from(selectOrigen.options).forEach(opt => {
                const optCode = (opt.dataset.codigo || '').toUpperCase();
                const param = origenParam.toUpperCase();
                if (opt.value && optCode.includes(param)) {
                    selectOrigen.value = opt.value;
                    found = true;
                }
            });

            if (found) {
                selectOrigen.dispatchEvent(new Event('change'));
                if (destinoParam) {
                    setTimeout(() => {
                        let destFound = false;
                        const param = destinoParam.toUpperCase();
                        Array.from(selectDestino.options).forEach(opt => {
                            const optCode = (opt.dataset.codigo || '').toUpperCase();
                            if (opt.value && optCode.includes(param)) {
                                selectDestino.value = opt.value;
                                selectDestino.disabled = false;
                                destFound = true;
                            }
                        });
                        if (destFound) {
                            selectDestino.dispatchEvent(new Event('change'));
                        }
                    }, 200);
                }
            }
        }

        // Filter chips
        function renderFilterChips() {
            const bar = document.getElementById('filtersBar');
            bar.innerHTML = '<span class="text-muted small fw-semibold me-1">Filtrar:</span>';

            const allChip = document.createElement('span');
            allChip.className = 'mrp-filter-chip active';
            allChip.innerHTML = '<i class="fa-solid fa-border-all"></i> Todos';
            allChip.addEventListener('click', () => {
                movimientosRows.forEach(r => r.style.display = '');
                renderFilterChips();
            });
            bar.appendChild(allChip);

            tiposDeposito.forEach(td => {
                const chip = document.createElement('span');
                chip.className = 'mrp-filter-chip';
                chip.innerHTML = `<i class="fa-solid fa-box fa-xs"></i> ${td.codigo}`;
                chip.addEventListener('click', () => {
                    movimientosRows.forEach(row => {
                        if (row.cells.length === 1) return;
                        row.style.display = (row.dataset.origenId == td.id) ? '' : 'none';
                    });
                    renderFilterChips();
                });
                bar.appendChild(chip);
            });
        }

        renderFilterChips();

        // Update stats
        function updateStats() {
            const total = movimientosRows.length;
            let compras = 0, ventas = 0;
            movimientosRows.forEach(row => {
                if (row.cells.length === 1) return;
                const origenId = row.dataset.origenId;
                const destinoId = row.dataset.destinoId;
                const origen = tiposDeposito.find(t => t.id == origenId);
                const destino = tiposDeposito.find(t => t.id == destinoId);
                if (origen && depositosCompra.some(c => origen.codigo.toUpperCase().includes(c))) compras++;
                if (destino && depositosCliente.some(c => destino.codigo.toUpperCase().includes(c))) ventas++;
            });
            document.getElementById('statTotal').textContent = total;
            document.getElementById('statCompras').textContent = compras;
            document.getElementById('statVentas').textContent = ventas;
            document.getElementById('statInternos').textContent = total - compras - ventas;
        }
        updateStats();

        // Submit
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            syncFechaHoraLimits(false);

            if (inputFechaHora && inputFechaHora.value > inputFechaHora.max) {
                showToast('La Fecha/Hora no puede ser superior al momento actual de Buenos Aires.', 'error');
                inputFechaHora.value = inputFechaHora.max;
                inputFechaHora.focus();
                return;
            }

            if (!parteSeleccionada) {
                showToast('Debe seleccionar una parte/variante', 'error');
                return;
            }

            const formData = new FormData(form);
            const data = Object.fromEntries(formData);

            if (!data.deposito_origen || !data.deposito_destino || !data.cantidad || parseFloat(data.cantidad) <= 0) {
                showToast('Por favor complete todos los campos requeridos correctamente.', 'error');
                return;
            }

            if (esCompra() && (!data.importe_total || parseFloat(data.importe_total) <= 0)) {
                showToast('En compras debe ingresar un Importe Total mayor a 0.', 'error');
                inputImporteTotal.focus();
                return;
            }

            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Guardando...';

            fetch('<?= url("transacciones/movimientos-partes") ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast(result.message || 'Movimiento guardado exitosamente');
                    form.reset();
                    inputMovimientoId.value = '';
                    setFormModeEditing(false);
                    syncFechaHoraLimits(true);
                    parteSeleccionada = null;
                    inputParteHidden.value = '';
                    searchInput.value = '';
                    setSearchLockedState(false);
                    selectDestino.disabled = true;
                    selectDestino.innerHTML = '<option value="">Primero seleccione origen</option>';
                    selectUM.disabled = true;
                    selectUM.innerHTML = '<option value="">Seleccione una parte primero</option>';
                    umTipoLabel.textContent = '';
                    if (parteSelectedDetail) parteSelectedDetail.classList.add('d-none');
                    if (flowVisual) flowVisual.classList.add('d-none');
                    fieldsCompra.forEach(f => f.classList.add('d-none'));
                    fieldsStd.forEach(f => f.classList.remove('d-none'));
                    window.location.reload();
                } else {
                    showToast('Error: ' + (result.message || 'Ocurri&oacute; un error desconocido'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error de conexi&oacute;n al guardar el movimiento', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = inputMovimientoId.value ? submitTextEdit : originalText;
            });
        });

        // Bind edit buttons
        document.querySelectorAll('.btn-editar-movimiento').forEach(btn => {
            btn.addEventListener('click', function() {
                cargarMovimientoParaEdicion(this);
            });
        });
    });
</script>