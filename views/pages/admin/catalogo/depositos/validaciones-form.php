<?php

use App\Core\View\View;

$tipoOrigen = $tipoOrigen ?? null;
$tiposDeposito = $tiposDeposito ?? [];
$destinosPermitidosIds = $destinosPermitidosIds ?? [];
$errors = $errors ?? [];
$old = $old ?? [];

if ($tipoOrigen === null) {
    echo '<div class="alert alert-danger">Error: Tipo de depósito no encontrado</div>';
    return;
}

$oldValue = static function (string $field, $default = '') use ($old) {
    return array_key_exists($field, $old) ? $old[$field] : $default;
};

?>
<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Configuración › Validaciones</p>
            <h1 class="h3 mb-0">Configurar Destinos Permitidos</h1>
            <p class="text-muted small mb-0">
                Tipo de depósito origen: <strong><?= View::escape($tipoOrigen['nombre']) ?></strong>
                <span class="badge bg-secondary-subtle text-secondary"><?= View::escape($tipoOrigen['codigo']) ?></span>
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/configuracion/depositos-validaciones') ?>">
            <i class="fa-solid fa-arrow-left me-1"></i> Volver
        </a>
    </div>
</section>

<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-body">
                <h2 class="h5 mb-3">Seleccione los Tipos de Depósito Destino Permitidos</h2>

                <?php if (isset($errors['general'])) : ?>
                    <div class="alert alert-danger">
                        <i class="fa-solid fa-circle-exclamation me-2"></i>
                        <?= View::escape($errors['general']) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= url('/configuracion/depositos-validaciones/' . (int) $tipoOrigen['id']) ?>">
                    <input type="hidden" name="_method" value="PUT">

                    <div class="mb-4">
                        <div class="row g-3">
                            <?php foreach ($tiposDeposito as $tipo) : ?>
                                <?php
                                $tipoId = (int) $tipo['id'];
                                $disabled = $tipoId === (int) $tipoOrigen['id'];
                                $checked = in_array($tipoId, $destinosPermitidosIds, true);
                                $checkboxId = 'destino-' . $tipoId;
                                ?>
                                <div class="col-12 col-md-6">
                                    <div class="form-check form-switch p-3 border rounded <?= $disabled ? 'bg-light' : ($checked ? 'bg-success-subtle border-success' : '') ?>">
                                        <input class="form-check-input"
                                            type="checkbox"
                                            name="destinos[]"
                                            value="<?= $tipoId ?>"
                                            id="<?= $checkboxId ?>"
                                            <?= $checked ? 'checked' : '' ?>
                                            <?= $disabled ? 'disabled' : '' ?>>
                                        <label class="form-check-label w-100 <?= $disabled ? 'text-muted' : '' ?>" for="<?= $checkboxId ?>">
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div>
                                                    <div class="fw-semibold">
                                                        <?= View::escape($tipo['nombre']) ?>
                                                        <?php if ($disabled) : ?>
                                                            <span class="badge bg-secondary-subtle text-secondary ms-1">Mismo origen</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted"><?= View::escape($tipo['codigo']) ?></small>
                                                    <?php if ($tipo['descripcion']) : ?>
                                                        <div class="small text-muted mt-1">
                                                            <?= View::escape($tipo['descripcion']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!$disabled) : ?>
                                                    <i class="fa-solid fa-arrow-right text-success ms-2 <?= $checked ? '' : 'd-none' ?>" style="transition: opacity 0.2s;"></i>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($tiposDeposito === []) : ?>
                            <div class="alert alert-warning">
                                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                                No hay tipos de depósito disponibles para configurar destinos.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Observaciones <span class="text-muted small">(opcional)</span></label>
                        <textarea class="form-control"
                            name="observaciones"
                            rows="3"
                            placeholder="Agregue notas sobre esta configuración..."><?= View::escape($oldValue('observaciones')) ?></textarea>
                        <small class="form-text text-muted">
                            Estas observaciones se aplicarán a todos los movimientos configurados para este origen
                        </small>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <a class="btn btn-secondary" href="<?= url('/configuracion/depositos-validaciones') ?>">
                            Cancelar
                        </a>
                        <button class="btn btn-primary" type="submit">
                            <i class="fa-solid fa-save me-1"></i>
                            Guardar Configuración
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card border-primary">
            <div class="card-body">
                <div class="text-center mb-3">
                    <i class="fa-solid fa-lightbulb fa-3x text-primary mb-2"></i>
                    <h3 class="h6 mb-0">Información</h3>
                </div>

                <div class="small">
                    <h4 class="h6 mb-2">
                        <i class="fa-solid fa-warehouse text-primary me-1"></i>
                        Depósito Origen
                    </h4>
                    <div class="mb-3 ps-3">
                        <div class="fw-semibold"><?= View::escape($tipoOrigen['nombre']) ?></div>
                        <div class="text-muted"><?= View::escape($tipoOrigen['codigo']) ?></div>
                    </div>

                    <h4 class="h6 mb-2">
                        <i class="fa-solid fa-arrow-right text-success me-1"></i>
                        ¿Qué hace esto?
                    </h4>
                    <ul class="mb-3 ps-3 text-muted">
                        <li class="mb-2">Marque los tipos de depósito a los que <strong>SÍ</strong> se pueden realizar movimientos desde este origen</li>
                        <li class="mb-2">Los tipos no marcados <strong>NO</strong> estarán disponibles como destino</li>
                        <li class="mb-2">Esta configuración se aplica automáticamente en todos los formularios</li>
                    </ul>

                    <div class="alert alert-info mb-0 py-2">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        <strong>Nota:</strong> No puede seleccionar el mismo tipo como destino (los movimientos deben ser entre tipos diferentes)
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h3 class="h6 mb-2">
                    <i class="fa-solid fa-chart-simple me-1"></i>
                    Resumen
                </h3>
                <div class="small">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total tipos disponibles:</span>
                        <span class="badge bg-secondary-subtle text-secondary"><?= count($tiposDeposito) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Destinos seleccionados:</span>
                        <span class="badge bg-success-subtle text-success" id="count-selected"><?= count($destinosPermitidosIds) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Actualizar contador de destinos seleccionados
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxes = document.querySelectorAll('input[name="destinos[]"]:not([disabled])');
        const countElement = document.getElementById('count-selected');
        const icons = document.querySelectorAll('.fa-arrow-right');

        function updateCount() {
            const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            if (countElement) {
                countElement.textContent = checkedCount;
            }
        }

        checkboxes.forEach((checkbox, index) => {
            checkbox.addEventListener('change', function() {
                updateCount();
                // Mostrar/ocultar icono de flecha
                const icon = this.closest('.form-check').querySelector('.fa-arrow-right');
                if (icon) {
                    icon.classList.toggle('d-none', !this.checked);
                }
                // Cambiar estilo del contenedor
                const container = this.closest('.form-check');
                if (this.checked) {
                    container.classList.add('bg-success-subtle', 'border-success');
                } else {
                    container.classList.remove('bg-success-subtle', 'border-success');
                }
            });
        });
    });
</script>
