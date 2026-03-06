# Gestión de Operaciones y Rutas de Producción

## 📋 Descripción

Sistema completo para la gestión de operaciones de producción, rutas de fabricación, centros de trabajo y planificación de recursos en el MRP.

## 🌐 URL de Acceso

```
http://localhost/mrp/produccion/operaciones
```

## 🗄️ Estructura de Base de Datos

### 1. Tabla: `centros_trabajo`

Centro de trabajo o estación donde se realizan las operaciones de producción.

| Campo | Tipo | NULL | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | integer | NO | AUTO | Identificador único |
| `codigo` | varchar(20) | NO | - | Código alfanumérico único del centro |
| `nombre` | varchar(100) | NO | - | Nombre descriptivo del centro |
| `descripcion` | text | YES | - | Descripción detallada del centro |
| `tipo` | varchar(20) | YES | 'manual' | Tipo de centro: 'manual', 'semi_automatico', 'automatico' |
| `capacidad_horas_dia` | numeric(4,2) | YES | 8.00 | Horas disponibles por día |
| `eficiencia_porcentaje` | numeric(5,2) | YES | 100.00 | Porcentaje de eficiencia (0-100) |
| `costo_hora` | numeric(12,2) | YES | 0.00 | Costo por hora de operación |
| `capacidad_finita` | boolean | YES | false | Si tiene capacidad limitada |
| `calendario_id` | integer | YES | NULL | Referencia a calendario de trabajo |
| `activo` | boolean | YES | true | Estado del centro |
| `ubicacion` | varchar(100) | YES | NULL | Ubicación física del centro |
| `responsable` | varchar(100) | YES | NULL | Persona responsable del centro |
| `observaciones` | text | YES | NULL | Notas adicionales |
| `created_at` | timestamp | YES | CURRENT_TIMESTAMP | Fecha de creación |
| `updated_at` | timestamp | YES | CURRENT_TIMESTAMP | Fecha de última actualización |

**Restricciones:**
- CHECK: `tipo` IN ('manual', 'semi_automatico', 'automatico')
- UNIQUE: `codigo`

---

### 2. Tabla: `rutas_produccion`

Define la secuencia de operaciones necesarias para fabricar un producto (asociado a un BOM).

| Campo | Tipo | NULL | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | integer | NO | AUTO | Identificador único |
| `bom_id` | integer | NO | - | Referencia a Lista de Materiales |
| `secuencia` | integer | NO | - | Orden de la operación (10, 20, 30...) |
| `centro_trabajo_id` | integer | NO | - | Centro donde se realiza la operación |
| `descripcion` | varchar(255) | NO | - | Descripción de la operación |
| `tiempo_setup_mins` | integer | YES | 0 | Tiempo de preparación en minutos |
| `tiempo_proceso_unitario_mins` | numeric(10,2) | YES | 0 | Tiempo de proceso por unidad en minutos |
| `tiempo_cola_mins` | integer | YES | 0 | Tiempo de espera en cola |
| `tiempo_movimiento_mins` | integer | YES | 0 | Tiempo de movimiento/transporte |
| `capacidad_requerida` | numeric(10,2) | YES | 1.0 | Cantidad de recursos necesarios |
| `costo_operacion_fijo` | numeric(12,2) | YES | 0.00 | Costo fijo de la operación |
| `costo_operacion_variable` | numeric(12,2) | YES | 0.00 | Costo variable por unidad |
| `instrucciones` | text | YES | NULL | Instrucciones detalladas para la operación |
| `created_at` | timestamp | YES | CURRENT_TIMESTAMP | Fecha de creación |

**Relaciones:**
- FK: `bom_id` → `boms.id`
- FK: `centro_trabajo_id` → `centros_trabajo.id`

**Índices recomendados:**
- `(bom_id, secuencia)` - Para obtener operaciones ordenadas
- `centro_trabajo_id` - Para filtrar por centro

---

### 3. Tabla: `ordenes_produccion`

Órdenes de producción que ejecutan la fabricación de productos.

