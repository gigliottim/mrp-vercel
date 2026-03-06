<?php

declare(strict_types=1);

namespace App\Controllers\Inventario;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\Variante;

final class CriticoController extends Controller
{
    private Variante $variantes;

    public function __construct(?Variante $variantes = null)
    {
        $this->variantes = $variantes ?? new Variante();
    }

    public function index(Request $request): Response
    {
        // Obtener filtro de estado (todos, critico, advertencia, normal)
        $filtroEstado = $request->query['estado'] ?? 'todos';

        // Obtener items según el filtro
        $items = $this->variantes->getStockCritico(
            $filtroEstado === 'todos' ? null : $filtroEstado
        );

        // Obtener estadísticas
        $estadisticas = $this->variantes->getStockCriticoStats();

        return $this->render('pages/inventario/critico', [
            'title' => 'Stock Crítico',
            'items' => $items,
            'estadisticas' => $estadisticas,
            'filtroEstado' => $filtroEstado,
        ]);
    }
}
