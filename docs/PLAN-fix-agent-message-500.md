# 📋 PLAN — Fix Error 500 en `/api/v1/agent/message`

> **Creado:** 5 de abril de 2026
> **Estado:** ⏳ Pendiente de diagnóstico
> **Endpoint:** `POST /api/v1/agent/message`
> **Síntoma:** `{"status":"error","message":"Internal Server Error"}` con HTTP 500

---

## 1. Estado Actual

### Lo que SÍ funciona
| Endpoint | Estado | Respuesta |
|----------|--------|-----------|
| `/api/v1/agent/config` | ✅ ONLINE | `isOnline: true, apiResponds: true` |
| `/api/v1/agent/suggestions` | ✅ OK | 4 sugerencias devueltas |
| API de Ollama Cloud | ✅ Funciona | HTTP 200 con respuestas válidas |

### Lo que NO funciona
| Endpoint | Estado | Error |
|----------|--------|-------|
| `POST /api/v1/agent/message` | ❌ 500 | `{"status":"error","message":"Internal Server Error"}` |

### Request que falla
```bash
curl -X POST https://mimrp.com.ar/api/v1/agent/message \
  -H "Content-Type: application/json" \
  -d '{"message":"hola","conversation_id":null,"intent":""}'
```

---

## 2. Cadenas de Ejecución del `handleMessage()`

```
POST /api/v1/agent/message
  → AgentController::handleMessage()
    → ensureSession()           ← ¿SessionManager funciona sin Valkey?
    → getConversationService()  ← ¿Valkey conectado? ¿Si no, genera temp ID?
    → getService()->processMessage()
      → ConversationService::getHistory()    ← ¿Valkey disponible?
      → PromptBuilder::detectIntent()        ← String matching
      → PromptBuilder::buildMessages()       ← Construye array de mensajes
      → Repository::getCachedResponse()      ← Valkey cache
      → AiClient::chat()                     ← HTTP a ollama.com ✅ funciona
      → ResponseValidator::validate()        ← Valida campos requeridos
      → Repository::setCachedResponse()      ← Valkey cache
      → AgentService::logAiCall()            ← ¿DB disponible?
    → return JSON response
```

### Puntos de fallo potenciales

| # | Punto | Riesgo | Probabilidad |
|---|-------|--------|-------------|
| PF-1 | `ensureSession()` — `SessionManager::start()` | SessionManager podría requerir DB | 🟠 Media |
| PF-2 | `getConversationService()` — Valkey no conectado | Si Valkey falla, genera temp ID (OK) | 🟡 Baja |
| PF-3 | `processMessage()` — `AiClient::chat()` | Ya probado: funciona ✅ | 🟢 Nula |
| PF-4 | `ResponseValidator::validate()` | Si AI devuelve JSON inválido | 🟠 Media |
| PF-5 | `logAiCall()` — DB no disponible | Si PostgreSQL falla, podría lanzar excepción | 🟠 Media |
| PF-6 | `Exception` genérico capturado | El catch devuelve 500 sin detalle | 🔴 Alta |

---

## 3. Plan de Diagnóstico

### Paso 1: Verificar logs de error en VPS
```bash
# SSH al VPS
ssh root@181.13.244.35 -p 5073

# Ver logs de nginx error (los más recientes)
tail -30 /opt/mrp/docker/logs/nginx/error.log | grep -i "agent\|message\|handleMessage"

# Ver logs de php-fpm
tail -50 /opt/mrp/docker/logs/php-fpm/error.log

# Ver logs de la aplicación
tail -50 /opt/mrp/storage/logs/error.log 2>/dev/null
```

### Paso 2: Test directo desde el VPS con verbose
```bash
# Test desde el host del VPS
curl -v -X POST https://localhost/api/v1/agent/message \
  -H "Content-Type: application/json" \
  -d '{"message":"hola"}' 2>&1 | tail -30

# Test desde DENTRO del container php-fpm
docker compose exec -T php-fpm php -r "
require_once '/app/bootstrap/autoload.php';

// Simular request
\$_SESSION['user_id'] = 1;
\$_SESSION['tenant_id'] = 1;

try {
    \$controller = new \App\AgenteAI\Backend\Controllers\AgentController();
    \$request = new \App\Core\Http\Request();
    \$request->body = ['message' => 'hola', 'conversation_id' => null, 'intent' => ''];

    \$response = \$controller->handleMessage(\$request);
    echo 'Response status: ' . \$response->status . PHP_EOL;
    echo 'Response body: ' . \$response->body . PHP_EOL;
} catch (Throwable \$e) {
    echo 'Exception: ' . \$e->getMessage() . PHP_EOL;
    echo 'File: ' . \$e->getFile() . ':' . \$e->getLine() . PHP_EOL;
    echo 'Trace: ' . \$e->getTraceAsString() . PHP_EOL;
}
"
```

