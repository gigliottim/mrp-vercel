<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\TipoDeposito;
use App\Models\TipoDepositoMovimiento;

final class DepositosValidacionesController extends Controller
{
    private TipoDeposito $tiposDeposito;
    private TipoDepositoMovimiento $movimientos;

    public function __construct(
        ?TipoDeposito $tiposDeposito = null,
        ?TipoDepositoMovimiento $movimientos = null
    ) {
        $this->tiposDeposito = $tiposDeposito ?? new TipoDeposito();
        $this->movimientos = $movimientos ?? new TipoDepositoMovimiento();
    }

    /**
     * Muestra la página de configuración de validaciones de movimientos
     */
    public function index(Request $request): Response
    {
        $tiposActivos = $this->tiposDeposito->activos();
        $movimientosConfig = $this->movimientos->getAll();

        // Agrupar movimientos por origen para facilitar la visualización
        $movimientosPorOrigen = [];
        foreach ($movimientosConfig as $mov) {
            $origenId = $mov['tipo_deposito_origen_id'];
            if (!isset($movimientosPorOrigen[$origenId])) {
                $movimientosPorOrigen[$origenId] = [
                    'origen' => [
                        'id' => $origenId,
                        'codigo' => $mov['origen_codigo'],
                        'nombre' => $mov['origen_nombre'],
                    ],
                    'destinos' => [],
                ];
            }
            $movimientosPorOrigen[$origenId]['destinos'][] = [
                'id' => $mov['tipo_deposito_destino_id'],
                'codigo' => $mov['destino_codigo'],
                'nombre' => $mov['destino_nombre'],
                'activo' => (bool) $mov['activo'],
                'observaciones' => $mov['observaciones'],
            ];
        }

        return $this->render('pages/admin/catalogo/depositos/validaciones', [
            'title' => 'Validaciones de Movimientos entre Depósitos',
            'tiposDeposito' => $tiposActivos,
            'movimientosPorOrigen' => $movimientosPorOrigen,
            'errors' => [],
            'success' => $request->query['success'] ?? null,
        ]);
    }

    /**
     * Muestra el formulario para configurar destinos de un origen específico
     */
    public function edit(Request $request, $id): Response
    {
        $id = (int) $id;
        $tipoOrigen = $this->tiposDeposito->find($id);

        if ($tipoOrigen === null) {
            return Response::redirect(url('/configuracion/depositos-validaciones'));
        }

        $tiposActivos = $this->tiposDeposito->activos();
        $destinosActuales = $this->movimientos->getByOrigen($id);

        // Crear array de IDs de destinos actuales para marcar los checkboxes
        $destinosPermitidosIds = array_column($destinosActuales, 'tipo_deposito_destino_id');

        return $this->render('pages/admin/catalogo/depositos/validaciones-form', [
            'title' => 'Configurar Destinos Permitidos',
            'tipoOrigen' => $tipoOrigen,
            'tiposDeposito' => $tiposActivos,
            'destinosPermitidosIds' => $destinosPermitidosIds,
            'errors' => [],
            'old' => [],
        ]);
    }

    /**
     * Actualiza la configuración de destinos permitidos para un origen
     */
    public function update(Request $request, $id): Response
    {
        $id = (int) $id;
        $tipoOrigen = $this->tiposDeposito->find($id);

        if ($tipoOrigen === null) {
            return Response::redirect(url('/configuracion/depositos-validaciones'));
        }

        // Obtener los destinos seleccionados
        $destinosIds = $request->body['destinos'] ?? [];

        // Validar que sean IDs válidos
        if (!is_array($destinosIds)) {
            $destinosIds = [];
        }

        // Filtrar solo IDs numéricos válidos y diferentes del origen
        $destinosIds = array_filter(
            array_map('intval', $destinosIds),
            fn($destinoId) => $destinoId > 0 && $destinoId !== $id
        );

        $observaciones = trim($request->body['observaciones'] ?? '');
        $observaciones = $observaciones !== '' ? $observaciones : null;

        // Guardar la configuración
        $success = $this->movimientos->setDestinosPermitidos($id, $destinosIds, $observaciones);

        if (!$success) {
            $tiposActivos = $this->tiposDeposito->activos();
            return $this->render('pages/admin/catalogo/depositos/validaciones-form', [
                'title' => 'Configurar Destinos Permitidos',
                'tipoOrigen' => $tipoOrigen,
                'tiposDeposito' => $tiposActivos,
                'destinosPermitidosIds' => $destinosIds,
                'errors' => ['general' => 'Error al guardar la configuración'],
                'old' => $request->body,
            ]);
        }

        return Response::redirect(url('/configuracion/depositos-validaciones?success=1'));
    }

    /**
     * Elimina todos los movimientos configurados para un origen
     */
    public function destroy(Request $request, $id): Response
    {
        $id = (int) $id;
        $this->movimientos->deleteByOrigen($id);
        return Response::redirect(url('/configuracion/depositos-validaciones?success=1'));
    }

    /**
     * API: Obtiene los tipos de depósito destino permitidos para un origen
     * Útil para cargar dinámicamente los select de destino en formularios
     */
    public function getDestinosPermitidos(Request $request, $origenId): Response
    {
        $origenId = (int) $origenId;
        $destinos = $this->movimientos->getDestinosPermitidos($origenId);

        return Response::json([
            'success' => true,
            'destinos' => $destinos,
        ]);
    }

    /**
     * API: Valida si un movimiento está permitido
     */
    public function validarMovimiento(Request $request): Response
    {
        $origenId = (int) ($request->body['origen_id'] ?? 0);
        $destinoId = (int) ($request->body['destino_id'] ?? 0);

        if ($origenId <= 0 || $destinoId <= 0) {
            return Response::json([
                'success' => false,
                'permitido' => false,
                'mensaje' => 'IDs inválidos',
            ], 400);
        }

        $permitido = $this->movimientos->isMovimientoPermitido($origenId, $destinoId);

        return Response::json([
            'success' => true,
            'permitido' => $permitido,
            'mensaje' => $permitido
                ? 'Movimiento permitido'
                : 'Movimiento no permitido entre estos tipos de depósito',
        ]);
    }
}
