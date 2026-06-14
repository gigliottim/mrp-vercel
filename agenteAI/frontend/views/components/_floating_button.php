<?php

use App\Core\View\View;

?>
<!-- Botón Flotante del Agente AI (Estilo WhatsApp) -->
<div id="agent-floating-btn" class="agent-floating-btn" x-data="agentFloating()" x-init="init()" x-bind:class="isMinimized ? 'minimized' : ''">
    <!-- Botón principal -->
    <button
        class="agent-float-toggle"
        @click="toggleChat"
        aria-label="Abrir Agente AI"
        title="Agente AI - Asistente Virtual">
        <svg class="icon" x-show="!isMinimized" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H3a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V3.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2z" />
            <path d="M9 10a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1z" />
            <path d="M9 16h6" />
        </svg>
        <svg class="icon" x-show="isMinimized" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
        </svg>
    </button>

    <!-- Contenedor del chat flotante -->
    <div class="agent-float-container" x-show="!isMinimized" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 transform scale-100" x-transition:leave-end="opacity-0 transform scale-95">
        <!-- Header del chat flotante -->
        <div class="agent-float-header">
            <div class="agent-float-header-info">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H3a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V3.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2z" />
                    <path d="M9 10a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1z" />
                    <path d="M9 16h6" />
                </svg>
                <div>
                    <strong>Agente AI</strong>
                    <span class="agent-status" x-show="!isOffline">En línea</span>
                    <span class="agent-status offline" x-show="isOffline">Fuera de línea</span>
                </div>
            </div>
            <div class="agent-float-header-actions">
                <button class="agent-float-btn" @click="minimizeChat" aria-label="Minimizar">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12" />
                    </svg>
                </button>
                <button class="agent-float-btn" @click="closeChat" aria-label="Cerrar">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mensajes del chat flotante -->
        <div class="agent-float-messages" id="agent-float-messages">
            <div class="agent-float-message agent-float-message-system">
                <div class="agent-float-message-content">
                    <p>¡Hola! Soy tu asistente de IA. Puedo ayudarte a crear piezas, BOMs, proveedores y más.</p>
                </div>
                <span class="agent-float-timestamp">Ahora</span>
            </div>
        </div>

        <!-- Sugerencias -->
        <div class="agent-float-suggestions" id="agent-float-suggestions">
            <div class="agent-float-suggestions-title">Opciones rápidas:</div>
            <div class="agent-float-suggestions-grid" id="agent-float-suggestions-grid">
                <!-- Las sugerencias se renderizarán aquí -->
            </div>
        </div>

        <!-- Área de input -->
        <div class="agent-float-input-area" x-show="!isOffline || guidedState !== null">
            <textarea
                x-model="userInput"
                @keydown.enter.exact.prevent="sendMessage"
                placeholder="Escribe tu mensaje..."
                :disabled="isLoading"></textarea>
            <button class="agent-float-btn" @click="sendMessage" :disabled="isLoading || !userInput.trim()">
                <svg class="icon" x-show="!isLoading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13" />
                    <polygon points="22 2 15 22 11 13 2 9 22 2" />
                </svg>
                <span x-show="isLoading">...</span>
            </button>
        </div>

        <!-- Mensaje de estado offline -->
        <div class="agent-float-offline-message" x-show="isOffline">
            <p>El agente está fuera de línea. Las opciones rápidas siguen disponibles.</p>
        </div>
    </div>
</div>