| Campo | Tipo | NULL | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | integer | NO | AUTO | Identificador único |
| `numero_orden` | varchar(50) | NO | - | Número único de orden |
| `variante_id` | integer | NO | - | Variante del producto a fabricar |
| `bom_id_utilizada` | integer | YES | NULL | BOM específica utilizada |
| `cantidad_planificada` | numeric(12,4) | NO | - | Cantidad a producir |
| `cantidad_producida` | numeric(12,4) | YES | 0 | Cantidad producida |
| `cantidad_desechada` | numeric(12,4) | YES | 0 | Cantidad desechada |
| `fecha_inicio_programada` | date | NO | - | Fecha de inicio planificada |
| `fecha_fin_programada` | date | NO | - | Fecha de fin planificada |
| `fecha_inicio_real` | timestamp | YES | NULL | Fecha/hora real de inicio |
| `fecha_fin_real` | timestamp | YES | NULL | Fecha/hora real de fin |
| `estado` | varchar(20) | YES | 'pendiente' | Estado de la orden |
| `prioridad` | varchar(20) | YES | 'normal' | Prioridad de ejecución |
| `configuracion_orden` | jsonb | YES | '{}' | Configuración adicional en JSON |
| `observaciones` | text | YES | NULL | Notas y comentarios |
| `usuario_creador` | bigint | YES | NULL | Usuario que creó la orden |
| `fecha_creacion` | timestamp | YES | CURRENT_TIMESTAMP | Fecha de creación |
| `fecha_actualizacion` | timestamp | YES | CURRENT_TIMESTAMP | Fecha de última actualización |

**Restricciones:**
- CHECK: `estado` IN ('borrador', 'planificada', 'liberada', 'en_proceso', 'pausada', 'completada', 'cancelada', 'cerrada')
- CHECK: `prioridad` IN ('baja', 'normal', 'alta', 'urgente')
- UNIQUE: `numero_orden`

**Relaciones:**
- FK: `variante_id` → `variantes.id`
- FK: `bom_id_utilizada` → `boms.id`
- FK: `usuario_creador` → `usuarios.id`

---

### 4. Tabla: `planificacion_recursos`

Planificación de recursos para ejecutar operaciones de órdenes de producción.

| Campo | Tipo | NULL | Default | Descripción |
|-------|------|------|---------|-------------|
| `id` | integer | NO | AUTO | Identificador único |
| `orden_produccion_id` | integer | NO | - | Orden de producción asociada |
| `operacion_id` | integer | YES | NULL | Operación específica (ruta) |
| `centro_trabajo_id` | integer | NO | - | Centro asignado |
| `periodo` | tsrange | NO | - | Rango de tiempo asignado |
| `estado` | varchar(20) | YES | 'programado' | Estado de la planificación |
| `created_at` | timestamp | YES | CURRENT_TIMESTAMP | Fecha de creación |

**Restricciones:**
- CHECK: `estado` IN ('programado', 'en_ejecucion', 'completado')
- CHECK: `periodo` - lower(periodo) < upper(periodo)

**Relaciones:**
- FK: `orden_produccion_id` → `ordenes_produccion.id`
- FK: `operacion_id` → `rutas_produccion.id`
- FK: `centro_trabajo_id` → `centros_trabajo.id`

**Nota:** El campo `periodo` es de tipo `tsrange` (rango de timestamps) que permite validar solapamientos y optimizar consultas de disponibilidad.

---

## 🔗 Diagrama de Relaciones

```
┌─────────────────┐
│   variantes     │
└────────┬────────┘
         │
         │ 1:N
         ↓
┌─────────────────┐        ┌──────────────────┐
│     boms        │←──────→│ rutas_produccion │
└────────┬────────┘   1:N  └────────┬─────────┘
         │                          │
         │                          │ N:1
         │ 1:N                      ↓
         ↓                  ┌───────────────────┐
┌─────────────────────┐    │ centros_trabajo   │
│ ordenes_produccion  │    └───────┬───────────┘
└──────────┬──────────┘            │
           │                       │
           │ 1:N                   │ N:1
           ↓                       │
   ┌──────────────────────┐       │
   │ planificacion_       │←──────┘
   │ recursos             │
   └──────────────────────┘
```

