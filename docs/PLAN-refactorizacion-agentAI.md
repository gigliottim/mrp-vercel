# 📋 PLAN — Análisis, Diagnóstico y Refactorización del Agente AI

> **Creado:** 4 de abril de 2026
> **Estado:** Pendiente de aprobación
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

## 2. Objetivos del Plan

### 2.1. Diagnosticar y resolver el error 500

Identificar la causa raíz del error `POST /api/v1/agent/message 500` y corregirla.

### 2.2. Centralizar todos los archivos del agente

Mover **todos** los archivos relacionados al Agente AI a una carpeta dedicada en la raíz llamada `agenteAI/`, agrupando por responsabilidad.

### 2.3. Mejorar la arquitectura de comunicación

Usar la API como única vía de interacción entre el frontend y el agente, eliminando duplicaciones y acoplamientos innecesarios.

---

## 3. Diagnóstico Preliminar — Causas Probables del Error 500

### Fase 1: Investigación (sin tocar código)

| # | Acción | Detalle | Resultado esperado |
|---|--------|---------|-------------------|
| 1.1 | Revisar logs del servidor VPS | Acceder a `/var/log/nginx/error.log`, `storage/logs/error.log`, y `error_log` de PHP | Encontrar el mensaje de error exacto que produce el 500 |
| 1.2 | Verificar conectividad al proveedor AI | Probar `curl` contra `https://api.ollama.com/v1/chat/completions` con las credenciales del `.env` | Confirmar si el endpoint responde o falla |
| 1.3 | Verificar conectividad a Valkey | Ejecutar `redis-cli -h lepp-valkey -p 6379 -a 'password' ping` desde el VPS | Confirmar si Valkey está accesible |
| 1.4 | Verificar OPcache | Comprobar si OPcache está cacheando una versión antigua del controller o config | Descartar cache stale |
| 1.5 | Verificar CSRF token | El controller valida CSRF; si el token no llega o no coincide, puede lanzar excepción | Confirmar que el token se envía correctamente desde el dashboard |
| 1.6 | Verificar que el router encuentra el controller | Revisar que `agent_AgentController.php` se carga correctamente con el autoloader | Descartar error de clase no encontrada |
| 1.7 | Revisar `agent_AiClient.php` | Verificar que el cURL está bien formado y que la respuesta se parsea correctamente | Identificar errores de timeout o JSON mal formado |
| 1.8 | Verificar `config('agent_ai')` | El config loader podría retornar null si el archivo no se encuentra | Descartar error de configuración |

### Hipótesis más probables (ordenadas):

| # | Hipótesis | Probabilidad | Cómo verificar |
|---|-----------|-------------|----------------|
| H1 | **El endpoint AI no responde** (API key expirada, endpoint caído, modelo no disponible) | Alta | `curl` directo al endpoint |
| H2 | **Valkey no conecta** desde producción (hostname `lepp-valkey` no resuelve en VPS) | Alta | `ping lepp-valkey` desde VPS |
| H3 | **OPcache** tiene una versión antigua cacheada | Media | `opcache_reset()` o reiniciar PHP-FPM |
| H4 | **CSRF token** no se envía o es inválido desde el dashboard | Media | `var_dump` del token en el controller |
| H5 | **Clase no encontrada** (namespace o autoloader incorrecto) | Baja | Logs de error de PHP |
| H6 | **`config()` helper** no encuentra `agent_ai.php` | Baja | `var_dump(config('agent_ai'))` |

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

### Fase 1: Diagnóstico del Error 500
1. Acceder al VPS y revisar logs
2. Probar conectividad al endpoint AI
3. Probar conectividad a Valkey
4. Identificar causa raíz
5. Aplicar fix inmediato

### Fase 2: Centralización en `agenteAI/`
1. Crear estructura de carpetas `agenteAI/`
2. Mover archivos backend (controllers, services, repositories, exceptions)
3. Mover archivos frontend (JS, CSS, views, components)
4. Mover migraciones y documentación
5. Actualizar todas las referencias (routes, includes, requires, autoloaders)
6. Eliminar archivos duplicados
7. Extraer JS y CSS inline a archivos externos

### Fase 3: Completar Stubs
1. Implementar métodos del repositorio contra PostgreSQL
2. Implementar `logAiCall()` en AgentService
3. Implementar `confirmAndSave()` con conexión a servicios de dominio

### Fase 4: Testing
1. Verificar que el floating button funciona en todas las páginas
2. Verificar que el chat completo funciona en `/agent`
3. Verificar que las opciones rápidas funcionan
4. Verificar que `confirmAndSave` guarda datos reales
5. Verificar que los logs se registran en `agent_ai_logs`

### Fase 5: Documentación
1. Actualizar `agenteAI/docs/README.md`
2. Documentar la nueva estructura
3. Agregar guía de troubleshooting

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
