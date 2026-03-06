<?php

declare(strict_types=1);

namespace App\Core\View;

use App\Core\Http\Response;
use App\Core\Logging\Logger;
use RuntimeException;

final class View
{
    public const DEFAULT_LAYOUT = 'layouts/app';

    public static function render(string $template, array $data = [], ?string $layout = self::DEFAULT_LAYOUT): void
    {
        self::make($template, $data, $layout)->send();
    }

    public static function make(string $template, array $data = [], ?string $layout = self::DEFAULT_LAYOUT): Response
    {
        $viewPath = base_path('views/' . $template . '.php');
        if (file_exists($viewPath) === false) {
            throw new RuntimeException(sprintf('View [%s] not found.', $template));
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $viewPath;
        $content = ob_get_clean() ?: '';

        if ($layout === null) {
            return Response::html($content);
        }

        $layoutPath = base_path('views/' . $layout . '.php');
        if (file_exists($layoutPath) === false) {
            Logger::getInstance()->error('Layout not found', ['layout' => $layout]);
            return Response::html($content);
        }

        $viewContent = $content;
        ob_start();
        $content = $viewContent;
        include $layoutPath;
        $layoutContent = ob_get_clean() ?: '';

        return Response::html($layoutContent);
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