---

## 📂 Estructura de Archivos Recomendada

### Arquitectura según estándares del proyecto (máx 400 líneas por archivo)

```
app/
├── controllers/
│   └── Produccion/
│       ├── OperacionesController.php           [300 líneas]
│       ├── CentrosTrabajoController.php        [300 líneas]
│       ├── RutasProduccionController.php       [400 líneas]
│       ├── OrdenesProduccionController.php     [400 líneas]
│       └── PlanificacionController.php         [400 líneas]
│
├── services/
│   └── produccion/
│       ├── OperacionService.php                [400 líneas]
│       ├── CentroTrabajoService.php            [350 líneas]
│       ├── RutaProduccionService.php           [400 líneas]
│       ├── OrdenProduccionService.php          [400 líneas]
│       ├── PlanificacionService.php            [400 líneas]
│       └── CapacidadService.php                [400 líneas]
│
├── Repositories/
│   └── produccion/
│       ├── OperacionRepository.php             [350 líneas]
│       ├── CentroTrabajoRepository.php         [350 líneas]
│       ├── RutaProduccionRepository.php        [400 líneas]
│       ├── OrdenProduccionRepository.php       [400 líneas]
│       └── PlanificacionRepository.php         [400 líneas]
│
├── models/
│   ├── CentroTrabajo.php                       [300 líneas] ✅ EXISTENTE
│   ├── RutaProduccion.php                      [300 líneas]
│   ├── OrdenProduccion.php                     [350 líneas]
│   └── PlanificacionRecurso.php                [300 líneas]
│
└── validators/
    └── produccion/
        ├── CentroTrabajoValidator.php          [250 líneas]
        ├── RutaProduccionValidator.php         [300 líneas]
        └── OrdenProduccionValidator.php        [350 líneas]

views/pages/produccion/
├── operaciones/
│   ├── index.php                               [200 líneas]
│   ├── _operaciones_list.php                   [150 líneas]
│   └── _operacion_form.php                     [200 líneas]
│
├── centros_trabajo/
│   ├── index.php                               [200 líneas]
│   ├── create.php                              [250 líneas]
│   ├── edit.php                                [250 líneas]
│   └── _centro_form.php                        [200 líneas]
│
├── rutas/
│   ├── index.php                               [200 líneas]
│   ├── editor.php                              [350 líneas]
│   ├── _ruta_secuencia.php                     [200 líneas]
│   └── _operacion_card.php                     [150 líneas]
│
├── ordenes/
│   ├── index.php                               [250 líneas]
│   ├── create.php                              [300 líneas]
│   ├── edit.php                                [300 líneas]
│   ├── view.php                                [350 líneas]
│   └── _orden_timeline.php                     [200 líneas]
│
└── planificacion/
    ├── gantt.php                               [350 líneas]
    ├── calendario.php                          [350 líneas]
    └── _recurso_timeline.php                   [250 líneas]

public/assets/js/
├── produccion/
│   ├── centros-trabajo.js                      [350 líneas]
│   ├── rutas-editor.js                         [400 líneas]
│   ├── ordenes-produccion.js                   [400 líneas]
│   └── planificacion-gantt.js                  [400 líneas]

routes/
└── web.php (agregar rutas)
```

---

## 🛤️ Rutas API/Web

### Centros de Trabajo

```php
// Lista y gestión de centros
GET     /produccion/centros-trabajo              → index()
GET     /produccion/centros-trabajo/create       → create()
POST    /produccion/centros-trabajo              → store()
GET     /produccion/centros-trabajo/{id}         → show()
GET     /produccion/centros-trabajo/{id}/edit    → edit()
PUT     /produccion/centros-trabajo/{id}         → update()
DELETE  /produccion/centros-trabajo/{id}         → destroy()

// API
GET     /api/centros-trabajo                     → getAllJson()
GET     /api/centros-trabajo/search              → searchJson()
GET     /api/centros-trabajo/{id}/disponibilidad → getDisponibilidad()
```

