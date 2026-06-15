# Plan: Agente AI Chatbot para MRP

## Resumen

Integrar un chatbot con IA (Qwen 2.5 vía Ollama local en VPS o API compatible OpenAI como DashScope/OpenRouter) como módulo aislado en el MRP multiempresa. El chatbot guía al usuario a cargar datos (piezas, BOMs, materias primas, etc.) mediante opciones predefinidas Y texto libre con repregunta automática cuando hay ambigüedad. Todos los archivos llevan prefijo `agent_` o están en carpetas `agentAI/`.

---

## 🚀 Arquitectura con Valkey para Rendimiento

### ¿Por qué Valkey?

Valkey (Redis 7+) aporta **3 mejoras críticas** para el agente AI:

| Uso | Beneficio | Impacto |
|-----|-----------|---------|
| **Caché de respuestas de IA** | Evita llamadas duplicadas a Ollama/DashScope | ~10-50ms vs ~150-500ms |
| **Sesiones activas de conversación** | TTL automático (ej. 30 min inactividad) | Escalabilidad horizontal |
| **Rate limiting por usuario** | Bloqueo sin sobrecargar DB | `INCR` + `EXPIRE` atomico |

### Arquitectura de datos

```
┌─────────────────────────────────────────────────────────────────┐
│                         Valkey (Cache/Sesiones)                 │
│  ┌──────────────┐  ┌──────────────┐  ┌─────────────────────┐   │
│  │  Respuestas  │  │  Sesiones    │  │  Rate Limiting      │   │
│  │  de IA       │  │  Activas     │  │  por Usuario        │   │
│  │  EX 3600s    │  │  TTL 1800s   │  │  INCR + EXPIRE      │   │
│  └──────────────┘  └──────────────┘  └─────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                    PostgreSQL (Persistencia)                    │
│  ┌──────────────────┐  ┌──────────────────┐  ┌────────────────┐ │
│  │ agent_conversations││ agent_messages   │  │ agent_ai_logs  │ │
│  │ (metadata)       │  │ (historial)      │  │ (auditoría)    │ │
│  └──────────────────┘  └──────────────────┘  └────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

### Flujo de procesamiento

```
1. Usuario → PHP AgentController
2. AgentService::processMessage()
   ├─→ Valkey: GET "ai:response:{prompt_hash}"
   ├─→ [HIT] → Devolver caché (skip IA)
   └─→ [MISS] → Ollama/DashScope
              ├─→ Guardar en Valkey EX 3600
              └─→ Guardar en PostgreSQL
3. Guardar log en agent_ai_logs (PostgreSQL)
```

## FASE 1.1 — Infraestructura Valkey (docker-compose.yml)

### Valkey en docker-compose.yml

Añadir el servicio Valkey (Bitnami 9.x) a tu `docker-compose.yml`:

```yaml
services:
  # ... servicios existentes ...

  valkey:
    image: bitnami/valkey:latest
    container_name: lepp-valkey
    restart: always
    ports:
      - "6379:6379"
    volumes:
      - valkey_data:/bitnami/valkey/data
    environment:
      - VALKEY_DISABLE_THREADS=1  # Single-threaded para simplicidad
    networks:
      - lepp-net

networks:
  lepp-net:
    driver: bridge

volumes:
  postgresql_data:
  valkey_data:
```

### Conexión desde PHP

```php
// config/valkey.php
return [
    'host' => $_ENV['VALKEY_HOST'] ?? 'localhost',
    'port' => (int)($_ENV['VALKEY_PORT'] ?? 6379),
    'password' => $_ENV['VALKEY_PASSWORD'] ?? null,
    'prefix' => 'mrp:agent:',
    'ttl' => 3600,  // TTL por defecto para respuestas de IA
];
```

---

## FASE 1 — Infraestructura y Configuración

### 1.1 Config del proveedor AI

Crear `config/agent_ai.php` con:
- Modo: `'local'` (Ollama) o `'api'` (DashScope/OpenRouter)
- Local: endpoint `http://ollama:11434/v1/chat/completions`, model `qwen2.5:1.5b`
- API: endpoint + `AGENT_AI_API_KEY` desde `.env`, model `qwen/qwen2.5-1.5b-instruct`
- Parámetros: `temperature: 0.1`, `max_tokens: 800`, `seed: 42`, `num_ctx: 4096`
- Retry: max 2 intentos, timeout 30s

