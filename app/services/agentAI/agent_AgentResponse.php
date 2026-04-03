<?php

declare(strict_types=1);

namespace App\Services\AgentAI;

/**
 * DTO de Respuesta del Agente AI
 */
final class AgentResponse
{
    public string $status; // preview, clarify, error, saved
    public string $message;
    public ?array $data;
    public array $suggestions;
    public string $conversationId;

    public function __construct(
        string $status,
        string $message,
        ?array $data,
        array $suggestions,
        string $conversationId
    ) {
        $this->status = $status;
        $this->message = $message;
        $this->data = $data;
        $this->suggestions = $suggestions;
        $this->conversationId = $conversationId;
    }

    /**
     * Verificar si la respuesta es para aclarar
     */
    public function needsClarification(): bool
    {
        return $this->status === 'clarify';
    }

    /**
     * Verificar si la respuesta es para preview
     */
    public function isPreview(): bool
    {
        return $this->status === 'preview';
    }

    /**
     * Verificar si la respuesta es un error
     */
    public function isError(): bool
    {
        return $this->status === 'error';
    }

    /**
     * Verificar si los datos fueron guardados
     */
    public function isSaved(): bool
    {
        return $this->status === 'saved';
    }
}