### Rutas de Producción

```php
// Gestión de rutas
GET     /produccion/rutas                        → index()
GET     /produccion/rutas/create                 → create()
POST    /produccion/rutas                        → store()
GET     /produccion/rutas/{id}                   → show()
GET     /produccion/rutas/{id}/edit              → edit()
PUT     /produccion/rutas/{id}                   → update()
DELETE  /produccion/rutas/{id}                   → destroy()

// Editor de rutas
GET     /produccion/rutas/{id}/editor            → editor()
POST    /produccion/rutas/{id}/operaciones       → addOperacion()
PUT     /produccion/rutas/operaciones/{opId}     → updateOperacion()
DELETE  /produccion/rutas/operaciones/{opId}     → deleteOperacion()
POST    /produccion/rutas/{id}/reordenar         → reorderOperaciones()

// API
GET     /api/rutas/bom/{bomId}                   → getRutasByBom()
GET     /api/rutas/{id}/operaciones              → getOperaciones()
POST    /api/rutas/{id}/calcular-tiempos         → calcularTiempos()
```

### Órdenes de Producción

```php
// CRUD de órdenes
GET     /produccion/ordenes                      → index()
GET     /produccion/ordenes/create               → create()
POST    /produccion/ordenes                      → store()
GET     /produccion/ordenes/{id}                 → show()
GET     /produccion/ordenes/{id}/edit            → edit()
PUT     /produccion/ordenes/{id}                 → update()
DELETE  /produccion/ordenes/{id}                 → destroy()

// Acciones de estado
POST    /produccion/ordenes/{id}/liberar         → liberar()
POST    /produccion/ordenes/{id}/iniciar         → iniciar()
POST    /produccion/ordenes/{id}/pausar          → pausar()
POST    /produccion/ordenes/{id}/reanudar        → reanudar()
POST    /produccion/ordenes/{id}/completar       → completar()
POST    /produccion/ordenes/{id}/cancelar        → cancelar()
POST    /produccion/ordenes/{id}/cerrar          → cerrar()

// Reportes
GET     /produccion/ordenes/{id}/avance          → getAvance()
GET     /produccion/ordenes/{id}/costos          → getCostos()

// API
GET     /api/ordenes/search                      → searchJson()
GET     /api/ordenes/pendientes                  → getPendientes()
GET     /api/ordenes/en-proceso                  → getEnProceso()
```

### Planificación de Recursos

```php
// Vistas de planificación
GET     /produccion/planificacion                → index()
GET     /produccion/planificacion/gantt          → gantt()
GET     /produccion/planificacion/calendario     → calendario()

// Operaciones de planificación
POST    /produccion/planificacion/calcular       → calcular()
POST    /produccion/planificacion/asignar        → asignarRecursos()
PUT     /produccion/planificacion/{id}           → updateAsignacion()
DELETE  /produccion/planificacion/{id}           → deleteAsignacion()

// API
GET     /api/planificacion/centro/{id}           → getPlanificacionByCentro()
GET     /api/planificacion/orden/{id}            → getPlanificacionByOrden()
GET     /api/planificacion/conflictos            → getConflictos()
POST    /api/planificacion/validar-capacidad     → validarCapacidad()
```

---

## 🎯 Flujos de Trabajo

### 1. Configuración Inicial de Operaciones

```
1. Crear Centros de Trabajo
   → Definir centros donde se realizarán operaciones
   → Configurar capacidad, eficiencia, costos

2. Definir Rutas de Producción
   → Asociar una ruta a un BOM específico
   → Agregar operaciones en secuencia
   → Asignar cada operación a un centro de trabajo
   → Definir tiempos y costos

3. Validar Rutas
   → Verificar que todos los centros estén activos
   → Calcular tiempos totales
   → Estimar costos de fabricación
```

