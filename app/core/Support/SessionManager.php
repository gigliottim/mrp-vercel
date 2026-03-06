<?php

declare(strict_types=1);

namespace App\Core\Support;

final class SessionManager
{
    private const DEFAULT_SAVE_DIR = 'storage/sessions';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $savePath = base_path(self::DEFAULT_SAVE_DIR);
        if (is_dir($savePath) === false) {
            mkdir($savePath, 0775, true);
        }

        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $sessionName = (string) config('app.session_name', 'MRPSESSID');

        session_name($sessionName);
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => $secure,
            'cookie_samesite' => 'Strict',
            'cookie_path' => '/',
            'save_path' => $savePath,
        ]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function flush(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        self::regenerate();
    }
}
