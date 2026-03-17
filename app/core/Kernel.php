<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Logging\Logger;
use App\Core\Middleware\MiddlewarePipeline;
use App\Core\Routing\Router;
use App\Middleware\AuthenticateMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\SessionMiddleware;
use Throwable;

final class Kernel
{
    private Router $router;
    private ?MiddlewarePipeline $pipeline;

    public function __construct(Router $router, ?MiddlewarePipeline $pipeline = null)
    {
        $this->router = $router;
        $this->pipeline = $pipeline;
    }

    public function handle(Request $request): Response
    {
        $pipeline = $this->pipeline ?? new MiddlewarePipeline([
            new SessionMiddleware(),
            new AuthenticateMiddleware(['/', '/login', '/register']),
            new SecurityHeadersMiddleware(),
        ]);

        return $pipeline->process($request, function (Request $request) {
            return $this->dispatch($request);
        });
    }

    private function dispatch(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (Throwable $throwable) {
            Logger::getInstance()->error('Unhandled exception', [
                'message' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            $isDebug = (bool) config('app.debug', false);
            if ($this->expectsJson($request)) {
                $payload = [
                    'status' => 'error',
                    'message' => $isDebug ? $throwable->getMessage() : 'Internal Server Error',
                ];

                return Response::json($payload, 500);
            }

            return $this->renderWebError($throwable, $isDebug);
        }
    }

    private function expectsJson(Request $request): bool
    {
        if (str_starts_with($request->uri, '/api')) {
            return true;
        }

        $headers = array_change_key_case($request->headers, CASE_LOWER);
        $accept = (string) ($headers['accept'] ?? '');
        $requestedWith = (string) ($headers['x-requested-with'] ?? '');

        return str_contains($accept, 'application/json')
            || strcasecmp($requestedWith, 'XMLHttpRequest') === 0;
    }

    private function renderWebError(Throwable $throwable, bool $isDebug): Response
    {
        $message = $isDebug
            ? htmlspecialchars($throwable->getMessage(), ENT_QUOTES, 'UTF-8')
            : 'Ocurrio un error inesperado. Intenta nuevamente en unos minutos.';

        $html = '<!doctype html>'
            . '<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Error interno</title>'
            . '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f5f7fb;color:#0f172a;margin:0;padding:32px}.card{max-width:780px;margin:60px auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;box-shadow:0 8px 30px rgba(2,6,23,.06)}h1{margin:0 0 10px;font-size:24px}.muted{color:#475569}code{display:block;margin-top:12px;padding:12px;border-radius:8px;background:#f8fafc;border:1px solid #e2e8f0;white-space:pre-wrap;word-break:break-word}</style>'
            . '</head><body><div class="card"><h1>Error interno</h1><p class="muted">No se pudo completar la solicitud.</p><code>'
            . $message
            . '</code></div></body></html>';

        return Response::html($html, 500);
    }
}