### 2. Creación y Ejecución de Órdenes de Producción

```
1. Crear Orden de Producción
   → Seleccionar variante del producto
   → Definir cantidad y fechas
   → Seleccionar BOM y ruta a utilizar
   → Estado: "borrador"

2. Planificar Orden
   → Sistema calcula recursos necesarios
   → Valida disponibilidad de centros
   → Genera planificación de recursos
   → Estado: "planificada"

3. Liberar Orden
   → Reserva materiales (integración con Inventario)
   → Bloquea recursos en centros de trabajo
   → Estado: "liberada"

4. Iniciar Producción
   → Registro de inicio real
   → Estado: "en_proceso"
   → Actualiza planificación de recursos

5. Reportar Avance
   → Registrar cantidad producida por operación
   → Registrar tiempo real consumido
   → Registrar desechos/rechazos

6. Completar Orden
   → Cierre de todas las operaciones
   → Registro de cantidad final
   → Cálculo de costos reales
   → Estado: "completada"

7. Cerrar Orden
   → Cierre contable y administrativo
   → Estado: "cerrada"
```

### 3. Planificación de Capacidad

```
1. Consultar Disponibilidad
   → Ver carga de trabajo por centro
   → Identificar cuellos de botella

2. Asignar Recursos
   → Asignar operaciones a períodos específicos
   → Validar no solapamiento de recursos finitos

3. Optimizar Planificación
   → Algoritmo de nivelación de carga
   → Considerar prioridades de órdenes
   → Respetar restricciones de capacidad

4. Reprogramar
   → Ajustar fechas ante cambios
   → Resolver conflictos de recursos
```

---

## 💡 Ejemplos de Uso

### Ejemplo 1: Crear Centro de Trabajo

```php
// POST /produccion/centros-trabajo
{
    "codigo": "CT-SOLD-01",
    "nombre": "Centro de Soldadura #1",
    "descripcion": "Centro de soldadura TIG/MIG para aluminio",
    "tipo": "semi_automatico",
    "capacidad_horas_dia": 16.00,
    "eficiencia_porcentaje": 85.00,
    "costo_hora": 45.50,
    "capacidad_finita": true,
    "ubicacion": "Planta 1 - Nave A",
    "responsable": "Juan Pérez",
    "activo": true
}
```

### Ejemplo 2: Definir Ruta de Producción

```php
// POST /produccion/rutas
{
    "bom_id": 15,
    "operaciones": [
        {
            "secuencia": 10,
            "centro_trabajo_id": 3,
            "descripcion": "Corte de tubos de aluminio",
            "tiempo_setup_mins": 30,
            "tiempo_proceso_unitario_mins": 2.5,
            "tiempo_cola_mins": 15,
            "tiempo_movimiento_mins": 10,
            "costo_operacion_fijo": 25.00,
            "costo_operacion_variable": 1.50,
            "instrucciones": "Cortar tubos a 850mm con sierra de cinta. Verificar medida con calibre."
        },
        {
            "secuencia": 20,
            "centro_trabajo_id": 5,
            "descripcion": "Soldadura TIG de cuadro",
            "tiempo_setup_mins": 45,
            "tiempo_proceso_unitario_mins": 25.0,
            "tiempo_cola_mins": 30,
            "tiempo_movimiento_mins": 5,
            "costo_operacion_fijo": 50.00,
            "costo_operacion_variable": 8.75,
            "instrucciones": "Soldar uniones con electrodo TIG. Temperatura 180-200°C. Inspección visual obligatoria."
        },
        {
            "secuencia": 30,
            "centro_trabajo_id": 8,
            "descripcion": "Pintura electrostática",
            "tiempo_setup_mins": 60,
            "tiempo_proceso_unitario_mins": 10.0,
            "tiempo_cola_mins": 45,
            "tiempo_movimiento_mins": 20,
            "costo_operacion_fijo": 35.00,
            "costo_operacion_variable": 12.00,
            "instrucciones": "Aplicar pintura epóxica negra. Espesor 60-80 micras. Curado en horno 200°C por 15 min."
        }
    ]
}
```

