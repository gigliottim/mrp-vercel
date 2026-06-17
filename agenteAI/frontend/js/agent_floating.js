/**
 * Luchi - Agente AI (Botón Flotante)
 * Componente Alpine.js independiente del chat de página completa.
 */
function agentFloating() {
    return {
        isMinimized: true,
        isMaximized: false,
        userInput: '',
        fieldValue: null,
        fieldLabel: null,
        messages: [],
        suggestions: [],
        isLoading: false,
        isOffline: false,
        conversationId: null,
        currentIntent: null,
        guidedState: null,
        lookupCache: {},
        umTypeChips: null,
        STORAGE_KEY: 'luchi_chat_state',

        async init() {
            await this.checkConfiguration();
            this.restoreState();

            window.addEventListener('openAgentChat', () => {
                this.isMinimized = false;
            });

            window.addEventListener('minimizeAgentChat', () => {
                this.isMinimized = true;
            });
        },

        saveState() {
            try {
                const state = {
                    messages: this.messages,
                    conversationId: this.conversationId,
                    currentIntent: this.currentIntent,
                    guidedState: this.guidedState,
                    isMinimized: this.isMinimized,
                    isMaximized: this.isMaximized,
                };
                localStorage.setItem(this.STORAGE_KEY, JSON.stringify(state));
            } catch (e) {}
        },

        restoreState() {
            try {
                const raw = localStorage.getItem(this.STORAGE_KEY);
                if (!raw) {
                    this.loadSuggestions();
                    return;
                }
                const state = JSON.parse(raw);
                this.messages = state.messages || [];
                this.conversationId = state.conversationId || null;
                this.currentIntent = state.currentIntent || null;
                this.guidedState = state.guidedState || null;
                this.isMaximized = state.isMaximized || false;

                if (this.messages.length > 0 || this.conversationId) {
                    this.rebuildMessagesDOM();
                    if (this.guidedState) {
                        this.restoreGuidedSuggestions();
                    } else {
                        this.loadSuggestions();
                    }
                } else {
                    this.loadSuggestions();
                }
            } catch (e) {
                this.loadSuggestions();
            }
        },

        rebuildMessagesDOM() {
            const container = document.getElementById('agent-float-messages');
            if (!container) return;
            container.innerHTML = '';
            for (const msg of this.messages) {
                const cls = msg.role === 'user' ? 'user' : (msg.role === 'assistant' ? 'assistant' : 'system');
                container.innerHTML += `
                    <div class="agent-float-message agent-float-message-${cls}">
                        <div class="agent-float-message-content">
                            <p>${this.escapeHtml(msg.content)}</p>
                        </div>
                        <span class="agent-float-timestamp">${msg.timestamp || ''}</span>
                    </div>`;
            }
            setTimeout(() => { container.scrollTop = container.scrollHeight; }, 100);
        },

        async restoreGuidedSuggestions() {
            if (!this.guidedState || !this.guidedState.intent) return;
            try {
                const response = await fetch('/api/v1/agent/message', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        message: 'continuar',
                        conversation_id: this.conversationId,
                        intent: this.guidedState.intent,
                        guided_state: this.guidedState
                    })
                });
                const data = await response.json();
                if (data.success) {
                    this.guidedState = data.guided_state || null;
                    if (data.suggestions && data.suggestions.length > 0) {
                        const hasLookup = data.suggestions.some(s => s.lookup);
                        if (hasLookup) {
                            this.renderSuggestionsWithLookup(data.suggestions, document.getElementById('agent-float-suggestions-grid'));
                        } else {
                            this.renderSuggestions(data.suggestions);
                        }
                    }
                }
            } catch (e) {}
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
            } else {
                this.isMaximized = false;
            }
            this.saveState();
        },

        minimizeChat() {
            this.isMinimized = true;
            this.saveState();
        },

        closeChat() {
            this.isMinimized = true;
            this.isMaximized = false;
            this.saveState();
        },

        maximizeChat() {
            this.isMaximized = !this.isMaximized;
            this.saveState();
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
                    const allSuggestions = data.suggestions || [];
                    this.suggestions = this.isOffline
                        ? allSuggestions.filter(s => s.offline_safe)
                        : allSuggestions;
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
                                onclick="selectFloatFieldOption('${this.escapeHtml(String(s.value))}', '${this.escapeHtml(s.label)}')">
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
                if (s.lookup === 'unidades_medida_all') {
                    chips = await this.renderUmTypeChips(grid);
                    break;
                } else if (s.lookup) {
                    const items = await this.fetchLookup(s.lookup);
                    chips = items.map(item => `
                        <button class="agent-float-suggestion-chip"
                                data-value="${this.escapeHtml(String(item.value))}"
                                onclick="selectFloatFieldOption('${this.escapeHtml(String(item.value))}', '${this.escapeHtml(item.label)}')">
                            <span>${this.escapeHtml(item.label)}</span>
                        </button>
                    `);
                    break;
                } else if (s.value !== undefined && s.label !== undefined) {
                    chips.push(`
                        <button class="agent-float-suggestion-chip"
                                data-value="${this.escapeHtml(String(s.value))}"
                                onclick="selectFloatFieldOption('${this.escapeHtml(String(s.value))}', '${this.escapeHtml(s.label)}')">
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

        async renderUmTypeChips(grid) {
            const types = await this.fetchLookup('unidades_medida_tipos');
            if (!types || types.length === 0) {
                return ['<span class="text-muted small">No hay unidades disponibles</span>'];
            }
            return types.map(t => `
                <button class="agent-float-suggestion-chip agent-float-um-type-chip"
                        data-lookup="${this.escapeHtml(t.lookup)}"
                        onclick="selectUmType('${this.escapeHtml(t.lookup)}', '${this.escapeHtml(t.label)}')">
                    <span>${this.escapeHtml(t.label)}</span>
                </button>
            `);
        },

        async selectUmType(lookupType, label) {
            const grid = document.getElementById('agent-float-suggestions-grid');
            if (!grid) return;

            grid.innerHTML = `<span class="text-muted small">Cargando ${label}...</span>`;

            const items = await this.fetchLookup(lookupType);
            const backBtn = `
                <button class="agent-float-suggestion-chip agent-float-um-back-chip"
                        onclick="selectUmTypeBack()">
                    <span>&#8592; Tipos</span>
                </button>
            `;
            const itemChips = items.map(item => `
                <button class="agent-float-suggestion-chip"
                        data-value="${this.escapeHtml(String(item.value))}"
                        onclick="selectFloatFieldOption('${this.escapeHtml(String(item.value))}', '${this.escapeHtml(item.label)}')">
                    <span>${this.escapeHtml(item.label)}</span>
                </button>
            `);

            grid.innerHTML = backBtn + itemChips.join('');
        },

        async sendMessage() {
            const isGuidedIntent = this.currentIntent && ['create_part', 'create_bom', 'create_supplier'].includes(this.currentIntent);
            if ((this.isOffline && !isGuidedIntent && !this.guidedState) || this.isLoading) return;
            if (!this.userInput.trim() && !this.guidedState) return;

            const message = this.userInput.trim() || '(omitir)';
            const displayMessage = this.fieldLabel || message;
            this.userInput = '';
            this.isLoading = true;

            this.addMessage('user', displayMessage);

            try {
                const payload = {
                    message: message,
                    conversation_id: this.conversationId,
                    intent: this.currentIntent,
                    guided_state: this.guidedState
                };
                if (this.fieldValue !== null) {
                    payload.field_value = this.fieldValue;
                }
                if (this.fieldLabel !== null) {
                    payload.field_label = this.fieldLabel;
                }
                this.fieldValue = null;
                this.fieldLabel = null;

                const response = await fetch('/api/v1/agent/message', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
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
                    this.saveState();
                }
            } catch (error) {
                console.error('Error sending message:', error);
                this.addMessage('system', 'Error al enviar el mensaje. Por favor, intenta de nuevo.');
            } finally {
                this.isLoading = false;
                this.saveState();
                this.focusInput();
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
            this.umTypeChips = null;
            this.loadSuggestions();
            this.saveState();

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
            const timestamp = new Date().toLocaleTimeString();
            this.messages.push({ role, content, timestamp });

            const messagesContainer = document.getElementById('agent-float-messages');
            if (!messagesContainer) return;

            const messageClass = role === 'user' ? 'user' : (role === 'assistant' ? 'assistant' : 'system');

            messagesContainer.innerHTML += `
                <div class="agent-float-message agent-float-message-${messageClass}">
                    <div class="agent-float-message-content">
                        <p>${this.escapeHtml(content)}</p>
                    </div>
                    <span class="agent-float-timestamp">${timestamp}</span>
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

function selectFloatFieldOption(value, label) {
    const chat = document.getElementById('agent-floating-btn');
    if (chat && typeof Alpine !== 'undefined') {
        const alpineComponent = Alpine.$data(chat);
        if (alpineComponent) {
            alpineComponent.fieldValue = String(value);
            alpineComponent.fieldLabel = label || String(value);
            alpineComponent.userInput = String(value);
            alpineComponent.sendMessage();
        }
    }
}

function selectUmType(lookupType, label) {
    const chat = document.getElementById('agent-floating-btn');
    if (chat && typeof Alpine !== 'undefined') {
        const alpineComponent = Alpine.$data(chat);
        if (alpineComponent) {
            alpineComponent.selectUmType(lookupType, label);
        }
    }
}

function selectUmTypeBack() {
    const chat = document.getElementById('agent-floating-btn');
    if (chat && typeof Alpine !== 'undefined') {
        const alpineComponent = Alpine.$data(chat);
        if (alpineComponent) {
            const grid = document.getElementById('agent-float-suggestions-grid');
            if (grid) {
                alpineComponent.renderSuggestionsWithLookup([{lookup: 'unidades_medida_all'}], grid);
            }
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