<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class Bom extends BaseTenantModel
{
    protected function getTable(): string
    {
        return 'bom_cabecera';
    }

    public function getActiveByVariante(int $varianteId): ?array
    {
        $sql = "SELECT * FROM bom_cabecera
                WHERE variante_padre_id = :variante_id
                AND activa = TRUE
                ORDER BY created_at DESC
                LIMIT 1";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['variante_id' => $varianteId]);
        $bom = $stmt->fetch(PDO::FETCH_ASSOC);

        return $bom === false ? null : $bom;
    }

    public function getAllActive(): array
    {
        $sql = "SELECT b.*,
                  p.codigo AS parte_codigo,
                       v.codigo_variante AS variante_codigo,
                       v.detalle AS variante_detalle,
                       p.detalle AS parte_detalle
                FROM bom_cabecera b
                INNER JOIN variantes v ON CAST(b.variante_padre_id AS INTEGER) = v.id
                INNER JOIN partes p ON v.id_parte = p.id
                WHERE b.activa = TRUE
              ORDER BY p.codigo, v.codigo_variante";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTree(int $rootVariantId, int $maxDepth = 5): array
    {
        $sql = "WITH RECURSIVE bom_tree AS (
            SELECT
                v.id as variante_id,
                p.codigo as parte_codigo,
                p.detalle as parte_detalle,
                v.codigo_variante,
                v.detalle as variante_detalle,
                tp.codigo as tipo_codigo,
                tp.id as tipo_parte_id,
                CAST(NULL AS INTEGER) as parent_id,
                CAST(NULL AS INTEGER) as bom_detalle_id,
                CAST(NULL AS INTEGER) as unidad_medida_id,
                CAST(1.0 AS NUMERIC) as cantidad,
                CAST(NULL AS VARCHAR) as unidad,
                0 as nivel,
                ARRAY[v.id] as path
            FROM variantes v
            JOIN partes p ON v.id_parte = p.id
            LEFT JOIN tipos_partes tp ON p.id_tipo = tp.id
            WHERE v.id = :root_id

            UNION ALL

            SELECT
                vc.id as variante_id,
                pc.codigo as parte_codigo,
                pc.detalle as parte_detalle,
                vc.codigo_variante,
                vc.detalle as variante_detalle,
                tpc.codigo as tipo_codigo,
                tpc.id as tipo_parte_id,
                bt.variante_id as parent_id,
                d.id as bom_detalle_id,
                d.unidad_medida_id as unidad_medida_id,
                d.cantidad_necesaria as cantidad,
                um.simbolo as unidad,
                bt.nivel + 1,
                bt.path || vc.id
            FROM bom_tree bt
            JOIN bom_cabecera b ON CAST(b.variante_padre_id AS INTEGER) = bt.variante_id AND b.activa = TRUE
            JOIN bom_detalle d ON d.bom_id = b.id
            JOIN variantes vc ON CAST(d.variante_componente_id AS INTEGER) = vc.id
            JOIN partes pc ON vc.id_parte = pc.id
            LEFT JOIN tipos_partes tpc ON pc.id_tipo = tpc.id
            JOIN unidades_medida um ON d.unidad_medida_id = um.id
            WHERE bt.nivel < :max_depth
        )
        SELECT * FROM bom_tree ORDER BY path";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'root_id' => $rootVariantId,
            'max_depth' => $maxDepth
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDetalles(int $bomId): array
    {
        $sql = "SELECT d.*,
                       v.codigo_variante AS componente_codigo,
                       v.detalle AS componente_detalle,
                       p.detalle AS parte_detalle,
                       um.simbolo AS unidad_simbolo,
                       tp.codigo AS tipo_codigo,
                       tp.id AS tipo_parte_id
                FROM bom_detalle d
                INNER JOIN variantes v ON CAST(d.variante_componente_id AS INTEGER) = v.id
                INNER JOIN partes p ON v.id_parte = p.id
                LEFT JOIN tipos_partes tp ON p.id_tipo = tp.id
                INNER JOIN unidades_medida um ON d.unidad_medida_id = um.id
                WHERE d.bom_id = :bom_id
                ORDER BY d.secuencia, v.codigo_variante";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['bom_id' => $bomId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDetalleById(int $id): ?array
    {
        $sql = "SELECT d.*,
                       v.detalle as variante_nombre,
                       b.variante_padre_id
                FROM bom_detalle d
                JOIN variantes v ON CAST(d.variante_componente_id AS INTEGER) = v.id
                JOIN bom_cabecera b ON d.bom_id = b.id
                WHERE d.id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    public function createHeader(int $varianteId): int
    {
        $sql = "INSERT INTO bom_cabecera (variante_padre_id, activa, fecha_efectiva, created_at)
                VALUES (:vid, TRUE, NOW(), NOW()) RETURNING id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['vid' => $varianteId]);
        return (int) $stmt->fetchColumn();
    }

    public function addDetail(int $bomId, int $componentId, float $qty, int $unitId): int
    {
        $sql = "INSERT INTO bom_detalle (bom_id, variante_componente_id, cantidad_necesaria, unidad_medida_id, secuencia)
                VALUES (:bom, :comp, :qty, :unit, 0)
                RETURNING id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'bom' => $bomId,
            'comp' => $componentId,
            'qty' => $qty,
            'unit' => $unitId
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function updateDetail(int $id, float $qty, int $unitId): bool
    {
        $sql = "UPDATE bom_detalle
                SET cantidad_necesaria = :qty, unidad_medida_id = :unit
                WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'qty' => $qty,
            'unit' => $unitId,
            'id' => $id
        ]);
    }

    public function deleteDetail(int $id): bool
    {
        $sql = "DELETE FROM bom_detalle WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function replaceComponent(int $detailId, int $newComponentId): bool
    {
        $sql = "UPDATE bom_detalle
                SET variante_componente_id = :comp
                WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'comp' => $newComponentId,
            'id' => $detailId
        ]);
    }

    /**
     * Verifica si un componente ya existe en una BOM
     */
    public function isDuplicate(int $bomId, int $componentId): bool
    {
        $sql = "SELECT COUNT(*) FROM bom_detalle
                WHERE bom_id = :bom_id
                AND variante_componente_id = :comp_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'bom_id' => $bomId,
            'comp_id' => $componentId
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica si se está intentando agregar un elemento a sí mismo
     */
    public function isSelfReference(int $parentVarianteId, int $componentVarianteId): bool
    {
        return $parentVarianteId === $componentVarianteId;
    }

    /**
     * Obtiene todos los ancestros (padres recursivos) de una variante
     */
    public function getAllAncestors(int $varianteId): array
    {
        $sql = "WITH RECURSIVE ancestors AS (
            -- Base: variante actual
            SELECT
                CAST(:variante_id AS INTEGER) as variante_id,
                0 as nivel

            UNION ALL

            -- Recursivo: encontrar padres
            SELECT
                CAST(bc.variante_padre_id AS INTEGER),
                a.nivel + 1
            FROM ancestors a
            INNER JOIN bom_detalle bd ON bd.variante_componente_id = a.variante_id
            INNER JOIN bom_cabecera bc ON bc.id = bd.bom_id AND bc.activa = TRUE
            WHERE a.nivel < 20  -- Límite de seguridad
        )
        SELECT DISTINCT variante_id
        FROM ancestors
        WHERE nivel > 0
        ORDER BY variante_id";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['variante_id' => $varianteId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'variante_id');
    }

    /**
     * Verifica si agregar un componente crearía un ciclo
     * Un ciclo ocurre en dos casos:
     * 1. Si el componente ya tiene al padre como ancestro (padre arriba en el árbol)
     * 2. Si el componente ya tiene al padre como descendiente (padre abajo en el árbol)
     */
    public function wouldCreateCycle(int $parentVarianteId, int $componentVarianteId): bool
    {
        // Caso 1: Si el componente ya tiene al padre como ancestro
        $componentAncestors = $this->getAllAncestors($componentVarianteId);
        if (in_array($parentVarianteId, $componentAncestors, true)) {
            return true;
        }

        // Caso 2: Si el componente ya tiene al padre como descendiente
        $componentDescendants = $this->getAllDescendants($componentVarianteId);
        if (in_array($parentVarianteId, $componentDescendants, true)) {
            return true;
        }

        return false;
    }

    /**
     * Obtiene todos los descendientes (hijos recursivos) de una variante
     */
    public function getAllDescendants(int $varianteId): array
    {
        $tree = $this->getTree($varianteId);
        $descendants = [];

        foreach ($tree as $node) {
            if ($node['parent_id'] !== null) {
                $descendants[] = (int)$node['variante_id'];
            }
        }

        return array_unique($descendants);
    }

    /**
     * Detecta si agregar un componente crearía un ciclo y proporciona información detallada
     * @return array ['hasCycle' => bool, 'message' => string, 'conflictingItems' => array]
     */
    public function detectCycle(int $parentVarianteId, int $componentVarianteId): array
    {
        // Obtener información de las variantes
        $parentInfo = $this->getVarianteInfo($parentVarianteId);
        $componentInfo = $this->getVarianteInfo($componentVarianteId);

        // Caso 1: El componente ya tiene al padre como ancestro (padre arriba en el árbol)
        $componentAncestors = $this->getAllAncestors($componentVarianteId);
        if (in_array($parentVarianteId, $componentAncestors, true)) {
            $ancestorPath = $this->getAncestorPath($componentVarianteId, $parentVarianteId);
            return [
                'hasCycle' => true,
                'message' => sprintf(
                    '❌ CICLO DETECTADO: No se puede agregar "%s" dentro de "%s" porque "%s" ya es un padre superior de "%s". Ruta del ciclo: %s',
                    $componentInfo['codigo'],
                    $parentInfo['codigo'],
                    $parentInfo['codigo'],
                    $componentInfo['codigo'],
                    $ancestorPath
                ),
                'conflictingItems' => $componentAncestors
            ];
        }

        // Caso 2: El componente ya tiene al padre como descendiente (padre abajo en el árbol)
        $componentDescendants = $this->getAllDescendants($componentVarianteId);
        if (in_array($parentVarianteId, $componentDescendants, true)) {
            $descendantPath = $this->getDescendantPath($componentVarianteId, $parentVarianteId);
            return [
                'hasCycle' => true,
                'message' => sprintf(
                    '❌ CICLO DETECTADO: No se puede agregar "%s" dentro de "%s" porque "%s" ya contiene a "%s" como componente en su estructura. Ruta del ciclo: %s',
                    $componentInfo['codigo'],
                    $parentInfo['codigo'],
                    $componentInfo['codigo'],
                    $parentInfo['codigo'],
                    $descendantPath
                ),
                'conflictingItems' => $componentDescendants
            ];
        }

        return ['hasCycle' => false, 'message' => '', 'conflictingItems' => []];
    }

    /**
     * Obtiene información básica de una variante
     */
    private function getVarianteInfo(int $varianteId): array
    {
        $sql = "SELECT v.id, v.codigo_variante as codigo, v.detalle, p.detalle as parte_detalle
                FROM variantes v
                JOIN partes p ON v.id_parte = p.id
                WHERE v.id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $varianteId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: ['id' => $varianteId, 'codigo' => 'ID:' . $varianteId, 'detalle' => 'Desconocido'];
    }

    /**
     * Obtiene la ruta de ancestros como string legible
     */
    private function getAncestorPath(int $childId, int $targetAncestorId): string
    {
        $sql = "WITH RECURSIVE ancestors AS (
            SELECT
                CAST(:child_id AS INTEGER) as variante_id,
                v.codigo_variante,
                0 as nivel
            FROM variantes v
            WHERE v.id = :child_id

            UNION ALL

            SELECT
                CAST(bc.variante_padre_id AS INTEGER),
                vp.codigo_variante,
                a.nivel + 1
            FROM ancestors a
            INNER JOIN bom_detalle bd ON CAST(bd.variante_componente_id AS INTEGER) = a.variante_id
            INNER JOIN bom_cabecera bc ON bc.id = bd.bom_id AND bc.activa = TRUE
            INNER JOIN variantes vp ON CAST(bc.variante_padre_id AS INTEGER) = vp.id
            WHERE a.nivel < 20
        )
        SELECT string_agg(codigo_variante, ' → ' ORDER BY nivel DESC) as path
        FROM ancestors";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['child_id' => $childId]);
        $result = $stmt->fetchColumn();
        return $result ?: 'Ruta no disponible';
    }

    /**
     * Obtiene la ruta de descendientes como string legible
     */
    private function getDescendantPath(int $parentId, int $targetDescendantId): string
    {
        $tree = $this->getTree($parentId);
        $path = [];

        // Encontrar el camino al descendiente objetivo
        foreach ($tree as $node) {
            if ((int)$node['variante_id'] === $targetDescendantId) {
                // Construir el path desde el parent hasta este nodo
                $path[] = $node['codigo_variante'];
                break;
            }
        }

        if (empty($path)) {
            // Buscar de forma más simple
            $parentInfo = $this->getVarianteInfo($parentId);
            $descendantInfo = $this->getVarianteInfo($targetDescendantId);
            return sprintf('%s → ... → %s', $parentInfo['codigo'], $descendantInfo['codigo']);
        }

        array_unshift($path, $this->getVarianteInfo($parentId)['codigo']);
        return implode(' → ', $path);
    }

    /**
     * Valida si se puede agregar un componente a una BOM
     * Retorna array con 'valid' (bool) y 'error' (string|null)
     */
    public function validateAddComponent(int $parentVarianteId, int $componentVarianteId): array
    {
        // 1. Verificar auto-referencia
        if ($this->isSelfReference($parentVarianteId, $componentVarianteId)) {
            return [
                'valid' => false,
                'error' => 'No se puede agregar un elemento a sí mismo'
            ];
        }

        // 2. Verificar si ya existe en la BOM
        $bom = $this->getActiveByVariante($parentVarianteId);
        if ($bom && $this->isDuplicate((int)$bom['id'], $componentVarianteId)) {
            return [
                'valid' => false,
                'error' => 'Este componente ya existe en la BOM del padre'
            ];
        }

        // 3. Verificar ciclos con información detallada
        $cycleInfo = $this->detectCycle($parentVarianteId, $componentVarianteId);
        if ($cycleInfo['hasCycle']) {
            return [
                'valid' => false,
                'error' => $cycleInfo['message']
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Obtiene todas las BOMs donde se utiliza una variante como componente
     */
    public function getWhereUsed(int $varianteId): array
    {
        $sql = "SELECT DISTINCT
                    bc.id AS bom_id,
                    bc.variante_padre_id,
                    vp.codigo_variante AS padre_codigo,
                    vp.detalle AS padre_detalle,
                    pp.detalle AS padre_parte,
                    bd.cantidad_necesaria,
                    um.simbolo AS unidad_codigo,
                    um.unidad AS unidad_detalle
                FROM bom_detalle bd
                INNER JOIN bom_cabecera bc ON bd.bom_id = bc.id
                INNER JOIN variantes vp ON CAST(bc.variante_padre_id AS INTEGER) = vp.id
                INNER JOIN partes pp ON vp.id_parte = pp.id
                LEFT JOIN unidades_medida um ON bd.unidad_medida_id = um.id
                WHERE bd.variante_componente_id = :variante_id
                AND bc.activa = TRUE
                ORDER BY vp.codigo_variante";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['variante_id' => $varianteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
