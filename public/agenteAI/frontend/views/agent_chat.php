<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agente AI - MRP</title>
    <link rel="stylesheet" href="/agenteAI/frontend/css/agent_chat.css">
    <link rel="stylesheet" href="/assets/css/variables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css">
</head>

<body>
    <div id="agent-chat" class="agent-chat-container" x-data="agentChat()" x-init="init()" x-bind:class="isLoading ? 'loading' : ''" x-bind:class="isOffline ? 'offline' : ''">
        <!-- Header -->
        <div class="agent-chat-header">
            <h2><i class="bi bi-robot"></i> Agente AI</h2>
            <p class="agent-status" x-show="!isOffline" x-html="getStatusMessage()"></p>
            <p class="agent-status offline-status" x-show="isOffline"><i class="bi bi-x-octagon"></i> Fuera de línea</p>
        </div>

        <!-- Messages History -->
        <div class="agent-messages" id="agent-messages">
            <div class="agent-message agent-message-system">
                <div class="agent-message-content">
                    <p>¡Hola! Soy tu asistente de IA. Puedo ayudarte a crear piezas, BOMs, proveedores y más.</p>
                </div>
                <span class="agent-timestamp">Ahora</span>
            </div>
        </div>

        <!-- Suggestions -->
        <div class="agent-suggestions" id="agent-suggestions">
            <div class="agent-suggestions-title">Opciones rápidas:</div>
            <div class="agent-suggestions-grid" id="suggestions-grid">
                <!-- Suggestions will be rendered here -->
            </div>
        </div>

        <!-- Preview Area -->
        <div class="agent-preview" id="agent-preview" x-show="previewData">
            <h3>Resumen de datos</h3>
            <div class="agent-preview-content" x-html="previewHtml"></div>
            <div class="agent-preview-actions">
                <button class="btn btn-secondary" @click="cancelPreview()">Cancelar / Corregir</button>
                <button class="btn btn-primary" @click="confirmSave()">Confirmar y Guardar</button>
            </div>
        </div>

        <!-- Input Area -->
        <div class="agent-input-area" x-show="!isOffline">
            <textarea
                x-model="userInput"
                @keydown.enter.exact.prevent="sendMessage"
                @keydown.shift.enter.prevent="addNewLine"
                placeholder="Escribe tu mensaje..."
                :disabled="isLoading"></textarea>
            <button class="btn btn-primary" @click="sendMessage" :disabled="isLoading || !userInput.trim()">
                <i class="bi bi-send-fill"></i>
            </button>
        </div>

        <!-- Offline Status Message -->
        <div class="agent-offline-message" x-show="isOffline">
            <div class="offline-icon"><i class="bi bi-wifi-off"></i></div>
            <p>No es posible enviar mensajes. Verifica la configuración de la API o el modelo local.</p>
        </div>
    </div>

    <script src="/agenteAI/frontend/js/agent_chat.js" defer></script>
</body>

</html>
