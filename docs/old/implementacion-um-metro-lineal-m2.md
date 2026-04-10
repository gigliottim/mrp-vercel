# Implementación del modelo genérico de doble unidad (aprovechando lo existente)

## Objetivo

Implementar y usar el modelo **genérico de doble unidad** para el caso `mL ↔ m²`, y dejarlo preparado para futuros casos (`kg ↔ unidad`, `rollo ↔ m²`, `caja ↔ unidad`, etc.) sin lógica especial por material.

---

## Evaluación del estado actual (manager `/productos/partes/manager/183`)

Con base en la revisión de la pantalla y del código del manager:

- En **Parte** ya existen y se editan:
   - `UM Compra`
   - `UM Uso`
   - `Factor de Conversión`
   - `Dimensiones físicas` (incluye `ancho`)
- En **Variante** ya existe y se edita:
   - `Lote Mín.`

Conclusión: el sistema **ya tiene casi todos los datos/campos necesarios**. No hace falta agregar un campo binario “¿es metro lineal?”.

---

## Contexto funcional del caso mL ↔ m²

Necesidad funcional:
- **UM Compra:** Metro lineal (**mL**)
- **UM Uso:** Metro cuadrado (**m²**)
- **Factor de conversión:** `1 x ancho` (ejemplo: `1 x 0.8`)
- **Lote mínimo compra:** `1 mL`

Ejemplo de negocio:
- Si la necesidad de uso es `1.0 m²` y el ancho es `0.8 m`, entonces:
  - Cada `1 mL` aporta `0.8 m²`
  - Compra necesaria = `1.0 / 0.8 = 1.25 mL`
  - Aplicando lote mínimo de `1 mL` y redondeo al múltiplo: **comprar 2 mL**
  - Ingreso a stock de uso = `2 x 0.8 = 1.6 m²`

---

## Decisión de implementación

**Decisión:** **NO** agregar un campo binario tipo “¿es metro lineal?”.

Usar el modelo **genérico de doble unidad** ya presente:
1. **UM de Compra**
2. **UM de Uso**
3. **Factor de conversión parametrizable**
4. **Lote mínimo de variante** (+ múltiplo de compra opcional)

### Por qué esta opción es mejor
- Evita lógica especial “hardcodeada” solo para mL.
- Resuelve también futuros casos (kg↔unidad, rollo↔m², caja↔unidad, etc.).
- Simplifica compras, MRP y stock al trabajar con una regla común de conversión.
- Reduce deuda técnica y excepciones en cálculos.

### Política recomendada para unidades (catálogo mixto)

Para soportar esos casos sin perder control de datos:

- Definir **unidades de sistema protegidas** (ej: `m`, `m²`, `mL`, `kg`, `u`, `caja`, `rollo`).
- Permitir **unidades personalizadas** para negocio, con permisos.
- No permitir edición/eliminación de unidades de sistema en operación normal.

Campos sugeridos en `unidades_medida`:
- `is_system` (boolean): unidad base del sistema.
- `locked` (boolean): no editable/no eliminable.
- `activo` (boolean): para baja lógica en unidades no sistema.

Reglas de negocio:
- Unidad `is_system=1` o `locked=1` ⇒ solo lectura.
- Unidad custom en uso (partes, variantes, movimientos, BOM, compras) ⇒ no se elimina físicamente.
- Para custom sin uso, permitir eliminación; con uso, solo `activo=0`.
- Solo perfil administrador avanzado puede gestionar unidades custom.

Beneficio: estabilidad de datos + flexibilidad para nuevos materiales y mercados.

---

## Regla matemática estándar

Definir factor como:

`factor_uso_por_compra = cantidad_uso que aporta 1 unidad de compra`

Para este caso:

`factor_uso_por_compra = ancho_metros`

Entonces:

1. **Compra teórica**
   - `compra_teorica = necesidad_uso / factor_uso_por_compra`

2. **Compra redondeada por lote/múltiplo**
   - `compra_final = ceil(compra_teorica / multiplo_compra) * multiplo_compra`
   - en el esquema actual, `multiplo_compra` puede tomar el `lote_minimo` de la variante

3. **Ingreso/impacto en stock de uso**
   - `uso_ingresado = compra_final * factor_uso_por_compra`

---

## Asignación de responsabilidades (Parte vs Variante)

### Parte (cabecera técnica del material)
- `UM Compra`
- `UM Uso`
- `Factor de Conversión`
- `Dimensiones físicas` (incluyendo `ancho`)

### Variante (política operativa de abastecimiento)
- `Lote Mín.`
- (opcional futuro) `Múltiplo de compra` si difiere de lote mínimo

---

## Impacto por módulo

## 1) Gestión de Partes
Usar los campos actuales con esta regla:
- Parte: `um_compra`, `um_uso`, `factor_conversion`, `ancho` y demás dimensiones.
- Variante: `lote_minimo`.

No duplicar `lote_minimo` en Parte.

### UX sugerida
- Campo “**Tipo de conversión**” con presets (ej: “Lineal→Superficie”).
- Si selecciona ese preset, mostrar campo “**Ancho (m)**”.
- El sistema calcula `factor_uso_por_compra = ancho`.

> Esto da una experiencia simple al usuario sin perder modelo genérico.

## 2) Listas de Ingeniería (BOM)
La BOM debe almacenar y calcular consumo en **UM de Uso (m²)**.
- `cantidad_bom` siempre expresada en `um_uso`.
- No mezclar en BOM valores en `um_compra`.

## 3) Cálculo de consumos
Consumo real de producción:
- Descarga stock en `um_uso`.
- Si también quieren trazabilidad logística de compra, guardar movimiento espejo en `um_compra` (opcional).

