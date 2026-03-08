<?php

/**
 * Componente: Timeline de Estados de Orden
 */
?>

<div class="card">
    <div class="card-header">
        <h6 class="mb-0">Historial de Estados</h6>
    </div>
    <div class="card-body">
        <div class="timeline">
            <?php
            $estados = [
                'borrador' => ['icon' => 'fa-file', 'color' => 'secondary'],
                'planificada' => ['icon' => 'fa-calendar', 'color' => 'info'],
                'liberada' => ['icon' => 'fa-unlock', 'color' => 'warning'],
                'en_proceso' => ['icon' => 'fa-cogs', 'color' => 'primary'],
                'pausada' => ['icon' => 'fa-pause', 'color' => 'warning'],
                'completada' => ['icon' => 'fa-check-circle', 'color' => 'success'],
                'cerrada' => ['icon' => 'fa-lock', 'color' => 'dark'],
                'cancelada' => ['icon' => 'fa-times-circle', 'color' => 'danger']
            ];

            $historialEstados = [
                ['estado' => 'borrador', 'fecha' => $orden['created_at'], 'usuario' => $orden['usuario_creador_nombre'] ?? 'Sistema'],
                ['estado' => $orden['estado'], 'fecha' => $orden['updated_at'], 'usuario' => 'Usuario'],
            ];

            foreach ($historialEstados as $h):
                $info = $estados[$h['estado']] ?? ['icon' => 'fa-circle', 'color' => 'secondary'];
            ?>
                <div class="timeline-item">
                    <div class="timeline-marker bg-<?= $info['color'] ?>">
                        <i class="fas <?= $info['icon'] ?>"></i>
                    </div>
                    <div class="timeline-content">
                        <h6 class="mb-1"><?= ucfirst(str_replace('_', ' ', $h['estado'])) ?></h6>
                        <small class="text-muted">
                            <?= esc(app_format_datetime($h['fecha'], true)) ?> - <?= esc($h['usuario']) ?>
                        </small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
    .timeline {
        position: relative;
        padding-left: 50px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 20px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }

    .timeline-item {
        position: relative;
        padding-bottom: 30px;
    }

    .timeline-marker {
        position: absolute;
        left: -38px;
        top: 0;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 16px;
        box-shadow: 0 0 0 4px white;
    }

    .timeline-content {
        background: #f8f9fa;
        padding: 10px 15px;
        border-radius: 5px;
    }
</style>
