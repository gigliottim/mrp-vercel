<?php

declare(strict_types=1);

namespace App\Models;

final class Entidad extends BaseTenantModel
{
    protected function getTable(): string
    {
        return 'entidades';
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM entidades ORDER BY razon_social LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function search(string $query): array
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM entidades WHERE razon_social ILIKE :q LIMIT 20'
        );
        $stmt->execute(['q' => "%$query%"]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
