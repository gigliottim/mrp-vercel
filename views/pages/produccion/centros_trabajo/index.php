<?php

/**
 * Vista: Listado de Centros de Trabajo
 */
?>

<div class="container-fluid py-4">
    <!-- Dashboard Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Centros Activos</h6>
                    <h2 class="mb-0"><?= $dashboard['total_activos'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Capacidad Total</h6>
                    <h2 class="mb-0"><?= number_format($dashboard['capacidad_total'] ?? 0, 1) ?>h</h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">En Uso</h6>
                    <h2 class="mb-0"><?= $dashboard['en_uso'] ?? 0 ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Eficiencia Promedio</h6>
                    <h2 class="mb-0"><?= number_format(($dashboard['eficiencia_promedio'] ?? 0) * 100, 1) ?>%</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Header con Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Centros de Trabajo</h5>
                <a href="<?= url('produccion/centros-trabajo/create') ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo Centro
                </a>
            </div>

            <!-- Filtros -->
            <form method="GET" action="<?= url('produccion/centros-trabajo') ?>" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Buscar..." value="<?= esc($filters['search'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <select name="tipo" class="form-select">
                        <option value="">Todos los tipos</option>
                        <option value="maquina" <?= ($filters['tipo'] ?? '') === 'maquina' ? 'selected' : '' ?>>Máquina</option>
                        <option value="manual" <?= ($filters['tipo'] ?? '') === 'manual' ? 'selected' : '' ?>>Manual</option>
                        <option value="celda" <?= ($filters['tipo'] ?? '') === 'celda' ? 'selected' : '' ?>>Celda</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="activo" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" <?= ($filters['activo'] ?? '') === '1' ? 'selected' : '' ?>>Activo</option>
                        <option value="0" <?= ($filters['activo'] ?? '') === '0' ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a href="<?= url('produccion/centros-trabajo') ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Centros -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Capacidad</th>
                            <th>Eficiencia</th>
                            <th>Costo/Hora</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($centros)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No se encontraron centros de trabajo
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($centros as $centro): ?>
                                <tr>
                                    <td>
                                        <strong><?= esc($centro['codigo']) ?></strong>
                                    </td>
                                    <td>
                                        <a href="<?= url('produccion/centros-trabajo/' . $centro['id']) ?>">
                                            <?= esc($centro['nombre']) ?>
                                        </a>
                                        <?php if (!empty($centro['descripcion'])): ?>
                                            <br><small class="text-muted"><?= esc($centro['descripcion']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= esc(ucfirst($centro['tipo'])) ?></span>
                                    </td>
                                    <td><?= number_format($centro['capacidad_horas_dia'], 1) ?>h/día</td>
                                    <td>
                                        <span class="badge <?= $centro['eficiencia'] >= 0.9 ? 'bg-success' : ($centro['eficiencia'] >= 0.7 ? 'bg-warning' : 'bg-danger') ?>">
                                            <?= number_format($centro['eficiencia'] * 100, 0) ?>%
                                        </span>
                                    </td>
                                    <td>$<?= number_format($centro['costo_hora'], 2) ?></td>
                                    <td>
                                        <?php if ($centro['activo']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= url('produccion/centros-trabajo/' . $centro['id']) ?>" class="btn btn-sm btn-info" title="Ver">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= url('produccion/centros-trabajo/' . $centro['id'] . '/edit') ?>" class="btn btn-sm btn-warning" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn btn-sm btn-danger btn-delete" data-id="<?= $centro['id'] ?>" title="Eliminar">
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
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Manejo de eliminación
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', async function() {
                const id = this.dataset.id;

                if (!confirm('¿Está seguro de eliminar este centro de trabajo?')) {
                    return;
                }

                try {
                    const response = await fetch(`/produccion/centros-trabajo/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    });

                    const result = await response.json();

                    if (result.success) {
                        location.reload();
                    } else {
                        alert(result.errors ? result.errors.join(', ') : 'Error al eliminar');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error al procesar la solicitud');
                }
            });
        });
    });
</script>
