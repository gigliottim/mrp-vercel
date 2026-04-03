<?php

declare(strict_types=1);

namespace App\Services\AgentAI;

/**
 * DTO para el resultado de la validación
 */
final class ValidationResult
{
    public bool $isValid;
    public array $errors;
    public array $data;

    public function __construct(bool $isValid, array $errors, array $data)
    {
        $this->isValid = $isValid;
        $this->errors = $errors;
        $this->data = $data;
    }

    /**
     * Verificar si la validación fue exitosa
     */
    public function success(): bool
    {
        return $this->isValid;
    }

    /**
     * Verificar si la validación falló
     */
    public function failed(): bool
    {
        return !$this->isValid;
    }
}