> Cambiar de proveedor local ↔ cloud requiere **solo editar un valor en esta config**. Sin tocar controller ni service.

### 1.2 Migraciones DB

Crear en `database/migrations/`:

| Archivo | Tabla | Columnas clave |
|---|---|---|
| `XXX_create_agent_conversations.sql` | `agent_conversations` | id, tenant_id, user_id, intent, status, created_at, updated_at |
| `XXX_create_agent_messages.sql` | `agent_messages` | id, conversation_id, role (user/assistant/system), content, metadata JSON, created_at |
| `XXX_create_agent_ai_logs.sql` | `agent_ai_logs` | id, conversation_id, message_id, model_used, provider (local/api), prompt_hash, response_time_ms, validation_result, tokens_used, created_at |

---

## FASE 2 — Backend PHP

### 2.1 Cliente HTTP AI
**Archivo:** `app/services/agentAI/agent_AiClient.php`
- Clase `AgentAiClient`
- Método `chat(array $messages, string $intent): array` — llama al endpoint configurado
- Detecta proveedor por config, cambia endpoint + auth header automáticamente
- Retry interno (máx 2): si JSON inválido o error HTTP, reintenta con hint de corrección añadido al prompt
- Lanza `AgentAiException` si ambos intentos fallan

### 2.2 Constructor de Prompts
**Archivo:** `app/services/agentAI/agent_PromptBuilder.php`
- Clase `AgentPromptBuilder`
- `systemPrompt(string $intent): string` — system prompt base según intent
- `buildMessages(array $history, string $userInput, string $intent): array` — arma el array `messages[]` para la API
- Intents soportados: `create_part`, `create_bom`, `create_supplier`, `general_query`
- Los system prompts exigen JSON estructurado plano (sin markdown, sin explicaciones)

### 2.3 Validador de Respuestas
**Archivo:** `app/services/agentAI/agent_ResponseValidator.php`
- Clase `AgentResponseValidator`
- `validate(array $data, string $intent): ValidationResult` — valida campos requeridos, tipos, formatos (regex de códigos, UOM válidas)
- Si falla → devuelve lista de errores legibles para incluir en el retry prompt
- Si pasa → devuelve datos normalizados

### 2.4 Servicio de Conversación
**Archivo:** `app/services/agentAI/agent_ConversationService.php`
- Clase `AgentConversationService`
- `startConversation(int $userId, int $tenantId, string $intent): string` — crea registro en `agent_conversations`, devuelve `conversationId`
- `addMessage(string $convId, string $role, string $content, array $metadata = []): void`
- `getHistory(string $convId): array` — devuelve mensajes ordenados
- `resolveIntent(string $userInput): string` — detecta intent por palabras clave si el usuario no usa opciones predefinidas
- `markCompleted(string $convId): void`
- `getActiveSession(string $userId, int $tenantId): ?array` — obtiene sesión activa desde Valkey (TTL 30 min)
- `saveSession(array $sessionData): void` — guarda sesión en Valkey con TTL 1800s
- `invalidateSession(string $userId, int $tenantId): void` — invalida sesión de Valkey

### 2.5 Servicio Principal del Agente
**Archivo:** `app/services/agentAI/agent_AgentService.php`
- Clase `AgentService` — orquesta todo
- `processMessage(string $convId, string $userInput): AgentResponse` — flujo:
  1. Obtiene historial de conversación (Valkey + PostgreSQL)
  2. Detecta intent (`resolveIntent` o el que vino del chip)
  3. **Caché Valkey**: busca `ai:response:{prompt_hash}` → [HIT] skip IA, [MISS] llama IA
  4. Llama `AgentPromptBuilder` → `AgentAiClient` → `AgentResponseValidator`
  5. Si validación ok → guarda en Valkey EX 3600 + devuelve `AgentResponse` con `status: 'preview'`
  6. Si validación falla → devuelve `AgentResponse` con `status: 'clarify'` + pregunta al usuario
  7. Guarda log en `agent_ai_logs` (PostgreSQL)
