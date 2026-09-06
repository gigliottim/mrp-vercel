# Graph Report - mrp-vercel  (2026-09-06)

## Corpus Check
- 351 files · ~274,117 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1776 nodes · 3752 edges · 188 communities (46 shown, 69 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 144 edges (avg confidence: 0.84)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- Logging y errores
- Composición BOM JS
- Assets y layout
- DTO Agente AI
- BOM Controller
- API Búsqueda
- Entidades CRUD
- Compras
- Validaciones depósitos
- Schema tenant (backup)
- Auth y agente
- Schema wizard tenant
- Empresa usuarios admin
- Partes y variantes CRUD
- Dump full tenant
- Kernel request lifecycle
- Planificación recursos
- Import partes
- Órdenes producción modelo
- Operaciones y rutas
- Home y dashboard
- Config core
- Export listado ingeniería
- SearchClient JS
- SearchRepository SQL
- Schema mrp_auth (backup)
- DatabaseManager PDO
- Deploy scripts
- Modelo Parte
- ConversationRepository
- Configuración general
- ConversationService
- Órdenes producción controller
- ValkeyClient
- DashboardSummaryService
- Agente AI controller
- AuthService
- Dump full mrp_auth
- EmpresaUsuariosAclService
- Modelo Variante
- PlanificacionService
- RutaProduccionService
- PromptBuilder
- UnidadMedida
- RutaProducción modelo
- RutaProduccionRepository
- Migración CakePHP
- MenuService ACL
- CentroTrabajoService
- JS layout main
- Agente AI excepciones y cliente
- Composición controller
- Middleware pipeline
- Validación respuestas IA
- Conceptos arquitectura (docs)
- Import maestro partes
- TipoDepositoMovimiento
- Helpers globales
- Permisos tree JS
- RowMapper import
- UnidadesMedidaController
- BaseTenantModel
- Centros trabajo admin
- Grupos partes CRUD
- Tipos partes CRUD
- UnitConversionService
- Configuración controller
- Herramientas BOM
- CSV parser import
- Fix encoding scripts
- Valkey y stack (docs)
- Módulo Agente AI (docs)
- AuthController registro
- ValidationResult
- Stack futuro y sidebar (docs)
- Búsqueda partes JS
- Fix encoding editor
- Roles super admin SQL
- SSH VPS PS1
- Multi-tenancy (concepto)
- Dependencias Composer
- Provisioning wizard (docs)
- Fix tunna SQL
- Modelos IA JSON (docs)
- Fixes 500 agente AI
- Migración CakePHP (docs)
- migrate_database.php
- Empresa delete JS
- Check register permissions
- Migración agent_conversations
- Migración agent_messages
- Migración agent_ai_logs
- Migración agent_conversations (dup)
- Migración agent_messages (dup)
- Migración agent_ai_logs (dup)
- BOMs (docs)
- Movimientos depósitos (docs)
- Doble unidad conversión (docs)
- Export XLSX (docs)
- Fix deploy agente (docs)
- Restore VPS Postgres
- Align DB owner VPS
- Fix DB perms VPS
- Fix DB owner host VPS
- Recreate DBs VPS
- Rotate DB password VPS
- Reproducibilidad IA (docs)
- Stack PHP 8.4 (docs)
- Credenciales demo (docs)
- JSONB flexibilidad (docs)
- Formatos búsqueda (docs)
- Refactor acordeón (docs)
- Favicon MRP
- Hero mockup UI
- Tabla unidades_medida

## God Nodes (most connected - your core abstractions)
1. `Response` - 256 edges
2. `Request` - 247 edges
3. `url()` - 78 edges
4. `Controller` - 74 edges
5. `AgentService` - 57 edges
6. `Bom` - 48 edges
7. `Variante` - 48 edges
8. `DatabaseManager` - 46 edges
9. `AuthManager` - 43 edges
10. `EmpresaUsuariosController` - 38 edges

## Surprising Connections (you probably didn't know these)
- `Separación DB Auth vs DB MRP + tenant_id` --semantically_similar_to--> `Multi-Tenancy DB-per-tenant`  [INFERRED] [semantically similar]
  docs/old/chat-Arquitectura JSONB para MRP Multiempresa.txt → AGENTS.md
- `Valkey: caché respuestas IA, sesiones TTL, rate limiting` --semantically_similar_to--> `Valkey 8 Cache`  [INFERRED] [semantically similar]
  .github/prompts/plan-agentAiChatbot.prompt.md → AGENTS.md
- `Normativa: máx 400-500 líneas/archivo, assets externalizados` --semantically_similar_to--> `Principios SOLID obligatorios`  [INFERRED] [semantically similar]
  docs/old/refactoring-maestro.md → AGENTS.md
- `Fix 500 agente AI: clase AgentController namespace` --semantically_similar_to--> `Fix error 500 /api/v1/agent/suggestions (config default)`  [INFERRED] [semantically similar]
  docs/old/PLAN-fix-agent-500-errors.md → agenteAI/docs/error-500-agent-suggestions.md
- `Fix 500 POST /api/v1/agent/message` --semantically_similar_to--> `Fix error 500 /api/v1/agent/suggestions (config default)`  [INFERRED] [semantically similar]
  docs/PLAN-fix-agent-message-500.md → agenteAI/docs/error-500-agent-suggestions.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Pipeline Agente AI (caché Valkey → IA → persistencia PostgreSQL)** — github_prompts_plan_agentai_chatbot_valkey, github_prompts_plan_agentai_chatbot_flow, agenteai_docs_agentearchivos_agent_tables [EXTRACTED 1.00]
- **Provisioning multi-tenant (wizard registro + migraciones + naming)** — docs_old_wizard_registro_provisioning, docs_old_wizard_registro_naming_rules, docs_old_wizard_provisioning_migrations, docs_old_jsonb_multitenant_auth_separation [EXTRACTED 1.00]
- **Sistema de búsqueda en capas SOLID** — docs_old_searchclient_architecture_flow, docs_old_searchclient_formats, docs_old_searchclient_solid [EXTRACTED 1.00]
- **Sistema de diseño UI actual (Bootstrap + Alpine + Chart.js)** — mrp_new_design_modern_demo, resumen_proyecto_ui_modules, resumen_proyecto_ui_agent_luchi, public_assets_img_hero_mockup [EXTRACTED 1.00]
- **Ciclo de fixes del agente AI (500s, offline, deploy)** — docs_old_plan_refactor_agentai_scattered, docs_old_plan_fix_agent_message_500, docs_old_plan_fix_agent_offline_fix, agenteai_docs_error500_suggestions_fix [EXTRACTED 1.00]

## Communities (188 total, 69 thin omitted)

### Community 0 - "Logging y errores"
Cohesion: 0.07
Nodes (9): Logger, self, EmpresaUsuariosService, PDO, PDO, TenantProvisioningService, base_path(), RuntimeException (+1 more)

### Community 1 - "Composición BOM JS"
Cohesion: 0.06
Nodes (65): addComponentFromList(), appendAddedComponentToTree(), applyAutoCalculatedEditQuantity(), autoSelectFocusedNode(), autoSelectRoot(), buildChildPath(), buildFullVariantLabel(), canAutoCalculateEditQuantity() (+57 more)

### Community 3 - "DTO Agente AI"
Cohesion: 0.10
Nodes (3): AgentResponse, AgentService, PDO

### Community 4 - "BOM Controller"
Cohesion: 0.06
Nodes (6): BomController, Bom, MaestroImportExportService, PDO, PDO, SugerenciasService

### Community 5 - "API Búsqueda"
Cohesion: 0.06
Nodes (5): SearchController, CentrosTrabajoController, PlanificacionController, self, Response

### Community 6 - "Entidades CRUD"
Cohesion: 0.06
Nodes (5): EntidadesController, TiposDepositosController, RutasProduccionController, MaestroImportController, Request

### Community 7 - "Compras"
Cohesion: 0.09
Nodes (7): ComprasController, MovimientosPartesController, Compra, Entidad, MovimientoStock, TipoDeposito, StockService

### Community 8 - "Validaciones depósitos"
Cohesion: 0.07
Nodes (10): DepositosValidacionesController, DebugController, FixController, HealthController, RevertController, CriticoController, OrdenesController, SugerenciasController (+2 more)

### Community 9 - "Schema tenant (backup)"
Cohesion: 0.09
Nodes (35): public.agent_ai_logs, public.agent_conversations, public.agent_messages, public.almacenes, public.bom_cabecera, public.bom_detalle, public.centros_trabajo, public.composicion_variantes (+27 more)

### Community 10 - "Auth y agente"
Cohesion: 0.09
Nodes (5): AuthManager, TenantContext, SessionManager, executeSeed(), getTenantInfoFromSession()

### Community 11 - "Schema wizard tenant"
Cohesion: 0.10
Nodes (32): public.almacenes, public.bom_cabecera, public.bom_detalle, public.centros_trabajo, public.composicion_variantes, public.compras, public.configuracion, public.configuracion_general (+24 more)

### Community 14 - "Dump full tenant"
Cohesion: 0.06
Nodes (27): public.agent_ai_logs, public.agent_conversations, public.agent_messages, public.almacenes, public.bom_cabecera, public.bom_detalle, public.centros_trabajo, public.composicion_variantes (+19 more)

### Community 15 - "Kernel request lifecycle"
Cohesion: 0.12
Nodes (8): Kernel, MiddlewareInterface, MiddlewarePipeline, Router, AuthenticateMiddleware, SecurityHeadersMiddleware, SessionMiddleware, InvalidArgumentException

### Community 16 - "Planificación recursos"
Cohesion: 0.09
Nodes (3): PlanificacionRecurso, PDO, PlanificacionRepository

### Community 17 - "Import partes"
Cohesion: 0.13
Nodes (5): PartesImportController, ReportesController, GrupoParte, TipoParte, PartesVariantesImportTemplateService

### Community 18 - "Órdenes producción modelo"
Cohesion: 0.12
Nodes (3): OrdenProduccion, OrdenProduccionRepository, PDO

### Community 19 - "Operaciones y rutas"
Cohesion: 0.13
Nodes (4): OperacionesController, CentroTrabajo, CentroTrabajoRepository, PDO

### Community 20 - "Home y dashboard"
Cohesion: 0.13
Nodes (3): HomeController, DiagnosticsService, OrdenProduccionService

### Community 22 - "Export listado ingeniería"
Cohesion: 0.14
Nodes (9): ListadoIngenieriaExportService, PhpOffice\PhpSpreadsheet\Cell\Coordinate, PhpOffice\PhpSpreadsheet\IOFactory, PhpOffice\PhpSpreadsheet\Spreadsheet, PhpOffice\PhpSpreadsheet\Style\Alignment, PhpOffice\PhpSpreadsheet\Style\Border, PhpOffice\PhpSpreadsheet\Style\Fill, PhpOffice\PhpSpreadsheet\Worksheet\PageSetup (+1 more)

### Community 24 - "SearchRepository SQL"
Cohesion: 0.14
Nodes (3): PDO, SearchRepository, SearchService

### Community 25 - "Schema mrp_auth (backup)"
Cohesion: 0.17
Nodes (20): public.audit_logs, public.companies, public.company_databases, public.fn_super_admin_auto_link(), public.menu_acl, public.menu_items, public.permissions, public.personal_access_tokens (+12 more)

### Community 26 - "DatabaseManager PDO"
Cohesion: 0.12
Nodes (7): DatabaseManager, PDO, PDOException, cleanupSmokeData(), fail(), line(), out()

### Community 27 - "Deploy scripts"
Cohesion: 0.18
Nodes (18): assert_file_exists(), assert_no_legacy_credentials(), _DEPLOY_SSH_HOST, _DEPLOY_SSH_KEY, _DEPLOY_SSH_PASS, _DEPLOY_SSH_PORT, _DEPLOY_SSH_USER, error() (+10 more)

### Community 28 - "Modelo Parte"
Cohesion: 0.16
Nodes (3): Parte, PDO, VarianteDeletionService

### Community 30 - "Configuración general"
Cohesion: 0.19
Nodes (3): ConfiguracionGeneral, PartesGeometryRecalculationService, PDO

### Community 34 - "DashboardSummaryService"
Cohesion: 0.20
Nodes (4): DashboardSummaryService, PDO, RegisterInterestService, PDO

### Community 37 - "Dump full mrp_auth"
Cohesion: 0.14
Nodes (14): public.audit_logs, public.companies, public.company_databases, public.fn_super_admin_auto_link(), public.menu_acl, public.menu_items, public.permissions, public.personal_access_tokens (+6 more)

### Community 46 - "Migración CakePHP"
Cohesion: 0.23
Nodes (13): Cake\Core\Configure, Cake\Database\Connection, Cake\Database\Driver\Postgres, Cake\Datasource\ConnectionManager, Connection, ensureMigrationsTable(), getExecutedMigrations(), isIdempotentSqlError() (+5 more)

### Community 50 - "Agente AI excepciones y cliente"
Cohesion: 0.24
Nodes (3): AgentAiException, AiClient, Exception

### Community 53 - "Validación respuestas IA"
Cohesion: 0.20
Nodes (3): ResponseValidator, ValidationResult, ValidationResult

### Community 54 - "Conceptos arquitectura (docs)"
Cohesion: 0.18
Nodes (11): Frontend CDN sin build (Bootstrap 5.3.7, Alpine 3.14.9), Arquitectura MVC Custom Framework, Principios SOLID obligatorios, Tablas producción: centros_trabajo, rutas, operaciones, Gestor moderno de partes y variantes (2 columnas, Alpine.js), Normativa: máx 400-500 líneas/archivo, assets externalizados, SearchClient arquitectura: JS → API → Service → Repository, SearchClient módulo reutilizable SOLID (+3 more)

### Community 55 - "Import maestro partes"
Cohesion: 0.27
Nodes (3): PartesVariantesImportService, PDO, Throwable

### Community 57 - "Helpers globales"
Cohesion: 0.25
Nodes (6): app_decimal_step(), app_format_datetime(), app_format_number(), app_general_settings(), app_round_decimal(), env()

### Community 58 - "Permisos tree JS"
Cohesion: 0.38
Nodes (9): extractSubjectId(), getSectionIds(), getSubtreeIds(), onRolePermChange(), propagateRoleToUsers(), selectNode(), updateRowState(), updateRowStateInherited() (+1 more)

### Community 72 - "Fix encoding scripts"
Cohesion: 0.52
Nodes (4): fixEncoding(), hasCp437Mojibake(), hasLatin1Mojibake(), hasMojibake()

### Community 73 - "Valkey y stack (docs)"
Cohesion: 0.33
Nodes (6): Valkey 8 Cache, Stack LEPP Docker Compose (nginx+php-fpm+postgresql+valkey), Fix offline: endpoint ollama.com/v1/chat/completions, Fix aplicado: endpoint y modelos correctos en .env, Rate limiting configurado en valkey.php pero no implementado, Valkey: caché respuestas IA, sesiones TTL, rate limiting

### Community 74 - "Módulo Agente AI (docs)"
Cohesion: 0.40
Nodes (5): Tablas agent_conversations / agent_messages / agent_ai_logs, Carpeta centralizada agenteAI/ backend+frontend+database, Problema: archivos agente AI desparramados por el proyecto, Flujo agente: Valkey GET hit/miss → IA → guardar, Agente AI 'Luchi': chat + botón flotante en layout app

### Community 77 - "Stack futuro y sidebar (docs)"
Cohesion: 0.40
Nodes (5): Decisión: mantener LEPP core, Node incremental para real-time, Rol arquitecto senior para migración con análisis por módulo, Visión general MRP: partes, BOM, órdenes, inventario, centros, Sidebar desde BD mrp_auth (menu_items + ACL por rol), Navegación dinámica desde MenuService basado en ACL

### Community 80 - "Roles super admin SQL"
Cohesion: 0.50
Nodes (4): roles, fn_super_admin_auto_link(), trg_super_admin_auto_link, users

### Community 83 - "SSH VPS PS1"
Cohesion: 0.60
Nodes (3): Ensure-PoshSsh(), Invoke-InteractiveShell(), Write-Step()

### Community 84 - "Multi-tenancy (concepto)"
Cohesion: 0.50
Nodes (4): BaseTenantModel Active Record, Multi-Tenancy DB-per-tenant, Auth DB mrp_auth compartida, Separación DB Auth vs DB MRP + tenant_id

### Community 85 - "Dependencias Composer"
Cohesion: 0.50
Nodes (3): require, dompdf/dompdf, phpoffice/phpspreadsheet

### Community 86 - "Provisioning wizard (docs)"
Cohesion: 0.50
Nodes (4): Migraciones multiempresa: template único + job diario 05:00, Regla: una empresa = una DB, sufijo incremental en colisión, Regla naming DB: mrp_(empresa_normalizada) [a-z0-9_] max 63, Wizard registro: provisiona DB por empresa mrp_<slug>

### Community 88 - "Modelos IA JSON (docs)"
Cohesion: 0.67
Nodes (3): Esquema JSON plano + ensamblaje en PHP para modelos pequeños, API compatible OpenAI (Ollama/DashScope/OpenRouter), Modelos Qwen 1.5B/3B para tareas JSON

### Community 89 - "Fixes 500 agente AI"
Cohesion: 0.67
Nodes (3): Fix error 500 /api/v1/agent/suggestions (config default), Fix 500 agente AI: clase AgentController namespace, Fix 500 POST /api/v1/agent/message

### Community 91 - "Migración CakePHP (docs)"
Cohesion: 0.67
Nodes (3): Fases de migración: config, entities, services, controllers, tests, deploy, Plan migración a CakePHP 5.3 (8-12 semanas), Business case migración a framework estándar

## Knowledge Gaps
- **98 isolated node(s):** `agent_conversations`, `agent_messages`, `agent_ai_logs`, `phpoffice/phpspreadsheet`, `dompdf/dompdf` (+93 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 538 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **69 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Response` connect `API Búsqueda` to `Logging y errores`, `BOM Controller`, `Entidades CRUD`, `Compras`, `Validaciones depósitos`, `Auth y agente`, `Empresa usuarios admin`, `Partes y variantes CRUD`, `Kernel request lifecycle`, `Import partes`, `Operaciones y rutas`, `Home y dashboard`, `Órdenes producción controller`, `Agente AI controller`, `RutaProducción modelo`, `Composición controller`, `Import maestro partes`, `UnidadesMedidaController`, `Centros trabajo admin`, `Grupos partes CRUD`, `Tipos partes CRUD`, `Configuración controller`, `Herramientas BOM`, `AuthController registro`?**
  _High betweenness centrality (0.106) - this node is a cross-community bridge._
- **Why does `Request` connect `Entidades CRUD` to `API Búsqueda`, `Compras`, `Validaciones depósitos`, `Auth y agente`, `Empresa usuarios admin`, `Partes y variantes CRUD`, `Kernel request lifecycle`, `Import partes`, `Operaciones y rutas`, `Home y dashboard`, `Config core`, `Órdenes producción controller`, `Agente AI controller`, `RutaProducción modelo`, `Composición controller`, `Middleware pipeline`, `Import maestro partes`, `UnidadesMedidaController`, `Centros trabajo admin`, `Grupos partes CRUD`, `Tipos partes CRUD`, `Configuración controller`, `Herramientas BOM`, `AuthController registro`?**
  _High betweenness centrality (0.086) - this node is a cross-community bridge._
- **Why does `AgentService` connect `DTO Agente AI` to `Agente AI controller`, `Auth y agente`, `PromptBuilder`, `Agente AI excepciones y cliente`, `Validación respuestas IA`, `ConversationRepository`, `ConversationService`?**
  _High betweenness centrality (0.060) - this node is a cross-community bridge._
- **Are the 76 inferred relationships involving `url()` (e.g. with `.destroy()` and `.edit()`) actually correct?**
  _`url()` has 76 INFERRED edges - model-reasoned connections that need verification._
- **What connects `agent_conversations`, `agent_messages`, `agent_ai_logs` to the rest of the system?**
  _98 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Logging y errores` be split into smaller, more focused modules?**
  _Cohesion score 0.06519114688128773 - nodes in this community are weakly interconnected._
- **Should `Composición BOM JS` be split into smaller, more focused modules?**
  _Cohesion score 0.05674044265593561 - nodes in this community are weakly interconnected._