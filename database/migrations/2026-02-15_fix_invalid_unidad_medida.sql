-- Migración: Corregir y eliminar unidad de medida inválida
-- Fecha: 2026-02-15
-- Descripción: Actualizar la unidad con símbolo 'XX' y nombre 'borrar'
--              en todas las tablas que la referencian y luego eliminarla

-- Paso 1: Actualizar referencias en bom_detalle
-- Cambiar todas las referencias de la unidad inválida (ID 13) a la unidad válida "unid" (ID 7)
UPDATE bom_detalle
SET unidad_medida_id = 7
WHERE unidad_medida_id = 13;

-- Verificar que no queden referencias
-- SELECT COUNT(*) FROM bom_detalle WHERE unidad_medida_id = 13;
-- Resultado esperado: 0

-- Paso 2: Eliminar la unidad inválida
DELETE FROM unidades_medida
WHERE id = 13
  AND simbolo IN ('XX', 'unid-old');

-- Verificar eliminación
-- SELECT * FROM unidades_medida WHERE id = 13;
-- Resultado esperado: 0 filas

-- Nota: Ya no se aplicarán filtros en el modelo UnidadMedida.php
-- Las unidades se filtran naturalmente por su tipo (longitud, masa, superficie, etc.)

-- Script de rollback (comentado)
-- IMPORTANTE: Este rollback solo es posible si se tiene un backup de los datos originales
-- INSERT INTO unidades_medida (id, unidad, simbolo, tipo, es_base)
-- VALUES (13, 'borrar', 'XX', 'longitud', false);
-- UPDATE bom_detalle SET unidad_medida_id = 13 WHERE unidad_medida_id = 7 AND <condición específica>;
