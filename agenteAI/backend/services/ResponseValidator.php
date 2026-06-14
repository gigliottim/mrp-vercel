<?php

declare(strict_types=1);

namespace App\AgenteAI\Backend\Services;

/**
 * Validador de respuestas del Agente AI
 */
final class ResponseValidator
{
    private const REQUIRED_FIELDS = [
        'create_part' => ['codigo', 'id_tipo', 'id_grupo'],
        'create_bom' => ['parent_part', 'components'],
        'create_supplier' => ['razon_social'],
        'create_material' => ['codigo', 'id_um_compra'],
    ];

    public function validate(array $data, string $intent): ValidationResult
    {
        $errors = [];
        $requiredFields = self::REQUIRED_FIELDS[$intent] ?? [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $errors[] = "El campo '{$field}' es requerido";
            }
        }

        if (isset($data['code']) && $data['code'] !== '') {
            if (!preg_match('/^[A-Za-z0-9\-]{1,50}$/', $data['code'])) {
                $errors[] = "El código debe tener entre 1 y 50 caracteres alfanuméricos";
            }
        }

        if (isset($data['identificacion_tributaria']) && $data['identificacion_tributaria'] !== '') {
            if (!preg_match('/^[0-9]{2}-?[0-9]{8}-?[0-9]{1}$/', $data['identificacion_tributaria'])) {
                $errors[] = "El CUIT debe tener el formato XX-XXXXXXXX-X";
            }
        }

        if (isset($data['contacto_email']) && $data['contacto_email'] !== '') {
            if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $data['contacto_email'])) {
                $errors[] = "El email debe tener un formato válido";
            }
        }

        if (isset($data['stock_seguridad']) && $data['stock_seguridad'] !== '' && $data['stock_seguridad'] !== null) {
            if (!is_numeric($data['stock_seguridad']) || (float) $data['stock_seguridad'] < 0) {
                $errors[] = "El stock de seguridad debe ser un número positivo";
            }
        }

        if (isset($data['punto_pedido']) && $data['punto_pedido'] !== '' && $data['punto_pedido'] !== null) {
            if (!is_numeric($data['punto_pedido']) || (float) $data['punto_pedido'] < 0) {
                $errors[] = "El punto de pedido debe ser un número positivo";
            }
        }

        if (isset($data['components']) && is_array($data['components'])) {
            foreach ($data['components'] as $index => $component) {
                if (!isset($component['code']) || $component['code'] === '') {
                    $errors[] = "El componente " . ($index + 1) . " debe tener un código";
                }
                if (!isset($component['quantity']) || !is_numeric($component['quantity'])) {
                    $errors[] = "El componente " . ($index + 1) . " debe tener una cantidad válida";
                }
            }
        }

        return new ValidationResult(
            empty($errors),
            $errors,
            $this->normalizeData($data, $intent)
        );
    }

    private function normalizeData(array $data, string $intent): array
    {
        if (isset($data['code']) && !isset($data['codigo'])) {
            $data['codigo'] = strtoupper(trim($data['code']));
        }
        if (isset($data['codigo'])) {
            $data['codigo'] = strtoupper(trim($data['codigo']));
        }
        if (isset($data['description']) && !isset($data['detalle'])) {
            $data['detalle'] = trim($data['description']);
        }
        if (isset($data['detalle'])) {
            $data['detalle'] = trim($data['detalle']);
        }
        if (isset($data['razon_social'])) {
            $data['razon_social'] = trim($data['razon_social']);
        }

        if ($intent === 'create_supplier') {
            $data['tipo'] = 'PROVEEDOR';
        }

        if (isset($data['stock_seguridad']) && is_numeric($data['stock_seguridad'])) {
            $data['stock_seguridad'] = (float) $data['stock_seguridad'];
        }
        if (isset($data['punto_pedido']) && is_numeric($data['punto_pedido'])) {
            $data['punto_pedido'] = (float) $data['punto_pedido'];
        }
        if (isset($data['lote_minimo']) && is_numeric($data['lote_minimo'])) {
            $data['lote_minimo'] = (float) $data['lote_minimo'];
        }
        if (isset($data['largo_alto']) && is_numeric($data['largo_alto'])) {
            $data['largo_alto'] = (float) $data['largo_alto'];
        }
        if (isset($data['ancho']) && is_numeric($data['ancho'])) {
            $data['ancho'] = (float) $data['ancho'];
        }
        if (isset($data['espesor_profundidad']) && is_numeric($data['espesor_profundidad'])) {
            $data['espesor_profundidad'] = (float) $data['espesor_profundidad'];
        }

        if (isset($data['variantes']) && is_array($data['variantes'])) {
            foreach ($data['variantes'] as &$var) {
                if (isset($var['codigo_variante'])) {
                    $var['codigo_variante'] = strtoupper(trim($var['codigo_variante']));
                }
                if (isset($var['lote_minimo']) && is_numeric($var['lote_minimo'])) {
                    $var['lote_minimo'] = (float) $var['lote_minimo'];
                }
                if (isset($var['punto_pedido']) && is_numeric($var['punto_pedido'])) {
                    $var['punto_pedido'] = (float) $var['punto_pedido'];
                }
                if (isset($var['peso']) && is_numeric($var['peso'])) {
                    $var['peso'] = (float) $var['peso'];
                }
            }
        }

        if (isset($data['components']) && is_array($data['components'])) {
            foreach ($data['components'] as &$component) {
                if (isset($component['code'])) {
                    $component['code'] = strtoupper(trim($component['code']));
                }
                if (isset($component['quantity'])) {
                    $component['quantity'] = (float) $component['quantity'];
                }
            }
        }

        return $data;
    }
}