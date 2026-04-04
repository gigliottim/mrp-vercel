-- Migración: agent_ai_logs
-- Tabla para auditoría de llamadas a la API de IA

CREATE TABLE IF NOT EXISTS agent_ai_logs (
    id SERIAL PRIMARY KEY,
    conversation_id INTEGER NOT NULL,
    message_id INTEGER,
    model_used VARCHAR(100) NOT NULL,
    provider VARCHAR(20) NOT NULL CHECK (provider IN ('local', 'api')),
    prompt_hash VARCHAR(64) NOT NULL,
    response_time_ms INTEGER NOT NULL,
    validation_result VARCHAR(20) NOT NULL CHECK (validation_result IN ('valid', 'invalid', 'retry')),
    validation_errors TEXT,
    tokens_used INTEGER,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_agent_ai_logs_conversation
        FOREIGN KEY (conversation_id) REFERENCES agent_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_agent_ai_logs_message
        FOREIGN KEY (message_id) REFERENCES agent_messages(id) ON DELETE SET NULL
);

CREATE INDEX idx_agent_ai_logs_conversation ON agent_ai_logs(conversation_id);
CREATE INDEX idx_agent_ai_logs_message ON agent_ai_logs(message_id);
CREATE INDEX idx_agent_ai_logs_provider ON agent_ai_logs(provider);
CREATE INDEX idx_agent_ai_logs_created_at ON agent_ai_logs(created_at);
CREATE INDEX idx_agent_ai_logs_prompt_hash ON agent_ai_logs(prompt_hash);