- `confirmAndSave(string $convId, array $confirmedData, string $intent): array` — llama al servicio real del dominio (ej. `PartService`, `BomService`) para persistir
- `clearCache(string $promptHash): void` — invalida caché de Valkey cuando se actualiza una pieza/BOM

### 2.6 DTO de Respuesta
**Archivo:** `app/dto/agent_AgentResponse.php`
- Propiedades: `status` (preview/clarify/error/saved), `message`, `data`, `suggestions`, `conversationId`

### 2.7 Repository
**Archivo:** `app/Repositories/AgentConversationRepository.php`
- CRUD sobre `agent_conversations`, `agent_messages` y `agent_ai_logs`
- Aislamiento multitenant por `tenant_id`
- `getCachedResponse(string $promptHash): ?array` — obtiene respuesta de Valkey
- `setCachedResponse(string $promptHash, array $data): void` — guarda en Valkey EX 3600
- `getActiveSessions(int $tenantId, int $userId): array` — obtiene sesiones activas de Valkey
- `saveSession(string $key, array $data, int $ttl = 1800): void` — guarda sesión en Valkey
- `invalidateSession(string $key): void` — invalida sesión de Valkey

### 2.8 Controller
**Archivo:** `app/controllers/agent_AgentController.php`
- Thin controller, namespace `App\Controllers`
- `showChat(Request $req): Response` — renderiza la view del chatbot
- `handleMessage(Request $req): Response` — valida input, llama `AgentService::processMessage()`, devuelve JSON
- `confirmSave(Request $req): Response` — llama `AgentService::confirmAndSave()`, devuelve JSON
- `getSuggestions(Request $req): Response` — devuelve opciones predefinidas por contexto/intent
- `clearCache(Request $req): Response` — invalida caché de Valkey (para administradores)

---

## FASE 3 — Rutas

**En `routes/web.php`:**
```
GET /agent  →  AgentController::showChat  (middleware: auth)
```

**En `routes/api.php`:**
```
POST /api/v1/agent/message      →  AgentController::handleMessage
POST /api/v1/agent/confirm      →  AgentController::confirmSave
GET  /api/v1/agent/suggestions  →  AgentController::getSuggestions
```

---

## FASE 4 — Frontend

### 4.1 View Principal
**Archivo:** `views/pages/agentAI/agent_chat.php`
- Layout: `layouts/admin_layout`
- Panel chat estilo messenger: historial arriba, input abajo
- Chips de sugerencias predefinidas (clicables, desaparecen al elegir)
- Área de preview con datos tabulados + botón "Confirmar" + botón "Cancelar / Corregir"

### 4.2 Componentes de Vista
| Archivo | Propósito |
|---|---|
| `views/components/agentAI/_agent_message.php` | Burbuja de mensaje (user/assistant), timestamp |
| `views/components/agentAI/_agent_suggestions.php` | Grid de chips de opciones predefinidas |
| `views/components/agentAI/_agent_preview.php` | Tabla de datos propuestos + acciones de confirmar/editar |

### 4.3 CSS
**Archivo:** `public/assets/css/modules/agentAI/agent_chat.css` (~150 líneas)
- Usa variables CSS del sistema (`variables.css`)
- Estilos: chat container, burbujas user/assistant, chips, preview box, loading spinner

### 4.4 JS
**Archivo:** `public/assets/js/modules/agentAI/agent_chat.js` (~200 líneas)
- Clase `AgentChat` con métodos: `sendMessage()`, `renderMessage()`, `renderSuggestions()`, `renderPreview()`, `confirmSave()`
- Fetch a `/api/v1/agent/message` y `/api/v1/agent/confirm`
- Side effects: spinner, scroll to bottom, desactivar input durante el procesamiento

