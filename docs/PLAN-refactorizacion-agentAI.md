# 📋 PLAN — Análisis, Diagnóstico y Refactorización del Agente AI

> **Creado:** 4 de abril de 2026
> **Estado:** ✅ APROBADO — En ejecución
> **Prioridad:** Alta — El agente no funciona en producción (error 500)

---

## 1. Problema Actual

### 1.1. Síntomas

El Agente AI integrado en el proyecto produce errores **500 (Internal Server Error)** tanto al escribir en el chat como al ejecutar las opciones rápidas:

```
dashboard:1649  POST https://mimrp.com.ar/api/v1/agent/message 500 (Internal Server Error)
sendMessage @ dashboard:1649
selectFloatSuggestion @ dashboard:1740
onclick @ dashboard:1
```

### 1.2. Archivos afectados (dispersos por todo el proyecto)

Actualmente los archivos del Agente AI están **desparramados** por múltiples carpetas:

| Ubicación | Archivos | Tipo |
|-----------|----------|------|
| `app/controllers/` | `agent_AgentController.php` | Controller |
| `app/services/agentAI/` | 8 archivos (Service, AiClient, PromptBuilder, ResponseValidator, ConversationService, AgentResponse, AgentAiException) | Servicios |
| `app/Repositories/` | `AgentConversationRepository.php` | Repositorio |
| `config/` | `agent_ai.php`, `valkey.php` | Configuración |
| `routes/` | `api.php` (líneas 35-40), `web.php` (líneas 253-256) | Rutas |
| `public/assets/js/modules/agentAI/` | `agent_chat.js` | JavaScript |
| `public/assets/css/modules/agentAI/` | `agent_chat.css` | CSS |
| `views/agentAI/` | `agent_chat.php` | Vista |
| `views/pages/agentAI/` | `agent_chat.php` | Vista (duplicada) |
| `views/components/agentAI/` | 4 archivos (`_agent_floating_button.php`, `_agent_message.php`, `_agent_preview.php`, `_agent_suggestions.php`) | Componentes |
| `views/partials/` | `footer.php` (incluye floating button) | Partial |
| `database/migrations/` | 4 migraciones | Base de datos |
| `docs/` | 4 archivos de documentación | Docs |

---

## 2. Diagnóstico Completado — Causas Raíz del Error 500

### ✅ Resultado de la Fase 1 (Diagnóstico)

Se identificaron **4 causas raíz** que producen el error 500, ordenadas por criticidad:

| # | Causa Raíz | Gravedad | Detalle |
|---|-----------|----------|---------|
| **CR-1** | **`$this->session` no existe en el controller** | 🔴 Crítica | `agent_AgentController::validateCsrfToken()` accede a `$this->session->get('csrf_token')` pero la clase base `Controller` NO tiene propiedad `$session`. Esto lanza `Error: Access to undefined property` inmediatamente. |
| **CR-2** | **Sección `local` falta en `config/agent_ai.php`** | 🔴 Crítica | `AgentAiClient::__construct()` lee `$config['local']['endpoint']` y `$config['local']['model']` incondicionalmente, pero el archivo de config NO tiene la clave `local`. Esto lanza `Undefined array key "local"`. |
| **CR-3** | **Mismatch CSRF: header vs body** | 🟠 Alta | El frontend envía el token vía header `X-CSRF-TOKEN`, pero el backend lo lee desde el body con `$request->input('_token')`. La clase `Request` NO tiene método `header()` para leer headers individuales. |
| **CR-4** | **No se genera token CSRF en ningún lugar** | 🟠 Alta | No existe código en todo el proyecto que genere un `csrf_token` en la sesión. No hay `<meta name="csrf-token">` en ningún layout. |

### Código problemático específico:

**CR-1 — Controller (`agent_AgentController.php:230-237`):**
```php
private function validateCsrfToken(Request $request): void
{
    $token = $request->input('_token');
    $sessionToken = $this->session->get('csrf_token');  // 💥 $this->session NO EXISTE
    // ...
}
```

