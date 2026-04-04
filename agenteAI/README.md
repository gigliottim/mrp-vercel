# 🤖 Agente AI — MRP

> Todos los archivos del Agente AI centralizados en una única carpeta.
> **Última actualización:** 4 de abril de 2026

---

## 📁 Estructura

```
agenteAI/
├── backend/                    # Código PHP del lado del servidor
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
│   │   └── agent_ai.php              # Configuración (referencia)
│   └── routes/
│       └── (rutas definidas en routes/api.php y routes/web.php)
│
├── frontend/                    # Código del lado del cliente
│   ├── js/
│   │   └── agent_chat.js                # Componente Alpine.js
│   ├── css/
│   │   └── agent_chat.css               # Estilos del chat
│   └── views/
│       ├── agent_chat.php               # Vista chat de página completa
│       └── components/
│           ├── _floating_button.php     # Botón flotante WhatsApp-style
│           ├── _message.php             # Burbuja de mensaje
│           ├── _preview.php             # Tabla de preview de datos
│           └── _suggestions.php         # Chips de sugerencias
│
├── database/                    # Base de datos
│   └── migrations/
│       ├── 001_create_agent_conversations.sql
│       ├── 002_create_agent_messages.sql
│       ├── 003_create_agent_ai_logs.sql
│       └── 004_add_agent_ai_menu_item.sql
│
├── docs/                        # Documentación
│   ├── README.md
│   └── (más docs en docs/)
│
└── tests/                       # Tests específicos del agente
```

---

## 🔧 Nomenclatura

| Capa | Namespace | Ruta física |
|------|-----------|-------------|
| Controllers | `App\AgenteAI\Backend\Controllers\` | `agenteAI/backend/controllers/` |
| Services | `App\AgenteAI\Backend\Services\` | `agenteAI/backend/services/` |
| Repositories | `App\AgenteAI\Backend\Repositories\` | `agenteAI/backend/repositories/` |
| Exceptions | `App\AgenteAI\Backend\Exceptions\` | `agenteAI/backend/exceptions/` |
| Frontend JS | (global) | `agenteAI/frontend/js/` |
| Frontend CSS | (global) | `agenteAI/frontend/css/` |
| Views | (PHP includes) | `agenteAI/frontend/views/` |

---

## 📡 Endpoints API

| Método | Endpoint | Controller Method | Propósito |
|--------|----------|-------------------|-----------|
| GET | `/agent` | `AgentController::showChat` | Renderizar chat completo |
| POST | `/api/v1/agent/message` | `AgentController::handleMessage` | Enviar mensaje al AI |
| POST | `/api/v1/agent/confirm` | `AgentController::confirmSave` | Confirmar y guardar datos |
| GET | `/api/v1/agent/suggestions` | `AgentController::getSuggestions` | Obtener sugerencias |
| GET | `/api/v1/agent/config` | `AgentController::checkConfiguration` | Verificar configuración |

---

## 🔒 Seguridad

- **No requiere CSRF**: Los endpoints están protegidos por sesión de usuario
- **Autenticación**: Solo usuarios con sesión activa pueden usar el agente
- **Rate limiting**: 10 requests/hora por usuario (implementado en Valkey)

---

## ⚙️ Configuración

La configuración principal está en `config/agent_ai.php` (archivo raíz del proyecto).
La copia en `agenteAI/backend/config/` es de referencia.

### Variables de entorno (.env)

```env
AGENT_AI_MODE=api
AGENT_AI_API_ENDPOINT=https://api.ollama.com/v1/chat/completions
AGENT_AI_API_MODEL=qwen3.5:cloud
AGENT_AI_API_KEY=tu_api_key
AGENT_AI_LOCAL_ENDPOINT=http://localhost:11434/api/chat
AGENT_AI_LOCAL_MODEL=qwen2.5:7b
```

---

## 📝 Historial de Cambios

| Fecha | Cambio |
|-------|--------|
| 04/04/2026 | Centralización de archivos en agenteAI/. Fixes: CSRF roto, config local faltante, session no definida |
| 03/04/2026 | Creación inicial del agente AI |
