<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth\TenantContext;
use App\Core\Database\DatabaseManager;
use PDO;
use RuntimeException;

abstract class BaseTenantModel
{
    protected PDO $connection;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct(?PDO $connection = null)
    {
        $this->connection = $connection ?? $this->resolveConnection();
    }

    abstract protected function getTable(): string;

    protected function resolveConnection(): PDO
    {
        $tenant = TenantContext::get();
        if ($tenant === null || empty($tenant['database']['name'])) {
            throw new RuntimeException('Tenant no inicializado.');
        }

        $config = config('database.connections.tenant');
        if ($config === null) {
            throw new RuntimeException('Conexión tenant no configurada.');
        }

        $overrides = $tenant['database'];
        $connectionName = 'tenant_' . ($tenant['database']['name'] ?? '');
        $this->configureTenantConnection($connectionName, $config, $overrides);

        return DatabaseManager::connection($connectionName);
    }

    private function configureTenantConnection(string $name, array $baseConfig, array $overrides): void
    {
        $connections = config('database.connections', []);
        if (isset($connections[$name])) {
            return;
        }

        $connections[$name] = [
            'driver' => $baseConfig['driver'] ?? 'pgsql',
            'host' => $overrides['host'] ?? $baseConfig['host'] ?? '127.0.0.1',
            'port' => $overrides['port'] ?? $baseConfig['port'] ?? '5432',
            'database' => $overrides['name'] ?? $baseConfig['database'],
            'username' => $overrides['username'] ?? $baseConfig['username'],
            'password' => $overrides['password'] ?? $baseConfig['password'],
            'charset' => $baseConfig['charset'] ?? 'utf8',
            'options' => $baseConfig['options'] ?? [],
        ];

        \App\Core\Config\Config::set('database.connections', $connections);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->connection->prepare("SELECT * FROM {$this->getTable()} WHERE {$this->primaryKey} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        return $record === false ? null : $record;
    }

    public function all(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->connection->prepare("SELECT * FROM {$this->getTable()} ORDER BY {$this->primaryKey} DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn($column) => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->getTable(),
            implode(',', $columns),
            implode(',', $placeholders)
        );

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($data);

        return (int) $this->connection->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $assignments = implode(',', array_map(static fn($column) => $column . ' = :' . $column, array_keys($data)));
        $sql = sprintf('UPDATE %s SET %s WHERE %s = :id', $this->getTable(), $assignments, $this->primaryKey);

        $stmt = $this->connection->prepare($sql);
        $data['id'] = $id;
        return $stmt->execute($data);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->connection->prepare("DELETE FROM {$this->getTable()} WHERE {$this->primaryKey} = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