### Ejemplo 3: Crear Orden de Producción

```php
// POST /produccion/ordenes
{
    "numero_orden": "OP-2026-001234",
    "variante_id": 42,
    "bom_id_utilizada": 15,
    "cantidad_planificada": 50,
    "fecha_inicio_programada": "2026-02-20",
    "fecha_fin_programada": "2026-02-28",
    "prioridad": "alta",
    "observaciones": "Pedido urgente para cliente XYZ. Coordinar con logística."
}
```

### Ejemplo 4: Consultar Disponibilidad de Centro

```php
// GET /api/centros-trabajo/5/disponibilidad?fecha_inicio=2026-02-20&fecha_fin=2026-02-28

// Respuesta:
{
    "centro_trabajo_id": 5,
    "centro_nombre": "Centro de Soldadura #2",
    "periodo_consultado": {
        "inicio": "2026-02-20T00:00:00Z",
        "fin": "2026-02-28T23:59:59Z"
    },
    "capacidad_total_horas": 128.0,
    "horas_asignadas": 87.5,
    "horas_disponibles": 40.5,
    "porcentaje_ocupacion": 68.36,
    "asignaciones": [
        {
            "orden_produccion_numero": "OP-2026-001200",
            "operacion_descripcion": "Soldadura TIG de cuadro",
            "periodo_asignado": {
                "inicio": "2026-02-20T08:00:00Z",
                "fin": "2026-02-22T17:00:00Z"
            },
            "horas": 32.0
        },
        {
            "orden_produccion_numero": "OP-2026-001215",
            "operacion_descripcion": "Soldadura de refuerzos",
            "periodo_asignado": {
                "inicio": "2026-02-23T08:00:00Z",
                "fin": "2026-02-26T18:30:00Z"
            },
            "horas": 55.5
        }
    ]
}
```

---

## ✨ Características a Implementar

### Funcionalidades Básicas (MVP)

- ✅ CRUD de Centros de Trabajo
- ✅ CRUD de Rutas de Producción
- ✅ Editor visual de secuencia de operaciones
- ✅ CRUD de Órdenes de Producción
- ✅ Cambios de estado de órdenes
- ✅ Vista de planificación básica

### Funcionalidades Avanzadas

- ⏳ Gantt de planificación de recursos
- ⏳ Algoritmo de asignación automática de recursos
- ⏳ Detección de conflictos de capacidad
- ⏳ Cálculo automático de fechas según ruta
- ⏳ Reportes de eficiencia por centro
- ⏳ Dashboard de operaciones en tiempo real
- ⏳ Integración con sistema de calidad
- ⏳ Reportes de costos reales vs planificados
- ⏳ Gestión de paradas/mantenimiento de centros
- ⏳ Capacitación y certificación de operadores

### Integraciones

- 📦 **Inventario**: Reserva de materiales al liberar orden
- 📋 **Planeamiento**: MRP genera automáticamente órdenes
- 💰 **Costos**: Cálculo de costos estándar y reales
- 📊 **Reportes**: Dashboards y KPIs de producción
- 👥 **RRHH**: Asignación de operadores a centros
- 🔧 **Mantenimiento**: Registro de paradas y mantenimiento

---

## 🧮 Cálculos Importantes

### Tiempo Total de Fabricación

```
Tiempo Total = Σ (Tiempo Setup + (Tiempo Proceso Unitario × Cantidad) + 
                  Tiempo Cola + Tiempo Movimiento)

Para todas las operaciones en la ruta
```

### Costo Total de Operaciones

```
Costo Total = Σ (Costo Fijo + (Costo Variable × Cantidad))

Para todas las operaciones en la ruta
```

### Capacidad Disponible de Centro

```
Capacidad Disponible = (Capacidad Horas Día × Días) × (Eficiencia % / 100) - 
                       Horas Ya Asignadas
```

