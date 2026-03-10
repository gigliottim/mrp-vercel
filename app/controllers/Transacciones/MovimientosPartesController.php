<?php

declare(strict_types=1);

namespace App\Controllers\Transacciones;

use App\Core\Controllers\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Models\Compra;
use App\Models\Entidad;
use App\Models\MovimientoStock;
use App\Models\TipoDeposito;
use App\Models\TipoDepositoMovimiento;
use App\Models\UnidadMedida;
use App\Models\Variante;
use App\Services\StockService;
use App\Services\UnitConversionService;

final class MovimientosPartesController extends Controller
{
    private TipoDeposito $tiposDeposito;
    private TipoDepositoMovimiento $movimientosConfig;
    private MovimientoStock $movimientosInfo;
    private UnidadMedida $unidadesMedida;
    private StockService $stockService;
    private Entidad $entidades;
    private Compra $compras;
    private Variante $variantes;
    private UnitConversionService $unitConversion;

    public function __construct(
        ?TipoDeposito $tiposDeposito = null,
        ?TipoDepositoMovimiento $movimientosConfig = null,
        ?\App\Models\MovimientoStock $movimientosInfo = null,
        ?UnidadMedida $unidadesMedida = null,
        ?StockService $stockService = null,
        ?Entidad $entidades = null,
        ?Compra $compras = null,
        ?Variante $variantes = null,
        ?UnitConversionService $unitConversion = null
    ) {
        $this->tiposDeposito = $tiposDeposito ?? new TipoDeposito();
        $this->movimientosConfig = $movimientosConfig ?? new TipoDepositoMovimiento();
        $this->movimientosInfo = $movimientosInfo ?? new MovimientoStock();
        $this->unidadesMedida = $unidadesMedida ?? new UnidadMedida();
        $this->stockService = $stockService ?? new StockService();
        $this->entidades = $entidades ?? new Entidad();
        $this->compras = $compras ?? new Compra();
        $this->variantes = $variantes ?? new Variante();
        $this->unitConversion = $unitConversion ?? new UnitConversionService();
    }

    public function index(Request $request): Response
    {
        // Obtener tipos de depósito activos
        $tiposDeposito = $this->tiposDeposito->activos();

        // Obtener configuración de movimientos permitidos
        $movimientosConfig = $this->movimientosConfig->getAll();

        // Crear un mapa de destinos permitidos por cada origen
        $destinosPorOrigen = [];
        foreach ($movimientosConfig as $mov) {
            $origenId = $mov['tipo_deposito_origen_id'];
            if (!isset($destinosPorOrigen[$origenId])) {
                $destinosPorOrigen[$origenId] = [];
            }
            if ($mov['activo']) {
                $destinosPorOrigen[$origenId][] = $mov['tipo_deposito_destino_id'];
            }
        }

        // Obtener unidades de medida
        $unidadesMedida = $this->unidadesMedida->all();

        // Obtener entidades (proveedores/clientes) para listado rápido
        $entidades = $this->entidades->all(100);

        // Obtener últimos movimientos
        $recentMovimientos = $this->movimientosInfo->getRecentWithDetails(10);

        return $this->render('pages/transacciones/movimientos-partes', [
            'title' => 'Movimientos de Partes',
            'tiposDeposito' => $tiposDeposito,
            'destinosPorOrigen' => $destinosPorOrigen,
            'unidadesMedida' => $unidadesMedida,
            'entidades' => $entidades,
            'movimientos' => $recentMovimientos
        ]);
    }

