-- Migracion: agent_ai_logs
-- Tabla para auditoria de llamadas a la API de IA

CREATE TABLE IF NOT EXISTS agent_ai_logs (
    id SERIAL PRIMARY KEY,
    conversation_id VARCHAR(100),
    model_used VARCHAR(100),
    provider VARCHAR(50),
    prompt_hash VARCHAR(64),
    response_time_ms INTEGER,
    validation_result VARCHAR(50) DEFAULT 'unknown',
    tokens_used INTEGER,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_agent_ai_logs_conversation ON agent_ai_logs(conversation_id);
CREATE INDEX IF NOT EXISTS idx_agent_ai_logs_created_at ON agent_ai_logs(created_at);