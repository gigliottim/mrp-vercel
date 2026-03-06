# 📦 Seeds de la Base de Datos

Este directorio contiene scripts SQL para poblar la base de datos con datos iniciales.

## 🌱 Unidades de Medida

### Archivo: `unidades_medida_seed.sql`

Contiene las unidades de medida más comunes con sus equivalencias respecto a la unidad base de cada tipo.

#### Tipos de unidades incluidas:

| Tipo | Unidad Base | Unidades Incluidas |
|------|-------------|-------------------|
| **Longitud** | Metro (m) | mm, cm, m, km, in, ft, yd, mi |
| **Superficie** | Metro cuadrado (m²) | mm², cm², m², ha, km², in², ft² |
| **Volumen** | Litro (l) | ml, cl, dl, l, m³, gal, fl oz, pt, qt |
| **Masa** | Kilogramo (kg) | mg, g, kg, t, oz, lb, ton |
| **Tiempo** | Segundo (s) | ms, s, min, h, d, sem, mes, año |
| **Temperatura** | Celsius (°C) | °C, °F, K |

#### Cómo usar:

**Opción 1: Desde el script PHP (Recomendado)**

⚠️ **Importante**: Debes estar logueado en el sistema para usar este método.

Ejecutar desde el navegador:
```
http://localhost/mrp/seed_unidades_medida.php
```

El script:
- Verificará que estés autenticado
- Usará la base de datos de la empresa a la que perteneces (multi-tenant)
- Ejecutará el seed y mostrará un reporte detallado de las unidades insertadas

**Opción 2: Manualmente con psql**

```bash
psql -U postgres -d nombre_base_datos -f database/seeds/unidades_medida_seed.sql
```

**Opción 3: Desde pgAdmin**

1. Conectarse a la base de datos del tenant
2. Abrir el Query Tool
3. Copiar y pegar el contenido del archivo SQL
4. Ejecutar (F5)

#### Equivalencias

Las equivalencias están expresadas en relación a la unidad base de cada tipo:

```
Ejemplo: Longitud
- Milímetro: 0.001 (1 mm = 0.001 m)
- Centímetro: 0.01 (1 cm = 0.01 m)
- Metro: 1.0 (unidad base)
- Kilómetro: 1000.0 (1 km = 1000 m)
```

Para convertir entre unidades:
```
valor_destino = valor_origen * (equivalencia_origen / equivalencia_destino)
```

#### Notas importantes:

- ⚠️ **Temperatura**: Las conversiones de temperatura requieren fórmulas especiales, no solo multiplicación. El campo `equivalencia_base` está configurado como 1.0 para todas, ya que la conversión debe hacerse mediante fórmulas:
  - °F = (°C × 9/5) + 32
  - K = °C + 273.15

- 🔒 **ON CONFLICT DO NOTHING**: El script está protegido contra ejecuciones múltiples. No insertará duplicados.

- 🗑️ **Limpieza**: Si deseas eliminar los datos existentes antes de insertar, descomenta la línea `TRUNCATE TABLE` al inicio del archivo.

## 📝 Agregar más unidades

Para agregar nuevas unidades de medida:

1. Edita el archivo `unidades_medida_seed.sql`
2. Agrega nuevos `INSERT` statements siguiendo el formato existente
3. Calcula la equivalencia respecto a la unidad base del tipo
4. Ejecuta el script nuevamente

Ejemplo:
```sql
INSERT INTO unidades_medida (tipo, unidad, simbolo, equivalencia_base, es_base, activo) VALUES
('longitud', 'Micrón', 'μm', 0.000001, false, true)
ON CONFLICT DO NOTHING;
```

## 🔍 Verificación

Para verificar las unidades insertadas:

```sql
-- Ver todas las unidades
SELECT * FROM unidades_medida ORDER BY tipo, equivalencia_base;

-- Contar por tipo
SELECT tipo, COUNT(*) as cantidad 
FROM unidades_medida 
GROUP BY tipo 
ORDER BY tipo;

-- Ver solo las unidades base
SELECT * FROM unidades_medida WHERE es_base = true;
```

## 🆘 Soporte

Si encuentras algún problema con las conversiones o necesitas agregar unidades adicionales, consulta:

- Factores de conversión: [NIST Special Publication 811](https://www.nist.gov/pml/special-publication-811)
- Sistemas de unidades: [Sistema Internacional (SI)](https://www.bipm.org/en/measurement-units/)
