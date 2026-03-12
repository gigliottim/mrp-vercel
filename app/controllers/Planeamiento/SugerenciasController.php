<?php

declare(strict_types=1);

namespace App\Controllers\Planeamiento;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Services\Planeamiento\SugerenciasService;

final class SugerenciasController extends Controller
{
    private SugerenciasService $service;

    public function __construct(?SugerenciasService $service = null)
    {
        $this->service = $service ?? new SugerenciasService();
    }

    public function index(Request $request): Response
    {
        $filtro = $request->query['filtro'] ?? null;
        if (!in_array($filtro, ['fabricable', 'parcial', 'sin_stock'], true)) {
            $filtro = null;
        }

        $resultado = $this->service->analizar($filtro);

        return $this->render('pages/planeamiento/sugerencias', [
            'title'     => 'Sugerencias de Fabricación',
            'variantes' => $resultado['variantes'],
            'resumen'   => $resultado['resumen'],
            'filtro'    => $filtro,
        ]);
    }
}
