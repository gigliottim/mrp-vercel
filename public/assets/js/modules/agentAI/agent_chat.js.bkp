/**
 * Agente AI Chat - Componente Alpine.js
 */
function agentChat() {
  return {
    userInput: '',
    messages: [],
    suggestions: [],
    previewData: null,
    previewHtml: '',
    isLoading: false,
    conversationId: null,
    currentIntent: null,
    isOffline: false,
    mode: null, // 'local' o 'api'

    async init() {
      await this.checkConfiguration();
      this.loadSuggestions();
    },

    async checkConfiguration() {
      try {
        const response = await fetch('/api/v1/agent/config');
        const data = await response.json();

        if (data.success) {
          this.isOffline = !data.isOnline;
          this.mode = data.mode;
        }
      } catch (error) {
        console.error('Error checking configuration:', error);
        // Si falla la verificación, asumir que está offline
        this.isOffline = true;
      }
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
      const grid = document.getElementById('suggestions-grid');
      if (!grid) return;

      grid.innerHTML = this.suggestions.map(s => `
                <button class="agent-suggestion-chip"
                        data-intent="${s.intent}"
                        onclick="selectSuggestion('${s.intent}', '${s.label}')">
                    <i class="${s.icon}"></i>
                    <span>${s.label}</span>
                </button>
            `).join('');
    },

    addNewLine(e) {
      // Agregar salto de línea al textarea
      const textarea = e.target;
      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      const text = textarea.value;

      textarea.value = text.substring(0, start) + '\n' + text.substring(end);
      textarea.selectionStart = textarea.selectionEnd = start + 1;

      // Ajustar altura del textarea
      textarea.style.height = 'auto';
      textarea.style.height = textarea.scrollHeight + 'px';
    },

    async sendMessage() {
      if (this.isOffline) {
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

          // Mostrar preview si hay datos
          if (data.status === 'preview' && data.data) {
            this.previewData = data.data;
            this.previewHtml = this.renderPreview(data.data);
            this.currentIntent = this.currentIntent || this.detectIntent(message);
          } else if (data.status === 'clarify') {
            // Limpiar preview si se necesita aclaración
            this.previewData = null;
            this.previewHtml = '';
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
        const messagesContainer = document.getElementById('agent-messages');
        if (messagesContainer) {
          messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
      }, 100);
    },

    renderPreview(data) {
      let html = '<table class="agent-preview-table">';
      for (const [key, value] of Object.entries(data)) {
        if (key !== 'suggestions' && key !== 'status' && key !== 'message') {
          html += `
                        <tr>
                            <th>${this.formatKey(key)}</th>
                            <td>${this.escapeHtml(String(value))}</td>
                        </tr>
                    `;
        }
      }
      html += '</table>';
      return html;
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
    },

    selectSuggestion(intent, label) {
      this.userInput = label;
      this.currentIntent = intent;
      this.sendMessage();
    },

    cancelPreview() {
      this.previewData = null;
      this.previewHtml = '';
      this.currentIntent = null;
    },

    async confirmSave() {
      if (!this.previewData || !this.currentIntent) return;

      this.isLoading = true;

      try {
        const response = await fetch('/api/v1/agent/confirm', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            conversation_id: this.conversationId,
            data: this.previewData,
            intent: this.currentIntent
          })
        });

        const data = await response.json();

        if (data.success) {
          this.addMessage('system', 'Datos guardados correctamente.');
          this.cancelPreview();
          this.conversationId = null;
          this.currentIntent = null;
        }
      } catch (error) {
        console.error('Error saving data:', error);
        this.addMessage('system', 'Error al guardar los datos.');
      } finally {
        this.isLoading = false;
      }
    },

    detectIntent(text) {
      const lowerText = text.toLowerCase();

      if (lowerText.includes('pieza') || lowerText.includes('parte')) {
        return 'create_part';
      }
      if (lowerText.includes('bom') || lowerText.includes('materiales') || lowerText.includes('componentes')) {
        return 'create_bom';
      }
      if (lowerText.includes('proveedor') || lowerText.includes('empresa')) {
        return 'create_supplier';
      }
      if (lowerText.includes('material') || lowerText.includes('materia prima')) {
        return 'create_material';
      }

      return 'general_query';
    },

    getStatusMessage() {
      // Obtener el elemento de estado para agregar la clase
      const statusElement = document.querySelector('.agent-status');
      if (statusElement) {
        // Remover clases anteriores
        statusElement.classList.remove('mode-local', 'mode-api');
        // Agregar clase según el modo
        if (this.mode === 'local') {
          statusElement.classList.add('mode-local');
        } else if (this.mode === 'api') {
          statusElement.classList.add('mode-api');
        }
      }

      if (this.isOffline) {
        return '<i class="bi bi-x-octagon"></i> Fuera de línea';
      }
      if (this.mode === 'local') {
        return '<i class="bi bi-server"></i> Usando Ollama local (sin costo)';
      }
      if (this.mode === 'api') {
        return '<i class="bi bi-cloud"></i> Usando DashScope/OpenRouter (API externa)';
      }
      return '¿En qué puedo ayudarte hoy?';
    }
  };
}

// Función global para seleccionar sugerencia
function selectSuggestion(intent, label) {
  const chat = document.getElementById('agent-chat');
  if (chat) {
    // Acceder al componente Alpine.js usando Alpine.$data
    const alpineComponent = Alpine.$data(chat);
    if (alpineComponent) {
      alpineComponent.selectSuggestion(intent, label);
    }
  }
}
