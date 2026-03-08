<?php

declare(strict_types=1);

namespace App\Services\Partes;

use App\Models\Parte;
use PDO;

final class PartesGeometryRecalculationService
{
    private PDO $connection;

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? (new Parte())->getConnection();
    }

    public function recalculateAll(bool $onlyCompleteDimensions = false): array
    {
        $units = $this->connection
            ->query("SELECT id, tipo, simbolo, equivalencia_base FROM unidades_medida WHERE tipo IN ('longitud','superficie','volumen')")
            ->fetchAll(PDO::FETCH_ASSOC);

        $longitudById = [];
        $superficieUnits = [];
        $volumenUnits = [];

        foreach ($units as $unit) {
            $id = (int) ($unit['id'] ?? 0);
            $tipo = (string) ($unit['tipo'] ?? '');
            $simbolo = (string) ($unit['simbolo'] ?? '');
            $equivalencia = isset($unit['equivalencia_base']) ? (float) $unit['equivalencia_base'] : null;

            if ($tipo === 'longitud') {
                $longitudById[$id] = $equivalencia;
                continue;
            }

            if ($tipo === 'superficie') {
                $superficieUnits[] = ['id' => $id, 'simbolo' => $simbolo];
                continue;
            }

            if ($tipo === 'volumen') {
                $volumenUnits[] = ['id' => $id, 'simbolo' => $simbolo];
            }
        }

        $defaultSuperficieId = null;
        foreach ($superficieUnits as $unit) {
            if (($unit['simbolo'] ?? '') === 'm²' || ($unit['simbolo'] ?? '') === 'mts²') {
                $defaultSuperficieId = (int) $unit['id'];
                break;
            }
        }

        $defaultVolumenId = null;
        foreach ($volumenUnits as $unit) {
            $simbolo = strtolower((string) ($unit['simbolo'] ?? ''));
            if (($unit['simbolo'] ?? '') === 'cm³' || str_contains($simbolo, 'cm')) {
                $defaultVolumenId = (int) $unit['id'];
                break;
            }
        }

        $parts = $this->connection
            ->query('SELECT id, largo_alto, id_um_largo_alto, ancho, id_um_ancho, espesor_profundidad, id_um_espesor, superficie, id_um_superficie, volumen, id_um_volumen FROM partes ORDER BY id')
            ->fetchAll(PDO::FETCH_ASSOC);

        $updateStmt = $this->connection->prepare(
            'UPDATE partes
            SET superficie = :superficie,
                id_um_superficie = :id_um_superficie,
                volumen = :volumen,
                id_um_volumen = :id_um_volumen
            WHERE id = :id'
        );

        $total = count($parts);
        $updated = 0;
        $unchanged = 0;
        $skipped = 0;

        $this->connection->beginTransaction();

        try {
            foreach ($parts as $part) {
                $largo = $this->toFloatOrZero($part['largo_alto'] ?? null);
                $ancho = $this->toFloatOrZero($part['ancho'] ?? null);
                $espesor = $this->toFloatOrZero($part['espesor_profundidad'] ?? null);

                if ($onlyCompleteDimensions && !($largo > 0 && $ancho > 0 && $espesor > 0)) {
                    $skipped++;
                    continue;
                }

                $equivLargo = $longitudById[(int) ($part['id_um_largo_alto'] ?? 0)] ?? 1.0;
                $equivAncho = $longitudById[(int) ($part['id_um_ancho'] ?? 0)] ?? 1.0;
                $equivEspesor = $longitudById[(int) ($part['id_um_espesor'] ?? 0)] ?? 1.0;

                $largoM = $largo * $equivLargo;
                $anchoM = $ancho * $equivAncho;
                $espesorM = $espesor * $equivEspesor;

                $newSuperficie = isset($part['superficie']) ? (float) $part['superficie'] : null;
                $newVolumen = isset($part['volumen']) ? (float) $part['volumen'] : null;
                $newIdUmSuperficie = $this->toNullableInt($part['id_um_superficie'] ?? null);
                $newIdUmVolumen = $this->toNullableInt($part['id_um_volumen'] ?? null);

                if ($largo > 0 && $ancho > 0) {
                    $newSuperficie = $this->roundLikeUi($largoM * $anchoM, 8);
                    if ($newIdUmSuperficie === null && $defaultSuperficieId !== null) {
                        $newIdUmSuperficie = $defaultSuperficieId;
                    }
                }

                if ($largo > 0 && $ancho > 0 && $espesor > 0) {
                    $newVolumen = $this->roundLikeUi($largoM * $anchoM * $espesorM * 1000000, 8);
                    if ($newIdUmVolumen === null && $defaultVolumenId !== null) {
                        $newIdUmVolumen = $defaultVolumenId;
                    }
                }

                $oldSuperficie = isset($part['superficie']) ? (float) $part['superficie'] : null;
                $oldVolumen = isset($part['volumen']) ? (float) $part['volumen'] : null;
                $oldIdUmSuperficie = $this->toNullableInt($part['id_um_superficie'] ?? null);
                $oldIdUmVolumen = $this->toNullableInt($part['id_um_volumen'] ?? null);

                $hasChanges = !$this->nearlyEqual($oldSuperficie, $newSuperficie)
                    || !$this->nearlyEqual($oldVolumen, $newVolumen)
                    || $oldIdUmSuperficie !== $newIdUmSuperficie
                    || $oldIdUmVolumen !== $newIdUmVolumen;

                if (!$hasChanges) {
                    $unchanged++;
                    continue;
                }

                $updateStmt->execute([
                    'superficie' => $newSuperficie,
                    'id_um_superficie' => $newIdUmSuperficie,
                    'volumen' => $newVolumen,
                    'id_um_volumen' => $newIdUmVolumen,
                    'id' => (int) $part['id'],
                ]);

                $updated++;
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $e;
        }

        return [
            'total' => $total,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'skipped' => $skipped,
            'only_complete_dimensions' => $onlyCompleteDimensions,
        ];
    }

    private function roundLikeUi(float $value, int $decimals = 8): float
    {
        $factor = 10 ** $decimals;
        return round($value * $factor) / $factor;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : null;
    }

    private function toFloatOrZero(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function nearlyEqual(?float $a, ?float $b, float $epsilon = 0.00000001): bool
    {
        if ($a === null && $b === null) {
            return true;
        }

        if ($a === null || $b === null) {
            return false;
        }

        return abs($a - $b) <= $epsilon;
    }
}
