<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * SearchRepository
 *
 * Repositorio para búsquedas en la aplicación.
 * Solo contiene queries SQL, sin lógica de negocio.
 */
final class SearchRepository
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Busca variantes por código, parte o detalle
     *
     * @param string $query Término de búsqueda
     * @param array $filters Filtros adicionales [tipo_codigo, exclude_ids]
     * @param int $limit Límite de resultados
     * @return array Lista de variantes encontradas
     */
    public function searchVariantes(string $query, array $filters = [], int $limit = 10): array
    {
        $whereClauses = ["(
            v.codigo_variante ILIKE :query1
            OR p.codigo ILIKE :query2
            OR v.detalle ILIKE :query3
            OR p.detalle ILIKE :query4
        )"];

        $searchTerm = "%{$query}%";
        $params = [
            'query1' => $searchTerm,
            'query2' => $searchTerm,
            'query3' => $searchTerm,
            'query4' => $searchTerm
        ];

        // Filtro por tipo de parte
        if (!empty($filters['tipo_codigo'])) {
            $whereClauses[] = "tp.codigo = :tipo_codigo";
            $params['tipo_codigo'] = $filters['tipo_codigo'];
        }

        // Excluir IDs específicos (para prevenir ciclos)
        if (!empty($filters['exclude_ids']) && is_array($filters['exclude_ids'])) {
            $excludePlaceholders = [];
            foreach ($filters['exclude_ids'] as $index => $id) {
                // Validación básica de ID
                $id = (int)$id;
                if ($id > 0) {
                    $key = "exclude_id_{$index}";
                    $excludePlaceholders[] = ":{$key}";
                    $params[$key] = $id;
                }
            }
            if (!empty($excludePlaceholders)) {
                $whereClauses[] = "v.id NOT IN (" . implode(',', $excludePlaceholders) . ")";
            }
        }

        // Filtrar solo variantes con BOM activa
        if (!empty($filters['has_bom'])) {
            $whereClauses[] = "EXISTS (SELECT 1 FROM bom_cabecera bc WHERE CAST(bc.variante_padre_id AS INTEGER) = v.id AND bc.activa = TRUE)";
        }

        $whereClause = implode(' AND ', $whereClauses);

        $sql = "SELECT
                    v.id,
                    v.id_parte,
                    v.codigo_variante,
                    v.detalle,
                    v.stock_actual,
                    v.lote_minimo,
                    p.codigo AS parte_codigo,
                    p.detalle AS parte_detalle,
                    p.id_um_compra,
                    p.id_um_uso,
                    p.factor_conversion,
                    tp.codigo AS tipo_codigo,
                    tp.nombre AS tipo_nombre,
                    um1.simbolo AS um_compra_codigo,
                    um2.simbolo AS um_uso_codigo,
                    um2.tipo AS um_uso_tipo
                FROM variantes v
                INNER JOIN partes p ON v.id_parte = p.id
                LEFT JOIN tipos_partes tp ON p.id_tipo = tp.id
                LEFT JOIN unidades_medida um1 ON p.id_um_compra = um1.id
                LEFT JOIN unidades_medida um2 ON p.id_um_uso = um2.id
                WHERE {$whereClause}
                ORDER BY v.codigo_variante
                LIMIT :limit";

        $stmt = $this->connection->prepare($sql);

        // Bind de parámetros
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene una variante por ID
     *
     * @param int $id ID de la variante
     * @return array|null Datos de la variante o null si no existe
     */
    public function getVarianteById(int $id): ?array
    {
        $sql = "SELECT
                    v.id,
                    v.id_parte,
                    v.codigo_variante,
                    v.detalle,
                    v.stock_actual,
                    v.lote_minimo,
                    p.codigo AS parte_codigo,
                    p.detalle AS parte_detalle,
                    p.id_um_compra,
                    p.id_um_uso,
                    p.factor_conversion,
                    tp.codigo AS tipo_codigo,
                    tp.nombre AS tipo_nombre,
                    um1.simbolo AS um_compra_codigo,
                    um2.simbolo AS um_uso_codigo,
                    um2.tipo AS um_uso_tipo
                FROM variantes v
                INNER JOIN partes p ON v.id_parte = p.id
                LEFT JOIN tipos_partes tp ON p.id_tipo = tp.id
                LEFT JOIN unidades_medida um1 ON p.id_um_compra = um1.id
                LEFT JOIN unidades_medida um2 ON p.id_um_uso = um2.id
                WHERE v.id = :id";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }

    /**
     * Busca tipos de partes
     *
     * @param string $query Término de búsqueda
     * @return array Lista de tipos encontrados
     */
    public function searchTiposParte(string $query): array
    {
        $sql = "SELECT id, codigo, nombre, descripcion
                FROM tipos_partes
                WHERE codigo ILIKE :query
                   OR nombre ILIKE :query
                ORDER BY codigo
                LIMIT 20";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['query' => "%{$query}%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca partes por código o detalle
     *
     * @param string $query Término de búsqueda
     * @param array $filters Filtros adicionales
     * @param int $limit Límite de resultados
     * @return array Lista de partes encontradas
     */
    public function searchPartes(string $query, array $filters = [], int $limit = 10): array
    {
        $searchTerm = "%{$query}%";

        // Construir condición WHERE para filtros
        $tipoCondition = '';
        $tipoParams = [];
        if (!empty($filters['tipo_codigo'])) {
            $tipoCondition = "AND tp.codigo = :tipo_codigo";
            $tipoParams['tipo_codigo'] = $filters['tipo_codigo'];
        }

        // Búsqueda unificada en Partes y Variantes usando CTE para claridad y rendimiento
        $sql = "
            WITH matches AS (
                -- Coincidencias en Partes
                SELECT
                    p.id,
                    p.codigo,
                    p.detalle,
                    tp.codigo AS tipo_codigo,
                    tp.nombre AS tipo_nombre,
                    gp.nombre AS grupo_nombre,
                    'parte' AS tipo_resultado,
                    NULL::int AS variante_id,
                    NULL::text AS variante_codigo,
                    NULL::text AS variante_detalle
                FROM partes p
                LEFT JOIN tipos_partes tp ON p.id_tipo = tp.id
                LEFT JOIN grupos_partes gp ON p.id_grupo = gp.id
                WHERE (p.codigo ILIKE :search1 OR p.detalle ILIKE :search2)
                {$tipoCondition}

                UNION ALL

                -- Coincidencias en Variantes
                SELECT
                    p.id,
                    p.codigo,
                    p.detalle,
                    tp.codigo AS tipo_codigo,
                    tp.nombre AS tipo_nombre,
                    gp.nombre AS grupo_nombre,
                    'variante' AS tipo_resultado,
                    v.id AS variante_id,
                    v.codigo_variante AS variante_codigo,
                    v.detalle AS variante_detalle
                FROM variantes v
                INNER JOIN partes p ON v.id_parte = p.id
                LEFT JOIN tipos_partes tp ON p.id_tipo = tp.id
                LEFT JOIN grupos_partes gp ON p.id_grupo = gp.id
                WHERE (v.codigo_variante ILIKE :search3 OR v.detalle ILIKE :search4)
                {$tipoCondition}
            )
            SELECT DISTINCT ON (tipo_resultado, id, variante_id) *
            FROM matches
            ORDER BY tipo_resultado, id, variante_id
            LIMIT :limit
        ";

        $stmt = $this->connection->prepare($sql);

        $stmt->bindValue(':search1', $searchTerm);
        $stmt->bindValue(':search2', $searchTerm);
        $stmt->bindValue(':search3', $searchTerm);
        $stmt->bindValue(':search4', $searchTerm);

        foreach ($tipoParams as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca centros de trabajo
     *
     * @param string $query Término de búsqueda
     * @return array Lista de centros encontrados
     */
    public function searchCentrosTrabajo(string $query): array
    {
        $sql = "SELECT id, codigo, nombre, descripcion
                FROM centros_trabajo
                WHERE codigo ILIKE :query
                   OR nombre ILIKE :query
                ORDER BY codigo
                LIMIT 20";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['query' => "%{$query}%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
