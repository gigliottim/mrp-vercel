<?php

declare(strict_types=1);

namespace App\Models;

final class TipoParte extends BaseTenantModel
{
    protected function getTable(): string
    {
        return 'tipos_partes';
    }

    public function activos(): array
    {
        $stmt = $this->connection->query('SELECT * FROM tipos_partes ORDER BY orden, nombre');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
