# 📋 PLAN — Diagnóstico y Fix de Errores 500 en Agente AI

> **Creado:** 4 de abril de 2026
> **Problema:** Los 3 endpoints del agente devuelven 500 en producción
> **Endpoints afectados:** `/api/v1/agent/config`, `/api/v1/agent/suggestions`, `/api/v1/agent/message`

---

## 1. Problema

Al acceder a `https://mimrp.com.ar/dashboard`, la consola muestra:

```
GET  /api/v1/agent/config     → 500
GET  /api/v1/agent/suggestions → 500
POST /api/v1/agent/message     → 500
```

Todos los endpoints del agente fallan. Esto indica un error en la cadena de inicialización del controller o de los servicios.

---

## 2. Causas Raíz Identificadas (Hipótesis)

### HR-1: Nombre de clase no coincide con el uso en rutas 🔴 CRÍTICA

**Archivo:** `agenteAI/backend/controllers/AgentController.php`

```php
namespace App\AgenteAI\Backend\Controllers;
// ...
final class agent_AgentController extends Controller  // ← El archivo se llama AgentController.php pero la clase es agent_AgentController
```

**Rutas (`routes/api.php` y `routes/web.php`):**
```php
use App\AgenteAI\Backend\Controllers\AgentController;
$router->post('/api/v1/agent/message', [AgentController::class, 'handleMessage']);
```

**Problema:** El archivo se llama `AgentController.php` pero la clase se llama `agent_AgentController`. PHP espera que `AgentController::class` resuelva a una clase llamada `AgentController`, no `agent_AgentController`. El autoloader PSR-4 encuentra el archivo pero la clase no existe con ese nombre.

### HR-2: Namespaces en servicios copiados no coinciden 🟠 ALTA

Los archivos en `agenteAI/backend/services/` tienen namespaces como `App\AgenteAI\Backend\Services\` pero sus clases internas pueden referenciar entre sí con nombres incorrectos:

- `AgentService` referencia `new AiClient()` (nombre corto, sin namespace completo)
- `AgentService` referencia `new PromptBuilder()` (nombre corto)
- `AgentService` referencia `new ResponseValidator()` (nombre corto)
- `AgentService` referencia `new ConversationService()` (renombrado desde `AgentConversationService`)

Si algún archivo no fue actualizado correctamente, las clases no se encontrarán.

### HR-3: Archivos `.bkp` ya no accesibles 🟠 ALTA

Los archivos originales fueron renombrados a `.bkp`:
- `app/services/agentAI/agent_AgentService.php.bkp`
- `app/services/agentAI/agent_AiClient.php.bkp`
- `app/Repositories/AgentConversationRepository.php.bkp`

Si algún código legacy todavía referencia `App\Services\AgentAI\*` o `App\Repositories\*`, fallará silenciosamente con class not found.

### HR-4: `config/agent_ai.php` puede fallar al cargar 🟡 MEDIA

El config usa `env()` directamente. Si alguna variable no está definida y no tiene default, podría retornar null y causar errores en `AgentAiClient::__construct()`.

### HR-5: Valkey no disponible en producción 🔵 BAJA

Si Valkey no conecta, los servicios deberían degradar gracefulmente (código lo contempla). Pero si el constructor lanza excepción no capturada, el controller no se instanciaría.

---

## 3. Plan de Diagnóstico

### Paso 1: Verificar en producción (SSH al VPS)

```bash
# Ver logs de error de PHP
tail -50 /var/log/php-fpm/error.log | grep -i agent
tail -50 /var/log/nginx/error.log | grep -i agent

# Si usa storage/logs
tail -50 /home/gigliotti/Proyectos/mrp/storage/logs/error.log 2>/dev/null | grep -i agent

# Verificar que los archivos existen en VPS
ls -la /home/gigliotti/Proyectos/mrp/agenteAI/backend/controllers/AgentController.php
ls -la /home/gigliotti/Proyectos/mrp/agenteAI/backend/services/
```

### Paso 2: Test rápido de cada endpoint

```bash
curl -v https://mimrp.com.ar/api/v1/agent/config 2>&1 | head -30
curl -v -X POST https://mimrp.com.ar/api/v1/agent/suggestions 2>&1 | head -30
```

### Paso 3: Test de instanciación de clases

```php
# Script temporal de diagnóstico
require_once 'bootstrap/autoload.php';

// Test 1: ¿El controller se puede instanciar?
try {
    $c = new \App\AgenteAI\Backend\Controllers\AgentController();
    echo "✅ Controller OK\n";
} catch (Throwable $e) {
    echo "❌ Controller: " . $e->getMessage() . "\n";
}

// Test 2: ¿El service se puede instanciar?
try {
    $s = new \App\AgenteAI\Backend\Services\AgentService();
    echo "✅ AgentService OK\n";
} catch (Throwable $e) {
    echo "❌ AgentService: " . $e->getMessage() . "\n";
}

