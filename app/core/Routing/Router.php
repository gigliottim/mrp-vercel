<?php

declare(strict_types=1);

namespace App\Core\Routing;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Logging\Logger;
use App\Core\Controllers\Controller;
use InvalidArgumentException;

final class Router
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $routes = [];

    public function get(string $uri, callable|array $action): void
    {
        $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, callable|array $action): void
    {
        $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, callable|array $action): void
    {
        $this->addRoute('PUT', $uri, $action);
    }

    public function delete(string $uri, callable|array $action): void
    {
        $this->addRoute('DELETE', $uri, $action);
    }

    private function addRoute(string $method, string $uri, callable|array $action): void
    {
        $method = strtoupper($method);
        $this->routes[$method][] = [
            'uri' => $uri,
            'action' => $action,
            'pattern' => $this->compilePattern($uri),
        ];
    }

    public function dispatch(Request $request): Response
    {
        $method = strtoupper($request->method);
        $uri = rtrim($request->uri, '/') ?: '/';

        $candidates = $this->routes[$method] ?? [];
        foreach ($candidates as $route) {
            $match = $this->matchUri($route['pattern'], $uri);
            if ($match === null) {
                continue;
            }

            $action = $route['action'];
            $params = $match;
            return $this->executeAction($action, $request, $params);
        }

        Logger::getInstance()->warning('Route not found', ['method' => $method, 'uri' => $uri]);
        return Response::json(['status' => 'error', 'message' => 'Route not found'], 404);
    }

    private function executeAction(callable|array $action, Request $request, array $params): Response
    {
        if (is_callable($action)) {
            return $action($request, ...array_values($params));
        }

        if (is_array($action) === false || count($action) !== 2) {
            throw new InvalidArgumentException('Controller action must be [ControllerClass, method]');
        }

        [$controllerClass, $method] = $action;
        if (class_exists($controllerClass) === false) {
            throw new InvalidArgumentException(sprintf('Controller %s not found', $controllerClass));
        }

        $controller = new $controllerClass();
        if ($controller instanceof Controller === false) {
            throw new InvalidArgumentException(sprintf('%s must extend %s', $controllerClass, Controller::class));
        }

        if (method_exists($controller, $method) === false) {
            throw new InvalidArgumentException(sprintf('Method %s::%s not found', $controllerClass, $method));
        }

        return $controller->{$method}($request, ...array_values($params));
    }

    private function compilePattern(string $uri): array
    {
        $uri = rtrim($uri, '/') ?: '/';
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_-]*)\}#', '(?P<$1>[^/]+)', $uri);
        $pattern = str_replace('/', '\\/', $pattern);
        return [
            'regex' => '#^' . $pattern . '$#',
        ];
    }

    private function matchUri(array $pattern, string $uri): ?array
    {
        $matches = [];
        if (preg_match($pattern['regex'], $uri, $matches) !== 1) {
            return null;
        }

        return array_filter(
            $matches,
            fn($key) => is_string($key),
            ARRAY_FILTER_USE_KEY
        );
    }
}