---

## FASE 5 — Opciones Predefinidas (Suggestions)

## FASE 6 — Valkey para Caché y Sesiones

### 6.1 Caché de Respuestas de IA

**Objetivo:** Evitar llamadas duplicadas a Ollama/DashScope para prompts similares.

**Estrategia:**
- Generar `prompt_hash` (SHA256) del prompt + modelo + parámetros
- Guardar respuesta en Valkey: `SET "ai:response:{prompt_hash}" {json} EX 3600`
- En `AgentService::processMessage()`: check Valkey → [HIT] skip IA, [MISS] llama IA

**Ejemplo de implementación:**

```php
// app/services/agentAI/agent_AgentService.php
private function getCachedResponse(string $promptHash): ?array {
    $valkey = new ValkeyClient();
    $cached = $valkey->get("ai:response:{$promptHash}");
    return $cached ? json_decode($cached, true) : null;
}

private function setCachedResponse(string $promptHash, array $response): void {
    $valkey = new ValkeyClient();
    $valkey->setex("ai:response:{$promptHash}", 3600, json_encode($response));
}
```

### 6.2 Sesiones Activas de Conversación

**Objetivo:** Mantener sesiones activas con expiración automática (TTL 30 min).

**Estrategia:**
- Guardar sesión en Valkey: `HSET "session:{userId}:{tenantId}" convId intent lastActivity`
- TTL 1800s (30 min) por sesión
- Al activity: `EXPIRE "session:{userId}:{tenantId}" 1800`

**Ejemplo de implementación:**

```php
// app/services/agentAI/agent_ConversationService.php
public function getActiveSession(int $userId, int $tenantId): ?array {
    $valkey = new ValkeyClient();
    $session = $valkey->hGetAll("session:{$userId}:{$tenantId}");
    return $session ? $session : null;
}

public function saveSession(int $userId, int $tenantId, array $sessionData): void {
    $valkey = new ValkeyClient();
    $valkey->hMSet("session:{$userId}:{$tenantId}", $sessionData);
    $valkey->expire("session:{$userId}:{$tenantId}", 1800);
}
```

### 6.3 Rate Limiting por Usuario

**Objetivo:** Evitar abuso del agente AI (ej. más de 10 llamadas/minuto).

**Ejemplo de implementación:**

```php
// app/services/agentAI/agent_AgentService.php
private function checkRateLimit(int $userId): bool {
    $valkey = new ValkeyClient();
    $key = "ratelimit:{$userId}:" . date('Y-m-d-H');
    $count = $valkey->incr($key);
    if ($count == 1) $valkey->expire($key, 3600);  // Reset hourly
    return $count <= 10;  // 10 requests per hour
}
```

### 6.4 Invalidación de Caché

**Cuándo invalidar caché:**
- Se actualiza una pieza/BOM que fue generada por IA
- Se modifica un proveedor/material que fue sugerido por IA
- Manualmente desde panel de administración

**Comando CLI:**
```bash
# Invalidar todas las respuestas de IA
redis-cli KEYS "ai:response:*" | xargs redis-cli DEL

# Invalidar sesiones activas
redis-cli KEYS "session:*" | xargs redis-cli DEL
```

---

## FASE 5 — Opciones Predefinidas (Suggestions)

Definidas en `config/agent_ai.php` bajo clave `'suggestions'`. Ejemplo:

```php
'suggestions' => [
    [
        'intent'      => 'create_part',
        'label'       => 'Crear nueva Parte (Pieza, MP, Conjuntos, PT, MO, etc)',
        'description' => 'Te guío para registrar código, tipo, grupo, descripción, UOM y variantes',
        'icon'        => 'bi-gear',
    ],
    [
        'intent'      => 'create_bom',
        'label'       => 'Armar lista de materiales (BOM)',
        'description' => 'Definí la pieza padre y sus componentes de nivel 1',
        'icon'        => 'bi-diagram-3',
    ],
    [
        'intent'      => 'create_supplier',
        'label'       => 'Registrar proveedor',
        'description' => 'Nombre, CUIT, contacto y condiciones comerciales',
        'icon'        => 'bi-truck',
    ],
],
```

