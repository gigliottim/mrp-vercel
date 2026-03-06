-- ============================================================================
-- Seed de Unidades de Medida con Equivalencias
-- ============================================================================
-- Este script inserta las unidades de medida más comunes con sus equivalencias
-- respecto a la unidad base de cada tipo.
--
-- IMPORTANTE: Ejecutar este script en la base de datos del tenant correspondiente
-- ============================================================================

-- Limpieza previa (comentar si no se desea eliminar los datos existentes)
-- TRUNCATE TABLE unidades_medida RESTART IDENTITY CASCADE;

-- ============================================================================
-- LONGITUD (Unidad base: metro)
-- ============================================================================
INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'longitud', 'Milímetro', 'mm', 0.001, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'longitud' AND simbolo = 'mm');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'longitud', 'Centímetro', 'cm', 0.01, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'longitud' AND simbolo = 'cm');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'longitud', 'Metro', 'm', 1.0, true, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'longitud' AND simbolo = 'm');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'longitud', 'Kilómetro', 'km', 1000.0, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'longitud' AND simbolo = 'km');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'longitud', 'Pulgada', 'in', 0.0254, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'longitud' AND simbolo = 'in');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'longitud', 'Pie', 'ft', 0.3048, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'longitud' AND simbolo = 'ft');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'longitud', 'Yarda', 'yd', 0.9144, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'longitud' AND simbolo = 'yd');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'longitud', 'Milla', 'mi', 1609.344, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'longitud' AND simbolo = 'mi');

-- ============================================================================
-- SUPERFICIE (Unidad base: metro cuadrado)
-- ============================================================================
INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'superficie', 'Milímetro cuadrado', 'mm²', 0.000001, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'superficie' AND simbolo = 'mm²');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'superficie', 'Centímetro cuadrado', 'cm²', 0.0001, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'superficie' AND simbolo = 'cm²');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'superficie', 'Metro cuadrado', 'm²', 1.0, true, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'superficie' AND simbolo = 'm²');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'superficie', 'Hectárea', 'ha', 10000.0, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'superficie' AND simbolo = 'ha');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'superficie', 'Kilómetro cuadrado', 'km²', 1000000, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'superficie' AND simbolo = 'km²');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'superficie', 'Pulgada cuadrada', 'in²', 0.00064516, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'superficie' AND simbolo = 'in²');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'superficie', 'Pie cuadrado', 'ft²', 0.09290304, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'superficie' AND simbolo = 'ft²');

-- ============================================================================
-- VOLUMEN (Unidad base: litro)
-- ============================================================================
INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'volumen', 'Mililitro', 'ml', 0.001, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'volumen' AND simbolo = 'ml');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'volumen', 'Centilitro', 'cl', 0.01, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'volumen' AND simbolo = 'cl');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'volumen', 'Decilitro', 'dl', 0.1, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'volumen' AND simbolo = 'dl');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'volumen', 'Litro', 'l', 1.0, true, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'volumen' AND simbolo = 'l');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'volumen', 'Metro cúbico', 'm³', 1000.0, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'volumen' AND simbolo = 'm³');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'volumen', 'Galón (US)', 'gal', 3.78541, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'volumen' AND simbolo = 'gal');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'volumen', 'Onza líquida (US)', 'fl oz', 0.0295735, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'volumen' AND simbolo = 'fl oz');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'volumen', 'Pinta (US)', 'pt', 0.473176, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'volumen' AND simbolo = 'pt');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'volumen', 'Cuarto (US)', 'qt', 0.946353, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'volumen' AND simbolo = 'qt');

-- ============================================================================
-- MASA (Unidad base: kilogramo)
-- ============================================================================
INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'masa', 'Miligramo', 'mg', 0.000001, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'masa' AND simbolo = 'mg');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'masa', 'Gramo', 'g', 0.001, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'masa' AND simbolo = 'g');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'masa', 'Kilogramo', 'kg', 1.0, true, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'masa' AND simbolo = 'kg');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'masa', 'Tonelada métrica', 't', 1000.0, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'masa' AND simbolo = 't');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'masa', 'Onza', 'oz', 0.0283495, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'masa' AND simbolo = 'oz');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'masa', 'Libra', 'lb', 0.453592, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'masa' AND simbolo = 'lb');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'masa', 'Tonelada corta (US)', 'ton', 907.185, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'masa' AND simbolo = 'ton');

-- ============================================================================
-- TIEMPO (Unidad base: segundo)
-- ============================================================================
INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'tiempo', 'Milisegundo', 'ms', 0.001, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'tiempo' AND simbolo = 'ms');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'tiempo', 'Segundo', 's', 1.0, true, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'tiempo' AND simbolo = 's');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'tiempo', 'Minuto', 'min', 60.0, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'tiempo' AND simbolo = 'min');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'tiempo', 'Hora', 'h', 3600.0, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'tiempo' AND simbolo = 'h');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'tiempo', 'Día', 'd', 86400.0, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'tiempo' AND simbolo = 'd');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'tiempo', 'Semana', 'sem', 604800, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'tiempo' AND simbolo = 'sem');

-- NOTA: Mes y Año tienen valores muy grandes para el campo equivalencia_base (15,8)
-- Se omiten o deben calcularse mediante múltiplos de unidades más pequeñas
-- Comentado para evitar overflow:
-- INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
-- SELECT 'tiempo', 'Mes (30 días)', 'mes', 2592000, false, true
-- WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'tiempo' AND simbolo = 'mes');

-- INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
-- SELECT 'tiempo', 'Año (365 días)', 'año', 31536000, false, true
-- WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'tiempo' AND simbolo = 'año');

-- ============================================================================
-- TEMPERATURA (Unidad base: Celsius)
-- ============================================================================
-- NOTA: La temperatura requiere conversión de fórmula, no solo multiplicación
-- Estas equivalencias son aproximadas para el sistema
INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'temperatura', 'Celsius', '°C', 1.0, true, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'temperatura' AND simbolo = '°C');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'temperatura', 'Fahrenheit', '°F', 1.0, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'temperatura' AND simbolo = '°F');

INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo)
SELECT 'temperatura', 'Kelvin', 'K', 1.0, false, true
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE tipo = 'temperatura' AND simbolo = 'K');

-- ============================================================================
-- Verificación de datos insertados
-- ============================================================================
-- Descomentar para verificar la inserción
-- SELECT tipo, COUNT(*) as cantidad FROM unidades_medida GROUP BY tipo ORDER BY tipo;
