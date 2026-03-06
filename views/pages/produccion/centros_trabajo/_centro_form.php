<?php

/**
 * Formulario parcial: Centro de Trabajo
 * Usado en create y edit
 */
$centro = $centro ?? [];
?>

<div class="row g-3">
    <!-- Código -->
    <div class="col-md-6">
        <label for="codigo" class="form-label">Código *</label>
        <input type="text" class="form-control" id="codigo" name="codigo"
            value="<?= esc($centro['codigo'] ?? '') ?>" required>
        <div class="form-text">Código único del centro</div>
    </div>

    <!-- Nombre -->
    <div class="col-md-6">
        <label for="nombre" class="form-label">Nombre *</label>
        <input type="text" class="form-control" id="nombre" name="nombre"
            value="<?= esc($centro['nombre'] ?? '') ?>" required>
    </div>

    <!-- Descripción -->
    <div class="col-12">
        <label for="descripcion" class="form-label">Descripción</label>
        <textarea class="form-control" id="descripcion" name="descripcion" rows="2"><?= esc($centro['descripcion'] ?? '') ?></textarea>
    </div>

    <!-- Tipo -->
    <div class="col-md-6">
        <label for="tipo" class="form-label">Tipo *</label>
        <select class="form-select" id="tipo" name="tipo" required>
            <option value="">Seleccione...</option>
            <option value="maquina" <?= ($centro['tipo'] ?? '') === 'maquina' ? 'selected' : '' ?>>Máquina</option>
            <option value="manual" <?= ($centro['tipo'] ?? '') === 'manual' ? 'selected' : '' ?>>Manual</option>
            <option value="celda" <?= ($centro['tipo'] ?? '') === 'celda' ? 'selected' : '' ?>>Celda</option>
        </select>
    </div>

    <!-- Capacidad -->
    <div class="col-md-6">
        <label for="capacidad_horas_dia" class="form-label">Capacidad (horas/día) *</label>
        <input type="number" class="form-control" id="capacidad_horas_dia" name="capacidad_horas_dia"
            value="<?= esc($centro['capacidad_horas_dia'] ?? '8') ?>" step="0.1" min="0.1" required>
    </div>

    <!-- Eficiencia -->
    <div class="col-md-6">
        <label for="eficiencia" class="form-label">Eficiencia *</label>
        <div class="input-group">
            <input type="number" class="form-control" id="eficiencia" name="eficiencia"
                value="<?= esc($centro['eficiencia'] ?? '0.85') ?>" step="0.01" min="0" max="1" required>
            <span class="input-group-text">(%)</span>
        </div>
        <div class="form-text">Valor entre 0 y 1 (ej: 0.85 = 85%)</div>
    </div>

    <!-- Costo por Hora -->
    <div class="col-md-6">
        <label for="costo_hora" class="form-label">Costo por Hora *</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" class="form-control" id="costo_hora" name="costo_hora"
                value="<?= esc($centro['costo_hora'] ?? '0') ?>" step="0.01" min="0" required>
        </div>
    </div>

    <!-- Activo -->
    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1"
                <?= ($centro['activo'] ?? true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="activo">
                Centro activo
            </label>
        </div>
    </div>

    <!-- Configuración JSONB -->
    <div class="col-12">
        <label class="form-label">Configuración Adicional</label>
        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="config_turnos" class="form-label">Turnos por día</label>
                        <input type="number" class="form-control" id="config_turnos" value="1" min="1" max="3">
                    </div>
                    <div class="col-md-4">
                        <label for="config_operadores" class="form-label">Operadores requeridos</label>
                        <input type="number" class="form-control" id="config_operadores" value="1" min="1">
                    </div>
                    <div class="col-md-4">
                        <label for="config_setup_min" class="form-label">Setup mínimo (min)</label>
                        <input type="number" class="form-control" id="config_setup_min" value="0" min="0">
                    </div>
                </div>
            </div>
        </div>
        <input type="hidden" id="configuracion" name="configuracion" value="{}">
    </div>
</div>

<script>
    // Serializar configuración antes de enviar
    document.getElementById('centroForm')?.addEventListener('submit', function(e) {
        const config = {
            turnos: parseInt(document.getElementById('config_turnos').value),
            operadores_requeridos: parseInt(document.getElementById('config_operadores').value),
            setup_minimo_minutos: parseInt(document.getElementById('config_setup_min').value)
        };
        document.getElementById('configuracion').value = JSON.stringify(config);
    });

    // Cargar configuración si existe
    <?php if (!empty($centro['configuracion'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const config = <?= json_encode($centro['configuracion']) ?>;
            if (config.turnos) document.getElementById('config_turnos').value = config.turnos;
            if (config.operadores_requeridos) document.getElementById('config_operadores').value = config.operadores_requeridos;
            if (config.setup_minimo_minutos) document.getElementById('config_setup_min').value = config.setup_minimo_minutos;
        });
    <?php endif; ?>
</script>
