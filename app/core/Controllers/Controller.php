<?php

declare(strict_types=1);

namespace App\Core\Controllers;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View\View;

abstract class Controller
{
    protected function render(string $template, array $data = [], ?string $layout = View::DEFAULT_LAYOUT): Response
    {
        return View::make($template, $data, $layout);
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }
}
