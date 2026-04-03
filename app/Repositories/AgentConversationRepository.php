<?php

declare(strict_types=1);

namespace App\Repositories;

use Exception;
use App\Core\Database\ValkeyClient;

/**
 * Repository para la conversación del Agente AI
 */
final class AgentConversationRepository
{
    private ?ValkeyClient $valkey;
    private string $prefix;
    private bool $enabled;

    public function __construct()
    {
        $valkeyConfig = config('valkey');
        $this->enabled = $valkeyConfig['enabled'] ?? true;

        if (!$this->enabled) {
            $this->valkey = null;
            $this->prefix = $valkeyConfig['prefix'] ?? 'mrp:agent:';
            return;
        }

        $this->valkey = new ValkeyClient();

        try {
            $this->valkey->connect(
                $valkeyConfig['host'],
                (int)$valkeyConfig['port'],
                2.0  // timeout de 2 segundos
            );

            if ($password = $valkeyConfig['password']) {
                $this->valkey->auth($password);
            }
        } catch (Exception $e) {
            // Si Valkey no está disponible, continuar sin caché
            $this->valkey = null;
            error_log("Valkey connection failed: " . $e->getMessage());
        }

        $this->prefix = $valkeyConfig['prefix'] ?? 'mrp:agent:';
    }

    /**
     * Obtener conversación por ID
     */
    public function findById(int $id): ?array
    {
        // Aquí iría la lógica para obtener la conversación de la base de datos
        return null;
    }

    /**
     * Obtener conversación por usuario y tenant
     */
    public function findByUserAndTenant(int $userId, int $tenantId): ?array
    {
        // Aquí iría la lógica para obtener la conversación de la base de datos
        return null;
    }

    /**
     * Crear una nueva conversación
     */
    public function create(array $data): ?int
    {
        // Aquí iría la lógica para crear la conversación en la base de datos
        return null;
    }

    /**
     * Actualizar una conversación
     */
    public function update(int $id, array $data): bool
    {
        // Aquí iría la lógica para actualizar la conversación en la base de datos
        return false;
    }

    /**
     * Eliminar una conversación
     */
    public function delete(int $id): bool
    {
        // Aquí iría la lógica para eliminar la conversación de la base de datos
        return false;
    }

    /**
     * Guardar un mensaje
     */
    public function saveMessage(array $data): ?int
    {
        // Aquí iría la lógica para guardar el mensaje en la base de datos
        return null;
    }

    /**
     * Obtener mensajes por conversación
     */
    public function getMessagesByConversation(string $convId): array
    {
        // Aquí iría la lógica para obtener los mensajes de la base de datos
        return [];
    }

    /**
     * Guardar log de llamada a IA
     */
    public function saveAiLog(array $data): ?int
    {
        // Aquí iría la lógica para guardar el log en la base de datos
        return null;
    }

    /**
     * Obtener respuesta de la caché de Valkey
     */
    public function getCachedResponse(string $promptHash): ?array
    {
        if (!$this->valkey) {
            return null;
        }

        $key = $this->prefix . "ai:response:{$promptHash}";
        $cached = $this->valkey->get($key);

        if (!$cached) {
            return null;
        }

        return json_decode($cached, true);
    }

    /**
     * Guardar respuesta en la caché de Valkey
     */
    public function setCachedResponse(string $promptHash, array $data): void
    {
        if (!$this->valkey) {
            return;
        }

        $key = $this->prefix . "ai:response:{$promptHash}";
        $ttl = config('valkey')['ttl']['response'];
        $this->valkey->setex($key, $ttl, json_encode($data));
    }

    /**
     * Invalidar respuesta de la caché de Valkey
     */
    public function invalidateCachedResponse(string $promptHash): void
    {
        if (!$this->valkey) {
            return;
        }

        $key = $this->prefix . "ai:response:{$promptHash}";
        $this->valkey->del($key);
    }

    /**
     * Obtener sesiones activas de Valkey
     */
    public function getActiveSessions(int $tenantId, int $userId): array
    {
        if (!$this->valkey) {
            return [];
        }

        $key = $this->prefix . "session:{$userId}:{$tenantId}";
        $session = $this->valkey->hGetAll($key);

        if (!$session) {
            return [];
        }

        return $session;
    }

    /**
     * Guardar sesión en Valkey
     */
    public function saveSession(string $key, array $data, int $ttl = 1800): void
    {
        if (!$this->valkey) {
            return;
        }

        $fullKey = $this->prefix . $key;
        $this->valkey->hMSet($fullKey, $data);
        $this->valkey->expire($fullKey, $ttl);
    }

    /**
     * Invalidar sesión de Valkey
     */
    public function invalidateSession(string $key): void
    {
        if (!$this->valkey) {
            return;
        }

        $fullKey = $this->prefix . $key;
        $this->valkey->del($fullKey);
    }
}
