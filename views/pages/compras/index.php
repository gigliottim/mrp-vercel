<?php

/**
 * Vista: Listado de Compras
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1"><i class="fas fa-shopping-cart text-primary"></i> Registro de Compras</h3>
            <p class="text-muted mb-0">Historial de compras y costos</p>
        </div>
        <a href="<?= url('compras/create') ?>" class="btn btn-primary btn-lg shadow-sm">
            <i class="fas fa-plus-circle me-2"></i> Registrar Nueva Compra
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> Compra registrada exitosamente. El costo del ítem ha sido actualizado.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary">
                        <tr>
                            <th class="ps-3 py-3">Fecha</th>
                            <th class="py-3">Ítem / Variante</th>
                            <th class="text-end py-3">Cant. Uso</th>
                            <th class="text-end py-3">Cant. Compra</th>
                            <th class="text-end py-3">Costo Unit.</th>
                            <th class="text-end py-3">Total</th>
                            <th class="py-3">Proveedor / Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="border-top-0">
                        <?php if (empty($compras)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <div class="py-4">
                                        <i class="fas fa-shopping-basket fa-3x mb-3 text-light"></i>
                                        <p class="mb-0">No hay compras registradas aún.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
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
                            ?>
                            <?php foreach ($compras as $compra): ?>
                                <?php
                                $factorConversion = isset($compra['factor_conversion']) ? (float) $compra['factor_conversion'] : 1.0;
                                if ($factorConversion <= 0) {
                                    $factorConversion = 1.0;
                                }
                                $cantidadUso = (float) ($compra['cantidad'] ?? 0);
                                $cantidadCompra = $cantidadUso / $factorConversion;
                                $umUso = (string) ($compra['um_uso_simbolo'] ?? 'u.');
                                $umCompra = (string) ($compra['um_compra_simbolo'] ?? 'u.');
                                ?>
                                <tr>
                                    <td class="ps-3 text-nowrap text-secondary small"><?= htmlspecialchars(app_format_datetime($compra['fecha'], false)) ?></td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-dark text-decoration-none">
                                                <?= htmlspecialchars($compra['codigo_variante']) ?>
                                            </span>
                                            <small class="text-muted text-truncate" style="max-width: 250px;">
                                                <?= htmlspecialchars($compra['variante_detalle']) ?>
                                            </small>
                                            <small class="text-xs text-primary bg-light px-1 rounded d-inline-block mt-1" style="width: fit-content;">
                                                <?= htmlspecialchars($compra['parte_codigo']) ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td class="text-end font-monospace align-middle fw-bold">
                                        <?= htmlspecialchars($formatCompactQty($cantidadUso) . ' ' . $umUso) ?>
                                    </td>
                                    <td class="text-end font-monospace align-middle">
                                        <?= htmlspecialchars($formatCompactQty($cantidadCompra) . ' ' . $umCompra) ?>
                                    </td>
                                    <td class="text-end font-monospace align-middle text-secondary">
                                        $<?= htmlspecialchars($formatCompactQty((float)$compra['precio_unitario'])) ?>
                                    </td>
                                    <td class="text-end fw-bold font-monospace align-middle text-success bg-light">
                                        $<?= htmlspecialchars($formatCompactQty((float)$compra['cantidad'] * (float)$compra['precio_unitario'])) ?>
                                    </td>
                                    <td class="small align-middle">
                                        <?php if ($compra['proveedor']): ?>
                                            <div class="mb-1"><i class="fas fa-truck text-muted me-1"></i><?= htmlspecialchars($compra['proveedor']) ?></div>
                                        <?php endif; ?>

                                        <?php if (!empty($compra['observaciones'])): ?>
                                            <div class="text-muted fst-italic border-start border-3 border-info ps-2 mt-1" style="font-size: 0.85em;">
                                                <?= nl2br(htmlspecialchars($compra['observaciones'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-top-0 py-3">
            <small class="text-muted">Mostrando las últimas 50 compras</small>
        </div>
    </div>
</div>
