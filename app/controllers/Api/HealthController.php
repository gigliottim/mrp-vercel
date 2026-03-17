<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;

final class HealthController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return $this->json([
            'status' => 'ok',
            'timestamp' => date(DATE_ATOM),
        ]);
    }
}
