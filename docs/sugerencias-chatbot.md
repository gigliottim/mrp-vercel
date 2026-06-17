# Sugerencias Rapidas para el ChatBot

## 1. Implementar historial de conversacion (Alta prioridad)

Los metodos `createConversation()`, `saveMessage()`, `getMessagesByConversation()` son stubs vacios. El `ConversationRepository` ya tiene el CRUD completo. Solo falta conectarlos en el service.

- **Archivos**: `agenteAI/backend/services/ConversationService.php`, `agenteAI/backend/repositories/ConversationRepository.php`
- **Esfuerzo**: Medio
- **Impacto**: Alto - permite persistir conversaciones entre sesiones

## 2. Agregar rate limiting (Seguridad)

La config ya existe en `valkey.php` (`max_requests: 10`, `window_seconds: 3600`) pero nunca se implementa. Se puede agregar un middleware o check en el controller con ~20 lineas.

- **Archivos**: `config/valkey.php`, `agenteAI/backend/controllers/AgentController.php`
- **Esfuerzo**: Bajo
- **Impacto**: Alto - protege contra abuso del endpoint

## 3. Limpiar archivos .bkp (Limpieza rapida)

Hay 7+ archivos `.bkp` en `app/controllers/` y `app/services/agentAI/` que son restos pre-refactor. Se pueden eliminar sin riesgo.

- **Archivos**:
  - `app/controllers/agent_AgentController.php.bkp`
  - `app/services/agentAI/agent_AgentService.php.bkp`
  - `app/services/agentAI/agent_AiClient.php.bkp`
  - `app/services/agentAI/agent_ConversationService.php.bkp`
  - `app/services/agentAI/agent_PromptBuilder.php.bkp`
  - `app/services/agentAI/agent_ResponseValidator.php.bkp`
  - `app/services/agentAI/agent_AgentResponse.php.bkp`
- **Esfuerzo**: Minimo
- **Impacto**: Limpieza de codigo

## 4. Registrar ruta clearCache (1 linea)

`AgentController::clearCache()` existe pero no tiene ruta en `api.php`. Solo agregar:

```php
$router->post('/api/v1/agent/cache/clear', [AgentController::class, 'clearCache']);
```

- **Archivos**: `routes/api.php`
- **Esfuerzo**: Minimo
- **Impacto**: Bajo - habilita funcionalidad ya existente

## 5. Completar flujo BOM (Funcionalidad)

El flujo `create_bom` esta parcial. Falta el paso secuencial de agregar multiples componentes (`component_qty`, `component_um`, `add_more`).

- **Archivos**: `agenteAI/backend/services/AgentService.php`, `agenteAI/backend/services/PromptBuilder.php`
- **Esfuerzo**: Medio
- **Impacto**: Alto - completa funcionalidad principal

## 6. Queries generales con IA (Feature)

El `general_query` existe en config pero los flujos guiados bypasean la IA por completo. Se podria habilitar para preguntas libres sobre datos del sistema.

- **Archivos**: `config/agent_ai.php`, `agenteAI/backend/services/AgentService.php`, `agenteAI/backend/services/AiClient.php`
- **Esfuerzo**: Medio-Alto
- **Impacto**: Alto - agrega valor significativo al asistente

## 7. Panel admin de conversaciones (Visibilidad)

Las tablas `agent_conversations`, `agent_messages`, `agent_ai_logs` ya existen pero no hay UI. Un CRUD rapido para ver/auditar conversaciones.

- **Archivos**: Nuevo controller, service, views
- **Esfuerzo**: Medio
- **Impacto**: Medio - visibilidad y debugging

## 8. Eliminar assets duplicados (Limpieza)

`agenteAI/frontend/views/components/_floating_button.php` tiene 871 lineas inline (CSS+JS) que duplican los archivos separados (`agent_floating.css` + `agent_floating.js`). Se puede borrar.

- **Archivos**: `agenteAI/frontend/views/components/_floating_button.php`
- **Esfuerzo**: Minimo
- **Impacto**: Limpieza de codigo

---

## Orden sugerido de implementacion

1. **#3** y **#8** - Limpieza inmediata, sin riesgo
2. **#4** - Una linea de codigo
3. **#2** - Seguridad rapida
4. **#1** - Historial de conversacion
5. **#5** - Completar BOM
6. **#6** - Queries generales con IA
7. **#7** - Panel admin