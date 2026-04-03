<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * Cliente de Valkey (wrapper para la extensión valkey de PHP)
 *
 * Esta clase envuelve la extensión nativa valkey de PHP y proporciona
 * una interfaz consistente para operaciones con Valkey.
 */
class ValkeyClient
{
    private \ValkeyClient $client;
    private bool $connected = false;

    public function __construct()
    {
        $this->client = new \ValkeyClient();
    }

    /**
     * Conectar al servidor Valkey
     */
    public function connect(string $host, int $port, float $timeout): bool
    {
        try {
            $this->client->connect($host, $port, $timeout);
            $this->connected = true;
            return true;
        } catch (\Exception $e) {
            $this->connected = false;
            throw $e;
        }
    }

    /**
     * Autenticar con contraseña
     */
    public function auth(string $password): bool
    {
        return $this->client->auth($password);
    }

    /**
     * Hacer ping al servidor
     */
    public function ping(): string
    {
        return $this->client->ping();
    }

    /**
     * Obtener valor por clave
     */
    public function get(string $key): ?string
    {
        return $this->client->get($key);
    }

    /**
     * Guardar valor con TTL
     */
    public function setex(string $key, int $ttl, string $value): bool
    {
        return $this->client->setex($key, $ttl, $value);
    }

    /**
     * Guardar valor
     */
    public function set(string $key, string $value): bool
    {
        return $this->client->set($key, $value);
    }

    /**
     * Eliminar clave
     */
    public function del(string $key): int
    {
        return $this->client->del($key);
    }

    /**
     * Obtener hash por clave
     */
    public function hGetAll(string $key): array
    {
        return $this->client->hGetAll($key);
    }

    /**
     * Guardar múltiples campos en un hash
     */
    public function hMSet(string $key, array $fieldValues): bool
    {
        return $this->client->hMSet($key, $fieldValues);
    }

    /**
     * Obtener valor de un campo en un hash
     */
    public function hGet(string $key, string $field): ?string
    {
        return $this->client->hGet($key, $field);
    }

    /**
     * Establecer TTL en una clave
     */
    public function expire(string $key, int $ttl): bool
    {
        return $this->client->expire($key, $ttl);
    }

    /**
     * Verificar si está conectado
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Cerrar la conexión
     */
    public function close(): bool
    {
        $this->connected = false;
        return $this->client->close();
    }
}
