<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth\AuthManager;
use App\Core\Auth\TenantContext;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;

final class AuthenticateMiddleware implements MiddlewareInterface
{
    /** @var array<int, string> */
    private array $except;

    /**
     * @param array<int, string> $except
     */
    public function __construct(array $except = ['/login'])
    {
        $this->except = $except;
    }

    public function handle(Request $request, callable $next): Response
    {
        $path = rtrim($request->uri, '/') ?: '/';
        if ($this->isExcepted($path)) {
            return $next($request);
        }

        if (AuthManager::check() === false) {
            return Response::redirect(url('/login'));
        }

        TenantContext::set(AuthManager::tenant());
        return $next($request);
    }

    private function isExcepted(string $path): bool
    {
        if (str_starts_with($path, '/api/')) {
            return true;
        }

        if (
            str_starts_with($path, '/assets/') ||
            str_starts_with($path, '/CDN/') ||
            $path === '/favicon.ico'
        ) {
            return true;
        }

        foreach ($this->except as $excepted) {
            if ($path === $excepted) {
                return true;
            }
        }

        return false;
    }
}
