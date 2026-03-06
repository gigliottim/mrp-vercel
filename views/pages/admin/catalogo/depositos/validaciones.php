<?php

use App\Core\View\View;

$tiposDeposito = $tiposDeposito ?? [];
$movimientosPorOrigen = $movimientosPorOrigen ?? [];
$errors = $errors ?? [];
$success = $success ?? null;

?>
<section class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-uppercase text-muted small mb-1">Configuración</p>
            <h1 class="h3 mb-0">Validaciones de Movimientos</h1>
            <p class="text-muted small mb-0">Configure qué movimientos están permitidos entre tipos de depósitos</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= url('/configuracion/tipos-depositos') ?>">
            <i class="fa-solid fa-arrow-left me-1"></i> Tipos de Depósito
        </a>
    </div>
</section>

<?php if ($success) : ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i>
        Configuración guardada correctamente
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($errors['general'])) : ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa-solid fa-circle-exclamation me-2"></i>
        <?= View::escape($errors['general']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="h5 mb-1">Configuración de Movimientos Permitidos</h2>
                        <p class="text-muted small mb-0">
                            Seleccione un tipo de depósito origen para configurar a qué tipos de destino puede realizar movimientos
                        </p>
                    </div>
                    <span class="badge bg-primary-subtle text-primary">
                        <?= count($tiposDeposito) ?> tipos
                    </span>
                </div>

                <?php if ($tiposDeposito === []) : ?>
                    <div class="alert alert-warning">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        No hay tipos de depósito configurados. <a href="<?= url('/configuracion/tipos-depositos') ?>">Crear tipos de depósito</a>
                    </div>
                <?php else : ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 20%">Tipo Origen</th>
                                    <th style="width: 50%">Destinos Permitidos</th>
                                    <th style="width: 15%" class="text-center">Total</th>
                                    <th style="width: 15%"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tiposDeposito as $tipo) : ?>
                                    <?php
                                    $tipoId = (int) $tipo['id'];
                                    $movimientos = $movimientosPorOrigen[$tipoId] ?? null;
                                    $destinos = $movimientos ? $movimientos['destinos'] : [];
                                    $tieneDestinos = !empty($destinos);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="fa-solid fa-warehouse text-primary"></i>
                                                <div>
                                                    <div class="fw-semibold"><?= View::escape($tipo['nombre']) ?></div>
                                                    <small class="text-muted"><?= View::escape($tipo['codigo']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($tieneDestinos) : ?>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php foreach ($destinos as $destino) : ?>
                                                        <span class="badge <?= $destino['activo'] ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>">
                                                            <i class="fa-solid fa-arrow-right me-1"></i>
                                                            <?= View::escape($destino['nombre']) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else : ?>
                                                <span class="text-muted small">
                                                    <i class="fa-solid fa-circle-xmark me-1"></i>
                                                    Sin destinos configurados
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $tieneDestinos ? 'bg-info-subtle text-info' : 'bg-secondary-subtle text-secondary' ?>">
                                                <?= count($destinos) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a class="btn btn-outline-primary"
                                                    href="<?= url('/configuracion/depositos-validaciones/' . $tipoId . '/editar') ?>"
                                                    title="Configurar destinos">
                                                    <i class="fa-solid fa-cog"></i>
                                                    <span class="d-none d-lg-inline ms-1">Configurar</span>
                                                </a>
                                                <?php if ($tieneDestinos) : ?>
                                                    <form method="post"
                                                        action="<?= url('/configuracion/depositos-validaciones/' . $tipoId) ?>"
                                                        onsubmit="return confirm('¿Eliminar todas las configuraciones de destinos para este tipo de depósito?');"
                                                        class="d-inline">
                                                        <input type="hidden" name="_method" value="DELETE">
                                                        <button class="btn btn-outline-danger" type="submit" title="Eliminar configuración">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Panel informativo -->
    <div class="col-12">
        <div class="card border-info">
            <div class="card-body">
                <div class="d-flex gap-3">
                    <div class="text-info">
                        <i class="fa-solid fa-circle-info fa-2x"></i>
                    </div>
                    <div>
                        <h3 class="h6 mb-2">¿Cómo funciona?</h3>
                        <ul class="mb-0 small text-muted">
                            <li>Configure para cada <strong>tipo de depósito origen</strong> a qué <strong>tipos de destino</strong> puede realizar movimientos</li>
                            <li>Esta configuración se aplica automáticamente en todos los formularios de movimientos de inventario</li>
                            <li>Los usuarios solo podrán seleccionar depósitos destino que estén permitidos según estas reglas</li>
                            <li>Esto ayuda a prevenir errores y mantener la integridad del proceso de movimientos</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