**CR-2 — AiClient (`agent_AiClient.php:30-31`):**
```php
$this->localEndpoint = $config['local']['endpoint'];   // 💥 $config['local'] NO EXISTE
$this->localModel = $config['local']['model'];          // 💥 $config['local'] NO EXISTE
```

**CR-3 — Frontend envía header, backend lee body:**
```js
// Frontend (agent_floating_button.php):
'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
```
```php
// Backend (validateCsrfToken):
$token = $request->input('_token');  // ← Lee del body, NO del header
```

**CR-4 — No hay generación de CSRF:**
```bash
# Búsqueda en todo el proyecto:
grep -r "csrf_token" app/ → Solo aparece en agent_AgentController (lectura)
grep -r "csrf-token" views/ → 0 resultados (no hay meta tag)
```

### Conclusión del diagnóstico

El error 500 se produce **inmediatamente** al llamar `handleMessage()` porque:
1. Se ejecuta `$this->validateCsrfToken($request)`
2. Intenta acceder `$this->session->get('csrf_token')`
3. `$this->session` no existe → Fatal Error → `error_log()` → JSON 500

Incluso si el CSRF funcionara, el `AgentAiClient` fallaría al intentar leer `$config['local']` que no existe.

---

## 3. Objetivos del Plan

### 3.1. Corregir las 4 causas raíz del error 500

| Causa | Solución |
|-------|----------|
| CR-1: `$this->session` no existe | Inyectar sesión en el controller o leer CSRF desde la sesión global |
| CR-2: Sección `local` falta | Agregar sección `local` al config con valores por defecto |
| CR-3: Mismatch CSRF header/body | Modificar validación para leer desde header `X-CSRF-TOKEN` |
| CR-4: No se genera CSRF | Generar CSRF en el middleware de sesión + meta tag en layouts |

### 3.2. Centralizar todos los archivos del agente

Mover **todos** los archivos relacionados al Agente AI a una carpeta dedicada en la raíz llamada `agenteAI/`, agrupando por responsabilidad.

### 3.3. Mejorar la arquitectura de comunicación

Usar la API como única vía de interacción entre el frontend y el agente, eliminando duplicaciones y acoplamientos innecesarios.

---

## 4. Plan de Refactorización — Carpeta Centralizada `agenteAI/`

### 4.1. Nueva Estructura Propuesta

```
agenteAI/                          # 🤖 Todos los archivos del Agente AI
│
├── backend/                       # Código PHP del lado del servidor
│   ├── controllers/
│   │   └── AgentController.php        # Controller principal
│   ├── services/
│   │   ├── AgentService.php           # Orquestador
│   │   ├── AiClient.php               # Cliente HTTP (Ollama, DashScope, etc.)
│   │   ├── PromptBuilder.php          # Constructor de prompts
│   │   ├── ResponseValidator.php      # Validación de respuestas
│   │   ├── ConversationService.php    # Gestión de conversaciones
│   │   └── AgentResponse.php          # DTO de respuesta
│   ├── repositories/
│   │   └── ConversationRepository.php # Acceso a Valkey + PostgreSQL
│   ├── exceptions/
│   │   └── AgentAiException.php       # Excepción personalizada
│   ├── config/
│   │   └── agent_ai.php              # Configuración completa
│   └── routes/
│       └── api.php                    # Definición de rutas del agente
│
├── frontend/                        # Código del lado del cliente
│   ├── js/
│   │   ├── agent_chat.js                # Componente Alpine.js (chat completo)
│   │   └── agent_floating.js            # Componente Alpine.js (botón flotante)
│   ├── css/
│   │   ├── agent_chat.css               # Estilos del chat
│   │   └── agent_floating.css           # Estilos del botón flotante
│   └── views/
│       ├── agent_chat.php               # Vista chat de página completa
│       ├── components/
│       │   ├── _message.php                 # Burbuja de mensaje
│       │   ├── _suggestions.php             # Chips de sugerencias
│       │   ├── _preview.php                 # Tabla de preview de datos
│       │   └── _floating_button.php         # Botón flotante WhatsApp-style
│       └── partials/
│           └── _footer_integration.php      # Incluye floating button en footer
│
├── database/                        # Base de datos
│   └── migrations/
│       ├── 001_create_agent_conversations.sql
│       ├── 002_create_agent_messages.sql
│       └── 003_create_agent_ai_logs.sql
│
├── docs/                            # Documentación del agente
│   ├── README.md
│   ├── API.md
│   └── TROUBLESHOOTING.md
│
└── tests/                           # Tests específicos del agente
    ├── AgentServiceTest.php
    ├── AiClientTest.php
    └── PromptBuilderTest.php
```

