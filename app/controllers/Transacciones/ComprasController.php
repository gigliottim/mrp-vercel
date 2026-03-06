<?php

declare(strict_types=1);

namespace App\Controllers\Transacciones;

use App\Core\Controllers\Controller;
use App\Models\Compra;
use App\Models\Entidad;
use App\Models\MovimientoStock;
use App\Models\TipoDeposito;
use App\Models\TipoDepositoMovimiento;
use App\Models\UnidadMedida;
use App\Models\Variante;
use App\Services\StockService;
use App\Services\UnitConversionService;

class ComprasController extends Controller
{
    private Compra $compraModel;
    private Variante $varianteModel;
    private StockService $stockService;
    private TipoDeposito $tiposDeposito;
    private TipoDepositoMovimiento $tiposDepositoMovimiento;
    private MovimientoStock $movimientosInfo;
    private UnidadMedida $unidadesMedida;
    private Entidad $entidades;
    private UnitConversionService $unitConversion;

    public function __construct()
    {
        // parent::__construct();
        $this->compraModel = new Compra();
        $this->varianteModel = new Variante();
        $this->stockService = new StockService();
        $this->tiposDeposito = new TipoDeposito();
        $this->tiposDepositoMovimiento = new TipoDepositoMovimiento();
        $this->movimientosInfo = new MovimientoStock();
        $this->unidadesMedida = new UnidadMedida();
        $this->entidades = new Entidad();
        $this->unitConversion = new UnitConversionService();
    }

    public function index()
    {
        $limit = 50;
        $offset = 0; // Fixed for now

        $compras = $this->compraModel->all($limit, $offset);

        return $this->render('pages/compras/index', [
            'compras' => $compras
        ]);
    }

    public function create()
    {
        // Redirigir a la vista unificada de movimientos con preselección de Compra
        header('Location: ' . url('transacciones/movimientos-partes?origen=PROVEEDOR&destino=ALMACEN'));
        exit;
    }

    public function store()
    {
        // 1. Obtener la variante para conocer su factor de conversión
        $idVariante = (int) ($_POST['id_variante'] ?? 0);
        $variante = $this->varianteModel->getFullDetails($idVariante);

        if (!$variante) {
            header('Location: ' . url('compras/create?error=invalid_variant'));
            exit;
        }

        // Obtener factor directamente de DB o asumir 1 si no está seteado
        // Necesitamos asegurar que getFullDetails traiga factor_conversion (ver paso siguiente)
        $factor = $this->unitConversion->normalizeFactor((float) ($variante['factor_conversion'] ?? 1.0));

        $cantidadCompra = (float) ($_POST['cantidad'] ?? 1);
        $precioTotal = (float) ($_POST['precio_total'] ?? 0);

        if ($cantidadCompra <= 0) {
            header('Location: ' . url('compras/create?error=invalid_quantity'));
            exit;
        }

        $precioUnitarioCompra = $precioTotal / $cantidadCompra;
        $precioUnitarioUso = $this->unitConversion->usageUnitPriceFromPurchase($precioUnitarioCompra, $factor);
        $cantidadUso = $this->unitConversion->toUsageFromPurchase($cantidadCompra, $factor);

        // Agregar nota sobre la compra original
        $obs = $_POST['observaciones'] ?? '';
        $obs .= sprintf(
            "\n[Auto] Compra original: %.2f %s a $%.2f (Total). Factor: %s",
            $cantidadCompra,
            $variante['um_compra_simbolo'] ?? 'Unid.',
            $precioTotal,
            $factor
        );

        $data = [
            'id_variante' => $idVariante,
            'fecha' => $_POST['fecha'] ?? date('Y-m-d'),
            'precio_unitario' => $precioUnitarioUso,
            'cantidad' => $cantidadUso,
            'proveedor' => $_POST['proveedor'] ?? null,
            'observaciones' => trim($obs),
        ];

        try {
            // 1. Gestionar Proveedor (Entidad)
            // Si viene texto, buscar o crear entidad.
            // TODO: Actualizar UI para usar selector de ID directamente.
            $proveedorNombre = trim($_POST['proveedor'] ?? '');
            $idEntidad = null;

            if ($proveedorNombre !== '') {
                $entidadModel = new \App\Models\Entidad();
                // Buscar si existe
                $existing = $entidadModel->getConnection()->prepare("SELECT id FROM entidades WHERE razon_social ILIKE ? LIMIT 1");
                $existing->execute([$proveedorNombre]);
                $found = $existing->fetch(\PDO::FETCH_ASSOC);

                if ($found) {
                    $idEntidad = (int)$found['id'];
                } else {
                    // Crear nuevo
                    $idEntidad = $entidadModel->create([
                        'razon_social' => $proveedorNombre,
                        'tipo' => 'PROVEEDOR'
                    ]);
                }
            }

            // 2. Registrar movimiento de stock (Master)
            $movId = $this->stockService->registerMovimiento([
                'id_variante' => $idVariante,
                'cantidad' => $cantidadUso,
                'origen' => 'PROVEEDOR',
                'destino' => 'ALMACEN',
                'referencia_tipo' => 'compra', // Referencia genérica, el ID específico se genera después (circular dependency loose) o usamos movId como link.
                // En el nuevo diseño, la compra apunta al movimiento, no al revés necesariamente.
                // Pero 'referencia_id' es útil en mvtos.
                // Dejaremos referencia_id NULL por ahora o lo actualizamos luego.
                // Mejor: referencia_tipo = 'compra_satelite' -> y referencia_id será el ID de la tabla compras que crearemos abajo.
                'observaciones' => "Compra. " . ($proveedorNombre ? "Prov: $proveedorNombre" : "")
            ]);

            // 3. Guardar registro comercial (Compras Satélite)
            $compraData = [
                'id_movimiento_stock' => $movId,
                'id_entidad' => $idEntidad,
                'fecha' => $_POST['fecha'] ?? date('Y-m-d'), // La fecha tambien esta en movimiento, redundante pero util para busquedas rapidas
                'precio_unitario' => $precioUnitarioUso,
                'nro_comprobante' => $_POST['nro_comprobante'] ?? null, // Si agregamos campo en form
                'observaciones' => trim($obs),
                // Campos antiguos para retrocompatibilidad si la migración no elimina columnas aun:
                // 'cantidad' => $cantidadUso,
                // 'id_variante' => $idVariante
            ];

            $compraId = $this->compraModel->create($compraData);

            // Actualizar referencia inversa en movimiento si se desea trazabilidad bidireccional
            // (Opcional, pero util)
            /*
            $updateMov = $this->stockService->getMovimientosModel()->update($movId, [
                'referencia_id' => $compraId
            ]);
            */

            // Actualizar costo en variante (Lógica de Negocio)
            if ($idVariante > 0 && $precioUnitarioUso > 0) {
                $this->varianteModel->update($idVariante, ['costo' => $precioUnitarioUso]);
            }

            header('Location: ' . url('compras?success=created'));
            exit;
        } catch (\Throwable $e) {
            error_log('Error saving purchase: ' . $e->getMessage());
            header('Location: ' . url('compras/create?error=db_error'));
            exit;
        }
    }
}
