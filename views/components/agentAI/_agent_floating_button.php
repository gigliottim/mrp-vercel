<?php

use App\Core\View\View;

?>
<!-- Botón Flotante del Agente AI (Estilo WhatsApp) -->
<div id="agent-floating-btn" class="agent-floating-btn" x-data="agentFloating()" x-init="init()" x-bind:class="{'minimized': isMinimized, 'maximized': isMaximized && !isMinimized}">
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
                    <strong>Luchi</strong>
                    <span class="agent-status" x-show="!isOffline">En línea</span>
                    <span class="agent-status offline" x-show="isOffline">Fuera de línea</span>
                </div>
            </div>
            <div class="agent-float-header-actions">
                <button class="agent-float-btn" @click="maximizeChat" :aria-label="isMaximized ? 'Restaurar' : 'Maximizar'" :title="isMaximized ? 'Restaurar' : 'Maximizar'">
                    <svg class="icon" x-show="!isMaximized" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 3 21 3 21 9" />
                        <polyline points="9 21 3 21 3 15" />
                        <line x1="21" y1="3" x2="14" y2="10" />
                        <line x1="3" y1="21" x2="10" y2="14" />
                    </svg>
                    <svg class="icon" x-show="isMaximized" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="4 14 10 14 10 20" />
                        <polyline points="20 10 14 10 14 4" />
                        <line x1="14" y1="10" x2="21" y2="3" />
                        <line x1="3" y1="21" x2="10" y2="14" />
                    </svg>
                </button>
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
                    <p>¡Hola! Soy Luchi, tu asistente. Puedo ayudarte a crear partes, BOMs, proveedores y más.</p>
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
                @keydown.enter.prevent="if(!$event.shiftKey) sendMessage()"
                placeholder="Escribe tu mensaje..."
                :disabled="isLoading"></textarea>
            <button class="agent-float-send-btn" @click="sendMessage" :disabled="isLoading || (!userInput.trim() && !guidedState)" title="Enviar">
                <svg class="icon" x-show="!isLoading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
                <span x-show="isLoading">...</span>
            </button>
            <button class="agent-float-cancel-btn" @click="cancelConversation" title="Cancelar operación" x-show="guidedState !== null">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <!-- Mensaje de estado offline -->
        <div class="agent-float-offline-message" x-show="isOffline">
            <p>El agente está fuera de línea. Las opciones rápidas siguen disponibles.</p>
        </div>
    </div>
</div>

<!-- CSS y JS externalizados del botón flotante -->
<link rel="stylesheet" href="/agenteAI/frontend/css/agent_floating.css?v=51.0.0">
<script src="/agenteAI/frontend/js/agent_floating.js?v=54.0.0" defer></script>
