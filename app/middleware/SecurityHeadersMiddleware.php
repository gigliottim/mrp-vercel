<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Middleware\MiddlewareInterface;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: geolocation=(), microphone=()');
        header('Content-Security-Policy: default-src \'self\'; img-src \'self\' data: https:; font-src \'self\' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; connect-src \'self\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com');

        return $response;
    }
}