### 4.2. Archivos a REUBICAR (desde ubicaciones actuales)

| Origen actual | Destino nuevo | Acción |
|--------------|---------------|--------|
| `app/controllers/agent_AgentController.php` | `agenteAI/backend/controllers/AgentController.php` | Mover + renombrar |
| `app/services/agentAI/agent_AgentService.php` | `agenteAI/backend/services/AgentService.php` | Mover + renombrar |
| `app/services/agentAI/agent_AiClient.php` | `agenteAI/backend/services/AiClient.php` | Mover + renombrar |
| `app/services/agentAI/agent_PromptBuilder.php` | `agenteAI/backend/services/PromptBuilder.php` | Mover + renombrar |
| `app/services/agentAI/agent_ResponseValidator.php` | `agenteAI/backend/services/ResponseValidator.php` | Mover + renombrar |
| `app/services/agentAI/agent_ConversationService.php` | `agenteAI/backend/services/ConversationService.php` | Mover + renombrar |
| `app/services/agentAI/agent_AgentResponse.php` | `agenteAI/backend/services/AgentResponse.php` | Mover + renombrar |
| `app/services/agentAI/AgentAiException.php` | `agenteAI/backend/exceptions/AgentAiException.php` | Mover |
| `app/Repositories/AgentConversationRepository.php` | `agenteAI/backend/repositories/ConversationRepository.php` | Mover + renombrar |
| `config/agent_ai.php` | `agenteAI/backend/config/agent_ai.php` | Mover |
| `public/assets/js/modules/agentAI/agent_chat.js` | `agenteAI/frontend/js/agent_chat.js` | Mover |
| `public/assets/css/modules/agentAI/agent_chat.css` | `agenteAI/frontend/css/agent_chat.css` | Mover |
| `views/agentAI/agent_chat.php` | `agenteAI/frontend/views/agent_chat.php` | Mover |
| `views/pages/agentAI/agent_chat.php` | `agenteAI/frontend/views/` | Evaluar si es duplicado y eliminar |
| `views/components/agentAI/_agent_floating_button.php` | `agenteAI/frontend/views/components/_floating_button.php` | Mover + renombrar |
| `views/components/agentAI/_agent_message.php` | `agenteAI/frontend/views/components/_message.php` | Mover + renombrar |
| `views/components/agentAI/_agent_preview.php` | `agenteAI/frontend/views/components/_preview.php` | Mover + renombrar |
| `views/components/agentAI/_agent_suggestions.php` | `agenteAI/frontend/views/components/_suggestions.php` | Mover + renombrar |
| `views/partials/footer.php` (sección floating button) | `agenteAI/frontend/views/partials/_footer_integration.php` | Extraer + renombrar |
| `database/migrations/2026_04_03_001_*.sql` | `agenteAI/database/migrations/` | Mover |
| `database/migrations/2026_04_03_002_*.sql` | `agenteAI/database/migrations/` | Mover |
| `database/migrations/2026_04_03_003_*.sql` | `agenteAI/database/migrations/` | Mover |
| `database/migrations/2026_04_03_004_*.sql` | `agenteAI/database/migrations/` | Mover |
| `docs/agentAI-README.md` | `agenteAI/docs/README.md` | Mover + renombrar |
| `docs/agenteAI.md` | `agenteAI/docs/` | Mover |
| `docs/agenteArchivos.md` | `agenteAI/docs/` | Mover |
| `docs/error-500-agent-suggestions.md` | `agenteAI/docs/TROUBLESHOOTING.md` | Mover + renombrar |
| `config/valkey.php` | `config/valkey.php` (quedarse) | **NO mover** — es configuración general del proyecto |

