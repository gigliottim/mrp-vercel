<?php

/**
 * Vista: Listado de Rutas de Producción
 */
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Rutas de Producción</h5>
                <a href="<?= url('produccion/rutas/create') ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nueva Ruta
                </a>
            </div>

            <!-- Filtros -->
            <form method="GET" action="<?= url('produccion/rutas') ?>" class="row g-3">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Buscar producto o BOM..." value="<?= esc($filters['search'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <select name="activo" class="form-select">
                        <option value="">Todas las rutas</option>
                        <option value="1" <?= ($filters['activo'] ?? '') === '1' ? 'selected' : '' ?>>Activas</option>
                        <option value="0" <?= ($filters['activo'] ?? '') === '0' ? 'selected' : '' ?>>Inactivas</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a href="<?= url('produccion/rutas') ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Rutas -->
    <div class="row">
        <?php if (empty($rutas)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="fas fa-route fa-3x mb-3"></i>
                        <p>No se encontraron rutas de producción</p>
                        <a href="<?= url('produccion/rutas/create') ?>" class="btn btn-primary">
                            Crear primera ruta
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($rutas as $ruta): ?>
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <?= esc($ruta['producto_nombre'] ?? 'Producto sin nombre') ?>
                            </h6>
                            <?php if ($ruta['activa']): ?>
                                <span class="badge bg-success">Activa</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactiva</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">BOM: <?= esc($ruta['bom_nombre'] ?? 'N/A') ?></p>

                            <!-- Estadísticas -->
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <h5 class="mb-0"><?= $ruta['total_operaciones'] ?? 0 ?></h5>
                                        <small class="text-muted">Operaciones</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <h5 class="mb-0"><?= esc(app_format_number($ruta['tiempo_total'] ?? 0)) ?></h5>
                                        <small class="text-muted">min</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <h5 class="mb-0">$<?= esc(app_format_number($ruta['costo_total'] ?? 0)) ?></h5>
                                        <small class="text-muted">Costo</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Preview de operaciones -->
                            <?php if (!empty($ruta['operaciones'])): ?>
                                <div class="border rounded p-2 mb-2" style="max-height: 100px; overflow-y: auto;">
                                    <small class="text-muted d-block mb-1">Operaciones:</small>
                                    <?php foreach ($ruta['operaciones'] as $i => $op): ?>
                                        <div class="d-flex align-items-center mb-1">
                                            <span class="badge bg-primary me-2"><?= $op['secuencia'] ?></span>
                                            <small><?= esc($op['nombre']) ?></small>
                                        </div>
                                        <?php if ($i >= 2): ?>
                                            <small class="text-muted">...y <?= count($ruta['operaciones']) - 3 ?> más</small>
                                            <?php break; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer d-flex justify-content-between">
                            <a href="<?= url('produccion/rutas/editor/' . $ruta['bom_id']) ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i> Editor
                            </a>
                            <a href="<?= url('produccion/rutas/' . $ruta['bom_id']) ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i> Ver
                            </a>
                            <button class="btn btn-sm btn-secondary btn-clonar" data-bom-id="<?= $ruta['bom_id'] ?>">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Clonar ruta
        document.querySelectorAll('.btn-clonar').forEach(btn => {
            btn.addEventListener('click', async function() {
                const bomId = this.dataset.bomId;
                const nuevoBomId = prompt('Ingrese el ID del BOM destino:');

                if (!nuevoBomId) return;

                try {
                    const response = await fetch(`/produccion/rutas/${bomId}/clonar`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            bom_id_destino: nuevoBomId
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('Ruta clonada exitosamente');
                        location.reload();
                    } else {
                        alert(result.errors ? result.errors.join(', ') : 'Error al clonar');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error al procesar la solicitud');
                }
            });
        });
    });
</script>