// Test 3: ¿El config carga?
try {
    $c = config('agent_ai');
    echo "✅ Config OK: mode=" . ($c['mode'] ?? 'null') . "\n";
} catch (Throwable $e) {
    echo "❌ Config: " . $e->getMessage() . "\n";
}

// Test 4: ¿AiClient se puede instanciar?
try {
    $c = new \App\AgenteAI\Backend\Services\AiClient();
    echo "✅ AiClient OK\n";
} catch (Throwable $e) {
    echo "❌ AiClient: " . $e->getMessage() . "\n";
}
```

---

## 4. Fixes Planificados

### Fix 1: Renombrar clase del controller (HR-1) 🔴

**Archivo:** `agenteAI/backend/controllers/AgentController.php`

```php
// Cambiar:
final class agent_AgentController extends Controller

// A:
final class AgentController extends Controller
```

Esto asegura que `AgentController::class` resuelva correctamente.

### Fix 2: Verificar namespaces en todos los services (HR-2) 🟠

Verificar que cada archivo tenga:

| Archivo | Namespace esperado | Clase esperada |
|---------|-------------------|----------------|
| `AgentService.php` | `App\AgenteAI\Backend\Services` | `AgentService` |
| `AiClient.php` | `App\AgenteAI\Backend\Services` | `AiClient` |
| `PromptBuilder.php` | `App\AgenteAI\Backend\Services` | `PromptBuilder` |
| `ResponseValidator.php` | `App\AgenteAI\Backend\Services` | `ResponseValidator` |
| `ConversationService.php` | `App\AgenteAI\Backend\Services` | `ConversationService` |
| `AgentResponse.php` | `App\AgenteAI\Backend\Services` | `AgentResponse` |
| `ConversationRepository.php` | `App\AgenteAI\Backend\Repositories` | `ConversationRepository` |
| `AgentAiException.php` | `App\AgenteAI\Backend\Exceptions` | `AgentAiException` |

### Fix 3: Verificar que no hay referencias a namespaces viejos (HR-3) 🟠

Buscar cualquier código que referencie:
- `App\Services\AgentAI\`
- `App\Repositories\AgentConversationRepository`
- `App\Controllers\agent_AgentController` (salvo el alias)

### Fix 4: Agregar error_log en controller para debug (HR-4) 🟡

Agregar en el constructor del controller:
```php
public function __construct()
{
    error_log("AgentController: instanciado correctamente");
}
```

Y en cada método:
```php
public function checkConfiguration(Request $request): Response
{
    error_log("AgentController::checkConfiguration called");
    // ...
}
```

---

## 5. Orden de Ejecución

| # | Acción | Estado |
|---|--------|--------|
| 1 | ✅ Renombrar `agent_AgentController` → `AgentController` en controller | ✅ APLICADO |
| 2 | ✅ Fix `AgentAiClient` → `AiClient` en AiClient.php | ✅ APLICADO |
| 3 | ✅ Fix `AgentPromptBuilder` → `PromptBuilder` en PromptBuilder.php | ✅ APLICADO |
| 4 | ✅ Fix `AgentResponseValidator` → `ResponseValidator` en ResponseValidator.php | ✅ APLICADO |
| 5 | ✅ Fix refs en ConversationService (`AgentPromptBuilder` → `PromptBuilder`, etc.) | ✅ APLICADO |
| 6 | Verificar que no hay refs a namespaces viejos en código activo | ✅ VERIFICADO |
| 7 | Probar endpoints localmente | ⏳ PENDIENTE |
| 8 | Subir a producción y verificar | ⏳ PENDIENTE |
| 9 | Si persiste, ejecutar script de diagnóstico en VPS | ⏳ PENDIENTE |

---

## 6. Resumen de Causas Raíz Encontradas y Fixes Aplicados

| # | Causa Raíz | Archivo(s) | Fix |
|---|-----------|-----------|-----|
| CR-1 | Clase `agent_AgentController` no coincide con `AgentController::class` en rutas | `agenteAI/backend/controllers/AgentController.php` | Renombrada a `AgentController` |
| CR-2 | Clase `AgentAiClient` no coincide con `new AiClient()` en AgentService | `agenteAI/backend/services/AiClient.php` | Renombrada a `AiClient` |
| CR-3 | Clase `AgentPromptBuilder` no coincide con `new PromptBuilder()` | `agenteAI/backend/services/PromptBuilder.php`, `ConversationService.php` | Renombrada a `PromptBuilder` |
| CR-4 | Clase `AgentResponseValidator` no coincide con `new ResponseValidator()` | `agenteAI/backend/services/ResponseValidator.php`, `ConversationService.php` | Renombrada a `ResponseValidator` |

---

## 7. Rollback Plan

Si los fixes no funcionan:
1. Revertir rutas a `App\Controllers\agent_AgentController`
2. Renombrar `.bkp` de vuelta a nombres originales
3. Restaurar controller original

---

**Documento creado:** 4 de abril de 2026
**Estado:** ⏳ Pendiente de ejecución
