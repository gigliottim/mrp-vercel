<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MovimientoStock;
use App\Models\TipoDeposito;
use App\Models\Variante;

final class StockService
{
    private MovimientoStock $movimientos;
    private TipoDeposito $tipos;
    private Variante $variantes;

    // Cache of deposit IDs
    private ?int $idAlmacen = null;
    private ?int $idProveedor = null;
    private ?int $idProduccion = null;
    private ?int $idCliente = null;
    private ?int $idAjuste = null;

    public function __construct()
    {
        $this->movimientos = new MovimientoStock();
        $this->tipos = new TipoDeposito();
        $this->variantes = new Variante();
        $this->loadDepositIds();
    }

    private function loadDepositIds(): void
    {
        $all = $this->tipos->all();
        foreach ($all as $tipo) {
            switch ($tipo['codigo']) {
                case 'ALMACEN':
                    $this->idAlmacen = (int)$tipo['id'];
                    break;
                case 'PROVEEDOR':
                    $this->idProveedor = (int)$tipo['id'];
                    break;
                case 'PRODUCCION':
                    $this->idProduccion = (int)$tipo['id'];
                    break;
                case 'CLIENTE':
                    $this->idCliente = (int)$tipo['id'];
                    break;
                case 'AJUSTE':
                    $this->idAjuste = (int)$tipo['id'];
                    break;
            }
        }
    }

    /**
     * Calcula el stock actual de una variante en el Depósito Principal (ALMACEN)
     * basado en el historial de movimientos.
     */
    public function calculateStock(int $varianteId): float
    {
        if (!$this->idAlmacen) return 0.0;

        // Suma de Ingresos al ALMACEN
        $sqlIngresos = "SELECT COALESCE(SUM(cantidad), 0) as total FROM movimientos_stock
                        WHERE id_variante = :var AND id_tipo_deposito_destino = :dep";

        // Suma de Egresos del ALMACEN
        $sqlEgresos = "SELECT COALESCE(SUM(cantidad), 0) as total FROM movimientos_stock
                       WHERE id_variante = :var AND id_tipo_deposito_origen = :dep";

        // Ejecutar consultas (usando modelo base para conexión)
        // Nota: Esto asume acceso PDO desde modelo.
        // Mejor inyectar conexión en servicio, pero usaremos modelo workaround
        // TODO: Refactorizar acceso a DB en servicio.

        // Ingresos
        $stmtIn = $this->movimientos->getConnection()->prepare($sqlIngresos);
        $stmtIn->execute(['var' => $varianteId, 'dep' => $this->idAlmacen]);
        $ingresos = (float) $stmtIn->fetch(\PDO::FETCH_ASSOC)['total'];

        // Egresos
        $stmtOut = $this->movimientos->getConnection()->prepare($sqlEgresos);
        $stmtOut->execute(['var' => $varianteId, 'dep' => $this->idAlmacen]);
        $egresos = (float) $stmtOut->fetch(\PDO::FETCH_ASSOC)['total'];

        return round($ingresos - $egresos, 6);
    }

    /**
     * Calcula el stock actual de una variante en un depósito específico
     */
    public function getStock(int $varianteId, int $depositoId): float
    {
        $conn = $this->movimientos->getConnection();

        // Ingresos
        $sqlIngresos = "SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_stock
                        WHERE id_variante = :var AND id_tipo_deposito_destino = :dep";
        $stmtIn = $conn->prepare($sqlIngresos);
        $stmtIn->execute(['var' => $varianteId, 'dep' => $depositoId]);
        $ingresos = (float) $stmtIn->fetchColumn();

        // Egresos
        $sqlEgresos = "SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_stock
                       WHERE id_variante = :var AND id_tipo_deposito_origen = :dep";
        $stmtOut = $conn->prepare($sqlEgresos);
        $stmtOut->execute(['var' => $varianteId, 'dep' => $depositoId]);
        $egresos = (float) $stmtOut->fetchColumn();

        return round($ingresos - $egresos, 6);
    }

    /**
     * Registra un movimiento y actualiza el stock en la variante
     */
    public function registerMovimiento(array $data): int
    {
        // 1. Crear movimiento
        // Campos requeridos: id_variante, cantidad, id_origen, id_destino, tipo, ref_id

        // Mapear Nombres de Depósito a IDs si vienen como Strings
        if (is_string($data['origen'] ?? null)) $data['id_tipo_deposito_origen'] = $this->getDepositId($data['origen']);
        if (is_string($data['destino'] ?? null)) $data['id_tipo_deposito_destino'] = $this->getDepositId($data['destino']);

        $moveData = [
            'id_variante' => $data['id_variante'],
            'cantidad' => $data['cantidad'],
            'id_tipo_deposito_origen' => $data['id_tipo_deposito_origen'],
            'id_tipo_deposito_destino' => $data['id_tipo_deposito_destino'],
            'referencia_tipo' => $data['referencia_tipo'] ?? null,
            'referencia_id' => $data['referencia_id'] ?? null,
            'observaciones' => $data['observaciones'] ?? null,
            'fecha' => $data['fecha'] ?? date('Y-m-d H:i:s')
        ];

        $id = $this->movimientos->create($moveData);

        // 2. Actualizar Stock en Variante (Cache)
        // Solo si involucra ALMACEN
        if ($data['id_tipo_deposito_origen'] === $this->idAlmacen || $data['id_tipo_deposito_destino'] === $this->idAlmacen) {
            $nuevoStock = $this->calculateStock($data['id_variante']);
            $this->variantes->update($data['id_variante'], ['stock_actual' => $nuevoStock]);
        }

        return (int)$id;
    }

    private function getDepositId(string $code): ?int
    {
        switch (strtoupper($code)) {
            case 'ALMACEN':
                return $this->idAlmacen;
            case 'PROVEEDOR':
                return $this->idProveedor;
            case 'PRODUCCION':
                return $this->idProduccion;
            case 'CLIENTE':
                return $this->idCliente;
            case 'AJUSTE':
                return $this->idAjuste;
            default:
                return null;
        }
    }
}
