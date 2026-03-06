<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

final class MiddlewarePipeline
{
    /** @var array<int, MiddlewareInterface> */
    private array $middleware;

    /** @param array<int, MiddlewareInterface> $middleware */
    public function __construct(array $middleware = [])
    {
        $this->middleware = $middleware;
    }

    public function process(Request $request, callable $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            function (callable $next, MiddlewareInterface $middleware) {
                return function (Request $request) use ($middleware, $next) {
                    return $middleware->handle($request, $next);
                };
            },
            $destination
        );

        return $pipeline($request);
    }
}
