<?php

/**
 * Vista: Detalle de Centro de Trabajo
 */
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Información Principal -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?= esc($centro['nombre']) ?></h5>
                    <div>
                        <a href="<?= url('produccion/centros-trabajo/' . $centro['id'] . '/edit') ?>" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit"></i> Editar
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-5">Código:</dt>
                                <dd class="col-sm-7"><?= esc($centro['codigo']) ?></dd>

                                <dt class="col-sm-5">Tipo:</dt>
                                <dd class="col-sm-7">
                                    <span class="badge bg-secondary"><?= esc(ucfirst($centro['tipo'])) ?></span>
                                </dd>

                                <dt class="col-sm-5">Capacidad:</dt>
                                <dd class="col-sm-7"><?= number_format($centro['capacidad_horas_dia'], 1) ?> horas/día</dd>

                                <dt class="col-sm-5">Eficiencia:</dt>
                                <dd class="col-sm-7">
                                    <span class="badge bg-success"><?= number_format($centro['eficiencia'] * 100, 0) ?>%</span>
                                </dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row">
                                <dt class="col-sm-5">Costo/Hora:</dt>
                                <dd class="col-sm-7">$<?= number_format($centro['costo_hora'], 2) ?></dd>

                                <dt class="col-sm-5">Estado:</dt>
                                <dd class="col-sm-7">
                                    <?php if ($centro['activo']): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </dd>

                                <dt class="col-sm-5">Creado:</dt>
                                <dd class="col-sm-7"><?= date('d/m/Y', strtotime($centro['created_at'])) ?></dd>
                            </dl>
                        </div>
                    </div>

                    <?php if (!empty($centro['descripcion'])): ?>
                        <hr>
                        <h6>Descripción</h6>
                        <p><?= nl2br(esc($centro['descripcion'])) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($centro['configuracion'])): ?>
                        <hr>
                        <h6>Configuración</h6>
                        <div class="row">
                            <?php if (isset($centro['configuracion']['turnos'])): ?>
                                <div class="col-md-4">
                                    <small class="text-muted">Turnos por día:</small>
                                    <p class="mb-0"><strong><?= $centro['configuracion']['turnos'] ?></strong></p>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($centro['configuracion']['operadores_requeridos'])): ?>
                                <div class="col-md-4">
                                    <small class="text-muted">Operadores requeridos:</small>
                                    <p class="mb-0"><strong><?= $centro['configuracion']['operadores_requeridos'] ?></strong></p>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($centro['configuracion']['setup_minimo_minutos'])): ?>
                                <div class="col-md-4">
                                    <small class="text-muted">Setup mínimo:</small>
                                    <p class="mb-0"><strong><?= $centro['configuracion']['setup_minimo_minutos'] ?> min</strong></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Operaciones que usan este centro -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Operaciones Configuradas</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($operaciones)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Operación</th>
                                        <th>Secuencia</th>
                                        <th>Tiempo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($operaciones as $op): ?>
                                        <tr>
                                            <td><?= esc($op['producto_nombre']) ?></td>
                                            <td><?= esc($op['nombre_operacion']) ?></td>
                                            <td><?= $op['secuencia'] ?></td>
                                            <td><?= number_format($op['tiempo_setup'] + $op['tiempo_operacion'], 1) ?> min</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No hay operaciones configuradas para este centro</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Panel Lateral -->
        <div class="col-lg-4">
            <!-- Disponibilidad Actual -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">Disponibilidad</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted">Capacidad efectiva:</small>
                        <h4><?= number_format($centro['capacidad_horas_dia'] * $centro['eficiencia'], 1) ?>h/día</h4>
                    </div>
                    <div class="progress mb-2" style="height: 25px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: 60%">
                            Disponible 60%
                        </div>
                    </div>
                    <small class="text-muted">Basado en planificación actual</small>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Acciones</h6>
                </div>
                <div class="card-body d-grid gap-2">
                    <a href="<?= url('produccion/planificacion?centro_id=' . $centro['id']) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-calendar"></i> Ver Planificación
                    </a>
                    <a href="<?= url('produccion/rutas?centro_id=' . $centro['id']) ?>" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-route"></i> Ver Rutas
                    </a>
                    <button class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-chart-bar"></i> Estadísticas
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
