<?php

declare(strict_types=1);

namespace App\Services\Partes;

use App\Models\Variante;
use App\Models\Parte;
use PDO;

final class VarianteDeletionService
{
    private Variante $variantes;
    private Parte $partes;
    private PDO $connection;

    public function __construct(
        ?Variante $variantes = null,
        ?Parte $partes = null
    ) {
        $this->variantes = $variantes ?? new Variante();
        $this->partes = $partes ?? new Parte();
        $this->connection = $this->variantes->getConnection();
    }

    public function canDelete(int $varianteId, int $parteId): array
    {
        $errors = [];

        $hasCompras = $this->hasCompras($varianteId);
        if ($hasCompras) {
            $errors[] = 'Tiene registros de compras asociados.';
        }

        $hasMovimientos = $this->hasMovimientos($varianteId);
        if ($hasMovimientos) {
            $errors[] = 'Tiene movimientos de stock registrados.';
        }

        $isChild = $this->isBomChild($varianteId);
        if ($isChild) {
            $parents = $this->getBomParents($varianteId);
            $parentList = implode(', ', array_map(static fn($p) => $p['codigo_variante'] . ' - ' . $p['detalle'], $parents));
            $errors[] = 'Es componente de las siguientes variantes padre: ' . $parentList . '.';
        }

        $isParent = $this->isBomParent($varianteId);
        if ($isParent) {
            $errors[] = 'Tiene una estructura de composición (BOM) definida como padre.';
        }

        $variantCount = $this->variantes->countByParteId($parteId);
        $isLastVariant = $variantCount <= 1;

        return [
            'can_delete' => $errors === [],
            'errors' => $errors,
            'is_last_variant' => $isLastVariant,
            'parte' => $this->partes->find($parteId),
        ];
    }

    public function delete(int $varianteId, int $parteId, bool $forceDeleteParte = false): array
    {
        $validation = $this->canDelete($varianteId, $parteId);

        if (!$validation['can_delete']) {
            return [
                'success' => false,
                'message' => 'No se puede eliminar la variante: ' . implode('; ', $validation['errors']),
            ];
        }

        $isLastVariant = $validation['is_last_variant'];
        $parte = $validation['parte'];

        if ($isLastVariant && !$forceDeleteParte) {
            return [
                'success' => false,
                'needs_parte_deletion' => true,
                'message' => 'Esta es la única variante de la parte "' . ($parte['codigo'] ?? '') . '". Al eliminarla se eliminará también la parte. ¿Desea continuar?',
            ];
        }

        $this->deleteEmptyBomHeaders($varianteId);
        $this->variantes->delete($varianteId);

        if ($isLastVariant && $forceDeleteParte && $parte !== null) {
            $this->partes->delete($parteId);
        }

        return [
            'success' => true,
            'message' => $isLastVariant && $forceDeleteParte
                ? 'Variante y parte eliminadas correctamente.'
                : 'Variante eliminada correctamente.',
            'deleted_parte' => $isLastVariant && $forceDeleteParte,
        ];
    }

    private function hasCompras(int $varianteId): bool
    {
        $sql = "SELECT COUNT(*) FROM compras c
                INNER JOIN movimientos_stock m ON c.id_movimiento_stock = m.id
                WHERE m.id_variante = :variante_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['variante_id' => $varianteId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function hasMovimientos(int $varianteId): bool
    {
        $sql = "SELECT COUNT(*) FROM movimientos_stock WHERE id_variante = :variante_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['variante_id' => $varianteId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function isBomChild(int $varianteId): bool
    {
        $sql = "SELECT COUNT(*) FROM bom_detalle WHERE variante_componente_id = :variante_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['variante_id' => $varianteId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function isBomParent(int $varianteId): bool
    {
        $sql = "SELECT COUNT(*) FROM bom_cabecera bc
                INNER JOIN bom_detalle bd ON bd.bom_id = bc.id
                WHERE bc.variante_padre_id = :variante_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['variante_id' => $varianteId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function deleteEmptyBomHeaders(int $varianteId): void
    {
        $sql = "DELETE FROM bom_cabecera
                WHERE variante_padre_id = :variante_id
                AND id NOT IN (SELECT bom_id FROM bom_detalle)";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['variante_id' => $varianteId]);
    }

    private function getBomParents(int $varianteId): array
    {
        $sql = "SELECT DISTINCT vp.codigo_variante, vp.detalle
                FROM bom_detalle bd
                INNER JOIN bom_cabecera bc ON bd.bom_id = bc.id
                INNER JOIN variantes vp ON CAST(bc.variante_padre_id AS INTEGER) = vp.id
                WHERE bd.variante_componente_id = :variante_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['variante_id' => $varianteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}