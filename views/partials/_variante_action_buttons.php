<?php

/**
 * Componente reutilizable: Botones de Acciones para Variantes
 *
 * Este partial renderiza un conjunto estándar de botones de acciones
 * para operaciones sobre variantes de productos.
 *
 * @param array $options Opciones de configuración
 *  - 'variante_id' (int): ID de la variante
 *  - 'parte_id' (int): ID de la parte asociada
 *  - 'codigo_variante' (string): Código de la variante para display
 *  - 'context' (string): Contexto de uso ('alpine'|'php')
 *  - 'show_edit' (bool): Mostrar botón editar (default: false, solo para Alpine.js)
 *  - 'show_replace' (bool): Mostrar botón reemplazar (default: false, solo para Alpine.js)
 *  - 'show_delete' (bool): Mostrar botón eliminar (default: false, solo para Alpine.js)
 *  - 'show_gestionar' (bool): Mostrar botón gestionar parte (default: true)
 *  - 'show_maestro' (bool): Mostrar botón cargar maestro (default: true)
 *  - 'show_destino' (bool): Mostrar botón destino de partes (default: true)
 *  - 'alpine_data' (string): Nombre de la variable en Alpine.js (default: 'child')
 *  - 'size' (string): Tamaño del grupo de botones ('sm'|'md') default: 'sm'
 */

// Valores por defecto
$varianteId = $options['variante_id'] ?? 0;
$parteId = $options['parte_id'] ?? 0;
$codigoVariante = $options['codigo_variante'] ?? '';
$context = $options['context'] ?? 'php'; // 'alpine' o 'php'
$showEdit = $options['show_edit'] ?? false;
$showReplace = $options['show_replace'] ?? false;
$showDelete = $options['show_delete'] ?? false;
$showGestionar = $options['show_gestionar'] ?? true;
$showMaestro = $options['show_maestro'] ?? true;
$showDestino = $options['show_destino'] ?? true;
$alpineData = $options['alpine_data'] ?? 'child';
$size = $options['size'] ?? 'sm';

$btnGroupClass = $size === 'sm' ? 'btn-group-sm' : '';

?>
<div class="btn-group <?= $btnGroupClass ?>">
    <?php if ($showEdit && $context === 'alpine'): ?>
        <!-- 1. Editar Cantidad (Solo Alpine.js) -->
        <button class="btn btn-outline-secondary" title="Editar Cantidad" @click="editItem(<?= $alpineData ?>)">
            <i class="fa-solid fa-pen"></i>
        </button>
    <?php endif; ?>

    <?php if ($showReplace && $context === 'alpine'): ?>
        <!-- 2. Reemplazar Partes (Solo Alpine.js) -->
        <button class="btn btn-outline-info" title="Reemplazar Partes" @click="replaceItem(<?= $alpineData ?>)">
            <i class="fa-solid fa-right-left"></i>
        </button>
    <?php endif; ?>

    <?php if ($showGestionar): ?>
        <!-- 3. Gestionar Parte -->
        <?php if ($context === 'alpine'): ?>
            <a :href="'<?= url('productos/partes/manager') ?>/' + getPartId(<?= $alpineData ?>) + '/variantes/' + <?= $alpineData ?>.variante_id + '/editar'"
                target="_blank"
                class="btn btn-outline-warning"
                title="Gestionar Parte">
                <i class="fa-solid fa-gear"></i>
            </a>
        <?php else: ?>
            <a href="<?= url('productos/partes/manager/' . $parteId . '/variantes/' . $varianteId . '/editar') ?>"
                target="_blank"
                class="btn btn-outline-warning"
                title="Gestionar Parte">
                <i class="fa-solid fa-gear"></i>
            </a>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($showMaestro): ?>
        <!-- 4. Cargar como Maestro -->
        <?php if ($context === 'alpine'): ?>
            <a :href="'<?= url('productos/maestro') ?>?id_variante=' + <?= $alpineData ?>.variante_id"
                class="btn btn-outline-primary"
                title="Cargar como Maestro">
                <i class="fa-solid fa-network-wired"></i>
            </a>
        <?php else: ?>
            <a href="<?= url('productos/maestro?id_variante=' . $varianteId) ?>"
                class="btn btn-outline-primary"
                title="Cargar como Maestro">
                <i class="fa-solid fa-network-wired"></i>
            </a>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($showDestino): ?>
        <!-- 5. Destino de Partes -->
        <?php if ($context === 'alpine'): ?>
            <a :href="'<?= url('reportes/destino-partes') ?>?id_variante=' + <?= $alpineData ?>.variante_id"
                class="btn btn-outline-secondary"
                title="Destino de partes"
                target="_blank">
                <i class="fa-solid fa-layer-group"></i>
            </a>
        <?php else: ?>
            <a href="<?= url('reportes/destino-partes?id_variante=' . $varianteId) ?>"
                class="btn btn-outline-secondary"
                title="Destino de partes"
                target="_blank">
                <i class="fa-solid fa-layer-group"></i>
            </a>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($showDelete && $context === 'alpine'): ?>
        <!-- 6. Eliminar (Solo Alpine.js) -->
        <button class="btn btn-outline-danger" title="Eliminar" @click="deleteItem(<?= $alpineData ?>)">
            <i class="fa-solid fa-trash"></i>
        </button>
    <?php endif; ?>
</div>
