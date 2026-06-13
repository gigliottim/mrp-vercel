<?php

declare(strict_types=1);

namespace App\AgenteAI\Backend\Repositories;

use Exception;
use PDO;
use App\Core\Auth\TenantContext;
use App\Core\Database\DatabaseManager;
use App\Core\Database\ValkeyClient;

/**
 * Repository para la conversación del Agente AI
 * Valkey: caché de respuestas y sesiones
 * PostgreSQL: persistencia de conversaciones, mensajes y logs
 */
final class ConversationRepository
{
    private ?ValkeyClient $valkey;
    private ?PDO $db;
    private string $prefix;
    private bool $enabled;
    private ?string $resolvedConnectionName = null;

    public function __construct()
    {
        // --- Valkey (caché) ---
        $valkeyConfig = config('valkey');
        $this->enabled = $valkeyConfig['enabled'] ?? true;

        if (!$this->enabled) {
            $this->valkey = null;
            $this->prefix = $valkeyConfig['prefix'] ?? 'mrp:agent:';
        } else {
            $this->valkey = new ValkeyClient();
            try {
                $this->valkey->connect(
                    $valkeyConfig['host'],
                    (int) $valkeyConfig['port'],
                    2.0
                );
                if ($password = $valkeyConfig['password']) {
                    $this->valkey->auth($password);
                }
            } catch (Exception $e) {
                $this->valkey = null;
                error_log("Valkey connection failed: " . $e->getMessage());
            }
            $this->prefix = $valkeyConfig['prefix'] ?? 'mrp:agent:';
        }

        // --- PostgreSQL (persistencia) ---
        try {
            $this->db = $this->resolveTenantConnection();
        } catch (Exception $e) {
            $this->db = null;
            error_log("Database connection failed in ConversationRepository: " . $e->getMessage());
        }
    }

    private function resolveTenantConnection(): ?PDO
    {
        $tenant = TenantContext::get();
        error_log("ConversationRepository::resolveTenantConnection - tenant: " . ($tenant ? json_encode($tenant) : 'NULL'));

        if ($tenant === null || empty($tenant['database']['name'])) {
            error_log("ConversationRepository::resolveTenantConnection - falling back to 'tenant' connection");
            return DatabaseManager::connection('tenant');
        }

        $baseConfig = config('database.connections.tenant');
        $overrides = $tenant['database'];
        $connectionName = 'tenant_' . $overrides['name'];
        error_log("ConversationRepository::resolveTenantConnection - connectionName: {$connectionName}, dbname: {$overrides['name']}");

        $existingConnections = config('database.connections', []);
        if (!isset($existingConnections[$connectionName])) {
            $existingConnections[$connectionName] = [
                'driver' => $baseConfig['driver'] ?? 'pgsql',
                'host' => $overrides['host'] ?? $baseConfig['host'] ?? '127.0.0.1',
                'port' => $overrides['port'] ?? $baseConfig['port'] ?? '5432',
                'database' => $overrides['name'] ?? $baseConfig['database'],
                'username' => $overrides['username'] ?? $baseConfig['username'],
                'password' => $overrides['password'] ?? $baseConfig['password'],
                'charset' => $baseConfig['charset'] ?? 'utf8',
                'options' => $baseConfig['options'] ?? [],
            ];
            \App\Core\Config\Config::set('database.connections', $existingConnections);
            error_log("ConversationRepository::resolveTenantConnection - registered new connection: {$connectionName}");
        }

        return DatabaseManager::connection($connectionName);
    }

    /**
     * Obtener la conexión PDO (para uso interno del agente)
     * Resuelve la conexión tenant dinámicamente si TenantContext fue establecido
     * después de la construcción del repositorio.
     */
    public function getDb(): ?PDO
    {
        $tenant = TenantContext::get();
        if ($tenant !== null && !empty($tenant['database']['name'])) {
            $connectionName = 'tenant_' . $tenant['database']['name'];
            if ($this->resolvedConnectionName !== $connectionName) {
                $this->db = $this->resolveTenantConnection();
                $this->resolvedConnectionName = $connectionName;
            }
        }
        return $this->db;
    }

    // ─── Persistencia en PostgreSQL ───────────────────────────────────

