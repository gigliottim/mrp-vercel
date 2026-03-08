<?php

/**
 * Vista: Detalle de Orden de Producción
 */
?>

<div class="container-fluid py-4">
    <!-- Header con acciones -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h4 class="mb-1"><?= esc($orden['numero_orden']) ?></h4>
                    <p class="text-muted mb-0">Orden de Producción</p>
                </div>
                <div class="col-md-6 text-end">
                    <?php
                    $estadoClass = [
                        'borrador' => 'secondary',
                        'planificada' => 'info',
                        'liberada' => 'warning',
                        'en_proceso' => 'primary',
                        'pausada' => 'warning',
                        'completada' => 'success',
                        'cancelada' => 'danger',
                        'cerrada' => 'dark'
                    ];
                    $class = $estadoClass[$orden['estado']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?= $class ?> fs-5 me-2">
                        <?= ucfirst(str_replace('_', ' ', $orden['estado'])) ?>
                    </span>

                    <?php if (in_array($orden['estado'], ['borrador', 'planificada'])): ?>
                        <a href="<?= url('produccion/ordenes/' . $orden['id'] . '/edit') ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                    <?php endif; ?>

                    <div class="btn-group">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-cogs"></i> Acciones
                        </button>
                        <ul class="dropdown-menu">
                            <?php if ($orden['estado'] === 'planificada'): ?>
                                <li><a class="dropdown-item" href="#" onclick="cambiarEstado(<?= $orden['id'] ?>, 'liberada')">
                                        <i class="fas fa-unlock"></i> Liberar para Producción
                                    </a></li>
                            <?php endif; ?>

                            <?php if ($orden['estado'] === 'liberada'): ?>
                                <li><a class="dropdown-item" href="#" onclick="cambiarEstado(<?= $orden['id'] ?>, 'en_proceso')">
                                        <i class="fas fa-play"></i> Iniciar Producción
                                    </a></li>
                            <?php endif; ?>

                            <?php if ($orden['estado'] === 'en_proceso'): ?>
                                <li><a class="dropdown-item" href="#" onclick="cambiarEstado(<?= $orden['id'] ?>, 'pausada')">
                                        <i class="fas fa-pause"></i> Pausar Producción
                                    </a></li>
                                <li><a class="dropdown-item" href="#" onclick="cambiarEstado(<?= $orden['id'] ?>, 'completada')">
                                        <i class="fas fa-check"></i> Marcar Completada
                                    </a></li>
                            <?php endif; ?>

                            <?php if ($orden['estado'] === 'pausada'): ?>
                                <li><a class="dropdown-item" href="#" onclick="cambiarEstado(<?= $orden['id'] ?>, 'en_proceso')">
                                        <i class="fas fa-play"></i> Reanudar Producción
                                    </a></li>
                            <?php endif; ?>

                            <?php if ($orden['estado'] === 'completada'): ?>
                                <li><a class="dropdown-item" href="#" onclick="cambiarEstado(<?= $orden['id'] ?>, 'cerrada')">
                                        <i class="fas fa-lock"></i> Cerrar Orden
                                    </a></li>
                            <?php endif; ?>

                            <?php if (in_array($orden['estado'], ['borrador', 'planificada', 'liberada'])): ?>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="cambiarEstado(<?= $orden['id'] ?>, 'cancelada')">
                                        <i class="fas fa-times"></i> Cancelar Orden
                                    </a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Información Principal -->
        <div class="col-lg-8">
            <!-- Datos de la Orden -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Información General</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-5">Producto:</dt>
                                <dd class="col-sm-7"><?= esc($orden['producto_nombre']) ?></dd>

                                <dt class="col-sm-5">Variante:</dt>
                                <dd class="col-sm-7"><?= esc($orden['variante_codigo']) ?></dd>

                                <dt class="col-sm-5">BOM Utilizada:</dt>
                                <dd class="col-sm-7"><?= esc($orden['bom_nombre']) ?> (v<?= $orden['bom_version'] ?>)</dd>

                                <dt class="col-sm-5">Prioridad:</dt>
                                <dd class="col-sm-7">
                                    <?php
                                    $prioClass = ['baja' => 'secondary', 'normal' => 'primary', 'alta' => 'warning', 'urgente' => 'danger'];
                                    ?>
                                    <span class="badge bg-<?= $prioClass[$orden['prioridad']] ?>"><?= ucfirst($orden['prioridad']) ?></span>
                                </dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-5">Cantidad Plan.:</dt>
                                <dd class="col-sm-7"><strong><?= esc(app_format_number($orden['cantidad_planificada'])) ?></strong> unidades</dd>

                                <dt class="col-sm-5">Producida:</dt>
                                <dd class="col-sm-7 text-success"><strong><?= esc(app_format_number($orden['cantidad_producida'])) ?></strong></dd>

                                <dt class="col-sm-5">Desechada:</dt>
                                <dd class="col-sm-7 text-danger"><?= esc(app_format_number($orden['cantidad_desechada'])) ?></dd>

                                <dt class="col-sm-5">Avance:</dt>
                                <dd class="col-sm-7">
                                    <div class="progress" style="height: 20px;">
                                        <?php $avancePct = ($avance['porcentaje_avance'] ?? 0); ?>
                                        <div class="progress-bar <?= $avancePct >= 100 ? 'bg-success' : 'bg-primary' ?>"
                                            style="width: <?= min($avancePct, 100) ?>%">
                                            <?= esc(app_format_number($avancePct)) ?>%
                                        </div>
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-5">Inicio Program.:</dt>
                                <dd class="col-sm-7"><?= esc(app_format_datetime($orden['fecha_inicio_programada'], true)) ?></dd>

                                <dt class="col-sm-5">Inicio Real:</dt>
                                <dd class="col-sm-7"><?= $orden['fecha_inicio_real'] ? esc(app_format_datetime($orden['fecha_inicio_real'], true)) : '-' ?></dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-5">Fin Program.:</dt>
                                <dd class="col-sm-7"><?= esc(app_format_datetime($orden['fecha_fin_programada'], true)) ?></dd>

                                <dt class="col-sm-5">Fin Real:</dt>
                                <dd class="col-sm-7"><?= $orden['fecha_fin_real'] ? esc(app_format_datetime($orden['fecha_fin_real'], true)) : '-' ?></dd>
                            </dl>
                        </div>
                    </div>

                    <?php if (!empty($orden['observaciones'])): ?>
                        <hr>
                        <h6>Observaciones</h6>
                        <p class="mb-0"><?= nl2br(esc($orden['observaciones'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Timeline de Estado -->
            <?php include __DIR__ . '/_orden_timeline.php'; ?>
        </div>

        <!-- Panel Lateral -->
        <div class="col-lg-4">
            <!-- Planificación -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Planificación de Recursos</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($orden['planificaciones'])): ?>
                        <?php foreach ($orden['planificaciones'] as $plan): ?>
                            <div class="border rounded p-2 mb-2">
                                <strong><?= esc($plan['centro_nombre']) ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?= esc(app_format_datetime($plan['fecha_inicio'], true)) ?> -
                                    <?= esc(app_format_datetime($plan['fecha_fin'], true)) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted mb-2">Sin planificación asignada</p>
                        <a href="<?= url('produccion/planificacion/calcular?orden_id=' . $orden['id']) ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-calendar-plus"></i> Generar Planificación
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Materiales -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Materiales Requeridos</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($orden['materiales'])): ?>
                        <table class="table table-sm">
                            <tbody>
                                <?php foreach ($orden['materiales'] as $mat): ?>
                                    <tr>
                                        <td><?= esc($mat['parte_codigo']) ?></td>
                                        <td class="text-end"><?= esc(app_format_number($mat['cantidad'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted mb-0">No hay materiales definidos</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Metadatos JSONB -->
            <?php if (!empty($orden['metadatos'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Información Adicional</h6>
                    </div>
                    <div class="card-body">
                        <pre class="mb-0"><?= json_encode($orden['metadatos'], JSON_PRETTY_PRINT) ?></pre>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    async function cambiarEstado(ordenId, nuevoEstado) {
        const mensajes = {
            'liberada': '¿Liberar esta orden para producción?',
            'en_proceso': '¿Iniciar producción?',
            'pausada': '¿Pausar producción?',
            'completada': '¿Marcar como completada?',
            'cerrada': '¿Cerrar orden? Esta acción es permanente.',
            'cancelada': '¿Cancelar esta orden?'
        };

        if (!confirm(mensajes[nuevoEstado] || '¿Confirmar cambio de estado?')) {
            return;
        }

        try {
            const response = await fetch(`/produccion/ordenes/${ordenId}/${nuevoEstado}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();

            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + (result.errors || []).join(', '));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error al cambiar el estado');
        }
    }
</script>