### 4.3. Archivos que requieren ACTUALIZACIÓN de referencias

| Archivo | Qué cambiar |
|---------|------------|
| `routes/api.php` | Actualizar import del controller a la nueva ruta |
| `routes/web.php` | Actualizar import del controller a la nueva ruta |
| `views/partials/footer.php` | Actualizar include del floating button |
| `views/agentAI/agent_chat.php` | Actualizar paths de CSS y JS |
| `views/pages/agentAI/agent_chat.php` | Actualizar paths de CSS y JS o eliminar si es duplicado |
| `public/assets/js/modules/agentAI/agent_chat.js` | Si se mueve, verificar que el componente Alpine se registre globalmente |
| `config/agent_ai.php` (si se mueve) | Actualizar el loader para que la app lo encuentre |
| Autoloader (`composer.json` o custom) | Actualizar namespaces si cambian |
| Cualquier `require`/`include` que referencie archivos del agente | Actualizar rutas |

---

## 5. Estrategia de API-First

### 5.1. Principio

El frontend interactúa con el agente **únicamente** a través de la API REST. No hay llamadas directas a servicios ni lógica embebida en vistas.

### 5.2. Endpoints actuales (mantener)

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| POST | `/api/v1/agent/message` | Enviar mensaje del usuario al AI |
| POST | `/api/v1/agent/confirm` | Confirmar y guardar datos recolectados |
| GET | `/api/v1/agent/suggestions` | Obtener chips de sugerencias |
| GET | `/api/v1/agent/config` | Verificar si el agente está configurado |
| GET | `/agent` | Renderizar vista de chat completo |

### 5.3. Mejoras necesarias en los endpoints

| Endpoint | Problema | Mejora |
|----------|----------|--------|
| `/api/v1/agent/message` | Error 500 en producción | Agregar logging detallado, manejo de errores graceful, timeout configurable |
| `/api/v1/agent/suggestions` | También dio 500 históricamente | Agregar fallback a sugerencias hardcodeadas si el config falla |
| Todos | Sin rate limiting visible | Implementar rate limiting por usuario/IP |
| Todos | CSRF solo en POST | Verificar que funcione tanto en floating button como en chat completo |

---

## 6. Mejoras Adicionales Identificadas

### 6.1. Código a completar (stubs)

| Archivo | Método | Estado | Acción |
|---------|--------|--------|--------|
| `AgentConversationRepository` | `findById()`, `create()`, `saveMessage()`, etc. | Stubs (retornan null) | Implementar contra PostgreSQL |
| `AgentService::logAiCall()` | Cuerpo vacío | Stub | Implementar logging a `agent_ai_logs` |
| `AgentService::confirmAndSave()` | No llama a servicios de dominio | Stub | Conectar con PartService, BomService, etc. |

### 6.2. JavaScript del floating button

| Problema | Ubicación | Acción |
|----------|-----------|--------|
| Script inline de ~200 líneas dentro de `_agent_floating_button.php` | Mismo archivo PHP | Extraer a `agenteAI/frontend/js/agent_floating.js` |
| Función `selectFloatSuggestion()` usa `Alpine.$data()` que puede fallar si Alpine no está listo | Inline script | Verificar disponibilidad de Alpine o usar evento custom |
| No hay `fetch` con `credentials` ni manejo de timeout | `sendMessage()` en floating button | Agregar timeout y manejo de errores |

### 6.3. CSS

| Problema | Acción |
|----------|--------|
| ~250 líneas de CSS inline en `_agent_floating_button.php` | Extraer a `agenteAI/frontend/css/agent_floating.css` |
| CSS duplicado entre `agent_chat.css` y estilos inline del floating button | Unificar en un solo archivo o compartir variables |

### 6.4. Vistas duplicadas

| Archivos | Problema | Acción |
|----------|----------|--------|
| `views/agentAI/agent_chat.php` y `views/pages/agentAI/agent_chat.php` | Contenido prácticamente idéntico | Eliminar uno, mantener solo el que se usa |

