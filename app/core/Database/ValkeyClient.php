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
    private ?\ValkeyClient $client = null;
    private bool $connected = false;

    public function __construct()
    {
        if (!class_exists(\ValkeyClient::class)) {
            error_log('ValkeyClient: extensión \ValkeyClient no disponible en este entorno.');
            $this->connected = false;
            return;
        }

        $this->client = new \ValkeyClient();
    }

    private function ensureClient(): void
    {
        if ($this->client === null) {
            throw new \RuntimeException('La extensión \ValkeyClient no está disponible.');
        }
    }

    /**
     * Conectar al servidor Valkey
     */
    public function connect(string $host, int $port, float $timeout): bool
    {
        try {
            $this->ensureClient();
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
        $this->ensureClient();
        return $this->client->auth($password);
    }

    /**
     * Hacer ping al servidor
     */
    public function ping(): string
    {
        $this->ensureClient();
        return $this->client->ping();
    }

    /**
     * Obtener valor por clave
     */
    public function get(string $key): ?string
    {
        $this->ensureClient();
        return $this->client->get($key);
    }

    /**
     * Guardar valor con TTL
     */
    public function setex(string $key, int $ttl, string $value): bool
    {
        $this->ensureClient();
        return $this->client->setex($key, $ttl, $value);
    }

    /**
     * Guardar valor
     */
    public function set(string $key, string $value): bool
    {
        $this->ensureClient();
        return $this->client->set($key, $value);
    }

    /**
     * Eliminar clave
     */
    public function del(string $key): int
    {
        $this->ensureClient();
        return $this->client->del($key);
    }

    /**
     * Obtener hash por clave
     */
    public function hGetAll(string $key): array
    {
        $this->ensureClient();
        return $this->client->hGetAll($key);
    }

    /**
     * Guardar múltiples campos en un hash
     */
    public function hMSet(string $key, array $fieldValues): bool
    {
        $this->ensureClient();
        return $this->client->hMSet($key, $fieldValues);
    }

    /**
     * Obtener valor de un campo en un hash
     */
    public function hGet(string $key, string $field): ?string
    {
        $this->ensureClient();
        return $this->client->hGet($key, $field);
    }

    /**
     * Establecer TTL en una clave
     */
    public function expire(string $key, int $ttl): bool
    {
        $this->ensureClient();
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
        $this->ensureClient();
        return $this->client->close();
    }
}
