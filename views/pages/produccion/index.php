<?php

/**
 * Vista: Dashboard Principal de Operaciones de Producción
 */
?>

<div class="container-fluid py-4">
    <!-- Hero Section -->
    <div class="card bg-gradient-primary text-white mb-4">
        <div class="card-body py-5">
            <h2 class="mb-2">Operaciones de Producción</h2>
            <p class="mb-0 opacity-75">Gestión completa de centros de trabajo, rutas, órdenes y planificación</p>
        </div>
    </div>

    <!-- Métricas Principales -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-industry fa-2x text-primary mb-2"></i>
                    <h3 class="mb-1"><?= $metricas['centros_activos'] ?? 0 ?></h3>
                    <p class="text-muted mb-0">Centros Activos</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-route fa-2x text-info mb-2"></i>
                    <h3 class="mb-1"><?= $metricas['rutas_configuradas'] ?? 0 ?></h3>
                    <p class="text-muted mb-0">Rutas Configuradas</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-cogs fa-2x text-warning mb-2"></i>
                    <h3 class="mb-1"><?= $metricas['ordenes_activas'] ?? 0 ?></h3>
                    <p class="text-muted mb-0">Órdenes en Proceso</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-check fa-2x text-success mb-2"></i>
                    <h3 class="mb-1"><?= number_format($metricas['capacidad_utilizada'] ?? 0, 0) ?>%</h3>
                    <p class="text-muted mb-0">Capacidad Utilizada</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Accesos Rápidos -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt"></i> Acciones Rápidas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="<?= url('produccion/ordenes/create') ?>" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-plus-circle"></i> Nueva Orden de Producción
                        </a>
                        <a href="<?= url('produccion/rutas/create') ?>" class="btn btn-outline-info btn-lg">
                            <i class="fas fa-route"></i> Configurar Nueva Ruta
                        </a>
                        <a href="<?= url('produccion/centros-trabajo/create') ?>" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-industry"></i> Registrar Centro de Trabajo
                        </a>
                        <a href="<?= url('produccion/planificacion/gantt') ?>" class="btn btn-outline-success btn-lg">
                            <i class="fas fa-chart-gantt"></i> Ver Gantt de Planificación
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-exclamation-triangle"></i> Alertas y Pendientes
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($alertas)): ?>
                        <ul class="list-group">
                            <?php foreach ($alertas as $alerta): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-<?= $alerta['icon'] ?> text-<?= $alerta['color'] ?>"></i>
                                        <?= esc($alerta['mensaje']) ?>
                                    </div>
                                    <a href="<?= esc($alerta['url']) ?>" class="btn btn-sm btn-outline-primary">
                                        Ver
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-check-circle fa-3x mb-2"></i>
                            <p class="mb-0">No hay alertas pendientes</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Módulos Principales -->
    <div class="row">
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100 hover-shadow">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-industry fa-4x text-primary"></i>
                    </div>
                    <h5>Centros de Trabajo</h5>
                    <p class="text-muted small">Gestión de máquinas, celdas y recursos productivos</p>
                    <a href="<?= url('produccion/centros-trabajo') ?>" class="btn btn-primary w-100">
                        Gestionar Centros
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100 hover-shadow">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-route fa-4x text-info"></i>
                    </div>
                    <h5>Rutas de Producción</h5>
                    <p class="text-muted small">Definición de operaciones y secuencias</p>
                    <a href="<?= url('produccion/rutas') ?>" class="btn btn-info w-100">
                        Ver Rutas
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100 hover-shadow">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-clipboard-list fa-4x text-warning"></i>
                    </div>
                    <h5>Órdenes de Producción</h5>
                    <p class="text-muted small">Control de órdenes y avance de fabricación</p>
                    <a href="<?= url('produccion/ordenes') ?>" class="btn btn-warning w-100">
                        Ver Órdenes
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3 mb-4">
            <div class="card h-100 hover-shadow">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-calendar-alt fa-4x text-success"></i>
                    </div>
                    <h5>Planificación</h5>
                    <p class="text-muted small">Asignación de recursos y programación</p>
                    <a href="<?= url('produccion/planificacion') ?>" class="btn btn-success w-100">
                        Ver Planificación
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Órdenes Recientes -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Órdenes Recientes</h5>
            <a href="<?= url('produccion/ordenes') ?>" class="btn btn-sm btn-outline-primary">
                Ver todas
            </a>
        </div>
        <div class="card-body">
            <?php if (!empty($ordenes_recientes)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Producto</th>
                                <th>Estado</th>
                                <th>Prioridad</th>
                                <th>Avance</th>
                                <th>Fecha Fin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ordenes_recientes as $orden): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('produccion/ordenes/' . $orden['id']) ?>">
                                            <?= esc($orden['numero_orden']) ?>
                                        </a>
                                    </td>
                                    <td><?= esc($orden['producto_nombre']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $orden['estado_color'] ?>">
                                            <?= esc($orden['estado_texto']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $orden['prioridad_color'] ?>">
                                            <?= esc($orden['prioridad']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="progress" style="width: 100px; height: 20px;">
                                            <div class="progress-bar" style="width: <?= $orden['avance'] ?>%">
                                                <?= $orden['avance'] ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($orden['fecha_fin_programada'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted text-center mb-0">No hay órdenes registradas</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .bg-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .hover-shadow {
        transition: all 0.3s;
    }

    .hover-shadow:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
</style>