### Paso 3: Test componentizado (aislar el punto de fallo)
```php
<?php
require_once '/app/bootstrap/autoload.php';

echo "1. SessionManager...\n";
\App\Core\Support\SessionManager::start();
echo "   OK (session_id=" . session_id() . ")\n";

echo "2. ConversationService (Valkey)...\n";
try {
    \$cs = new \App\AgenteAI\Backend\Services\ConversationService();
    echo "   OK\n";
} catch (Throwable \$e) {
    echo "   FAIL: " . \$e->getMessage() . "\n";
}

echo "3. AgentService...\n";
try {
    \$as = new \App\AgenteAI\Backend\Services\AgentService();
    echo "   OK\n";
} catch (Throwable \$e) {
    echo "   FAIL: " . \$e->getMessage() . "\n";
}

echo "4. processMessage()...\n";
try {
    \$as = new \App\AgenteAI\Backend\Services\AgentService();
    \$convId = 'test_' . uniqid();
    \$result = \$as->processMessage(\$convId, 'hola como estas');
    echo "   OK - status=" . \$result->status . "\n";
    echo "   message=" . substr(\$result->message, 0, 100) . "\n";
} catch (Throwable \$e) {
    echo "   FAIL: " . \$e->getMessage() . "\n";
    echo "   File: " . \$e->getFile() . ":" . \$e->getLine() . "\n";
}
```

### Paso 4: Verificar ResponseValidator
```php
<?php
require_once '/app/bootstrap/autoload.php';

echo "Testing ResponseValidator...\n";
\$validator = new \App\AgenteAI\Backend\Services\ResponseValidator();

// Test con datos incompletos (como los devolvería la IA para un mensaje genérico)
\$testData = ['message' => 'Hola, ¿cómo estás?'];
\$result = \$validator->validate(\$testData, 'general_query');
echo "Result: " . json_encode(\$result) . "\n";
echo "Failed: " . (\$result->failed() ? 'YES' : 'NO') . "\n";
```

---

## 4. Fixes Planificados (según causa raíz)

### Fix A: Exception silencioso en `handleMessage()` → Agregar logging detallado

**Archivo:** `agenteAI/backend/controllers/AgentController.php`

```php
public function handleMessage(Request $request): Response
{
    $this->ensureSession();

    $convId = $request->input('conversation_id');
    $userInput = trim($request->input('message'));
    $intent = $request->input('intent', '');
    $userId = $this->getCurrentUserId();
    $tenantId = $this->getCurrentTenantId();

    error_log("[AgentAI] handleMessage: userId=$userId, tenantId=$tenantId, input=$userInput");

    if (!$convId) {
        $conversationService = $this->getConversationService();
        if (!$intent && $conversationService) {
            $intent = $conversationService->resolveIntent($userInput);
        }
        if ($conversationService) {
            $convId = $conversationService->startConversation($userId, $tenantId, $intent);
        } else {
            $convId = 'temp_' . uniqid();
        }
    }

    error_log("[AgentAI] convId=$convId, intent=$intent");

    try {
        $response = $this->getService()->processMessage($convId, $userInput);

        error_log("[AgentAI] Response: status=" . $response->status);

        return $this->json([
            'success' => true,
            'conversation_id' => $response->conversationId,
            'status' => $response->status,
            'message' => $response->message,
            'data' => $response->data,
            'suggestions' => $response->suggestions,
        ]);
    } catch (\Exception $e) {
        error_log("[AgentAI] EXCEPTION: " . $e->getMessage());
        error_log("[AgentAI] Trace: " . $e->getTraceAsString());
        return $this->json([
            'success' => false,
            'message' => 'Error al procesar el mensaje con la IA: ' . $e->getMessage(),
        ], 500);
    }
}
```

### Fix B: `ResponseValidator` falla para `general_query`

Si la IA devuelve una respuesta para `general_query` que no pasa validación, el servicio retorna `status: 'clarify'` pero podría haber un error en la construcción del `AgentResponse`.

