# Agente AI Chatbot para MRP

## Descripción

El Agente AI Chatbot es un módulo que guía al usuario a cargar datos (piezas, BOMs, proveedores, etc.) mediante opciones predefinidas y texto libre con repregunta automática cuando hay ambigüedad.

## Arquitectura

### Componentes

- **Valkey**: Caché para respuestas de IA y sesiones activas
- **PostgreSQL**: Persistencia de conversaciones y logs
- **Ollama/DashScope**: Proveedores de IA configurables

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

## Instalación

### 1. Configurar Valkey

Asegúrate de que Valkey esté corriendo en tu sistema:

```bash
docker-compose up -d valkey
```

### 2. Ejecutar migraciones

```bash
psql -U mrp -d mrp_tunna -f database/migrations/2026_04_03_001_create_agent_conversations.sql
psql -U mrp -d mrp_tunna -f database/migrations/2026_04_03_002_create_agent_messages.sql
psql -U mrp -d mrp_tunna -f database/migrations/2026_04_03_003_create_agent_ai_logs.sql
```

### 3. Configurar proveedor de IA

Edita `config/agent_ai.php`:

```php
'mode' => env('AGENT_AI_MODE', 'local'), // 'local' o 'api'
```

#### Modo Local (Ollama)

```bash
# Configurar Ollama en tu VPS
docker run -d -p 11434:11434 --name ollama ollama/ollama:latest

# Descargar el modelo
docker exec -it ollama ollama pull qwen2.5:1.5b
```

#### Modo API (DashScope)

```bash
# Configurar en .env
AGENT_AI_MODE=api
AGENT_AI_API_KEY=tu_clave_api
```

## Uso

### Acceder al chatbot

```
GET /agent
```

### Endpoints API

- `POST /api/v1/agent/message` - Enviar mensaje
- `POST /api/v1/agent/confirm` - Confirmar y guardar datos
- `GET /api/v1/agent/suggestions` - Obtener sugerencias

## Intents soportados

- `create_part` - Crear nueva Parte (Pieza, MP, Conjuntos, PT, MO, etc)
- `create_bom` - Armar lista de materiales (BOM)
- `create_supplier` - Registrar proveedor
- `general_query` - Preguntas generales

## Configuración

### Parámetros de generación

```php
'generation' => [
    'temperature' => 0.1, // Baja para más precisión
    'max_tokens' => 800,
    'seed' => 42, // Reproducibilidad
    'num_ctx' => 4096, // Contexto máximo
],
```

### Retry configuration

```php
'retry' => [
    'max_attempts' => 2,
    'delay_ms' => 500,
],
```

## Validación

El agente valida automáticamente los datos antes de guardarlos:

- Código: alfanumérico, máximo 20 caracteres
- UOM: u, kg, m, l, g
- CUIT: formato XX-XXXXXXXX-X
- Email: formato válido
- Números positivos: para stocks mínimos

## Caché

### Respuestas de IA

- TTL: 3600 segundos (1 hora)
- Clave: `ai:response:{prompt_hash}`

### Sesiones activas

- TTL: 1800 segundos (30 minutos)
- Clave: `session:{userId}:{tenantId}`

### Rate Limiting

- Máximo: 10 requests por hora
- Clave: `ratelimit:{userId}:{hour}`

## Logs

Los logs se guardan en la tabla `agent_ai_logs` con información sobre:

- Modelo utilizado
- Proveedor (local/api)
- Tiempo de respuesta
- Resultado de validación
- Tokens utilizados

## Troubleshooting

### Error: "Failed to get response from AI"

- Verifica que Ollama esté corriendo: `docker ps | grep ollama`
- Verifica la configuración en `config/agent_ai.php`
- Revisa los logs en `agent_ai_logs`

### Error: "Valkey connection refused"

- Verifica que Valkey esté corriendo: `docker ps | grep valkey`
- Verifica la configuración en `config/valkey.php`

### Error: "Invalid JSON response"

- El modelo puede estar generando JSON inválido
- Aumenta `max_tokens` en la configuración
- Verifica que el prompt sea claro y específico

## Mantenimiento

### Limpiar caché

```bash
# Limpiar respuestas de IA
redis-cli KEYS "mrp:agent:ai:response:*" | xargs redis-cli DEL

# Limpiar sesiones activas
redis-cli KEYS "mrp:agent:session:*" | xargs redis-cli DEL
```

### Ver conversaciones activas

```sql
SELECT * FROM agent_conversations WHERE status = 'active';
```

### Ver logs de IA

```sql
SELECT * FROM agent_ai_logs ORDER BY created_at DESC LIMIT 10;
```

## Escalabilidad

- **Caché Valkey**: Permite escalar horizontalmente sin sobrecargar la DB
- **Sesiones TTL**: Auto-expiran, no requiere limpieza manual
- **Rate Limiting**: Evita abuso sin sobrecargar la IA

## Mejoras futuras

- Upload de PDF/Excel para extracción automática
- BOMs multi-nivel generados por IA
- Panel de historial y auditoría
- Streaming de respuestas (SSE/WebSocket)
- Fine-tuning del modelo con datos propios
