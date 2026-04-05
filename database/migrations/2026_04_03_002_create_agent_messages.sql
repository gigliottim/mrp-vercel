-- Migración: agent_messages
-- Tabla para almacenar el historial de mensajes de conversación

CREATE TABLE IF NOT EXISTS agent_messages (
    id SERIAL PRIMARY KEY,
    conversation_id INTEGER NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('user', 'assistant', 'system')),
    content TEXT NOT NULL,
    metadata JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_agent_messages_conversation
        FOREIGN KEY (conversation_id) REFERENCES agent_conversations(id) ON DELETE CASCADE
);

CREATE INDEX idx_agent_messages_conversation ON agent_messages(conversation_id);
CREATE INDEX idx_agent_messages_role ON agent_messages(role);
CREATE INDEX idx_agent_messages_created_at ON agent_messages(created_at);

-- Índice compuesto para consultas por conversación y rol
CREATE INDEX idx_agent_messages_conv_role ON agent_messages(conversation_id, role);
