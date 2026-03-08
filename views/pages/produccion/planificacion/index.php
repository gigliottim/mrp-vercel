<?php

/**
 * Vista: Planificación de Recursos - Index
 */
?>

<div class="container-fluid py-4" x-data="planificacionIndex()">
    <!-- Header -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Planificación de Recursos</h5>
                <div>
                    <a href="<?= url('produccion/planificacion/gantt') ?>" class="btn btn-primary">
                        <i class="fas fa-chart-bar"></i> Vista Gantt
                    </a>
                </div>
            </div>

            <!-- Filtros -->
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Centro de Trabajo</label>
                    <select name="centro_id" class="form-select" x-model="centroId" @change="$el.form.submit()">
                        <option value="">Todos los centros</option>
                        <template x-for="centro in centros" :key="centro.id">
                            <option :value="centro.id" x-text="centro.nombre"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="<?= esc($filters['fecha_inicio'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Fin</label>
                    <input type="date" name="fecha_fin" class="form-control" value="<?= esc($filters['fecha_fin'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-secondary d-block w-100">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Alertas de Conflictos -->
    <?php if (!empty($conflictos)): ?>
        <div class="alert alert-warning">
            <h6 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Conflictos Detectados</h6>
            <ul class="mb-0">
                <?php foreach ($conflictos as $conflicto): ?>
                    <li>
                        Centro: <strong><?= esc($conflicto['centro_nombre']) ?></strong> -
                        <?= esc(app_format_datetime($conflicto['fecha_inicio'], true)) ?>
                        (<?= $conflicto['ordenes_afectadas'] ?> órdenes)
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Lista de Planificaciones -->
    <div class="card">
        <div class="card-body">
            <h6 class="card-title">Asignaciones de Recursos</h6>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Orden</th>
                            <th>Operación</th>
                            <th>Centro de Trabajo</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Fin</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($planificaciones)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No hay planificaciones en el período seleccionado
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($planificaciones as $plan): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('produccion/ordenes/' . $plan['orden_id']) ?>">
                                            <?= esc($plan['orden_numero']) ?>
                                        </a>
                                    </td>
                                    <td><?= esc($plan['operacion_nombre'] ?? '-') ?></td>
                                    <td>
                                        <strong><?= esc($plan['centro_nombre']) ?></strong>
                                        <br><small class="text-muted"><?= esc($plan['centro_tipo']) ?></small>
                                    </td>
                                    <td><?= esc(app_format_datetime($plan['fecha_inicio'], true)) ?></td>
                                    <td><?= esc(app_format_datetime($plan['fecha_fin'], true)) ?></td>
                                    <td>
                                        <?php
                                        $estadoClass = [
                                            'programado' => 'info',
                                            'confirmado' => 'success',
                                            'en_proceso' => 'primary',
                                            'completado' => 'success',
                                            'cancelado' => 'danger'
                                        ];
                                        $class = $estadoClass[$plan['estado']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $class ?>"><?= ucfirst($plan['estado']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-warning" @click="editarAsignacion(<?= $plan['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" @click="eliminarAsignacion(<?= $plan['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Resumen por Centro -->
    <div class="row mt-4">
        <div class="col-12">
            <h6>Carga de Trabajo por Centro</h6>
        </div>
        <template x-for="centro in centrosConCarga" :key="centro.id">
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 x-text="centro.nombre"></h6>
                        <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar" :class="centro.porcentaje_uso > 90 ? 'bg-danger' : (centro.porcentaje_uso > 70 ? 'bg-warning' : 'bg-success')"
                                :style="'width: ' + Math.min(centro.porcentaje_uso, 100) + '%'">
                                <span x-text="window.appFormatNumber(centro.porcentaje_uso || 0) + '%'"></span>
                            </div>
                        </div>
                        <small class="text-muted">
                            <span x-text="centro.horas_asignadas"></span>h /
                            <span x-text="centro.capacidad_total"></span>h
                        </small>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>

<script>
    function planificacionIndex() {
        return {
            centroId: <?= json_encode($filters['centro_id'] ?? '') ?>,
            centros: [],
            centrosConCarga: [],

            async init() {
                await this.cargarCentros();
                await this.cargarCargaTrabajo();
            },

            async cargarCentros() {
                try {
                    const response = await fetch('/produccion/centros-trabajo/json');
                    this.centros = await response.json();
                } catch (error) {
                    console.error('Error:', error);
                }
            },

            async cargarCargaTrabajo() {
                // Cargar datos de carga por centro
                this.centrosConCarga = [];
            },

            async editarAsignacion(id) {
                // Implementar edición
                alert('Editar asignación ' + id);
            },

            async eliminarAsignacion(id) {
                if (!confirm('¿Eliminar esta asignación?')) return;

                try {
                    const response = await fetch(`/produccion/planificacion/${id}`, {
                        method: 'DELETE',
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
                }
            }
        };
    }
</script>
