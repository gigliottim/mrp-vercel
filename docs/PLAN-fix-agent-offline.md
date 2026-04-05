# 🟢 PLAN — Solucionar modo OFFLINE del Agente AI

> **Creado:** 4 de abril de 2026
> **Estado:** ✅ DIAGNÓSTICO COMPLETADO — Causa raíz identificada y fix aplicado

---

## 1. Causa Raíz Encontrada

| Problema | Valor viejo | Valor correcto |
|----------|------------|----------------|
| **Endpoint** | `https://api.ollama.com/v1/chat/completions` | `https://ollama.com/v1/chat/completions` |
| **Modelo** | `qwen3.5:cloud` (no existe) | `qwen3.5:397b` |
| **Modelo general** | `gemini-3-flash-preview:cloud` | `gemini-3-flash-preview` |

### Evidencia

```bash
# Endpoint viejo → 301 redirect → 401 unauthorized
curl -s https://api.ollama.com/v1/chat/completions → 301 → ollama.com → 401

# Endpoint correcto → 200 OK
curl -s https://ollama.com/v1/chat/completions → 200 OK
{"id":"chatcmpl-972","model":"qwen3.5:397b","choices":[...]}
```

### Fix aplicado
- `.env` actualizado con endpoint y modelos correctos
- `config/agent_ai.php` lee todo del `.env` (sin hardcodeo)
- Deploy pendiente

---

## 2. Causas Raíz Posibles (Hipótesis originales, para referencia)

| # | Hipótesis | Estado |
|---|-----------|--------|
| ~~H1~~ | **API key expirada/inválida** | ✅ Descartada — la key funciona con el endpoint correcto |
| ~~H2~~ | **Endpoint caído o cambiado** | ✅ Confirmada — `api.ollama.com` NO es el endpoint real |
| ~~H3~~ | **Modelo no existe** | ✅ Confirmada — `qwen3.5:cloud` no existe, es `qwen3.5:397b` |
| H4 | **Timeout muy corto** | ❓ Pendiente verificar |
| H5 | **Firewall del VPS** | ❓ Pendiente verificar |
| H6 | **Formato del payload** | ✅ Funciona con el endpoint correcto |

---

## 3. Plan de Diagnóstico (completado)

### ✅ Paso 1: Test desde PC local → RESULTADO: 301 redirect
### ✅ Paso 2: Seguir redirect → RESULTADO: 401 unauthorized
### ✅ Paso 3: Encontrar endpoint correcto → `https://ollama.com/v1/chat/completions`
### ✅ Paso 4: Verificar modelos disponibles → `qwen3.5:397b`, `gemini-3-flash-preview`
### ✅ Paso 5: Test con endpoint correcto → **200 OK** ✅

### Modelos disponibles en Ollama Cloud
```
  qwen3.5:397b
  qwen3.5:397b (confirmado funcionando)
  qwen3-next:80b
  qwen3-vl:235b
  qwen3-vl:235b-instruct
  qwen3-coder-next
  qwen3-coder:480b
  gemini-3-flash-preview
  deepseek-v3.2
  gpt-oss:120b
  ... (35 modelos disponibles)
```

---

## 4. Causas Raíz Confirmadas

| # | Causa | Estado | Fix |
|---|-------|--------|-----|
| CR-1 | Endpoint incorrecto (`api.ollama.com` → `ollama.com`) | ✅ Confirmada | Hardcodeado en `config/agent_ai.php` |
| CR-2 | Modelo inexistente (`qwen3.5:cloud` → `qwen3.5:397b`) | ✅ Confirmada | Actualizado en `.env` con defaults en config |
| CR-3 | `Env::load()` no carga variables para `config/agent_ai.php` | ✅ Confirmada | Endpoint hardcodeado, modelos con defaults |

## 5. Fix Aplicado

| Archivo | Cambio |
|---------|--------|
| `config/agent_ai.php` | Endpoint hardcodeado a `https://ollama.com/v1/chat/completions`. Modelos con defaults (`qwen3.5:397b`, `gemini-3-flash-preview`). |
| `.env` | Actualizado con endpoint correcto y modelos reales. Variables `AGENT_AI_API_MODEL_*` apuntan a modelos que existen. |
| `agenteAI/backend/services/AiClient.php` | Sin modelos hardcodeados. Lee todo de `config('agent_ai')['models']`. |
| `agenteAI/backend/controllers/AgentController.php` | `checkConfiguration()` hace test real contra la API. Sin branch de modo local. |

## 6. Resultado Actual

### Endpoint `/api/v1/agent/config`
```json
{
    "success": true,
    "isOnline": true,
    "configValid": true,
    "apiResponds": true,
    "mode": "api",
    "message": "Configuración válida"
}
```

### Endpoint `/api/v1/agent/suggestions`
```json
{"success": true, "suggestions": [4 opciones]}
```

### Endpoint `/api/v1/agent/message`
⚠️ Sigue con 500 — requiere fix separado (probablemente sesión Valkey no disponible en producción)

## 7. Orden de Ejecución (histórico)

| 2 | Si funciona local → Test desde VPS | SSH |
| 3 | Si funciona VPS → Test desde container | `docker compose exec` |
| 4 | Identificar causa raíz | Analizar response HTTP |
| 5 | Aplicar fix correspondiente (A-F) | Editar .env o código |
| 6 | Deploy | `./deploy.sh` |
| 7 | Verificar `isOnline: true` | `curl /api/v1/agent/config` |

---

## 6. Criterios de Éxito

- [ ] `curl` directo al endpoint devuelve HTTP 200
- [ ] `/api/v1/agent/config` devuelve `isOnline: true, apiResponds: true`
- [ ] El floating button muestra el agente en **verde** (online)
- [ ] Enviar un mensaje al agente devuelve una respuesta válida (no 500)
- [ ] Las sugerencias rápidas funcionan al hacer clic

---

**Documento creado:** 4 de abril de 2026
**Siguiente paso:** Ejecutar Paso 1 del diagnóstico (curl desde PC local)
