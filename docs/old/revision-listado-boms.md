# Revision del Listado de BOMs

Guia y plantilla para ejecutar revisiones funcionales y tecnicas de la pantalla:
`Productos > Listado de BOMs Activas`.

Referencia de vista actual:
- `views/pages/productos/bom/index.php`

## 1. Objetivo

Validar que el Listado de BOMs:
- muestre solo BOMs activas y vigentes,
- presente datos correctos por variante/version,
- permita navegar al detalle de composicion,
- tenga comportamiento correcto con y sin datos.

## 2. Alcance de la revision

Incluye:
- UI de listado (`tabla`, columnas, acciones)
- estados de pantalla (con datos y sin datos)
- links de navegacion (`Nueva BOM`, `Detalle`)
- consistencia basica de datos mostrados

No incluye:
- logica interna de explosion MRP
- costos, reservas o consumo de stock

## 3. Precondiciones

Antes de revisar:
- [ ] Tener usuario con permisos para modulo Productos
- [ ] Tener al menos 1 BOM activa en ambiente de prueba
- [ ] Tener al menos 1 variante con datos de detalle
- [ ] Confirmar ruta accesible: `productos/bom`

Opcional (recomendado):
- [ ] Preparar un caso sin datos (sin BOMs activas)

## 4. Checklist de revision

### 4.1 Encabezado y acciones principales
- [ ] Se muestra el titulo `Listado de BOMs Activas`
- [ ] Se muestra subtitulo `Listas de Materiales activas y vigentes`
- [ ] Boton `Nueva BOM` visible
- [ ] `Nueva BOM` navega a `productos/maestro`

### 4.2 Tabla (cuando hay datos)
- [ ] Se renderiza tabla responsive
- [ ] Columnas visibles: `Variante`, `Version`, `Fecha Efectiva`, `Observaciones`, `Acciones`
- [ ] Cada fila muestra `variante_codigo` y `variante_detalle`
- [ ] La version se muestra en badge
- [ ] `fecha_efectiva` no aparece vacia en registros activos
- [ ] `observaciones` muestra texto o `--` si no existe
- [ ] Boton `Detalle` visible por fila
- [ ] `Detalle` navega a `productos/maestro?id_variante={id}`

### 4.3 Estado vacio (cuando no hay BOMs activas)
- [ ] Se muestra mensaje `No hay BOMs activas`
- [ ] Se muestra CTA `Crear primera BOM`
- [ ] CTA navega a `productos/maestro`
- [ ] No aparece tabla vacia sin contexto

### 4.4 Calidad visual y UX
- [ ] Sin solapamientos en desktop
- [ ] Sin cortes en mobile/tablet
- [ ] Botones y texto legibles
- [ ] Iconos cargan correctamente

### 4.5 Seguridad y robustez basica
- [ ] Datos visibles escapados correctamente (sin HTML inyectado)
- [ ] IDs en links se envian como enteros
- [ ] Sin errores JS/console en la pantalla

## 5. Casos de prueba sugeridos

Caso A - Listado con datos
1. Cargar `productos/bom` con 2 o mas BOMs activas.
2. Verificar columnas y contenido por fila.
3. Abrir `Detalle` de una fila y validar variante correcta.

Caso B - Estado vacio
1. Probar en empresa/base sin BOMs activas.
2. Verificar mensaje vacio y CTA de creacion.

Caso C - Observaciones nulas
1. Usar BOM con `observaciones = null`.
2. Confirmar que muestra `--`.

Caso D - Navegacion nueva BOM
1. Click en `Nueva BOM`.
2. Confirmar apertura de `productos/maestro`.

## 6. Plantilla de reporte de revision

## Revision: Listado de BOMs
- Fecha: `YYYY-MM-DD`
- Revisor: `Nombre`
- Ambiente: `local | test | prod`
- Branch/Commit: `hash`

### Resultado general
- Estado: `APROBADO | APROBADO CON OBSERVACIONES | RECHAZADO`
- Resumen: `1-3 lineas`

### Hallazgos
| Severidad | Descripcion | Pasos para reproducir | Evidencia | Estado |
|---|---|---|---|---|
| Alta/Media/Baja | ... | ... | screenshot/log | Abierto/Cerrado |

### Checklist completada
- [ ] 4.1 Encabezado y acciones
- [ ] 4.2 Tabla con datos
- [ ] 4.3 Estado vacio
- [ ] 4.4 Calidad visual
- [ ] 4.5 Seguridad/robustez

### Recomendaciones
- `Item 1`
- `Item 2`

## 7. Criterios de aprobacion

Aprobar solo si:
- no hay errores bloqueantes de navegacion,
- datos clave del listado son correctos,
- estado vacio funciona,
- no hay riesgos evidentes de seguridad en el render.

## 8. Trazabilidad (opcional)

Relacionar esta revision con:
- ticket de desarrollo
- ticket de QA
- PR asociado
- evidencia en `storage/logs` o capturas