## 4) Compras / MRP
El MRP calcula necesidad en `um_uso`, luego convierte a compra:
- `necesidad_compra = necesidad_uso / factor_uso_por_compra`
- Aplicar `lote_minimo` de la variante como múltiplo de redondeo (hacia arriba).
- Generar OC en `um_compra`.

## 5) Stock
Definir una UM base de stock para MRP/consumo:
- Recomendado: stock operativo en `um_uso` (m²), porque BOM y consumos están en uso.
- Al ingresar compras, convertir automáticamente a `um_uso`.

---

## Política de redondeo recomendada

Para evitar faltantes:
- En compras/MRP usar **redondeo hacia arriba** (`ceil`) al múltiplo permitido.
- Registrar sobrante como stock disponible futuro.

Ejemplo:
- Necesidad: `1.0 m²`
- Factor: `0.8 m² por mL`
- Teórico: `1.25 mL`
- Múltiplo: `1 mL`
- Compra final: `2 mL`
- Stock ingresado en uso: `1.6 m²`

---

## Modelo de datos: mínimo ajuste recomendado

Campos ya disponibles y a utilizar:
- Parte: `um_compra`, `um_uso`, `factor_conversion`, dimensiones.
- Variante: `lote_minimo`.

Faltantes opcionales (no bloqueantes para MVP):
- `multiplo_compra` en variante (si en algún material difiere de `lote_minimo`).
- `regla_redondeo` (`UP`, `NEAREST`, `DOWN`; recomendado `UP`).
- metadatos de seguridad en catálogo de unidades: `is_system`, `locked`, `activo`.

Índices/validaciones:
- `factor_conversion > 0` cuando `UM Compra != UM Uso`.
- `lote_minimo > 0` en variante.
- Si existe `multiplo_compra`, entonces `multiplo_compra > 0`.

---

## Respuesta directa a la duda

### ¿Usar UM Metro Lineal con equivalencia a 1 en longitud?
**Sí**, como configuración de `UM Compra` + `UM Uso` + `Factor`, dentro del modelo genérico.

### ¿O agregar opción “¿es Metro Lineal?”?
**No como enfoque principal.**
Si se desea, usarlo solo como **atajo de UI/preset** que autocompleta:
- `um_compra = mL`
- `um_uso = m²`
- solicita `ancho_m`
- calcula `factor_conversion = ancho_m`

---

## Plan de implementación (MVP sobre sistema actual)

1. Mantener la estructura actual: `UM/Factor/Dimensiones` en **Parte** y `Lote Mín.` en **Variante**.
2. Centralizar función de conversión y redondeo en servicio de dominio (única fuente de verdad).
3. En MRP/Compras, convertir `necesidad_uso → necesidad_compra` y redondear por `lote_minimo` de variante.
4. En ingreso de compra, convertir automáticamente a stock de uso (`um_uso`).
5. Mostrar trazabilidad del cálculo (teórico, redondeado y sobrante).
6. Probar caso base: `ancho=0.8`, `necesidad=1.0 m²` ⇒ compra `2 mL`, ingreso `1.6 m²`.

## Checklist técnico adicional: unidades bloqueadas

1. Agregar/validar columnas en `unidades_medida`: `is_system`, `locked`, `activo`.
2. Sembrar catálogo base bloqueado (`m`, `m²`, `mL`, `kg`, `u`, `caja`, `rollo`).
3. Ajustar ABM de unidades para impedir editar/eliminar cuando `locked=1`.
4. Agregar validación de “unidad en uso” antes de eliminar unidades custom.
5. Implementar baja lógica (`activo=0`) para unidades custom con referencias históricas.

---

**Fecha:** 24-02-2026
**Estado:** Implementación de código realizada (pendiente aplicar migración en entorno)

---

## Estado de implementación en código (24-02-2026)

Implementado en este repositorio:

- Migración para unidades de sistema bloqueadas y tipo `unidad`:
   - `database/migrations/2026-02-24_add_system_lock_units_and_unidad_type_seed.sql`
- Servicio central de conversión doble UM:
   - `app/services/UnitConversionService.php`
- Protección de ABM de unidades (no editar/eliminar protegidas, no eliminar en uso):
   - `app/models/UnidadMedida.php`
   - `app/controllers/Admin/UnidadesMedidaController.php`
   - `views/pages/admin/catalogo/unidades/index.php`
- Conversión compra→uso integrada en transacciones:
   - `app/controllers/Transacciones/ComprasController.php`
   - `app/controllers/Transacciones/MovimientosPartesController.php`
   - `views/pages/transacciones/movimientos-partes.php`
- Conversión en planificación/MRP (a comprar en UM compra + impacto en stock uso):
   - `app/controllers/Reportes/ReportesController.php`
   - `views/pages/reportes/planificacion-produccion.php`
- Gestión de partes: autocalcular factor en caso `mL -> m²` usando ancho cuando no se informa factor:
   - `app/controllers/Admin/PartesVariantesController.php`

Script de verificación agregado:
- `scripts/verify-unidades-conversion-model.php`

Prerequisito del script de verificación:
- Requiere sesión tenant activa (usuario logueado en la app) para abrir conexión de tenant.
- Si se ejecuta por CLI sin sesión, devolverá: `No hay sesión de tenant activa`.

Nota operativa:
- El migrador `migrate_database.php` ya existe y está integrado a tareas VS Code.
- Si falla la tarea `🔄 Ejecutar Migraciones`, revisar conectividad al host de DB (en este entorno se resolvió usando `--db-host=127.0.0.1`).
