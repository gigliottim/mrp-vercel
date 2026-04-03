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
        <i class="bi bi-robot" x-show="!isMinimized"></i>
        <i class="bi bi-chat-dots" x-show="isMinimized"></i>
    </button>

    <!-- Contenedor del chat flotante -->
    <div class="agent-float-container" x-show="!isMinimized" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 transform scale-100" x-transition:leave-end="opacity-0 transform scale-95">
        <!-- Header del chat flotante -->
        <div class="agent-float-header">
            <div class="agent-float-header-info">
                <i class="bi bi-robot"></i>
                <div>
                    <strong>Agente AI</strong>
                    <span class="agent-status online">En línea</span>
                </div>
            </div>
            <div class="agent-float-header-actions">
                <button class="agent-float-btn" @click="minimizeChat" aria-label="Minimizar">
                    <i class="bi bi-dash-lg"></i>
                </button>
                <button class="agent-float-btn" @click="closeChat" aria-label="Cerrar">
                    <i class="bi bi-x-lg"></i>
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
        <div class="agent-float-input-area">
            <textarea
                x-model="userInput"
                @keydown.enter.exact.prevent="sendMessage"
                placeholder="Escribe tu mensaje..."
                :disabled="isLoading"></textarea>
            <button class="agent-float-btn" @click="sendMessage" :disabled="isLoading || !userInput.trim()">
                <i class="bi bi-send" x-show="!isLoading"></i>
                <span x-show="isLoading">...</span>
            </button>
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

    .agent-float-header-info i {
        font-size: 24px;
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

    .agent-float-suggestion-chip i {
        font-size: 12px;
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
            conversationId: null,
            currentIntent: null,

            init() {
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
                    const response = await fetch('/api/v1/agent/suggestions');
                    const data = await response.json();

                    if (data.success) {
                        this.suggestions = data.suggestions;
                        this.renderSuggestions();
                    }
                } catch (error) {
                    console.error('Error loading suggestions:', error);
                }
            },

            renderSuggestions() {
                const grid = document.getElementById('agent-float-suggestions-grid');
                if (!grid) return;

                grid.innerHTML = this.suggestions.map(s => `
                <button class="agent-float-suggestion-chip"
                        data-intent="${s.intent}"
                        onclick="selectFloatSuggestion('${s.intent}', '${s.label}')">
                    <i class="${s.icon}"></i>
                    <span>${s.label}</span>
                </button>
            `).join('');
            },

            async sendMessage() {
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
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
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

                // Scroll al último mensaje
                setTimeout(() => {
                    const messagesContainer = document.getElementById('agent-float-messages');
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
            chat.agentFloating().userInput = label;
            chat.agentFloating().currentIntent = intent;
            chat.agentFloating().sendMessage();
        }
    }
</script>
