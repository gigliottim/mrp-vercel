/**
 * Luchi - Agente AI (Botón Flotante)
 * Componente Alpine.js independiente del chat de página completa.
 */
function agentFloating() {
    return {
        isMinimized: true,
        userInput: '',
        messages: [],
        suggestions: [],
        isLoading: false,
        isOffline: false,
        conversationId: null,
        currentIntent: null,
        guidedState: null,
        lookupCache: {},

        init() {
            this.checkConfiguration();
            this.loadSuggestions();

            window.addEventListener('openAgentChat', () => {
                this.isMinimized = false;
            });

            window.addEventListener('minimizeAgentChat', () => {
                this.isMinimized = true;
            });
        },

        async checkConfiguration() {
            try {
                const response = await fetch('/api/v1/agent/config');
                const data = await response.json();
                if (data.success) {
                    this.isOffline = !data.isOnline;
                }
            } catch (error) {
                console.error('Error checking configuration:', error);
                this.isOffline = true;
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

        async fetchLookup(type) {
            if (this.lookupCache[type]) {
                return this.lookupCache[type];
            }
            try {
                const response = await fetch(`/api/v1/agent/lookup/${type}`);
                const data = await response.json();
                if (data.success && data.data) {
                    this.lookupCache[type] = data.data;
                    return data.data;
                }
            } catch (error) {
                console.error('Error fetching lookup:', error);
            }
            return [];
        },

        renderSuggestions(suggestions = null) {
            const grid = document.getElementById('agent-float-suggestions-grid');
            if (!grid) return;

            const data = suggestions ?? this.suggestions;
            if (!data || data.length === 0) {
                grid.innerHTML = '';
                return;
            }

            const hasLookup = data.some(s => s.lookup);
            if (hasLookup) {
                this.renderSuggestionsWithLookup(data, grid);
                return;
            }

            grid.innerHTML = data.map(s => {
                if (s.value !== undefined && s.label !== undefined) {
                    return `
                        <button class="agent-float-suggestion-chip"
                                data-value="${this.escapeHtml(String(s.value))}"
                                onclick="selectFloatFieldOption('${this.escapeHtml(String(s.value))}')">
                            <span>${this.escapeHtml(s.label)}</span>
                        </button>
                    `;
                }

                const icon = s.icon ? `<i class="${s.icon}"></i>` : `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>`;
                return `
                    <button class="agent-float-suggestion-chip"
                            data-intent="${s.intent || ''}"
                            onclick="selectFloatSuggestion('${s.intent || ''}', '${this.escapeHtml(s.label || s)}')">
                        ${icon}
                        <span>${this.escapeHtml(s.label || s)}</span>
                    </button>
                `;
            }).join('');
        },

        async renderSuggestionsWithLookup(suggestions, grid) {
            grid.innerHTML = '<span class="text-muted small">Cargando opciones...</span>';

            let chips = [];
            for (const s of suggestions) {
                if (s.lookup) {
                    const items = await this.fetchLookup(s.lookup);
                    chips = items.map(item => `
                        <button class="agent-float-suggestion-chip"
                                data-value="${this.escapeHtml(String(item.value))}"
                                onclick="selectFloatFieldOption('${this.escapeHtml(String(item.value))}')">
                            <span>${this.escapeHtml(item.label)}</span>
                        </button>
                    `);
                    break;
                } else if (s.value !== undefined && s.label !== undefined) {
                    chips.push(`
                        <button class="agent-float-suggestion-chip"
                                data-value="${this.escapeHtml(String(s.value))}"
                                onclick="selectFloatFieldOption('${this.escapeHtml(String(s.value))}')">
                            <span>${this.escapeHtml(s.label)}</span>
                        </button>
                    `);
                } else {
                    const icon = s.icon ? `<i class="${s.icon}"></i>` : `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>`;
                    chips.push(`
                        <button class="agent-float-suggestion-chip"
                                data-intent="${s.intent || ''}"
                                onclick="selectFloatSuggestion('${s.intent || ''}', '${this.escapeHtml(s.label || s)}')">
                            ${icon}
                            <span>${this.escapeHtml(s.label || s)}</span>
                        </button>
                    `);
                }
            }

            grid.innerHTML = chips.join('');
        },

        async sendMessage() {
            if (this.isOffline || !this.userInput.trim() || this.isLoading) return;

            const message = this.userInput.trim();
            this.userInput = '';
            this.isLoading = true;

            this.addMessage('user', message);

            try {
                const response = await fetch('/api/v1/agent/message', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        message: message,
                        conversation_id: this.conversationId,
                        intent: this.currentIntent,
                        guided_state: this.guidedState
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.conversationId = data.conversation_id;
                    this.guidedState = data.guided_state || null;
                    this.addMessage('assistant', data.message);

                    if (data.suggestions && data.suggestions.length > 0) {
                        const hasLookup = data.suggestions.some(s => s.lookup);
                        if (hasLookup) {
                            this.renderSuggestionsWithLookup(data.suggestions, document.getElementById('agent-float-suggestions-grid'));
                        } else {
                            this.renderSuggestions(data.suggestions);
                        }
                    } else {
                        this.renderSuggestions([]);
                    }
                }
            } catch (error) {
                console.error('Error sending message:', error);
                this.addMessage('system', 'Error al enviar el mensaje. Por favor, intenta de nuevo.');
            } finally {
                this.isLoading = false;
            }
        },

        async cancelConversation() {
            if (!this.conversationId) {
                this.resetChat();
                return;
            }

            try {
                await fetch('/api/v1/agent/cancel', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ conversation_id: this.conversationId })
                });
            } catch (error) {
                console.error('Error cancelling conversation:', error);
            }

            this.resetChat();
        },

        resetChat() {
            this.conversationId = null;
            this.currentIntent = null;
            this.guidedState = null;
            this.messages = [];
            this.loadSuggestions();

            const messagesContainer = document.getElementById('agent-float-messages');
            if (messagesContainer) {
                messagesContainer.innerHTML = `
                    <div class="agent-float-message agent-float-message-system">
                        <div class="agent-float-message-content">
                            <p>Operación cancelada. ¿En qué puedo ayudarte?</p>
                        </div>
                        <span class="agent-float-timestamp">${new Date().toLocaleTimeString()}</span>
                    </div>
                `;
            }
        },

        addMessage(role, content) {
            this.messages.push({
                role,
                content,
                timestamp: new Date().toLocaleTimeString()
            });

            const messagesContainer = document.getElementById('agent-float-messages');
            if (!messagesContainer) return;

            const messageClass = role === 'user' ? 'user' : (role === 'assistant' ? 'assistant' : 'system');

            messagesContainer.innerHTML += `
                <div class="agent-float-message agent-float-message-${messageClass}">
                    <div class="agent-float-message-content">
                        <p>${this.escapeHtml(content)}</p>
                    </div>
                    <span class="agent-float-timestamp">${new Date().toLocaleTimeString()}</span>
                </div>
            `;

            setTimeout(() => {
                if (messagesContainer) {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            }, 100);
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
}

function selectFloatSuggestion(intent, label) {
    const chat = document.getElementById('agent-floating-btn');
    if (chat && typeof Alpine !== 'undefined') {
        const alpineComponent = Alpine.$data(chat);
        if (alpineComponent) {
            alpineComponent.userInput = label;
            alpineComponent.currentIntent = intent;
            alpineComponent.sendMessage();
        }
    }
}

function selectFloatFieldOption(value) {
    const chat = document.getElementById('agent-floating-btn');
    if (chat && typeof Alpine !== 'undefined') {
        const alpineComponent = Alpine.$data(chat);
        if (alpineComponent) {
            alpineComponent.userInput = value;
            alpineComponent.sendMessage();
        }
    }
}

function cancelFloatConversation() {
    const chat = document.getElementById('agent-floating-btn');
    if (chat && typeof Alpine !== 'undefined') {
        const alpineComponent = Alpine.$data(chat);
        if (alpineComponent) {
            alpineComponent.cancelConversation();
        }
    }
}