**Verificar:** Que `AgentResponse` tenga los campos correctos y que `buildClarifyMessage` funcione sin errores.

### Fix C: `logAiCall()` falla si DB no está disponible

Si `ConversationRepository::saveAiLog()` lanza excepción cuando no hay DB, el try/catch del `processMessage()` la captura y relanza como 500.

**Fix:** Envolver `logAiCall()` en try/catch silencioso:

```php
private function logAiCall(...): void {
    try {
        if (!$this->repository) return;
        $this->repository->saveAiLog([...]);
    } catch (\Throwable $e) {
        // Log silencioso, no bloquear la respuesta
        error_log("[AgentAI] logAiCall failed: " . $e->getMessage());
    }
}
```

### Fix D: `AiClient::chat()` lanza excepción no manejada

Si el timeout de 10s se cumple, `curl_exec` devuelve error y `AgentAiException` se lanza. El controller la captura, pero si la excepción ocurre dentro de un try interno de `processMessage()`, podría no propagarse correctamente.

**Verificar:** Que todas las excepciones de `AiClient` se propaguen correctamente.

---

## 5. Orden de Ejecución

| # | Acción | Resultado esperado |
|---|--------|-------------------|
| 1 | Revisar logs en VPS | Identificar el error exacto |
| 2 | Si no hay logs → agregar logging detallado (Fix A) | Logs útiles |
| 3 | Ejecutar test componentizado (Paso 3) | Aislar el punto de fallo |
| 4 | Aplicar fix correspondiente (B, C o D) | El endpoint responde 200 |
| 5 | Deploy y verificar | `curl` devuelve JSON con `success: true` |
| 6 | Verificar en browser que el chat funciona | Mensaje enviado y respuesta recibida |

---

## 6. Criterios de Éxito

- [ ] `POST /api/v1/agent/message` devuelve HTTP 200
- [ ] La respuesta JSON contiene `success: true` con `status` (preview/clarify/response)
- [ ] El floating button puede enviar mensajes y recibir respuestas
- [ ] Los errores se loguean con detalle en `storage/logs/`
- [ ] Si Valkey no está disponible, el agente funciona en modo degradado
- [ ] Si DB no está disponible, los logs de AI se descartan silenciosamente

---

## 7. Contexto Técnico

### AgentController::handleMessage() — código actual
```php
public function handleMessage(Request $request): Response
{
    $this->ensureSession();
    $convId = $request->input('conversation_id');
    $userInput = trim($request->input('message'));
    $intent = $request->input('intent', '');
    $userId = $this->getCurrentUserId();
    $tenantId = $this->getCurrentTenantId();

    if (!$convId) {
        $conversationService = $this->getConversationService();
        if (!$intent && $conversationService) {
            $intent = $conversationService->resolveIntent($userInput);
        }
        if ($conversationService) {
            $convId = $conversationService->startConversation($userId, $tenantId, $intent);
        } else {
            $convId = 'temp_' . uniqid();
        }
    }

    try {
        $response = $this->getService()->processMessage($convId, $userInput);
        return $this->json([...]);
    } catch (\Exception $e) {
        error_log("Error: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        return $this->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
    }
}
```

### AgentService::processMessage() — flujo
1. Obtiene historial de Valkey (puede ser vacío)
2. Detecta intent del input
3. Construye mensajes con system prompt
4. Verifica cache en Valkey
5. Llama a `AiClient::chat()` → ✅ funciona
6. Valida respuesta con `ResponseValidator`
7. Si válido → cache + response
8. Si inválido → `status: clarify`
9. Llama a `logAiCall()` → puede fallar si DB no disponible

### ResponseValidator — validación por intent
| Intent | Required fields |
|--------|----------------|
| `create_part` | code, description, uom, part_type, category |
| `create_bom` | parent_part, components |
| `create_supplier` | name, cuit, contact, email, phone |
| `create_material` | code, description, uom, min_stock |
| `general_query` | **ninguno** — no valida campos |

### Últimos errores en logs (referencia)
```
2026/04/04 20:47:30 [error] Undefined property: App\Controllers\agent_AgentController::$session
  in /app/app/controllers/agent_AgentController.php on line 245
```
Este error es del archivo `.bkp` viejo, **no del controller nuevo**.

---

**Documento creado:** 5 de abril de 2026
**Siguiente paso:** Ejecutar Paso 1 (revisar logs en VPS)
