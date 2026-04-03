# Agente AI - Pasos/Fases para Solución de Problemas

## Problema Identificado

Al hacer click en las opciones rápidas o al enviar un mensaje desde el chat, aparecen errores en la consola:

1. **Error 1**: `Uncaught TypeError: chat.agentFloating is not a function`
   - Ubicación: `dashboard:1693`
   - Causa: Intento de acceder al componente Alpine.js usando `chat.agentFloating()` que no existe

2. **Error 2**: `POST https://mimrp.com.ar/api/v1/agent/message 500 (Internal Server Error)`
   - Causa: El agente no está configurado correctamente o el backend no puede procesar la solicitud

3. **Requisito adicional**: Si el agente no está online, mostrarlo en modo offline

---

## Fases de Solución

### Fase 1: Corregir Referencia al Componente Alpine.js

**Problema**: En las funciones globales `selectFloatSuggestion` y `selectSuggestion`, se intentaba acceder al componente Alpine.js usando `chat.agentFloating()` o `chat.agentChat()`, pero estos métodos no existen.

**Solución**: Usar `Alpine.$data(elemento)` para acceder al componente Alpine.js desde un elemento del DOM.

#### Archivos Modificados:

1. **[`views/components/agentAI/_agent_floating_button.php`](views/components/agentAI/_agent_floating_button.php:611)**
   ```javascript
   // Antes (INCORRECTO):
   function selectFloatSuggestion(intent, label) {
       const chat = document.getElementById('agent-floating-btn');
       if (chat) {
           chat.agentFloating().userInput = label;  // ❌ Error: chat.agentFloating no es una función
           chat.agentFloating().currentIntent = intent;
           chat.agentFloating().sendMessage();
       }
   }

   // Después (CORRECTO):
   function selectFloatSuggestion(intent, label) {
       const chat = document.getElementById('agent-floating-btn');
       if (chat) {
           const alpineComponent = Alpine.$data(chat);  // ✅ Acceder al componente Alpine.js
           if (alpineComponent) {
               alpineComponent.userInput = label;
               alpineComponent.currentIntent = intent;
               alpineComponent.sendMessage();
           }
       }
   }
   ```

2. **[`public/assets/js/modules/agentAI/agent_chat.js`](public/assets/js/modules/agentAI/agent_chat.js:253)**
   ```javascript
   // Antes (INCORRECTO):
   function selectSuggestion(intent, label) {
       const chat = document.getElementById('agent-chat');
       if (chat) {
           chat.agentChat().selectSuggestion(intent, label);  // ❌ Error: chat.agentChat no es una función
       }
   }

   // Después (CORRECTO):
   function selectSuggestion(intent, label) {
       const chat = document.getElementById('agent-chat');
       if (chat) {
           const alpineComponent = Alpine.$data(chat);  // ✅ Acceder al componente Alpine.js
           if (alpineComponent) {
               alpineComponent.selectSuggestion(intent, label);
           }
       }
   }
   ```

---

### Fase 2: Agregar Estado Offline en el Botón Flotante

**Problema**: No se indicaba cuando el agente estaba fuera de línea, lo que causaba confusión al usuario.

**Solución**: 
1. Agregar propiedad `isOffline` al componente Alpine.js
2. Verificar configuración del agente al inicializar
3. Mostrar estado visual (en línea/offline)
4. Deshabilitar área de input cuando esté offline

#### Archivos Modificados:

1. **[`views/components/agentAI/_agent_floating_button.php`](views/components/agentAI/_agent_floating_button.php:436)**
   ```javascript
   function agentFloating() {
       return {
           isMinimized: false,
           userInput: '',
           messages: [],
           suggestions: [],
           isLoading: false,
           isOffline: false,  // ✅ Nueva propiedad
           conversationId: null,
           currentIntent: null,

           init() {
               this.checkConfiguration();  // ✅ Verificar configuración al iniciar
               this.loadSuggestions();
               // ...
           },

           async checkConfiguration() {  // ✅ Nueva función
               try {
                   const response = await fetch('/api/v1/agent/config');
                   const data = await response.json();

                   if (data.success) {
                       this.isOffline = !data.isOnline;
                   }
               } catch (error) {
                   console.error('Error checking configuration:', error);
                   this.isOffline = true;  // Asumir offline si falla
               }
           },

           async sendMessage() {
               if (this.isOffline) {  // ✅ Verificar estado antes de enviar
                   console.warn('Chat offline - No se puede enviar mensaje');
                   return;
               }
               // ...
           }
       };
   }
   ```

