<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agente AI - MRP</title>
    <link rel="stylesheet" href="/assets/css/modules/agentAI/agent_chat.css">
    <link rel="stylesheet" href="/assets/css/variables.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css">
</head>

<body>
    <div id="agent-chat" class="agent-chat-container" x-data="agentChat()" x-init="init()" x-bind:class="isLoading ? 'loading' : ''">
        <!-- Header -->
        <div class="agent-chat-header">
            <h2><i class="bi bi-robot"></i> Agente AI</h2>
            <p class="agent-status">¿En qué puedo ayudarte hoy?</p>
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
        <div class="agent-input-area">
            <textarea
                x-model="userInput"
                @keydown.enter.exact.prevent="sendMessage"
                placeholder="Escribe tu mensaje..."
                :disabled="isLoading"></textarea>
            <button class="btn btn-primary" @click="sendMessage" :disabled="isLoading || !userInput.trim()">
                <i class="bi bi-send" x-show="!isLoading"></i>
                <span x-show="isLoading">Procesando...</span>
            </button>
        </div>
    </div>

    <script src="/assets/js/modules/agentAI/agent_chat.js" defer></script>
</body>

</html>
