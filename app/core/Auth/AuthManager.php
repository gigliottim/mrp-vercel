<?php

declare(strict_types=1);

namespace App\Core\Auth;

use App\Core\Support\SessionManager;

final class AuthManager
{
    private const SESSION_KEY = 'auth';

    public static function login(array $payload): void
    {
        SessionManager::start();
        $_SESSION[self::SESSION_KEY] = $payload;
        TenantContext::set($payload['tenant'] ?? null);
        SessionManager::regenerate();
    }

    public static function logout(): void
    {
        SessionManager::start();
        unset($_SESSION[self::SESSION_KEY]);
        TenantContext::set(null);
        SessionManager::regenerate();
    }

    public static function check(): bool
    {
        SessionManager::start();
        return isset($_SESSION[self::SESSION_KEY]['user']);
    }

    public static function user(): ?array
    {
        SessionManager::start();
        return $_SESSION[self::SESSION_KEY]['user'] ?? null;
    }

    public static function tenant(): ?array
    {
        SessionManager::start();
        return $_SESSION[self::SESSION_KEY]['tenant'] ?? null;
    }

    public static function permissions(): array
    {
        SessionManager::start();
        return $_SESSION[self::SESSION_KEY]['permissions'] ?? [];
    }
}
