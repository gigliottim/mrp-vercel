# 🤖 Agente AI — MRP

> Todos los archivos del Agente AI centralizados en una única carpeta.
> **Última actualización:** 4 de abril de 2026
> **Versión:** 3.1 — Schema real verificado + stubs completados

---

## 📁 Estructura

```
agenteAI/
├── backend/                    # Código PHP del lado del servidor
│   ├── controllers/
│   │   └── AgentController.php        # Controller principal
│   ├── services/
│   │   ├── AgentService.php           # Orquestador (con CRUD real)
│   │   ├── AiClient.php               # Cliente HTTP (Ollama, DashScope, etc.)
│   │   ├── PromptBuilder.php          # Constructor de prompts
│   │   ├── ResponseValidator.php      # Validación de respuestas
│   │   ├── ConversationService.php    # Gestión de conversaciones
│   │   └── AgentResponse.php          # DTO de respuesta
│   ├── repositories/
│   │   └── ConversationRepository.php # Valkey (caché) + PostgreSQL (persistencia)
│   └── exceptions/
│       └── AgentAiException.php       # Excepción personalizada
│
├── frontend/                    # Código del lado del cliente
│   ├── js/
│   │   ├── agent_chat.js                # Componente Alpine.js (chat completo)
│   │   └── agent_floating.js            # Componente Alpine.js (botón flotante)
│   ├── css/
│   │   ├── agent_chat.css               # Estilos del chat
│   │   └── agent_floating.css           # Estilos del botón flotante
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
├── docs/                        # Documentación de referencia
├── tests/                       # Tests específicos del agente
└── README.md                    # Este archivo
```

### Archivos accesibles desde web (public/)

```
public/agenteAI/
└── frontend/
    ├── js/
    │   ├── agent_chat.js
    │   └── agent_floating.js
    ├── css/
    │   ├── agent_chat.css
    │   └── agent_floating.css
    └── views/
        └── agent_chat.php        # Copia de referencia
```

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

## 🗃️ Schema Real Verificado

Todas las operaciones de guardado usan las tablas y columnas reales del proyecto MRP:

### savePart() — Crear pieza
| Tabla | Columnas usadas |
|-------|----------------|
| `tipos_partes` | `id`, `codigo` (lookup del tipo de parte) |
| `grupos_partes` | `id`, `nombre` (lookup del grupo/categoría) |
| `partes` | `id`, `codigo`, `id_tipo`, `id_grupo`, `detalle`, `activo`, `fecha_creacion` |
| `variantes` | `id`, `id_parte`, `codigo_variante`, `detalle`, `estado`, `stock_actual`, `fecha_creacion` |

### saveSupplier() — Crear proveedor
| Tabla | Columnas usadas |
|-------|----------------|
| `entidades` | `id`, `razon_social`, `tipo` (= 'PROVEEDOR'), `identificacion_tributaria` (CUIT), `contacto_email`, `contacto_telefono` |

### saveBom() — Crear lista de materiales
| Tabla | Columnas usadas |
|-------|----------------|
| `variantes` | Lookup de `variante_padre_id` y `variante_componente_id` por código |
| `bom_cabecera` | `id`, `variante_padre_id`, `version`, `activa`, `fecha_efectiva` |
| `bom_detalle` | `id`, `bom_id`, `variante_componente_id`, `cantidad_necesaria`, `unidad_medida_id` |

### saveMaterial() — Crear materia prima
| Tabla | Columnas usadas |
|-------|----------------|
| `tipos_partes` | Lookup tipo 'materia_prima' |
| `partes` | Mismo flujo que savePart |
| `variantes` | Además setea `stock_seguridad` y `atributos` con min_stock |

---

## 🔒 Seguridad

- **No requiere CSRF**: Los endpoints están protegidos por sesión de usuario
- **Autenticación**: Solo usuarios con sesión activa pueden usar el agente
- **Rate limiting**: 10 requests/hora por usuario (implementado en Valkey)

---

## ⚙️ Configuración

La configuración activa está en `config/agent_ai.php` (raíz del proyecto).
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

| Fecha | Versión | Cambio |
|-------|---------|--------|
| 04/04/2026 | 3.1 | Schema real verificado. savePart/saveSupplier/saveBom/saveMaterial implementados con tablas correctas. Stubs completados. |
| 04/04/2026 | 3.0 | Centralización en `agenteAI/`. Fixes: CSRF roto, config local faltante, session no definida. Autoloader actualizado. |
| 03/04/2026 | — | Creación inicial del agente AI |