### Flujos de conversación

**Con opción predefinida (guiado):**
1. Usuario hace clic en chip → frontend envía `{ message: '...', intent: 'create_part' }`
2. Agente responde con la primera pregunta (ej. "¿Cuál es el nombre o descripción de la pieza?")
3. Conversación secuencial recogiendo campos uno a uno
4. Al tener todos los campos → muestra preview → usuario confirma → guarda

**Con texto libre:**
1. Usuario escribe → `resolveIntent()` detecta intent por palabras clave
2. Si confianza baja → agente pregunta: "¿Qué querés hacer?" y muestra chips
3. Flujo igual al guiado una vez determinado el intent

**Ante ambigüedad o datos faltantes:**
- El agente siempre repregunta antes de asumir: "¿El código que mencionás es `CN-40` o `CN-0040`?"
- Nunca guarda con datos incompletos o ambiguos

---

## Mapa completo de archivos a crear

### Backend
```
config/
  agent_ai.php

app/
  controllers/
    agent_AgentController.php
  services/
    agentAI/
      agent_AiClient.php
      agent_PromptBuilder.php
      agent_ResponseValidator.php
      agent_ConversationService.php
      agent_AgentService.php
  Repositories/
    AgentConversationRepository.php
  dto/
    agent_AgentResponse.php
```

### Migraciones
```
database/migrations/
  XXX_create_agent_conversations.sql
  XXX_create_agent_messages.sql
  XXX_create_agent_ai_logs.sql
```

### Frontend
```
views/
  pages/
    agentAI/
      agent_chat.php
  components/
    agentAI/
      _agent_message.php
      _agent_suggestions.php
      _agent_preview.php

public/assets/
  css/modules/agentAI/
    agent_chat.css
  js/modules/agentAI/
    agent_chat.js
```

### Rutas (modificar existentes)
```
routes/web.php   →  GET  /agent
routes/api.php   →  POST /api/v1/agent/message
                    POST /api/v1/agent/confirm
                    GET  /api/v1/agent/suggestions
```

**Total: 18 archivos nuevos + 2 modificaciones de rutas**

---

## Checklist de verificación

- [ ] `curl -X POST /api/v1/agent/message` con texto libre → respuesta JSON `{ status: "clarify" | "preview" }`
- [ ] Flujo completo: chip → preguntas guiadas → preview → confirmar → verificar fila en BD del tenant
- [ ] Cambiar `agent_ai.php` modo `local` → `api` → mismo test pasa sin tocar controller/service
- [ ] Simular respuesta JSON inválida de la IA → verificar retry automático y log en `agent_ai_logs`
- [ ] Verificar aislamiento multitenant: conversaciones de empresa A no visibles en empresa B
- [ ] Verificar que ningún dato se persiste sin confirmación explícita del usuario

---

## Decisiones de diseño

| Decisión | Razón |
|---|---|
| Proveedor configurable en `config/agent_ai.php` | Cambiar local↔cloud sin tocar lógica |
| Nunca guarda sin confirmación | Evita datos erróneos; el usuario siempre revisa el preview |
| Historial en DB con `tenant_id` | Multitenant nativo, auditable |
| Intents iniciales: 4 | Expandibles añadiendo una entrada en config + un case en `PromptBuilder` |
| No WebSocket en v1 | Fetch simple; SSE/WS se puede añadir después sin romper arquitectura |
| IA genera solo BOM nivel 1 | Modelos <3B pierden coherencia con árbol profundo; PHP maneja la jerarquía |

---

## Scope excluido (backlog futuro)

- Upload de PDF/Excel para extracción automática de datos
- BOMs multi-nivel generados por IA en un solo paso
- Panel de historial y auditoría de conversaciones para el admin
- Streaming de respuestas (SSE/WebSocket)
- Fine-tuning del modelo con datos propios del MRP