### Fecha Fin Estimada

```
Para cada operación:
    Fecha Inicio = Fecha Fin Operación Anterior + Tiempo Movimiento
    Duración = Tiempo Setup + (Tiempo Proceso × Cantidad / Capacidad Centro)
    Fecha Fin = Fecha Inicio + Duración + Tiempo Cola

Fecha Fin Orden = Fecha Fin Última Operación
```

---

## 📊 Consultas SQL Útiles

### Operaciones de una Ruta Ordenadas

```sql
SELECT 
    rp.secuencia,
    rp.descripcion,
    ct.codigo AS centro_codigo,
    ct.nombre AS centro_nombre,
    rp.tiempo_setup_mins,
    rp.tiempo_proceso_unitario_mins,
    rp.costo_operacion_fijo,
    rp.costo_operacion_variable
FROM rutas_produccion rp
INNER JOIN centros_trabajo ct ON ct.id = rp.centro_trabajo_id
WHERE rp.bom_id = :bom_id
  AND ct.activo = true
ORDER BY rp.secuencia;
```

### Órdenes en Proceso por Centro

```sql
SELECT 
    op.numero_orden,
    v.codigo AS variante,
    pr.periodo,
    rp.descripcion AS operacion
FROM planificacion_recursos pr
INNER JOIN ordenes_produccion op ON op.id = pr.orden_produccion_id
INNER JOIN variantes v ON v.id = op.variante_id
LEFT JOIN rutas_produccion rp ON rp.id = pr.operacion_id
WHERE pr.centro_trabajo_id = :centro_id
  AND pr.estado IN ('programado', 'en_ejecucion')
  AND pr.periodo && tsrange(CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + interval '7 days')
ORDER BY lower(pr.periodo);
```

### Carga de Trabajo por Centro (próximos 30 días)

```sql
SELECT 
    ct.codigo,
    ct.nombre,
    ct.capacidad_horas_dia,
    COUNT(pr.id) AS operaciones_asignadas,
    SUM(
        EXTRACT(EPOCH FROM (upper(pr.periodo) - lower(pr.periodo))) / 3600
    )::numeric(10,2) AS horas_asignadas,
    (ct.capacidad_horas_dia * 30)::numeric(10,2) AS capacidad_total_mes,
    (SUM(
        EXTRACT(EPOCH FROM (upper(pr.periodo) - lower(pr.periodo))) / 3600
    ) / (ct.capacidad_horas_dia * 30) * 100)::numeric(5,2) AS porcentaje_ocupacion
FROM centros_trabajo ct
LEFT JOIN planificacion_recursos pr ON pr.centro_trabajo_id = ct.id
    AND pr.periodo && tsrange(CURRENT_DATE, CURRENT_DATE + interval '30 days')
WHERE ct.activo = true
GROUP BY ct.id, ct.codigo, ct.nombre, ct.capacidad_horas_dia
ORDER BY porcentaje_ocupacion DESC;
```

### Órdenes Prioritarias sin Planificar

```sql
SELECT 
    op.numero_orden,
    op.prioridad,
    v.codigo AS variante,
    op.cantidad_planificada,
    op.fecha_inicio_programada,
    op.fecha_fin_programada,
    COUNT(rp.id) AS total_operaciones,
    COUNT(pr.id) AS operaciones_planificadas
FROM ordenes_produccion op
INNER JOIN variantes v ON v.id = op.variante_id
LEFT JOIN rutas_produccion rp ON rp.bom_id = op.bom_id_utilizada
LEFT JOIN planificacion_recursos pr ON pr.orden_produccion_id = op.id
WHERE op.estado IN ('planificada', 'liberada')
GROUP BY op.id, v.codigo
HAVING COUNT(pr.id) < COUNT(rp.id)
ORDER BY 
    CASE op.prioridad
        WHEN 'urgente' THEN 1
        WHEN 'alta' THEN 2
        WHEN 'normal' THEN 3
        WHEN 'baja' THEN 4
    END,
    op.fecha_inicio_programada;
```

