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
            $payload = [
                'status' => 'error',
                'message' => $isDebug ? $throwable->getMessage() : 'Internal Server Error',
            ];

            return Response::json($payload, 500);
        }
    }
}