    public function store(Request $request): Response
    {
        // Validar datos básicos
        $data = $request->body;
        $movimientoId = (int) ($data['movimiento_id'] ?? 0);

        $idVariante = (int)($data['parte'] ?? 0);
        $cantidad = (float)($data['cantidad'] ?? 0);
        $origenId = (int)($data['deposito_origen'] ?? 0);
        $destinoId = (int)($data['deposito_destino'] ?? 0);
        $entidadId = !empty($data['proveedor_cliente']) ? (int)$data['proveedor_cliente'] : null;

        // Datos adicionales
        $importeTotal = (float)($data['importe_total'] ?? 0);

        $fechaMovimientoInput = trim((string) ($data['fecha_hora'] ?? ''));
        $argentinaTz = new \DateTimeZone('America/Argentina/Buenos_Aires');
        $ahoraArgentina = new \DateTimeImmutable('now', $argentinaTz);
        $fechaMovimientoDateTime = $ahoraArgentina;

        if ($fechaMovimientoInput !== '') {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $fechaMovimientoInput, $argentinaTz);
            $errors = \DateTimeImmutable::getLastErrors();

            if (
                !$dt instanceof \DateTimeImmutable
                || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            ) {
                return $this->jsonResponse(['success' => false, 'message' => 'Fecha/Hora inválida'], 400);
            }

            if ($dt > $ahoraArgentina) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'La Fecha/Hora no puede ser superior al momento actual de Buenos Aires.'
                ], 400);
            }

            $fechaMovimientoDateTime = $dt;
        }

        $fechaMovimiento = $fechaMovimientoDateTime->format('Y-m-d H:i:s');

        if ($idVariante <= 0 || $cantidad <= 0 || $origenId <= 0 || $destinoId <= 0) {
            return $this->jsonResponse(['success' => false, 'message' => 'Datos incompletos o inválidos'], 400);
        }

        try {
            // Identificar tipos de depósito
            $tipos = $this->tiposDeposito->activos();
            $tipoOrigen = '';
            // $tipoDestino = ''; (No usado por ahora)
            foreach ($tipos as $t) {
                if ($t['id'] == $origenId) $tipoOrigen = strtoupper($t['codigo']);
            }

            // Validar Stock Negativo en Origen
            // Sólo se permite stock negativo en los depósitos AJUSTE y PROVEEDOR sin excepciones.
            if ($tipoOrigen !== 'AJUSTE' && strpos($tipoOrigen, 'PROVEEDOR') === false) {
                $stockActual = $this->stockService->getStock($idVariante, $origenId);
                if (($stockActual - $cantidad) < 0) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => "Stock insuficiente en origen ($stockActual disponible). Solo AJUSTE y PROVEEDOR permiten stock negativo."
                    ], 400);
                }
            }

            // Determinar si es una Compra (Origen PROVEEDOR)
            // Nota: Podríamos verificar también si el destino NO es interno, pero asumimos Proveedor -> Almacen
            $esCompra = (strpos($tipoOrigen, 'PROVEEDOR') !== false);

            if ($esCompra) {
                if (!$entidadId) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Debe seleccionar un Proveedor para movimientos desde PROVEEDOR'
                    ], 400);
                }

                if ($importeTotal <= 0) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'En compras debe ingresar el Importe Total de la operación.'
                    ], 400);
                }
            }

            $factorConversion = 1.0;
            $cantidadUso = $cantidad;

            if ($esCompra) {
                $variante = $this->variantes->getFullDetails($idVariante);
                if ($variante === null) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'No se encontró la variante para aplicar conversión de compra.'
                    ], 400);
                }

                $factorConversion = $this->unitConversion->normalizeFactor((float) ($variante['factor_conversion'] ?? 1));
                $cantidadUso = $this->unitConversion->toUsageFromPurchase($cantidad, $factorConversion);
            }

            if ($movimientoId > 0) {
                return $this->updateExistingMovimiento(
                    $movimientoId,
                    $data,
                    $idVariante,
                    $cantidad,
                    $cantidadUso,
                    $origenId,
                    $destinoId,
                    $entidadId,
                    $fechaMovimiento,
                    $esCompra,
                    $importeTotal,
                    $factorConversion
                );
            }

            // Registrar movimiento usando el servicio unificado (stock siempre en UM de uso)
            $referenciaTipo = $esCompra ? 'compra_satelite' : 'interno';

            $movId = $this->stockService->registerMovimiento([
                'id_variante' => $idVariante,
                'cantidad' => $cantidadUso,
                'id_tipo_deposito_origen' => $origenId,
                'id_tipo_deposito_destino' => $destinoId,
                'referencia_tipo' => $referenciaTipo,
                'referencia_id' => 0,
                'observaciones' => trim($data['observaciones'] ?? ''),
                'fecha' => $fechaMovimiento,
            ]);

            // Si es COMPRA, crear registro satélite
            if ($esCompra && $movId) {
                $precioUnitarioCompra = $importeTotal / $cantidad;
                if ($precioUnitarioCompra <= 0) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'No se pudo calcular el precio unitario de compra.'
                    ], 400);
                }

                $precioUnitarioUso = $this->unitConversion->usageUnitPriceFromPurchase($precioUnitarioCompra, $factorConversion);

                $compraId = $this->compras->create([
                    'id_movimiento_stock' => $movId,
                    'id_entidad' => $entidadId,
                    'fecha' => $fechaMovimiento,
                    'precio_unitario' => $precioUnitarioUso,
                    'nro_comprobante' => $data['cbte'] ?? null,
                    'observaciones' => trim((string) ($data['observaciones'] ?? ''))
                ]);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Movimiento registrado correctamente',
                'id' => $movId
            ]);
        } catch (\Throwable $e) {
            error_log('Error en MovimientosPartesController::store: ' . $e->getMessage());
            return $this->jsonResponse(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()], 500);
        }
    }

    private function updateExistingMovimiento(
        int $movimientoId,
        array $data,
        int $idVariante,
        float $cantidadCompra,
        float $cantidadUso,
        int $origenId,
        int $destinoId,
        ?int $entidadId,
        string $fechaMovimiento,
        bool $esCompra,
        float $importeTotal,
        float $factorConversion
    ): Response {
        $existing = $this->movimientosInfo->find($movimientoId);
        if ($existing === null) {
            return $this->jsonResponse(['success' => false, 'message' => 'Movimiento a editar no encontrado.'], 404);
        }

        $conn = $this->movimientosInfo->getConnection();
        $variantesARecalcular = [
            (int) ($existing['id_variante'] ?? 0),
            $idVariante,
        ];

        try {
            $conn->beginTransaction();

            $this->movimientosInfo->update($movimientoId, [
                'id_variante' => $idVariante,
                'cantidad' => $cantidadUso,
                'id_tipo_deposito_origen' => $origenId,
                'id_tipo_deposito_destino' => $destinoId,
                'referencia_tipo' => $esCompra ? 'compra_satelite' : 'interno',
                'referencia_id' => 0,
                'observaciones' => trim((string) ($data['observaciones'] ?? '')),
                'fecha' => $fechaMovimiento,
            ]);

            if ($esCompra) {
                $precioUnitarioCompra = $importeTotal / max($cantidadCompra, 0.000001);
                $precioUnitarioUso = $this->unitConversion->usageUnitPriceFromPurchase($precioUnitarioCompra, $factorConversion);

                $obs = trim((string) ($data['observaciones'] ?? ''));

                $compraStmt = $conn->prepare('SELECT id FROM compras WHERE id_movimiento_stock = :id_mov LIMIT 1');
                $compraStmt->execute(['id_mov' => $movimientoId]);
                $compraId = $compraStmt->fetchColumn();

                $compraData = [
                    'id_movimiento_stock' => $movimientoId,
                    'id_entidad' => $entidadId,
                    'fecha' => $fechaMovimiento,
                    'precio_unitario' => $precioUnitarioUso,
                    'nro_comprobante' => $data['cbte'] ?? null,
                    'observaciones' => $obs,
                ];

                if ($compraId !== false) {
                    $this->compras->update((int) $compraId, [
                        'id_entidad' => $compraData['id_entidad'],
                        'fecha' => $compraData['fecha'],
                        'precio_unitario' => $compraData['precio_unitario'],
                        'nro_comprobante' => $compraData['nro_comprobante'],
                        'observaciones' => $compraData['observaciones'],
                    ]);
                } else {
                    $this->compras->create($compraData);
                }
            } else {
                $deleteCompra = $conn->prepare('DELETE FROM compras WHERE id_movimiento_stock = :id_mov');
                $deleteCompra->execute(['id_mov' => $movimientoId]);
            }

            $variantesARecalcular = array_values(array_unique(array_filter($variantesARecalcular, static fn($id) => $id > 0)));
            foreach ($variantesARecalcular as $varianteId) {
                $stockActualizado = $this->stockService->calculateStock((int) $varianteId);
                $this->variantes->update((int) $varianteId, ['stock_actual' => $stockActualizado]);
            }

            $conn->commit();

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Movimiento actualizado correctamente',
                'id' => $movimientoId,
            ]);
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            return $this->jsonResponse(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    private function jsonResponse(array $data, int $status = 200): Response
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
}
