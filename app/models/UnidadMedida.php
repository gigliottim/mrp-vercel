<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class UnidadMedida extends BaseTenantModel
{
    private const SYSTEM_SYMBOLS_BY_TYPE = [
        'longitud' => ['m', 'ml'],
        'superficie' => ['m²'],
        'masa' => ['kg'],
        'unidad' => ['u', 'caja', 'rollo', 'bobina'],
    ];

    protected function getTable(): string
    {
        return 'unidades_medida';
    }

    public function byTipo(?string $tipo = null): array
    {
        if ($tipo === null) {
            return $this->all(200, 0);
        }

        $stmt = $this->connection->prepare(
            'SELECT * FROM unidades_medida
                        WHERE tipo = :tipo
                            AND activo = TRUE
            ORDER BY unidad'
        );
        $stmt->execute(['tipo' => $tipo]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function allActive(int $limit = 500, int $offset = 0): array
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->getTable()}
            WHERE activo = TRUE
            ORDER BY tipo ASC, unidad ASC
            LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sobrescribe el método all() para ordenar alfabéticamente por tipo y luego por unidad
     */
    public function all(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->getTable()}
            ORDER BY tipo ASC, unidad ASC
            LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Encuentra la unidad base de un tipo específico, excluyendo opcionalmente un ID
     */
    public function findBaseByTipo(string $tipo, ?int $excludeId = null): ?array
    {
        $sql = 'SELECT * FROM unidades_medida
                WHERE tipo = :tipo
                AND es_base = TRUE';
        $params = ['tipo' => $tipo];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    public function isProtectedById(int $id): bool
    {
        $record = $this->find($id);
        if ($record === null) {
            return false;
        }

        return $this->isProtectedRecord($record);
    }

    public function decorateEffectiveFlags(array $units): array
    {
        foreach ($units as &$unit) {
            $isProtected = $this->isProtectedRecord($unit);
            $unit['_is_system_effective'] = $isProtected;
            $unit['_is_locked_effective'] = $isProtected;
        }
        unset($unit);

        return $units;
    }

    public function hasReferences(int $id): bool
    {
        $sql = "
            SELECT table_name, column_name
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name <> 'unidades_medida'
              AND (
                    column_name = 'unidad_medida_id'
                    OR column_name LIKE 'id_um_%'
              )
        ";

        $stmt = $this->connection->query($sql);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $column) {
            $table = $column['table_name'] ?? '';
            $field = $column['column_name'] ?? '';

            if ($table === '' || $field === '') {
                continue;
            }

            $query = sprintf(
                'SELECT 1 FROM %s WHERE %s = :id LIMIT 1',
                $this->quoteIdentifier($table),
                $this->quoteIdentifier($field)
            );

            $checkStmt = $this->connection->prepare($query);
            $checkStmt->execute(['id' => $id]);

            if ($checkStmt->fetchColumn() !== false) {
                return true;
            }
        }

        return false;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function isProtectedRecord(array $record): bool
    {
        if (((int) ($record['is_system'] ?? 0) === 1) || ((int) ($record['locked'] ?? 0) === 1)) {
            return true;
        }

        $tipo = strtolower(trim((string) ($record['tipo'] ?? '')));
        $simbolo = strtolower(trim((string) ($record['simbolo'] ?? '')));

        if ($tipo === '' || $simbolo === '') {
            return false;
        }

        $allowed = self::SYSTEM_SYMBOLS_BY_TYPE[$tipo] ?? [];
        return in_array($simbolo, $allowed, true);
    }
}
