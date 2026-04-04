<?php

declare(strict_types=1);

namespace App\AgenteAI\Backend\Services;

/**
 * Validador de respuestas del Agente AI
 */
final class AgentResponseValidator
{
    /**
     * Validar la respuesta de la IA según el intent
     *
     * @param array $data Datos a validar
     * @param string $intent Intent de la conversación
     * @return ValidationResult Resultado de la validación
     */
    public function validate(array $data, string $intent): ValidationResult
    {
        $errors = [];
        $validationConfig = config('agent_ai')['validation'];
        $requiredFields = $validationConfig['required_fields'][$intent] ?? [];

        // Verificar campos requeridos
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                $errors[] = "El campo '{$field}' es requerido";
            }
        }

        // Validar formatos específicos
        if (isset($data['code']) && !$this->validateRegex($data['code'], $validationConfig['code'] ?? '')) {
            $errors[] = "El código debe tener entre 1 y 20 caracteres alfanuméricos";
        }

        if (isset($data['uom']) && !$this->validateRegex($data['uom'], $validationConfig['uom'] ?? '')) {
            $errors[] = "La unidad de medida debe ser una de: u, kg, m, l, g";
        }

        if (isset($data['cuit']) && !$this->validateRegex($data['cuit'], $validationConfig['cuit'] ?? '')) {
            $errors[] = "El CUIT debe tener el formato XX-XXXXXXXX-X";
        }

        if (isset($data['email']) && !$this->validateRegex($data['email'], $validationConfig['email'] ?? '')) {
            $errors[] = "El email debe tener un formato válido";
        }

        if (isset($data['min_stock']) && !$this->validateRegex($data['min_stock'], $validationConfig['positive_number'] ?? '')) {
            $errors[] = "El stock mínimo debe ser un número positivo";
        }

        // Validar components si existe
        if (isset($data['components']) && is_array($data['components'])) {
            foreach ($data['components'] as $index => $component) {
                if (isset($component['code']) && !$this->validateRegex($component['code'], $validationConfig['code'] ?? '')) {
                    $errors[] = "El código del componente {$index} debe tener entre 1 y 20 caracteres alfanuméricos";
                }
                if (isset($component['quantity']) && !is_numeric($component['quantity'])) {
                    $errors[] = "La cantidad del componente {$index} debe ser un número";
                }
                if (isset($component['uom']) && !$this->validateRegex($component['uom'], $validationConfig['uom'] ?? '')) {
                    $errors[] = "La unidad de medida del componente {$index} debe ser una de: u, kg, m, l, g";
                }
            }
        }

        return new ValidationResult(
            empty($errors),
            $errors,
            $this->normalizeData($data, $intent)
        );
    }

    /**
     * Validar un valor contra una expresión regular
     */
    private function validateRegex(string $value, string $pattern): bool
    {
        if (empty($pattern)) {
            return true;
        }
        return (bool)preg_match($pattern, $value);
    }

    /**
     * Normalizar los datos según el intent
     */
    private function normalizeData(array $data, string $intent): array
    {
        // Normalizar valores comunes
        if (isset($data['code'])) {
            $data['code'] = trim($data['code']);
        }
        if (isset($data['description'])) {
            $data['description'] = trim($data['description']);
        }
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        // Normalizar components si existe
        if (isset($data['components']) && is_array($data['components'])) {
            foreach ($data['components'] as &$component) {
                if (isset($component['code'])) {
                    $component['code'] = trim($component['code']);
                }
                if (isset($component['quantity'])) {
                    $component['quantity'] = (float)$component['quantity'];
                }
            }
        }

        return $data;
    }
}
