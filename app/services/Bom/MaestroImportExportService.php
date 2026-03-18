<?php

declare(strict_types=1);

namespace App\Services\Bom;

use App\Models\Bom;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Servicio de importación/exportación de estructura BOM (Maestro de Productos).
 *
 * CSV: padre_parte_codigo, padre_variante_codigo, hijo_parte_codigo,
 *      hijo_variante_codigo, cantidad, unidad_codigo
 */
final class MaestroImportExportService
{
    private const REQUIRED_HEADERS = [
        'padre_parte_codigo',
        'padre_variante_codigo',
        'hijo_parte_codigo',
        'hijo_variante_codigo',
        'cantidad',
        'unidad_codigo',
    ];

    public function __construct(private readonly Bom $bomModel) {}

    // ---------------------------------------------------------------
    // EXPORTAR
    // ---------------------------------------------------------------

    public function exportToCsv(): string
    {
        $conn = $this->bomModel->getConnection();
        $sql = "SELECT pp.codigo  AS padre_parte_codigo,
                       vp.codigo_variante AS padre_variante_codigo,
                       ph.codigo  AS hijo_parte_codigo,
                       vh.codigo_variante AS hijo_variante_codigo,
                       d.cantidad_necesaria AS cantidad,
                       um.simbolo AS unidad_codigo
                FROM bom_cabecera bc
                JOIN bom_detalle d  ON d.bom_id = bc.id
                JOIN variantes vp   ON vp.id = CAST(bc.variante_padre_id AS INTEGER)
                JOIN partes pp      ON pp.id = vp.id_parte
                JOIN variantes vh   ON vh.id = CAST(d.variante_componente_id AS INTEGER)
                JOIN partes ph      ON ph.id = vh.id_parte
                JOIN unidades_medida um ON um.id = d.unidad_medida_id
                WHERE bc.activa = TRUE
                ORDER BY pp.codigo, vp.codigo_variante, ph.codigo, vh.codigo_variante";

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = implode(',', self::REQUIRED_HEADERS) . "\n";
        foreach ($rows as $row) {
            $out .= implode(',', array_map(
                fn($v) => $this->csvEscape((string) $v),
                $row
            )) . "\n";
        }
        return $out;
    }

    public function buildTemplateCsv(): string
    {
        $out = implode(',', self::REQUIRED_HEADERS) . "\n";
        $out .= "PROD-001,V1,MAT-001,V1,2.5,kg\n";
        $out .= "PROD-001,V1,MAT-002,V1,1,u\n";
        return $out;
    }

    // ---------------------------------------------------------------
    // IMPORTAR
    // ---------------------------------------------------------------

