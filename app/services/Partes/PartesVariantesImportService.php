<?php

declare(strict_types=1);

namespace App\Services\Partes;

use App\Models\Parte;
use App\Models\Variante;
use PDO;
use Throwable;

final class PartesVariantesImportService
{
    public function __construct(
        private readonly Parte $partes,
        private readonly Variante $variantes,
        private readonly PartesVariantesImportCsvParser $csvParser,
        private readonly PartesVariantesImportRowMapper $rowMapper,
        private readonly array $tipos,
        private readonly array $grupos,
        private readonly array $unidades
    ) {}

    public function importFromUpload(array $uploadedFile): array
    {
        $report = [
            'total_rows' => 0,
            'ok_rows' => 0,
            'error_rows' => 0,
            'created_parts' => 0,
            'updated_parts' => 0,
            'created_variants' => 0,
            'updated_variants' => 0,
            'rows' => [],
            'fatal_error' => null,
        ];

        if (!isset($uploadedFile['error']) || (int) $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $report['fatal_error'] = 'No se pudo cargar el archivo. Verifica que sea un CSV valido.';
            return $report;
        }

        $tmpPath = (string) ($uploadedFile['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            $report['fatal_error'] = 'El archivo cargado no es valido.';
            return $report;
        }

        try {
            $parsed = $this->csvParser->parseFile($tmpPath);
        } catch (\RuntimeException $exception) {
            $report['fatal_error'] = $exception->getMessage();
            return $report;
        }

        $conn = $this->partes->getConnection();
        $tipoMap = $this->rowMapper->buildReferenceMap($this->tipos, 'codigo', 'id', 'nombre');
        $grupoMap = $this->rowMapper->buildReferenceMap($this->grupos, 'codigo', 'id', 'nombre');
        $unitMap = $this->rowMapper->buildUnitMap($this->unidades);

        foreach ($parsed['rows'] as $entry) {
            $line = (int) $entry['line'];
            $row = $entry['data'];

            $report['total_rows']++;

            [$partData, $variantData, $rowErrors] = $this->rowMapper->mapRowToEntities($row, $tipoMap, $grupoMap, $unitMap);
            if ($rowErrors !== []) {
                $report['error_rows']++;
                $report['rows'][] = [
                    'line' => $line,
                    'status' => 'error',
                    'message' => implode(' | ', $rowErrors),
                ];
                continue;
            }

            try {
                $conn->beginTransaction();

                $existingParte = $this->findParteByCodigo($conn, (string) $partData['codigo']);
                if ($existingParte === null) {
                    $parteId = $this->partes->create($partData);
                    $partAction = 'creada';
                    $report['created_parts']++;
                } else {
                    $parteId = (int) $existingParte['id'];
                    $this->partes->update($parteId, $partData);
                    $partAction = 'actualizada';
                    $report['updated_parts']++;
                }

                $variantData['id_parte'] = $parteId;
                $existingVariant = $this->findVarianteByCodigo($conn, $parteId, (string) $variantData['codigo_variante']);

                if ($existingVariant === null) {
                    $this->variantes->create($variantData);
                    $variantAction = 'creada';
                    $report['created_variants']++;
                } else {
                    $this->variantes->update((int) $existingVariant['id'], $variantData);
                    $variantAction = 'actualizada';
                    $report['updated_variants']++;
                }

                $conn->commit();

                $report['ok_rows']++;
                $report['rows'][] = [
                    'line' => $line,
                    'status' => 'ok',
                    'message' => sprintf(
                        'Parte %s y variante %s (%s / %s).',
                        $partAction,
                        $variantAction,
                        (string) $partData['codigo'],
                        (string) $variantData['codigo_variante']
                    ),
                ];
            } catch (Throwable $exception) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }

                $report['error_rows']++;
                $report['rows'][] = [
                    'line' => $line,
                    'status' => 'error',
                    'message' => $this->resolveRowErrorMessage($exception),
                ];
            }
        }

        return $report;
    }

    private function findParteByCodigo(PDO $conn, string $codigo): ?array
    {
        $stmt = $conn->prepare('SELECT id FROM partes WHERE codigo = :codigo LIMIT 1');
        $stmt->execute(['codigo' => $codigo]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function findVarianteByCodigo(PDO $conn, int $parteId, string $codigo): ?array
    {
        $stmt = $conn->prepare('SELECT id FROM variantes WHERE id_parte = :id_parte AND codigo_variante = :codigo LIMIT 1');
        $stmt->execute([
            'id_parte' => $parteId,
            'codigo' => $codigo,
        ]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        return $record === false ? null : $record;
    }

    private function resolveRowErrorMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'partes_codigo_key')) {
            return 'Codigo de parte duplicado.';
        }

        if (str_contains($message, 'variantes_id_parte_codigo_variante_key')) {
            return 'Codigo de variante duplicado para la parte.';
        }

        return 'Error al guardar fila: ' . $message;
    }
}
