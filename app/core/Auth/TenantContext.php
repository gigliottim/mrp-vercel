<?php

declare(strict_types=1);

namespace App\Core\Auth;

final class TenantContext
{
    private static ?array $tenant = null;

    public static function set(?array $tenant): void
    {
        self::$tenant = $tenant;
    }

    public static function get(): ?array
    {
        return self::$tenant;
    }
}