2. **HTML - Header del chat flotante**
   ```html
   <span class="agent-status" x-show="!isOffline">En línea</span>
   <span class="agent-status offline" x-show="isOffline">Fuera de línea</span>
   ```

3. **HTML - Área de input**
   ```html
   <div class="agent-float-input-area" x-show="!isOffline">
       <textarea :disabled="isLoading || isOffline"></textarea>
       <button :disabled="isLoading || !userInput.trim() || isOffline"></button>
   </div>

   <div class="agent-float-offline-message" x-show="isOffline">
       <p>El agente está fuera de línea. Verifica la configuración.</p>
   </div>
   ```

4. **CSS - Estilos para estado offline**
   ```css
   .agent-status.offline {
       color: #fca5a5;
   }

   .agent-float-offline-message {
       padding: 16px;
       text-align: center;
       background: #fff3cd;
       border-top: 1px solid #ffeeba;
       color: #856404;
       font-size: 12px;
   }
   ```

---

### Fase 3: Verificar Configuración del Agente

**Problema**: El agente no estaba configurado correctamente, causando errores 500.

**Solución**: Verificar que la configuración en [`config/agent_ai.php`](config/agent_ai.php) sea correcta.

#### Configuración Requerida:

1. **Modo local (Ollama)**:
   ```php
   'mode' => 'local',
   'local' => [
       'endpoint' => env('AGENT_AI_LOCAL_ENDPOINT', 'http://localhost:11434/v1/chat/completions'),
       'model' => env('AGENT_AI_LOCAL_MODEL', 'qwen3.5:0.8b'),
       'timeout' => 30,
   ],
   ```

2. **Modo API (DashScope/OpenRouter)**:
   ```php
   'mode' => 'api',
   'api' => [
       'endpoint' => env('AGENT_AI_API_ENDPOINT', 'https://api.dashscope.aliyuncs.com/compatible-mode/v1/chat/completions'),
       'model' => env('AGENT_AI_API_MODEL', 'qwen/qwen2.5-1.5b-instruct'),
       'key' => env('AGENT_AI_API_KEY'),  // Requerido
       'timeout' => 30,
   ],
   ```

3. **Variables de entorno (.env)**:
   ```
   AGENT_AI_MODE=local
   AGENT_AI_LOCAL_ENDPOINT=http://localhost:11434/v1/chat/completions
   AGENT_AI_LOCAL_MODEL=qwen3.5:0.8b
   ```

---

### Fase 4: Verificar Backend (AgentService)

**Problema**: El backend podía fallar si Valkey no estaba disponible.

**Solución**: El [`AgentService`](app/services/agentAI/agent_AgentService.php) ya maneja esto correctamente:
- Si Valkey no está disponible, usa conversaciones temporales
- Si la validación falla, devuelve respuesta para aclarar

---

## Resumen de Cambios

| Archivo | Cambios |
|---------|---------|
| [`views/components/agentAI/_agent_floating_button.php`](views/components/agentAI/_agent_floating_button.php) | - Corregir `selectFloatSuggestion` para usar `Alpine.$data()`<br>- Agregar propiedad `isOffline`<br>- Agregar función `checkConfiguration()`<br>- Actualizar HTML para mostrar estado offline<br>- Actualizar CSS para estilos offline |
| [`public/assets/js/modules/agentAI/agent_chat.js`](public/assets/js/modules/agentAI/agent_chat.js) | - Corregir `selectSuggestion` para usar `Alpine.$data()`<br>- Ya tenía verificación de estado offline |

---

## Pruebas Recomendadas

1. **Verificar que no aparezcan errores en consola** al hacer click en sugerencias
2. **Verificar que el agente muestre "Fuera de línea"** cuando la configuración es inválida
3. **Verificar que el área de input esté deshabilitada** cuando el agente esté offline
4. **Verificar que los mensajes se envíen correctamente** cuando el agente esté online
5. **Verificar que los errores 500 desaparezcan** al corregir la configuración

---

## Notas Adicionales

- El componente Alpine.js se inicializa automáticamente con `x-data="agentFloating()"`
- `Alpine.$data(elemento)` es la forma correcta de acceder al componente desde fuera
- El estado offline se verifica contra la API `/api/v1/agent/config`
- El backend ya tiene manejo de errores para Valkey no disponible
