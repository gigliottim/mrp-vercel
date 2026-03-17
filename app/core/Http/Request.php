<?php

declare(strict_types=1);

namespace App\Core\Http;

final class Request
{
    public string $method;
    public string $uri;
    public array $query;
    public array $body;
    public array $headers;
    public array $server;

    public function __construct(
        string $method,
        string $uri,
        array $query = [],
        array $body = [],
        array $headers = [],
        array $server = []
    ) {
        $this->method = $method;
        $this->uri = $uri;
        $this->query = $query;
        $this->body = $body;
        $this->headers = $headers;
        $this->server = $server;
    }
    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';

        // Ajuste para soportar subdirectorios basado en APP_URL
        if (function_exists('config')) {
            $appUrl = config('app.url', '');
            if ($appUrl) {
                $path = parse_url($appUrl, PHP_URL_PATH);
                if ($path && $path !== '/' && str_starts_with($uri, $path)) {
                    $uri = substr($uri, strlen($path));
                    if ($uri === '' || !str_starts_with($uri, '/')) {
                        $uri = '/' . $uri;
                    }
                }
            }
        }

        $query = $_GET ?? [];
        $body = $_POST ?? [];

        if ($method === 'POST') {
            $override = $body['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null;
            if (is_string($override) && $override !== '') {
                $method = strtoupper($override);
                unset($body['_method']);
            }
        }
        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];

        $payload = file_get_contents('php://input');
        if ($payload !== false && $payload !== '' && self::isJsonRequest($headers)) {
            $json = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $body = array_merge($body, $json);
            }
        }

        if ($method !== 'POST' && isset($body['_method'])) {
            unset($body['_method']);
        }

        return new self($method, $uri, $query, $body, $headers, $_SERVER);
    }

    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    private static function isJsonRequest(array $headers): bool
    {
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
        return str_contains($contentType, 'application/json');
    }
}
