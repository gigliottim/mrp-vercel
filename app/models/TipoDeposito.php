<?php

declare(strict_types=1);

namespace App\Models;

final class TipoDeposito extends BaseTenantModel
{
    protected function getTable(): string
    {
        return 'tipos_depositos';
    }

    public function activos(): array
    {
        $stmt = $this->connection->query('SELECT * FROM tipos_depositos ORDER BY orden, nombre');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function esSistema(int $id): bool
    {
        $stmt = $this->connection->prepare('SELECT es_sistema FROM tipos_depositos WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? (bool) $result['es_sistema'] : false;
    }
}
