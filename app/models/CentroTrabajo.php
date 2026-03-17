<?php

declare(strict_types=1);

namespace App\Models;

final class CentroTrabajo extends BaseTenantModel
{
    protected function getTable(): string
    {
        return 'centros_trabajo';
    }

    public function search(string $term = ''): array
    {
        if ($term === '') {
            return $this->all(100, 0);
        }

        $stmt = $this->connection->prepare('SELECT * FROM centros_trabajo WHERE nombre ILIKE :term OR codigo ILIKE :term ORDER BY nombre');
        $stmt->execute(['term' => '%' . $term . '%']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