---

## 🔐 Validaciones Importantes

### Al Crear/Editar Ruta de Producción

1. ✅ El BOM debe existir y estar activo
2. ✅ Las secuencias deben ser únicas dentro de la ruta
3. ✅ Las secuencias deben ser múltiplos de 10 (10, 20, 30...)
4. ✅ Todos los centros de trabajo deben existir y estar activos
5. ✅ Los tiempos deben ser no negativos
6. ✅ Los costos deben ser no negativos

### Al Crear Orden de Producción

1. ✅ La variante debe existir y estar activa
2. ✅ La cantidad debe ser mayor a cero
3. ✅ Fecha fin debe ser posterior a fecha inicio
4. ✅ El BOM seleccionado debe estar asociado a la variante
5. ✅ Debe existir una ruta definida para el BOM seleccionado
6. ✅ Número de orden debe ser único

### Al Liberar Orden

1. ✅ Orden debe estar en estado "planificada"
2. ✅ Debe existir planificación de recursos completa
3. ✅ No debe haber conflictos de capacidad
4. ✅ Debe haber materiales disponibles (integración con Inventario)

### Al Asignar Recursos

1. ✅ El centro debe estar activo
2. ✅ No debe solaparse con otras asignaciones del mismo centro (si capacidad finita)
3. ✅ El rango de tiempo debe ser válido
4. ✅ La fecha debe estar dentro del rango de la orden

---

## 🎨 Consideraciones de UI/UX

### Editor de Rutas

- Drag & drop para reordenar operaciones
- Preview de tiempos totales y costos
- Validación en tiempo real de centros
- Copiar ruta de otro BOM similar

### Planificación Gantt

- Vista por centro de trabajo
- Vista por orden de producción
- Zoom temporal (día/semana/mes)
- Drag & drop para reasignar períodos
- Colores por prioridad/estado
- Tooltips con información detallada

### Dashboard de Operaciones

- Órdenes en proceso en tiempo real
- Alertas de órdenes retrasadas
- Centros con sobreasignación
- KPIs: eficiencia, cumplimiento, utilización

---

## 🚀 Plan de Implementación Sugerido

### Fase 1: Fundamentos (Semana 1-2)
1. Modelos, Repositories, Services de Centros de Trabajo
2. CRUD completo de Centros
3. Validaciones y tests unitarios

### Fase 2: Rutas de Producción (Semana 3-4)
1. Modelos y lógica de Rutas
2. Editor de rutas con secuencia
3. Cálculos de tiempos y costos

### Fase 3: Órdenes de Producción (Semana 5-7)
1. CRUD de Órdenes
2. Máquina de estados
3. Integración con Inventario
4. Reportes básicos

### Fase 4: Planificación (Semana 8-10)
1. Lógica de asignación de recursos
2. Validación de capacidad
3. Vista Gantt básica
4. Detección de conflictos

### Fase 5: Optimización (Semana 11-12)
1. Algoritmos de planificación automática
2. Dashboards y métricas
3. Reportes avanzados
4. Optimización de performance

---

## 📚 Referencias y Recursos

- [PostgreSQL Range Types](https://www.postgresql.org/docs/current/rangetypes.html) - Para `tsrange`
- [Teoría de Restricciones (TOC)](https://es.wikipedia.org/wiki/Teor%C3%ADa_de_las_restricciones) - Gestión de cuellos de botella
- [Gantt Charts Best Practices](https://www.gantt.com/) - Para planificación visual
- Estándares del proyecto: `.github/instructions/unik.instructions.md`

---

## 📝 Notas Finales

Este documento define la estructura completa para implementar Operaciones y Rutas de Producción en el MRP. Sigue los estándares arquitectónicos del proyecto (máx 400 líneas por archivo) y utiliza la estructura de base de datos existente.

**Última actualización:** 15 de febrero de 2026  
**Versión:** 1.0  
**Autor:** Sistema MRP - Módulo de Producción