    /**
     * Crear una nueva conversación
     */
    public function create(array $data): ?int
    {
        if (!$this->db) return null;

        $stmt = $this->db->prepare(
            "INSERT INTO agent_conversations (tenant_id, user_id, intent, status, metadata, created_at)
             VALUES (?, ?, ?, 'active', ?, NOW()) RETURNING id"
        );
        $stmt->execute([
            $data['tenant_id'] ?? 0,
            $data['user_id'] ?? 0,
            $data['intent'] ?? 'general_query',
            json_encode($data['metadata'] ?? []),
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtener conversación por ID
     */
    public function findById(int $id): ?array
    {
        if (!$this->db) return null;

        $stmt = $this->db->prepare("SELECT * FROM agent_conversations WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtener conversación activa por usuario y tenant
     */
    public function findByUserAndTenant(int $userId, int $tenantId): ?array
    {
        if (!$this->db) return null;

        $stmt = $this->db->prepare(
            "SELECT * FROM agent_conversations WHERE user_id = ? AND tenant_id = ? AND status = 'active'
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$userId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Actualizar una conversación (ej: cambiar status)
     */
    public function update(int $id, array $data): bool
    {
        if (!$this->db) return false;

        $sets = [];
        $values = [];
        foreach ($data as $key => $value) {
            if ($key === 'metadata' && is_array($value)) {
                $sets[] = "$key = ?";
                $values[] = json_encode($value);
            } else {
                $sets[] = "$key = ?";
                $values[] = $value;
            }
        }
        $sets[] = "updated_at = NOW()";
        $values[] = $id;

        $sql = "UPDATE agent_conversations SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Eliminar una conversación
     */
    public function delete(int $id): bool
    {
        if (!$this->db) return false;
        $stmt = $this->db->prepare("DELETE FROM agent_conversations WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Guardar un mensaje
     */
    public function saveMessage(array $data): ?int
    {
        if (!$this->db) return null;

        $stmt = $this->db->prepare(
            "INSERT INTO agent_messages (conversation_id, role, content, metadata, created_at)
             VALUES (?, ?, ?, ?, NOW()) RETURNING id"
        );
        $stmt->execute([
            $data['conversation_id'],
            $data['role'],
            $data['content'],
            json_encode($data['metadata'] ?? []),
        ]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtener mensajes por conversación
     */
    public function getMessagesByConversation(string $convId): array
    {
        if (!$this->db) return [];

        $stmt = $this->db->prepare(
            "SELECT * FROM agent_messages WHERE conversation_id = ? ORDER BY created_at ASC"
        );
        $stmt->execute([$convId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Guardar log de llamada a IA
     */
    public function saveAiLog(array $data): ?int
    {
        if (!$this->db) return null;

        $stmt = $this->db->prepare(
            "INSERT INTO agent_ai_logs (conversation_id, model_used, provider, prompt_hash, response_time_ms, validation_result, tokens_used, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW()) RETURNING id"
        );
        $stmt->execute([
            $data['conversation_id'] ?? null,
            $data['model_used'] ?? null,
            $data['provider'] ?? null,
            $data['prompt_hash'] ?? null,
            $data['response_time_ms'] ?? null,
            $data['validation_result'] ?? 'unknown',
            $data['tokens_used'] ?? null,
        ]);
        return (int) $stmt->fetchColumn();
    }

    // ─── Caché en Valkey ──────────────────────────────────────────────

    /**
     * Obtener respuesta de la caché de Valkey
     */
    public function getCachedResponse(string $promptHash): ?array
    {
        if (!$this->valkey) return null;

        $key = $this->prefix . "ai:response:{$promptHash}";
        $cached = $this->valkey->get($key);
        if (!$cached) return null;

        return json_decode($cached, true);
    }

    /**
     * Guardar respuesta en la caché de Valkey
     */
    public function setCachedResponse(string $promptHash, array $data): void
    {
        if (!$this->valkey) return;

        $key = $this->prefix . "ai:response:{$promptHash}";
        $ttl = config('valkey')['ttl']['response'] ?? 3600;
        $this->valkey->setex($key, $ttl, json_encode($data));
    }

    /**
     * Invalidar respuesta de la caché de Valkey
     */
    public function invalidateCachedResponse(string $promptHash): void
    {
        if (!$this->valkey) return;

        $key = $this->prefix . "ai:response:{$promptHash}";
        $this->valkey->del($key);
    }

    /**
     * Obtener sesiones activas de Valkey
     */
    public function getActiveSessions(int $tenantId, int $userId): array
    {
        if (!$this->valkey) return [];

        $key = $this->prefix . "session:{$userId}:{$tenantId}";
        $session = $this->valkey->hGetAll($key);
        return $session ?: [];
    }

    /**
     * Guardar sesión en Valkey
     */
    public function saveSession(string $key, array $data, int $ttl = 1800): void
    {
        if (!$this->valkey) return;

        $fullKey = $this->prefix . $key;
        $this->valkey->hMSet($fullKey, $data);
        $this->valkey->expire($fullKey, $ttl);
    }

    /**
     * Invalidar sesión de Valkey
     */
    public function invalidateSession(string $key): void
    {
        if (!$this->valkey) return;

        $fullKey = $this->prefix . $key;
        $this->valkey->del($fullKey);
    }
}