    public function importFromUpload(array $uploadedFile): array
    {
        $report = [
            'total_rows'     => 0,
            'ok_rows'        => 0,
            'error_rows'     => 0,
            'skipped_rows'   => 0,
            'created_links'  => 0,
            'rows'           => [],
            'fatal_error'    => null,
        ];

        if (!isset($uploadedFile['error']) || (int) $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $report['fatal_error'] = 'No se pudo cargar el archivo. Verifica que sea un CSV válido.';
            return $report;
        }

        $tmpPath = (string) ($uploadedFile['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            $report['fatal_error'] = 'El archivo cargado no es válido.';
            return $report;
        }

        try {
            $parsed = $this->parseCsv($tmpPath);
        } catch (RuntimeException $e) {
            $report['fatal_error'] = $e->getMessage();
            return $report;
        }

        $conn = $this->bomModel->getConnection();

        // Precargar lookups
        $varianteMap  = $this->loadVarianteMap($conn);   // [parte_cod][var_cod] => id
        $unidadMap    = $this->loadUnidadMap($conn);     // [simbolo] => id

        // Grafo en memoria: padre_id -> [hijo_id, ...]  (DB + nuevas filas válidas)
        $graph = $this->loadExistingGraph($conn);

        foreach ($parsed as $entry) {
            $line = (int) $entry['line'];
            $row  = $entry['data'];
            $report['total_rows']++;

            // Resolver IDs
            [$padreId, $hijoId, $cantidad, $unidadId, $rowErrors] =
                $this->resolveRow($row, $varianteMap, $unidadMap);

            if ($rowErrors !== []) {
                $report['error_rows']++;
                $report['rows'][] = [
                    'line' => $line,
                    'status' => 'error',
                    'message' => implode(' | ', $rowErrors)
                ];
                continue;
            }

            // Autoreferencia
            if ($padreId === $hijoId) {
                $report['error_rows']++;
                $report['rows'][] = [
                    'line' => $line,
                    'status' => 'error',
                    'message' => 'Un producto no puede ser componente de sí mismo.'
                ];
                continue;
            }

            // Ciclo (verificar con grafo combinado memoria+DB)
            if ($this->detectCycle($graph, $padreId, $hijoId)) {
                $report['error_rows']++;
                $report['rows'][] = [
                    'line' => $line,
                    'status' => 'error',
                    'message' => 'Bucle detectado: agregar esta relación crearía un ciclo infinito.'
                ];
                continue;
            }

            // Duplicado en DB
            $bom = $this->bomModel->getActiveByVariante($padreId);
            if ($bom && $this->bomModel->isDuplicate((int) $bom['id'], $hijoId)) {
                $report['skipped_rows']++;
                $report['rows'][] = [
                    'line' => $line,
                    'status' => 'skip',
                    'message' => 'Relación ya existe en la BOM (omitida).'
                ];
                // Aun así actualizamos el grafo para no romper ciclos futuros
                $graph[$padreId][] = $hijoId;
                continue;
            }

            // Insertar
            try {
                $bomId = $bom ? (int) $bom['id'] : $this->bomModel->createHeader($padreId);
                $this->bomModel->addDetail($bomId, $hijoId, $cantidad, $unidadId);

                // Actualizar grafo en memoria
                $graph[$padreId][] = $hijoId;

                $report['created_links']++;
                $report['ok_rows']++;
                $report['rows'][] = [
                    'line' => $line,
                    'status' => 'ok',
                    'message' => sprintf(
                        '%s/%s → %s/%s (cant: %s)',
                        $row['padre_parte_codigo'],
                        $row['padre_variante_codigo'],
                        $row['hijo_parte_codigo'],
                        $row['hijo_variante_codigo'],
                        $cantidad
                    )
                ];
            } catch (Throwable $e) {
                $report['error_rows']++;
                $report['rows'][] = [
                    'line' => $line,
                    'status' => 'error',
                    'message' => 'Error al guardar: ' . $e->getMessage()
                ];
            }
        }

        return $report;
    }

    // ---------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------

    /** Detecta si añadir padre→hijo crearía un ciclo en $graph (BFS desde hijo). */
    private function detectCycle(array $graph, int $padreId, int $hijoId): bool
    {
        // Si podemos llegar a $padre partiendo de $hijo, hay ciclo
        $visited = [];
        $queue   = [$hijoId];

        while ($queue !== []) {
            $node = array_shift($queue);
            if ($node === $padreId) {
                return true;
            }
            if (isset($visited[$node])) {
                continue;
            }
            $visited[$node] = true;
            foreach ($graph[$node] ?? [] as $child) {
                $queue[] = $child;
            }
        }
        return false;
    }

    /** Carga todas las aristas BOM activas de DB como lista de adyacencia. */
    private function loadExistingGraph(PDO $conn): array
    {
        $sql = "SELECT CAST(bc.variante_padre_id AS INTEGER)   AS padre_id,
                       CAST(d.variante_componente_id AS INTEGER) AS hijo_id
                FROM bom_cabecera bc
                JOIN bom_detalle d ON d.bom_id = bc.id
                WHERE bc.activa = TRUE";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $graph = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $graph[(int) $r['padre_id']][] = (int) $r['hijo_id'];
        }
        return $graph;
    }

