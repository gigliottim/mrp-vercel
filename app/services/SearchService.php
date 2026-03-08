<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SearchRepository;

/**
 * SearchService
 *
 * Servicio de búsqueda con lógica de negocio.
 * Orquesta el SearchRepository y aplica transformaciones.
 */
final class SearchService
{
    private SearchRepository $repository;

    public function __construct(SearchRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Busca variantes aplicando validaciones y transformaciones
     *
     * @param string $query Término de búsqueda
     * @param array $options Opciones de búsqueda
     * @return array Resultados formateados
     */
    public function searchVariantes(string $query, array $options = []): array
    {
        // Validar longitud mínima
        $query = trim($query);
        if (strlen($query) < 2) {
            return [
                'success' => false,
                'message' => 'El término de búsqueda debe tener al menos 2 caracteres',
                'results' => []
            ];
        }

        // Extraer opciones
        $filters = $options['filters'] ?? [];
        $limit = $options['limit'] ?? 10;
        $format = $options['format'] ?? 'standard'; // standard, compact, detailed

        // Realizar búsqueda
        $results = $this->repository->searchVariantes($query, $filters, $limit);

        // Formatear resultados según el formato solicitado
        $formattedResults = array_map(function ($item) use ($format) {
            return $this->formatVariante($item, $format);
        }, $results);

        return [
            'success' => true,
            'message' => count($formattedResults) > 0
                ? "Se encontraron " . count($formattedResults) . " resultado(s)"
                : "No se encontraron resultados",
            'results' => $formattedResults,
            'count' => count($formattedResults)
        ];
    }

    /**
     * Obtiene una variante por ID con formato
     *
     * @param int $id ID de la variante
     * @param string $format Formato de salida
     * @return array|null Variante formateada o null
     */
    public function getVarianteById(int $id, string $format = 'standard'): ?array
    {
        $variante = $this->repository->getVarianteById($id);

        if ($variante === null) {
            return null;
        }

        return $this->formatVariante($variante, $format);
    }

    /**
     * Formatea una variante según el tipo solicitado
     *
     * @param array $variante Datos crudos de la variante
     * @param string $format Tipo de formato
     * @return array Variante formateada
     */
    private function formatVariante(array $variante, string $format): array
    {
        $base = [
            'id' => (int) $variante['id'],
            'codigo_variante' => $variante['codigo_variante'],
            'parte_codigo' => $variante['parte_codigo'] ?? '',
            'tipo_codigo' => $variante['tipo_codigo'] ?? 'OTRO',
            'tipo_nombre' => $variante['tipo_nombre'] ?? 'Otro'
        ];

        switch ($format) {
            case 'compact':
                // Solo lo esencial para dropdowns
                return [
                    'id' => $base['id'],
                    'label' => "[{$base['tipo_codigo']}] {$base['codigo_variante']}",
                    'tipo' => $base['tipo_codigo']
                ];

            case 'detailed':
                // Información completa incluyendo unidades de medida
                return array_merge($base, [
                    'id_parte' => isset($variante['id_parte']) ? (int) $variante['id_parte'] : null,
                    'detalle' => $variante['detalle'] ?? '',
                    'parte_detalle' => $variante['parte_detalle'] ?? '',
                    'id_um_compra' => isset($variante['id_um_compra']) ? (int) $variante['id_um_compra'] : null,
                    'id_um_uso' => isset($variante['id_um_uso']) ? (int) $variante['id_um_uso'] : null,
                    'um_uso_codigo' => $variante['um_uso_codigo'] ?? '',
                    'um_uso_tipo' => $variante['um_uso_tipo'] ?? '',
                    'descripcion_completa' => $this->buildDescripcionCompleta($variante),
                    'variante_detalle' => $variante['detalle'] ?? '' // Alias para compatibilidad
                ]);

            case 'standard':
            default:
                // Balance entre información y tamaño
                return array_merge($base, [
                    'detalle' => $variante['detalle'] ?? '',
                    'display_text' => "[{$base['tipo_codigo']}] {$base['codigo_variante']} - " .
                        ($variante['detalle'] ?? $variante['parte_detalle'] ?? 'Sin detalle')
                ]);
        }
    }

    /**
     * Construye descripción completa de una variante
     *
     * @param array $variante Datos de la variante
     * @return string Descripción formateada
     */
    private function buildDescripcionCompleta(array $variante): string
    {
        $parts = [];

        if (!empty($variante['parte_codigo'])) {
            $parts[] = "Parte: {$variante['parte_codigo']}";
        }

        if (!empty($variante['codigo_variante'])) {
            $parts[] = "Código: {$variante['codigo_variante']}";
        }

        if (!empty($variante['detalle'])) {
            $parts[] = $variante['detalle'];
        } elseif (!empty($variante['parte_detalle'])) {
            $parts[] = $variante['parte_detalle'];
        }

        return implode(' | ', $parts);
    }

    /**
     * Busca tipos de partes
     *
     * @param string $query Término de búsqueda
     * @return array Resultados
     */
    public function searchTiposParte(string $query): array
    {
        $query = trim($query);
        if (strlen($query) < 1) {
            return ['success' => false, 'message' => 'Consulta muy corta', 'results' => []];
        }

        $results = $this->repository->searchTiposParte($query);

        return [
            'success' => true,
            'results' => $results,
            'count' => count($results)
        ];
    }

    /**
     * Busca partes aplicando validaciones y transformaciones
     *
     * @param string $query Término de búsqueda
     * @param array $options Opciones de búsqueda
     * @return array Resultados formateados
     */
    public function searchPartes(string $query, array $options = []): array
    {
        // Validar longitud mínima
        $query = trim($query);
        if (strlen($query) < 2) {
            return [
                'success' => false,
                'message' => 'El término de búsqueda debe tener al menos 2 caracteres',
                'results' => []
            ];
        }

        // Extraer opciones
        $filters = $options['filters'] ?? [];
        $limit = $options['limit'] ?? 10;
        $format = $options['format'] ?? 'standard'; // standard, compact, detailed

        // Realizar búsqueda
        $results = $this->repository->searchPartes($query, $filters, $limit);

        // Formatear resultados según el formato solicitado
        $formattedResults = array_map(function ($item) use ($format) {
            return $this->formatParte($item, $format);
        }, $results);

        return [
            'success' => true,
            'message' => count($formattedResults) > 0
                ? "Se encontraron " . count($formattedResults) . " resultado(s)"
                : "No se encontraron resultados",
            'results' => $formattedResults,
            'count' => count($formattedResults)
        ];
    }

    /**
     * Formatea una parte según el tipo solicitado
     *
     * @param array $parte Datos crudos de la parte
     * @param string $format Tipo de formato
     * @return array Parte formateada
     */
    private function formatParte(array $parte, string $format): array
    {
        $base = [
            'id' => (int) $parte['id'],
            'codigo' => $parte['codigo'],
            'detalle' => $parte['detalle'] ?? '',
            'tipo_codigo' => $parte['tipo_codigo'] ?? 'OTRO',
            'tipo_nombre' => $parte['tipo_nombre'] ?? 'Otro',
            'grupo_nombre' => $parte['grupo_nombre'] ?? '',
            'variante_codigo' => $parte['variante_codigo'] ?? null,
            'variante_detalle' => $parte['variante_detalle'] ?? null,
            'tipo_resultado' => $parte['tipo_resultado'] ?? 'parte',

            // Campos adicionales para compras/calculos
            'stock_actual' => $parte['stock_actual'] ?? 0,
            'lote_minimo' => $parte['lote_minimo'] ?? 0,
            'factor_conversion' => $parte['factor_conversion'] ?? 1,
            'id_um_compra' => $parte['id_um_compra'] ?? null,
            'id_um_uso' => $parte['id_um_uso'] ?? null,
            'um_compra_codigo' => $parte['um_compra_codigo'] ?? '',
            'um_uso_codigo' => $parte['um_uso_codigo'] ?? '',
        ];

        // Construir display text considerando si es variante
        $displayText = $base['codigo'];
        if ($base['variante_codigo']) {
            $displayText .= " | " . $base['variante_codigo'];
        }
        $displayText .= " - " . $base['detalle'];
        if ($base['variante_detalle']) {
            $displayText .= " (" . $base['variante_detalle'] . ")";
        }

        switch ($format) {
            case 'compact':
                // Solo lo esencial para dropdowns
                return [
                    'id' => $base['id'],
                    'label' => $displayText,
                    'codigo' => $base['codigo'],
                    'variante_codigo' => $base['variante_codigo']
                ];

            case 'detailed':
                // Información completa
                return array_merge($base, [
                    'display_text' => $displayText
                ]);

            case 'standard':
            default:
                // Balance entre información y tamaño
                return array_merge($base, [
                    'display_text' => $displayText
                ]);
        }
    }

    /**
     * Busca centros de trabajo
     *
     * @param string $query Término de búsqueda
     * @return array Resultados
     */
    public function searchCentrosTrabajo(string $query): array
    {
        $query = trim($query);
        if (strlen($query) < 1) {
            return ['success' => false, 'message' => 'Consulta muy corta', 'results' => []];
        }

        $results = $this->repository->searchCentrosTrabajo($query);

        return [
            'success' => true,
            'results' => $results,
            'count' => count($results)
        ];
    }
}
