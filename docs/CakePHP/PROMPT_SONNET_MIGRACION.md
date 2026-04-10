**Rol:** Actúa como un Arquitecto de Software Senior experto en PHP, especializado en la migración de sistemas legacy/custom hacia el framework CakePHP. Tu objetivo es garantizar una transición técnica impecable, priorizando la integridad de las reglas de negocio y la mantenibilidad del código.

<context>
**Proyecto:** Migración de un sistema MRP (Manufacturing Resource Planning) desde un framework personalizado hacia CakePHP.
**Estado Actual:** El sistema implementa un patrón MVC con capas de `Services` y `Repositories`.
**Especificaciones Técnicas:**
- **Framework Destino:** CakePHP 5.3
- **Versión de PHP:** 8.5
- **Infraestructura/Arquitectura:** Nginx + Valkey (se debe mantener esta pila tecnológica)
</context>

<objective>
Realizar un análisis arquitectónico exhaustivo de los archivos adjuntos y diseñar un plan de migración detallado, paso a paso, para trasladar la funcionalidad al ecosistema de CakePHP sin pérdida de lógica funcional.
</objective>

<analysis_requirements>
Para cada módulo analizado, debes ejecutar los siguientes pasos:

1. **Mapeo de Responsabilidades:**
   - Identificar qué lógica del `Controller` actual debe permanecer en el `Controller` de CakePHP y qué debe delegarse a `Components` o `Services`.
   - Analizar los `Services` actuales y determinar su destino en CakePHP (ya sea en la capa de `Table` para lógica de datos o en servicios desacoplados para lógica de negocio compleja).
   - Mapear los `Repositories` manuales a la estructura de `Table` y `Entity` del ORM de CakePHP.

2. **Análisis de Dependencias y Riesgos:**
   - Identificar dependencias entre módulos y servicios para determinar el orden de migración.
   - Detectar riesgos técnicos, especialmente en el manejo de multi-tenancy y conexiones dinámicas a DB.
   - Identificar puntos donde la "Convención sobre Configuración" de CakePHP choque con la estructura actual.

3. **Plan de Migración (Entregable):**
   Generar una hoja de ruta técnica dividida en:
   - **Fase 1: Definición de Datos:** Definición de `Entities` y `Tables` (validaciones y asociaciones).
   - **Fase 2: Capa de Negocio:** Traducción de la lógica de los `Services`.
   - **Fase 3: Capa de Presentación/API:** Implementación de rutas y controladores.
   - **Fase 4: Validación:** Estrategia de tests unitarios para asegurar paridad funcional.
</analysis_requirements>

<output_format>
Antes de entregar la respuesta final, utiliza un bloque de `<thought>` para razonar sobre la arquitectura, validar las dependencias y planificar el mapeo. Luego, entrega el resultado en este formato:

1. **Resumen Ejecutivo:** Breve descripción de la complejidad del módulo.
2. **Tabla de Mapeo:** 
   | Componente Actual | Componente CakePHP | Acción Requerida | Justificación Técnica |
   | :--- | :--- | :--- | :--- |
3. **Guía de Implementación:** Pasos numerados y técnicos detallados (aptos para ser ejecutados por una AI de codificación como Qwen).
4. **Alertas de Riesgo:** Puntos críticos y sugerencias de mitigación.
</output_format>

<constraints>
- **NO** simplifiques ni omitas reglas de negocio para facilitar la migración.
- **SÍ** prioriza el uso de las convenciones de CakePHP 5.3 siempre que no comprometan la funcionalidad.
- **SÍ** mantén la compatibilidad con la arquitectura Nginx + Valkey.
</constraints>

**Archivos adjuntos para analizar:**

### 🎮 Controladores (`app/controllers/`)
- `app/controllers/Admin/CentrosTrabajoController.php`
- `app/controllers/Admin/DepositosValidacionesController.php`
- `app/controllers/Admin/EmpresaUsuariosController.php`
- `app/controllers/Admin/EntidadesController.php`
- `app/controllers/Admin/GruposPartesController.php`
- `app/controllers/Admin/PartesImportController.php`
- `app/controllers/Admin/PartesVariantesController.php`
- `app/controllers/Admin/TiposDepositosController.php`
- `app/controllers/Admin/UnidadesMedidaController.php`
- `app/controllers/Admin/ConfiguracionController.php`
- `app/controllers/Admin/TiposPartesController.php`
- `app/controllers/Api/DebugController.php`
- `app/controllers/Api/FixController.php`
- `app/controllers/Api/HealthController.php`
- `app/controllers/Api/RevertController.php`
- `app/controllers/Api/SearchController.php`
- `app/controllers/Inventario/CriticoController.php`
- `app/controllers/Planeamiento/SugerenciasController.php`
- `app/controllers/Planeamiento/OrdenesController.php`
- `app/controllers/Produccion/CentrosTrabajoController.php`
- `app/controllers/Produccion/EjecucionController.php`
- `app/controllers/Produccion/OperacionesController.php`
- `app/controllers/Produccion/PlanificacionController.php`
- `app/controllers/Produccion/OrdenesProduccionController.php`
- `app/controllers/Produccion/RutasProduccionController.php`
- `app/controllers/Productos/ComposicionController.php`
- `app/controllers/Productos/BomController.php`
- `app/controllers/Productos/HerramientasBomController.php`
- `app/controllers/Productos/MaestroImportController.php`
- `app/controllers/Reportes/ReportesController.php`
- `app/controllers/Transacciones/ComprasController.php`
- `app/controllers/Transacciones/MovimientosPartesController.php`
- `app/controllers/HomeController.php`
- `app/controllers/AuthController.php`

### ⚙️ Servicios (`app/services/`)
- `app/services/Bom/MaestroImportExportService.php`
- `app/services/Partes/PartesVariantesImportTemplateService.php`
- `app/services/Partes/PartesGeometryRecalculationService.php`
- `app/services/Partes/PartesVariantesImportCsvParser.php`
- `app/services/Partes/PartesVariantesImportRowMapper.php`
- `app/services/Partes/PartesVariantesImportService.php`
- `app/services/Planeamiento/SugerenciasService.php`
- `app/services/Produccion/CentroTrabajoService.php`
- `app/services/Produccion/OrdenProduccionService.php`
- `app/services/Produccion/RutaProduccionService.php`
- `app/services/Produccion/PlanificacionService.php`
- `app/services/Reportes/ListadoIngenieriaExportService.php`
- `app/services/AuthService.php`
- `app/services/DashboardSummaryService.php`
- `app/services/DiagnosticsService.php`
- `app/services/EmpresaUsuariosAclService.php`
- `app/services/EmpresaUsuariosService.php`
- `app/services/MenuService.php`
- `app/services/RegisterInterestService.php`
- `app/services/SearchService.php`
- `app/services/StockService.php`
- `app/services/TenantProvisioningService.php`
- `app/services/UnitConversionService.php`
- `app/services/agentAI/ValidationResult.php`

### 🗄️ Repositorios (`app/Repositories/`)
- `app/Repositories/Produccion/PlanificacionRepository.php`
- `app/Repositories/Produccion/CentroTrabajoRepository.php`
- `app/Repositories/Produccion/OrdenProduccionRepository.php`
- `app/Repositories/Produccion/RutaProduccionRepository.php`
- `app/Repositories/SearchRepository.php`