    /** [parte_codigo][variante_codigo] => variante_id */
    private function loadVarianteMap(PDO $conn): array
    {
        $sql = "SELECT v.id, p.codigo AS parte_codigo, v.codigo_variante
                FROM variantes v JOIN partes p ON p.id = v.id_parte";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[(string) $r['parte_codigo']][(string) $r['codigo_variante']] = (int) $r['id'];
        }
        return $map;
    }

    /** [simbolo] => unidad_id */
    private function loadUnidadMap(PDO $conn): array
    {
        $sql = "SELECT id, simbolo FROM unidades_medida WHERE activa = TRUE";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[strtolower((string) $r['simbolo'])] = (int) $r['id'];
        }
        return $map;
    }

    /** Resuelve una fila CSV a IDs concretos. Retorna [padreId, hijoId, cantidad, unidadId, errors[]] */
    private function resolveRow(array $row, array $varianteMap, array $unidadMap): array
    {
        $errors = [];

        $ppCod = trim((string) ($row['padre_parte_codigo']    ?? ''));
        $pvCod = trim((string) ($row['padre_variante_codigo'] ?? ''));
        $hpCod = trim((string) ($row['hijo_parte_codigo']     ?? ''));
        $hvCod = trim((string) ($row['hijo_variante_codigo']  ?? ''));
        $cantStr = trim((string) ($row['cantidad']            ?? ''));
        $umCod   = strtolower(trim((string) ($row['unidad_codigo'] ?? '')));

        $padreId = $varianteMap[$ppCod][$pvCod] ?? null;
        $hijoId  = $varianteMap[$hpCod][$hvCod] ?? null;
        $unidadId = $unidadMap[$umCod] ?? null;

        if ($padreId === null) {
            $errors[] = "Variante padre no encontrada: {$ppCod}/{$pvCod}";
        }
        if ($hijoId === null) {
            $errors[] = "Variante hijo no encontrada: {$hpCod}/{$hvCod}";
        }
        if ($unidadId === null) {
            $errors[] = "Unidad de medida no encontrada: {$umCod}";
        }

        $cantidad = (float) str_replace(',', '.', $cantStr);
        if ($cantidad <= 0.0) {
            $errors[] = "Cantidad inválida: {$cantStr}";
        }

        return [$padreId ?? 0, $hijoId ?? 0, $cantidad, $unidadId ?? 0, $errors];
    }

    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo CSV.');
        }

        $lineNo    = 0;
        $header    = [];
        $rows      = [];
        $delimiter = ',';

        while (($line = fgets($handle)) !== false) {
            $lineNo++;
            $line = preg_replace('/^\xEF\xBB\xBF/', '', (string) $line);
            if (trim($line) === '') {
                continue;
            }

            if ($header === []) {
                $delimiter = substr_count($line, ';') >= substr_count($line, ',') ? ';' : ',';
            }

            $parsed = str_getcsv($line, $delimiter);
            if ($parsed === null) {
                continue;
            }
            $parsed = array_map(static fn($v) => trim((string) $v), $parsed);

            if ($header === []) {
                $header = array_map(fn($h) => strtolower(trim((string) $h)), $parsed);
                $missing = array_diff(self::REQUIRED_HEADERS, $header);
                if ($missing !== []) {
                    fclose($handle);
                    throw new RuntimeException('Faltan columnas: ' . implode(', ', $missing));
                }
                continue;
            }

            $row = [];
            foreach ($header as $i => $col) {
                $row[$col] = $parsed[$i] ?? '';
            }

            if (array_filter($row, static fn($v) => $v !== '') === []) {
                continue;
            }

            $rows[] = ['line' => $lineNo, 'data' => $row];
        }

        fclose($handle);

        if ($header === []) {
            throw new RuntimeException('El archivo no contiene cabecera válida.');
        }

        return $rows;
    }

    private function csvEscape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }
}
