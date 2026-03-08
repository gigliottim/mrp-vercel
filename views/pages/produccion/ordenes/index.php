<?php

/**
 * Vista: Listado de Órdenes de Producción
 */
?>

<div class="container-fluid py-4">
    <!-- Dashboard -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h6 class="text-muted small mb-1">Borradores</h6>
                    <h3 class="mb-0"><?= $dashboard['borradores'] ?? 0 ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center bg-info text-white">
                <div class="card-body">
                    <h6 class="small mb-1">Planificadas</h6>
                    <h3 class="mb-0"><?= $dashboard['planificadas'] ?? 0 ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center bg-warning text-white">
                <div class="card-body">
                    <h6 class="small mb-1">Liberadas</h6>
                    <h3 class="mb-0"><?= $dashboard['liberadas'] ?? 0 ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center bg-primary text-white">
                <div class="card-body">
                    <h6 class="small mb-1">En Proceso</h6>
                    <h3 class="mb-0"><?= $dashboard['en_proceso'] ?? 0 ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center bg-success text-white">
                <div class="card-body">
                    <h6 class="small mb-1">Completadas</h6>
                    <h3 class="mb-0"><?= $dashboard['completadas'] ?? 0 ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center bg-secondary text-white">
                <div class="card-body">
                    <h6 class="small mb-1">Cerradas</h6>
                    <h3 class="mb-0"><?= $dashboard['cerradas'] ?? 0 ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Órdenes de Producción</h5>
                <a href="<?= url('produccion/ordenes/create') ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nueva Orden
                </a>
            </div>

            <form method="GET" action="<?= url('produccion/ordenes') ?>" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Buscar..."
                        value="<?= esc($filters['search'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <select name="estado" class="form-select">
                        <option value="">Todos los estados</option>
                        <option value="borrador" <?= ($filters['estado'] ?? '') === 'borrador' ? 'selected' : '' ?>>Borrador</option>
                        <option value="planificada" <?= ($filters['estado'] ?? '') === 'planificada' ? 'selected' : '' ?>>Planificada</option>
                        <option value="liberada" <?= ($filters['estado'] ?? '') === 'liberada' ? 'selected' : '' ?>>Liberada</option>
                        <option value="en_proceso" <?= ($filters['estado'] ?? '') === 'en_proceso' ? 'selected' : '' ?>>En Proceso</option>
                        <option value="completada" <?= ($filters['estado'] ?? '') === 'completada' ? 'selected' : '' ?>>Completada</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="prioridad" class="form-select">
                        <option value="">Todas prioridades</option>
                        <option value="baja" <?= ($filters['prioridad'] ?? '') === 'baja' ? 'selected' : '' ?>>Baja</option>
                        <option value="normal" <?= ($filters['prioridad'] ?? '') === 'normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="alta" <?= ($filters['prioridad'] ?? '') === 'alta' ? 'selected' : '' ?>>Alta</option>
                        <option value="urgente" <?= ($filters['prioridad'] ?? '') === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="fecha_desde" class="form-control" value="<?= esc($filters['fecha_desde'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="fecha_hasta" class="form-control" value="<?= esc($filters['fecha_hasta'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-secondary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Órdenes -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Fechas</th>
                            <th>Prioridad</th>
                            <th>Estado</th>
                            <th>Avance</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ordenes)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No se encontraron órdenes de producción
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ordenes as $orden): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('produccion/ordenes/' . $orden['id']) ?>">
                                            <strong><?= esc($orden['numero_orden']) ?></strong>
                                        </a>
                                    </td>
                                    <td>
                                        <?= esc($orden['producto_nombre'] ?? 'N/A') ?>
                                        <br><small class="text-muted"><?= esc($orden['variante_codigo'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <?= esc(app_format_number($orden['cantidad_planificada'])) ?>
                                        <?php if ($orden['cantidad_producida'] > 0): ?>
                                            <br><small class="text-success"><?= esc(app_format_number($orden['cantidad_producida'])) ?> prod.</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?= esc(app_format_datetime($orden['fecha_inicio_programada'], false)) ?>
                                            <br>
                                            <?= esc(app_format_datetime($orden['fecha_fin_programada'], false)) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php
                                        $prioridadClass = [
                                            'baja' => 'secondary',
                                            'normal' => 'primary',
                                            'alta' => 'warning',
                                            'urgente' => 'danger'
                                        ];
                                        $class = $prioridadClass[$orden['prioridad']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $class ?>"><?= ucfirst($orden['prioridad']) ?></span>
                                    </td>
                                    <td>
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
                                        <span class="badge bg-<?= $class ?>"><?= ucfirst(str_replace('_', ' ', $orden['estado'])) ?></span>
                                    </td>
                                    <td>
                                        <?php $avance = ($orden['cantidad_producida'] / $orden['cantidad_planificada']) * 100; ?>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar"
                                                style="width: <?= min($avance, 100) ?>%"
                                                aria-valuenow="<?= $avance ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?= esc(app_format_number($avance)) ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= url('produccion/ordenes/' . $orden['id']) ?>" class="btn btn-info" title="Ver">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($orden['estado'] !== 'cerrada' && $orden['estado'] !== 'cancelada'): ?>
                                                <a href="<?= url('produccion/ordenes/' . $orden['id'] . '/edit') ?>" class="btn btn-warning" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($orden['estado'] === 'liberada'): ?>
                                                <button class="btn btn-success btn-iniciar" data-id="<?= $orden['id'] ?>" title="Iniciar">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Iniciar orden
        document.querySelectorAll('.btn-iniciar').forEach(btn => {
            btn.addEventListener('click', async function() {
                const id = this.dataset.id;

                if (!confirm('¿Iniciar producción de esta orden?')) return;

                try {
                    const response = await fetch(`/produccion/ordenes/${id}/iniciar`, {
                        method: 'POST'
                    });

                    const result = await response.json();

                    if (result.success) {
                        location.reload();
                    } else {
                        alert(result.errors ? result.errors.join(', ') : 'Error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                }
            });
        });
    });
</script>