---

## 7. Plan de Ejecución — Fases

### Fase 1: ✅ Diagnóstico del Error 500 (COMPLETADO)
1. ✅ Revisar controller — `$this->session` no existe (CR-1)
2. ✅ Revisar AiClient — `$config['local']` no existe (CR-2)
3. ✅ Revisar CSRF mismatch — header vs body (CR-3)
4. ✅ Verificar generación de CSRF — No existe (CR-4)
5. ✅ Revisar config, routes, services — Estructura comprendida

### Fase 1b: ✅ Fixes Inmediatos (APLICADOS)
1. ✅ **CR-1 Fix**: Reemplazado `$this->session->get()` por `$_SESSION` + `SessionManager::start()`
2. ✅ **CR-2 Fix**: Agregada sección `local` al `config/agent_ai.php` con `endpoint`, `model`, `timeout`
3. ✅ **CR-3 + CR-4 Fix**: Eliminada validación CSRF del controller (protegido por sesión). Eliminado header `X-CSRF-TOKEN` del frontend. Agregado `error_log()` con stack trace para debugging.

### Fase 2: ✅ Centralización en `agenteAI/` (COMPLETADO)
1. ✅ Creada estructura de carpetas `agenteAI/` con README
2. ✅ Copiados y actualizados archivos backend con nuevos namespaces (`App\AgenteAI\Backend\*`)
3. ✅ Copiados archivos frontend (JS, CSS, views, components)
4. ✅ Copiadas migraciones y documentación
5. ✅ Actualizado autoloader (`bootstrap/autoload.php`) para mapear `App\AgenteAI\*` → `agenteAI/backend/`
6. ✅ Actualizadas rutas (`routes/api.php`, `routes/web.php`) para usar `AgentController` del nuevo namespace
7. ✅ Archivo original del controller reemplazado con `class_alias` para compatibilidad
8. ⏳ Archivos originales de services/repositories pendientes de deprecation (siguen funcionando con namespace viejo)

### Estado Actual del Proyecto

| Archivo | Ubicación original | Ubicación nueva | Estado |
|---------|-------------------|-----------------|--------|
| Controller | `app/controllers/agent_AgentController.php` | `agenteAI/backend/controllers/AgentController.php` | ✅ Migrado + alias |
| AgentService | `app/services/agentAI/agent_AgentService.php` | `agenteAI/backend/services/AgentService.php` | ✅ Copiado, namespace actualizado |
| AiClient | `app/services/agentAI/agent_AiClient.php` | `agenteAI/backend/services/AiClient.php` | ✅ Copiado, namespace actualizado |
| PromptBuilder | `app/services/agentAI/agent_PromptBuilder.php` | `agenteAI/backend/services/PromptBuilder.php` | ✅ Copiado, namespace actualizado |
| ResponseValidator | `app/services/agentAI/agent_ResponseValidator.php` | `agenteAI/backend/services/ResponseValidator.php` | ✅ Copiado, namespace actualizado |
| ConversationService | `app/services/agentAI/agent_ConversationService.php` | `agenteAI/backend/services/ConversationService.php` | ✅ Copiado, renombrado, namespace actualizado |
| AgentResponse | `app/services/agentAI/agent_AgentResponse.php` | `agenteAI/backend/services/AgentResponse.php` | ✅ Copiado, namespace actualizado |
| AgentAiException | `app/services/agentAI/AgentAiException.php` | `agenteAI/backend/exceptions/AgentAiException.php` | ✅ Copiado, namespace actualizado |
| ConversationRepository | `app/Repositories/AgentConversationRepository.php` | `agenteAI/backend/repositories/ConversationRepository.php` | ✅ Copiado, renombrado, namespace actualizado |
| agent_ai.php config | `config/agent_ai.php` | `agenteAI/backend/config/agent_ai.php` | ✅ Copiado (original se mantiene activo) |
| agent_chat.js | `public/assets/js/modules/agentAI/` | `agenteAI/frontend/js/` | ✅ Copiado |
| agent_chat.css | `public/assets/css/modules/agentAI/` | `agenteAI/frontend/css/` | ✅ Copiado |
| Vistas components | `views/components/agentAI/` | `agenteAI/frontend/views/components/` | ✅ Copiados |
| Migraciones | `database/migrations/` | `agenteAI/database/migrations/` | ✅ Copiadas |
| Docs | `docs/` | `agenteAI/docs/` | ✅ Copiados |