<style>
    /* Estilos del botón flotante */
    .agent-floating-btn {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 10px;
    }

    .agent-floating-btn.minimized {
        align-items: flex-end;
    }

    .agent-floating-btn.minimized .agent-float-container {
        display: none !important;
    }

    /* Botón principal */
    .agent-float-toggle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        color: white;
        border: none;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }

    .agent-float-toggle:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
    }

    .agent-float-toggle:active {
        transform: scale(0.95);
    }

    /* Contenedor del chat flotante */
    .agent-float-container {
        width: 350px;
        max-width: 90vw;
        height: 500px;
        max-height: 80vh;
        background: white;
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        animation: slideUp 0.3s ease-out;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px) scale(0.95);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    /* Header del chat flotante */
    .agent-float-header {
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        color: white;
        padding: 12px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .agent-float-header-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .agent-float-header-info i,
    .agent-float-header-info .icon {
        font-size: 24px;
        width: 24px;
        height: 24px;
    }

    .agent-float-header-info strong {
        font-size: 14px;
        font-weight: 600;
    }

    .agent-status {
        font-size: 11px;
        opacity: 0.9;
    }

    .agent-status.online {
        color: #a7f3d0;
    }

    .agent-status.offline {
        color: #fca5a5;
    }

    .agent-float-header-actions {
        display: flex;
        gap: 8px;
    }

    .agent-float-btn {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: none;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        font-size: 14px;
    }

    .agent-float-btn:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    /* Mensajes del chat flotante */
    .agent-float-messages {
        flex: 1;
        overflow-y: auto;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: #f5f7fa;
    }

    .agent-float-message {
        display: flex;
        flex-direction: column;
        max-width: 85%;
        padding: 10px 14px;
        border-radius: 12px;
        position: relative;
    }

    .agent-float-message.user {
        align-self: flex-end;
        background: #dcf8c6;
        border-bottom-right-radius: 2px;
    }

    .agent-float-message.assistant {
        align-self: flex-start;
        background: white;
        border-bottom-left-radius: 2px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .agent-float-message.system {
        align-self: center;
        background: #fff3cd;
        border: 1px solid #ffeeba;
        max-width: 100%;
        font-size: 0.85rem;
    }

    .agent-float-message-content {
        margin-bottom: 4px;
    }

    .agent-float-message-content p {
        margin: 0;
        line-height: 1.4;
        font-size: 13px;
    }

    .agent-float-timestamp {
        font-size: 10px;
        opacity: 0.6;
        text-align: right;
    }

    /* Sugerencias */
    .agent-float-suggestions {
        padding: 12px 16px;
        background: white;
        border-top: 1px solid #e1e4e8;
    }

    .agent-float-suggestions-title {
        font-size: 11px;
        font-weight: 600;
        margin-bottom: 8px;
        color: #495057;
    }

    .agent-float-suggestions-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .agent-float-suggestion-chip {
        display: flex;
        align-items: center;
        gap: 5px;
        padding: 6px 10px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 16px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .agent-float-suggestion-chip:hover {
        background: #e9ecef;
        border-color: #007bff;
    }

    .agent-float-suggestion-chip i,
    .agent-float-suggestion-chip .icon {
        font-size: 12px;
        width: 12px;
        height: 12px;
    }

    /* Área de input */
    .agent-float-input-area {
        display: flex;
        gap: 8px;
        padding: 12px;
        background: #f8f9fa;
        border-top: 1px solid #e1e4e8;
    }

    .agent-float-input-area textarea {
        flex: 1;
        padding: 10px;
        border: 1px solid #ced4da;
        border-radius: 8px;
        resize: none;
        height: 40px;
        font-family: inherit;
        font-size: 13px;
    }

    .agent-float-input-area textarea:focus {
        outline: none;
        border-color: #25D366;
        box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.2);
    }

    .agent-float-input-area button {
        padding: 0 14px;
        background: #25D366;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .agent-float-input-area button:hover:not(:disabled) {
        background: #20bd5a;
    }

    .agent-float-input-area button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Mensaje de estado offline */
    .agent-float-offline-message {
        padding: 16px;
        text-align: center;
        background: #fff3cd;
        border-top: 1px solid #ffeeba;
        color: #856404;
        font-size: 12px;
    }

    /* Responsive */
    @media (max-width: 480px) {
        .agent-floating-btn {
            bottom: 15px;
            right: 15px;
        }

        .agent-float-container {
            width: 100%;
            height: 100%;
            max-width: 100vw;
            max-height: 100vh;
            border-radius: 0;
        }

        .agent-float-header {
            padding: 10px 12px;
        }

        .agent-float-messages {
            padding: 12px;
        }

        .agent-float-suggestions {
            padding: 10px 12px;
        }

        .agent-float-input-area {
            padding: 10px;
        }
    }

    /* Estilos para iconos SVG */
    .icon {
        width: 24px;
        height: 24px;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
        fill: none;
    }

    .agent-float-toggle .icon {
        width: 28px;
        height: 28px;
    }

    .agent-float-btn .icon {
        width: 16px;
        height: 16px;
    }

    .agent-float-suggestion-chip .icon {
        width: 14px;
        height: 14px;
    }
</style>

<script>
    /**
     * Agente AI Botón Flotante - Componente Alpine.js
     */
    function agentFloating() {
        return {
            isMinimized: false,
            userInput: '',
            messages: [],
            suggestions: [],
            isLoading: false,
            isOffline: false,
            conversationId: null,
            currentIntent: null,

            init() {
                this.checkConfiguration();
                this.loadSuggestions();

                // Escuchar eventos personalizados para abrir el chat
                window.addEventListener('openAgentChat', () => {
                    this.isMinimized = false;
                });

                // Escuchar eventos para minimizar
                window.addEventListener('minimizeAgentChat', () => {
                    this.isMinimized = true;
                });
            },

            async checkConfiguration() {
                try {
                    const response = await fetch('/api/v1/agent/config');
                    const data = await response.json();

                    if (data.success) {
                        const wasOffline = this.isOffline;
                        this.isOffline = !data.isOnline;
                        if (wasOffline !== this.isOffline) {
                            this.loadSuggestions();
                        }
                    }
                } catch (error) {
                    console.error('Error checking configuration:', error);
                    // Si falla la verificación, asumir que está offline
                    if (!this.isOffline) {
                        this.isOffline = true;
                        this.loadSuggestions();
                    }
                }
            },

            toggleChat() {
                this.isMinimized = !this.isMinimized;
                if (!this.isMinimized) {
                    this.focusInput();
                }
            },

            minimizeChat() {
                this.isMinimized = true;
            },

            closeChat() {
                this.isMinimized = true;
                // Opcional: limpiar mensajes al cerrar
                // this.messages = [];
                // this.userInput = '';
            },

            focusInput() {
                setTimeout(() => {
                    const textarea = document.querySelector('#agent-floating-btn textarea');
                    if (textarea) {
                        textarea.focus();
                    }
                }, 100);
            },

            async loadSuggestions() {
                try {
                    const offlineParam = this.isOffline ? '?offline=1' : '';
                    const response = await fetch('/api/v1/agent/suggestions' + offlineParam);
                    const data = await response.json();

                    if (data.success) {
                        this.suggestions = data.suggestions;
                        this.renderSuggestions();
                    }
                } catch (error) {
                    console.error('Error loading suggestions:', error);
                }
            },

            renderSuggestions(suggestions = null) {
                const grid = document.getElementById('agent-float-suggestions-grid');
                if (!grid) return;

                const data = suggestions ?? this.suggestions;

                grid.innerHTML = data.map(s => `
                <button class="agent-float-suggestion-chip"
                        data-intent="${s.intent || ''}"
                        onclick="selectFloatSuggestion('${s.intent || ''}', '${s.label || s}')">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <span>${s.label || s}</span>
                </button>
            `).join('');
            },

            async sendMessage() {
                const isGuidedIntent = this.currentIntent && ['create_part', 'create_bom', 'create_supplier', 'create_material'].includes(this.currentIntent);
                if (this.isOffline && !isGuidedIntent) {
                    console.warn('Chat offline - No se puede enviar mensaje');
                    return;
                }

                if (!this.userInput.trim() || this.isLoading) return;

                const message = this.userInput.trim();
                this.userInput = '';
                this.isLoading = true;

                // Agregar mensaje del usuario
                this.addMessage('user', message);

                try {
                    const response = await fetch('/api/v1/agent/message', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            message: message,
                            conversation_id: this.conversationId,
                            intent: this.currentIntent
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.conversationId = data.conversation_id;

                        // Agregar respuesta del asistente
                        this.addMessage('assistant', data.message);

                        // Mostrar sugerencias si hay
                        if (data.suggestions && data.suggestions.length > 0) {
                            this.renderSuggestions(data.suggestions);
                        }
                    }
                } catch (error) {
                    console.error('Error sending message:', error);
                    this.addMessage('system', 'Error al enviar el mensaje. Por favor, intenta de nuevo.');
                } finally {
                    this.isLoading = false;
                }
            },

            addMessage(role, content) {
                this.messages.push({
                    role,
                    content,
                    timestamp: new Date().toLocaleTimeString()
                });

                // Renderizar mensaje
                const messagesContainer = document.getElementById('agent-float-messages');
                if (!messagesContainer) return;

                const messageClass = role === 'user' ? 'user' : (role === 'assistant' ? 'assistant' : 'system');
                const avatar = role === 'assistant' ?
                    `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H3a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V3.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2z"/><path d="M9 10a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1z"/><path d="M9 16h6"/></svg>` :
                    (role === 'user' ? `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>` :
                        `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`);

                messagesContainer.innerHTML += `
                    <div class="agent-float-message agent-float-message-${messageClass}">
                        <div class="agent-float-message-content">
                            <p>${this.escapeHtml(content)}</p>
                        </div>
                        <span class="agent-float-timestamp">${new Date().toLocaleTimeString()}</span>
                    </div>
                `;

                // Scroll al último mensaje
                setTimeout(() => {
                    if (messagesContainer) {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                }, 100);
            },

            formatKey(key) {
                return key
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, l => l.toUpperCase());
            },

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        };
    }

    // Función global para seleccionar sugerencia
    function selectFloatSuggestion(intent, label) {
        const chat = document.getElementById('agent-floating-btn');
        if (chat) {
            // Acceder al componente Alpine.js usando Alpine.$data
            const alpineComponent = Alpine.$data(chat);
            if (alpineComponent) {
                alpineComponent.userInput = label;
                alpineComponent.currentIntent = intent;
                alpineComponent.sendMessage();
            }
        }
    }
</script>
