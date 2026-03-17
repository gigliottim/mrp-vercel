<?php

declare(strict_types=1);

namespace App\Core\Http;

final class Response
{
    public function __construct(
        private string $content,
        private int $status = 200,
        private array $headers = []
    ) {}

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        $headers = array_merge($headers, ['Content-Type' => 'application/json; charset=utf-8']);
        return new self(json_encode($data, JSON_UNESCAPED_UNICODE), $status, $headers);
    }

    public static function html(string $content, int $status = 200, array $headers = []): self
    {
        $headers = array_merge($headers, ['Content-Type' => 'text/html; charset=utf-8']);
        return new self($content, $status, $headers);
    }

    public static function redirect(string $url, int $status = 302, array $headers = []): self
    {
        $headers = array_merge($headers, ['Location' => $url]);
        return new self('', $status, $headers);
    }

    public function send(): void
    {
        // Evitar corrupcion de descargas binarias por output residual (warnings/notices/BOM)
        $contentType = strtolower((string) ($this->headers['Content-Type'] ?? ''));
        $isBinaryDownload = str_contains($contentType, 'application/pdf')
            || str_contains($contentType, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        if ($isBinaryDownload) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        http_response_code($this->status);
        foreach ($this->headers as $key => $value) {
            header(sprintf('%s: %s', $key, $value));
        }
        echo $this->content;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