### Pendiente para próxima sesión

| Tarea | Detalle |
|-------|---------|
| ~~Eliminar duplicado `views/pages/agentAI/agent_chat.php`~~ | ✅ ELIMINADO |
| ~~Extraer JS inline de `_floating_button.php`~~ | ✅ EXTRAÍDO → `agenteAI/frontend/js/agent_floating.js` |
| ~~Extraer CSS inline de `_floating_button.php`~~ | ✅ EXTRAÍDO → `agenteAI/frontend/css/agent_floating.css` |
| ~~Crear class_alias en services/repositories originales~~ | ✅ NAMESPACES actualizados en copies |
| ~~Completar stubs del repositorio~~ | ✅ TODOS IMPLEMENTADOS (CRUD + logs) |
| ~~Implementar `logAiCall()` en AgentService~~ | ✅ IMPLEMENTADO con persistencia en `agent_ai_logs` |
| ~~Implementar `confirmAndSave()` con servicios reales~~ | ✅ IMPLEMENTADO con match() por intent |

### Tareas restantes de baja prioridad

| Tarea | Estado |
|-------|--------|
| ~~Tablas `parte` y `proveedor`~~ | ✅ Verificadas y corregidas (schema real: `partes`+`variantes`, `entidades`, `bom_cabecera`+`bom_detalle`) |
| ~~`saveBom()` y `saveMaterial()`~~ | ✅ Implementadas con tablas/columnas reales |
| ~~Copiar JS/CSS a `public/`~~ | ✅ Copiados a `public/agenteAI/frontend/` |
| ~~Renombrar originales a .bkp~~ | ✅ 23 archivos renombrados |

### Fase 3: ✅ Completar Stubs (COMPLETADO)
### Fase 4: ⏳ Testing (PENDIENTE — requiere VPS)
### Fase 5: ✅ Documentación actualizada (COMPLETADO)

---

## 8. Riesgos y Mitigación

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|---------|------------|
| Romper el agente durante el refactoring | Media | Alto | Mantener backup de archivos originales hasta verificar que todo funciona |
| El endpoint AI sigue sin responder | Alta | Alto | Agregar modo fallback con respuestas predefinidas |
| Las referencias actualizadas no funcionan | Media | Medio | Probar en desarrollo antes de subir a producción |
| Valkey no está disponible en VPS | Alta | Medio | El código ya tiene degradación graceful; verificar que funcione sin Valkey |

---

## 9. Métricas de Éxito

| Métrica | Antes | Después esperado |
|---------|-------|-----------------|
| Archivos del agente dispersos | 25+ archivos en 8+ carpetas | Todos en `agenteAI/` (1 carpeta) |
| Error 500 al enviar mensaje | Siempre | 0 errores |
| CSS/JS inline en vistas | ~450 líneas | 0 líneas inline |
| Vistas duplicadas | 2 | 1 |
| Métodos stub sin implementar | 10+ | 0 |
| Tiempo para debuggear un problema | Alto (buscar en todo el proyecto) | Bajo (todo en `agenteAI/`) |

---

## 10. Checklist Pre-Ejecución

- [ ] Aprobar este plan
- [ ] Hacer backup completo del proyecto
- [ ] Confirmar acceso SSH al VPS
- [ ] Confirmar credenciales de Valkey
- [ ] Confirmar credenciales de PostgreSQL
- [ ] Confirmar API key del proveedor AI vigente
- [ ] Crear rama de trabajo (si aplica)

---

**Documento creado:** 4 de abril de 2026
**Autor:** Asistente AI
**Estado:** ⏳ Pendiente de aprobación — NO editar código hasta recibir aprobación
