# 📋 Plan de Migración Detallado por Módulo

## 🎯 Módulo: Producción

### 1. Resumen Ejecutivo

El módulo de Producción es el corazón del sistema MRP. Maneja órdenes de producción, rutas de producción, centros de trabajo y planificación de recursos. Es complejo debido a las reglas de negocio y las dependencias entre entidades.

### 2. Tabla de Mapeo

| Componente Actual | Componente CakePHP | Acción Requerida | Justificación Técnica |
| :--- | :--- | :--- | :--- |
| `Produccion/OrdenesProduccionController.php` | `App\Controller\Produccion\OrdenesProduccionController.php` | Crear Controller | CRUD de órdenes de producción |
| `Produccion/RutasProduccionController.php` | `App\Controller\Produccion\RutasProduccionController.php` | Crear Controller | Gestión de rutas de producción |
| `Produccion/CentrosTrabajoController.php` | `App\Controller\Produccion\CentrosTrabajoController.php` | Crear Controller | Gestión de centros de trabajo |
| `Produccion/PlanificacionController.php` | `App\Controller\Produccion\PlanificacionController.php` | Crear Controller | Planificación de recursos |
| `Produccion/EjecucionController.php` | `App\Controller\Produccion\EjecucionController.php` | Crear Controller | Ejecución de producción |
| `Produccion/OperacionesController.php` | `App\Controller\Produccion\OperacionesController.php` | Crear Controller | Gestión de operaciones |
| `Produccion/OrdenProduccionService.php` | `App\Service\OrdenProduccionService.php` | Crear Service | Lógica de órdenes de producción |
| `Produccion/RutaProduccionService.php` | `App\Service\RutaProduccionService.php` | Crear Service | Lógica de rutas de producción |
| `Produccion/CentroTrabajoService.php` | `App\Service\CentroTrabajoService.php` | Crear Service | Lógica de centros de trabajo |
| `Produccion/PlanificacionService.php` | `App\Service\PlanificacionService.php` | Crear Service | Lógica de planificación |
| `Produccion/OrdenProduccionRepository.php` | `App\Model\Table\OrdenesProduccionTable.php` | Mover lógica a Table | Consultas complejas en Table |
| `Produccion/RutaProduccionRepository.php` | `App\Model\Table\RutasProduccionTable.php` | Mover lógica a Table | Validaciones en Table |
| `Produccion/CentroTrabajoRepository.php` | `App\Model\Table\CentrosTrabajoTable.php` | Mover lógica a Table | Estadísticas en Table |
| `Produccion/PlanificacionRepository.php` | `App\Model\Table\PlanificacionesTable.php` | Mover lógica a Table | Planificaciones en Table |

### 3. Guía de Implementación

#### Paso 3.1: Crear Entities

```php
// src/Model/Entity/OrdenProduccion.php
<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class OrdenProduccion extends Entity
{
    protected $_accessible = [
        '*' => true,
        'id' => false,
        'numero_orden' => false,
    ];

    protected function _getEstadoDisplay(): string
    {
        $estados = [
            self::ESTADO_BORRADOR => 'Borrador',
            self::ESTADO_PLANIFICADA => 'Planificada',
            self::ESTADO_LIBERADA => 'Liberada',
            self::ESTADO_EN_PROCESO => 'En Proceso',
            self::ESTADO_PAUSADA => 'Pausada',
            self::ESTADO_CERRADA => 'Cerrada',
            self::ESTADO_CANCELADA => 'Cancelada',
        ];

        return $estados[$this->estado] ?? 'Desconocido';
    }

    protected function _getPrioridadDisplay(): string
    {
        $prioridades = [
            self::PRIORIDAD_URGENTE => 'Urgente',
            self::PRIORIDAD_ALTA => 'Alta',
            self::PRIORIDAD_NORMAL => 'Normal',
            self::PRIORIDAD_BAJA => 'Baja',
        ];

        return $prioridades[$this->prioridad] ?? 'Normal';
    }
}
```

```php
// src/Model/Entity/RutaProduccion.php
<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class RutaProduccion extends Entity
{
    protected $_accessible = [
        '*' => true,
        'id' => false,
    ];

    protected function _getTiempoTotalMins(): float
    {
        return $this->tiempo_setup_mins +
               $this->tiempo_proceso_unitario_mins +
               $this->tiempo_cola_mins +
               $this->tiempo_movimiento_mins;
    }

    protected function _getCostoTotal(): float
    {
        return $this->costo_operacion_fijo +
               ($this->costo_operacion_variable * 1); // Por unidad
    }
}
```

#### Paso 3.2: Crear Tables

```php
// src/Model/Table/OrdenesProduccionTable.php
<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Table\TableBase;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

class OrdenesProduccionTable extends TableBase
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('ordenes_produccion');
        $this->setPrimaryKey('id');

        $this->belongsTo('Variantes', [
            'foreignKey' => 'variante_id',
        ]);
        $this->belongsTo('Boms', [
            'foreignKey' => 'bom_id_utilizada',
        ]);
        $this->hasMany('Planificaciones', [
            'foreignKey' => 'orden_produccion_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->uuid('id')
            ->requirePresence('numero_orden', 'create')
            ->notEmptyString('numero_orden')
            ->requirePresence('variante_id', 'create')
            ->numeric('variante_id')
            ->requirePresence('cantidad_planificada', 'create')
            ->numeric('cantidad_planificada')
            ->requirePresence('fecha_inicio_programada', 'create')
            ->dateTime('fecha_inicio_programada')
            ->requirePresence('estado', 'create')
            ->inList('estado', [
                self::ESTADO_BORRADOR,
                self::ESTADO_PLANIFICADA,
                self::ESTADO_LIBERADA,
                self::ESTADO_EN_PROCESO,
                self::ESTADO_PAUSADA,
                self::ESTADO_CERRADA,
                self::ESTADO_CANCELADA,
            ]);
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn('variante_id', 'Variantes'));
        $rules->add($rules->existsIn('bom_id_utilizada', 'Boms'));

        return $rules;
    }

    public function generarNumeroOrden(): string
    {
        $ano = date('Y');
        $mes = date('m');
        
        $ultimaOrden = $this->find()
            ->where([
                'YEAR(fecha_creacion)' => $ano,
                'MONTH(fecha_creacion)' => $mes,
            ])
            ->orderBy('numero_orden DESC')
            ->first();
        
        $numero = 1;
        if ($ultimaOrden) {
            $ultimoNumero = (int)substr($ultimaOrden->numero_orden, -6);
            $numero = $ultimoNumero + 1;
        }
        
        return sprintf('OP-%s%s-%06d', $ano, $mes, $numero);
    }

    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        $orden = $this->find()
            ->where(['id' => $id])
            ->first();
        
        if (!$orden) {
            return false;
        }
        
        $orden->estado = $nuevoEstado;
        
        return $this->save($orden);
    }

    public function getDashboard(): array
    {
        return $this->find()
            ->select([
                'estado',
                'cantidad' => $this->find()->func()->count('*'),
                'pendiente' => $this->find()->func()->sum('cantidad_planificada - cantidad_producida'),
            ])
            ->where(['estado NOT IN' => [self::ESTADO_CERRADA, self::ESTADO_CANCELADA]])
            ->group('estado')
            ->toArray();
    }

    public function getAtrasadas(): array
    {
        return $this->find()
            ->select([
                'op.*',
                'dias_atraso' => $this->find()->func()->diff('CURRENT_DATE', 'op.fecha_fin_programada'),
            ])
            ->where([
                'op.estado IN' => [self::ESTADO_LIBERADA, self::ESTADO_EN_PROCESO, self::ESTADO_PAUSADA],
                'op.fecha_fin_programada <' => date('Y-m-d'),
            ])
            ->orderBy('dias_atraso DESC')
            ->toArray();
    }
}
```

```php
// src/Model/Table/RutasProduccionTable.php
<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Table\TableBase;
use Cake\Validation\Validator;

class RutasProduccionTable extends TableBase
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('rutas_produccion');
        $this->setPrimaryKey('id');

        $this->belongsTo('Boms', [
            'foreignKey' => 'bom_id',
        ]);
        $this->belongsTo('CentrosTrabajo', [
            'foreignKey' => 'centro_trabajo_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->uuid('id')
            ->requirePresence('bom_id', 'create')
            ->numeric('bom_id')
            ->requirePresence('secuencia', 'create')
            ->integer('secuencia')
            ->requirePresence('centro_trabajo_id', 'create')
            ->numeric('centro_trabajo_id')
            ->requirePresence('descripcion', 'create')
            ->notEmptyString('descripcion')
            ->requirePresence('tiempo_setup_mins', 'create')
            ->numeric('tiempo_setup_mins')
            ->requirePresence('tiempo_proceso_unitario_mins', 'create')
            ->numeric('tiempo_proceso_unitario_mins');
    }

    public function getOperacionesByBomId(int $bomId): array
    {
        return $this->find()
            ->where(['bom_id' => $bomId])
            ->orderBy('secuencia')
            ->toArray();
    }

    public function validarRuta(int $bomId): array
    {
        $errores = [];
        $operaciones = $this->getOperacionesByBomId($bomId);

        if (empty($operaciones)) {
            $errores[] = 'La ruta no tiene operaciones definidas';
            return $errores;
        }

        // Validar que todos los centros estén activos
        foreach ($operaciones as $op) {
            if (!$op->centro_trabajo->activo) {
                $errores[] = "La operación {$op->secuencia} usa el centro {$op->centro_trabajo->codigo} que está inactivo";
            }
        }

        // Validar secuencias consecutivas
        $secuencias = array_column($operaciones, 'secuencia');
        sort($secuencias);
        for ($i = 1; $i < count($secuencias); $i++) {
            if ($secuencias[$i] - $secuencias[$i - 1] > 50) {
                $errores[] = "Hay un gap significativo entre las secuencias {$secuencias[$i - 1]} y {$secuencias[$i]}";
            }
        }

        return $errores;
    }

    public function reordenar(int $bomId, array $nuevasSecuencias): bool
    {
        try {
            $this->getConnection()->begin();

            foreach ($nuevasSecuencias as $operacionId => $secuencia) {
                $this->updateAll(
                    ['secuencia' => $secuencia],
                    ['id' => $operacionId, 'bom_id' => $bomId]
                );
            }

            $this->getConnection()->commit();
            return true;
        } catch (\Exception $e) {
            $this->getConnection()->rollback();
            return false;
        }
    }
}
```

#### Paso 3.3: Crear Services

```php
// src/Service/OrdenProduccionService.php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Table\OrdenesProduccionTable;
use App\Model\Table\PlanificacionesTable;
use Cake\ORM\TableRegistry;

class OrdenProduccionService extends ServiceBase
{
    private OrdenesProduccionTable $OrdenesProduccion;
    private PlanificacionesTable $Planificaciones;

    public function initialize(): void
    {
        $this->OrdenesProduccion = $this->getTableLocator()->get('OrdenesProduccion');
        $this->Planificaciones = $this->getTableLocator()->get('Planificaciones');
    }

    public function crearOrden(array $data): ?array
    {
        try {
            $this->OrdenesProduccion->getConnection()->begin();

            // Generar número de orden
            if (empty($data['numero_orden'])) {
                $data['numero_orden'] = $this->OrdenesProduccion->generarNumeroOrden();
            }

            // Crear orden
            $orden = $this->OrdenesProduccion->newEmptyEntity();
            $orden = $this->OrdenesProduccion->patchEntity($orden, $data);
            
            if (!$this->OrdenesProduccion->save($orden)) {
                throw new \Exception('Error al crear la orden');
            }

            $this->OrdenesProduccion->getConnection()->commit();

            return [
                'success' => true,
                'orden' => $orden,
            ];
        } catch (\Exception $e) {
            $this->OrdenesProduccion->getConnection()->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function cambiarEstado(int $ordenId, string $nuevoEstado): array
    {
        $orden = $this->OrdenesProduccion->find()
            ->where(['id' => $ordenId])
            ->first();

        if (!$orden) {
            return [
                'success' => false,
                'error' => 'Orden no encontrada',
            ];
        }

        // Validar transición de estado
        if (!$this->validarTransicionEstado($orden->estado, $nuevoEstado)) {
            return [
                'success' => false,
                'error' => 'Transición de estado inválida',
            ];
        }

        $orden->estado = $nuevoEstado;

        if (!$this->OrdenesProduccion->save($orden)) {
            return [
                'success' => false,
                'error' => 'Error al cambiar el estado',
            ];
        }

        return [
            'success' => true,
            'orden' => $orden,
        ];
    }

    public function planificarOrdenAutomatica(int $ordenId, string $fechaInicio): array
    {
        $orden = $this->OrdenesProduccion->find()
            ->where(['id' => $ordenId])
            ->contain(['Variantes.Boms.RutasProduccion'])
            ->first();

        if (!$orden) {
            return [
                'success' => false,
                'error' => 'Orden no encontrada',
            ];
        }

        try {
            $this->OrdenesProduccion->getConnection()->begin();

            $fechaInicioPlan = new \DateTime($fechaInicio);
            $resultados = [];

            foreach ($orden->variante->bom->rutas_produccion as $ruta) {
                // Calcular duración
                $tiempoSetup = (float)$ruta->tiempo_setup_mins;
                $tiempoProceso = (float)$ruta->tiempo_proceso_unitario_mins * $orden->cantidad_planificada;
                $tiempoCola = (float)$ruta->tiempo_cola_mins;
                $tiempoMovimiento = (float)$ruta->tiempo_movimiento_mins;

                $duracion = $tiempoSetup + $tiempoProceso + $tiempoCola + $tiempoMovimiento;

                $fechaFin = (clone $fechaInicioPlan)->modify("+{$duracion} minutes");

                // Crear planificación
                $planificacion = $this->Planificaciones->newEmptyEntity();
                $planificacion->orden_produccion_id = $ordenId;
                $planificacion->operacion_id = $ruta->id;
                $planificacion->centro_trabajo_id = $ruta->centro_trabajo_id;
                $planificacion->fecha_inicio = $fechaInicioPlan->format('Y-m-d H:i:s');
                $planificacion->fecha_fin = $fechaFin->format('Y-m-d H:i:s');
                $planificacion->estado = 'programado';

                if (!$this->Planificaciones->save($planificacion)) {
                    throw new \Exception('Error al crear planificación');
                }

                $resultados[] = [
                    'operacion_id' => $ruta->id,
                    'planificacion_id' => $planificacion->id,
                    'fecha_inicio' => $fechaInicioPlan->format('Y-m-d H:i:s'),
                    'fecha_fin' => $fechaFin->format('Y-m-d H:i:s'),
                    'duracion_mins' => $duracion,
                ];

                $fechaInicioPlan = $fechaFin;
            }

            $this->OrdenesProduccion->getConnection()->commit();

            return [
                'success' => true,
                'planificaciones' => $resultados,
            ];
        } catch (\Exception $e) {
            $this->OrdenesProduccion->getConnection()->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function validarTransicionEstado(string $estadoActual, string $nuevoEstado): bool
    {
        $transiciones = [
            'borrador' => ['planificada', 'cancelada'],
            'planificada' => ['liberada', 'cancelada'],
            'liberada' => ['en_proceso', 'pausada', 'cancelada'],
            'en_proceso' => ['pausada', 'cerrada', 'cancelada'],
            'pausada' => ['en_proceso', 'cancelada'],
            'cerrada' => [],
            'cancelada' => [],
        ];

        return in_array($nuevoEstado, $transiciones[$estadoActual] ?? []);
    }
}
```

```php
// src/Service/PlanificacionService.php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Table\PlanificacionesTable;
use App\Model\Table\CentrosTrabajoTable;
use Cake\ORM\TableRegistry;

class PlanificacionService extends ServiceBase
{
    private PlanificacionesTable $Planificaciones;
    private CentrosTrabajoTable $CentrosTrabajo;

    public function initialize(): void
    {
        $this->Planificaciones = $this->getTableLocator()->get('Planificaciones');
        $this->CentrosTrabajo = $this->getTableLocator()->get('CentrosTrabajo');
    }

    public function verificarDisponibilidad(
        int $centroId,
        string $fechaInicio,
        string $fechaFin
    ): array {
        $centro = $this->CentrosTrabajo->find()
            ->where(['id' => $centroId])
            ->first();

        if (!$centro) {
            return [
                'success' => false,
                'error' => 'Centro no encontrado',
            ];
        }

        if (!$centro->capacidad_finita) {
            return [
                'success' => true,
                'disponible' => true,
            ];
        }

        $planificaciones = $this->Planificaciones->find()
            ->where([
                'centro_trabajo_id' => $centroId,
                'fecha_inicio <' => $fechaFin,
                'fecha_fin >' => $fechaInicio,
                'estado' => 'programado',
            ])
            ->toArray();

        // Calcular carga actual
        $cargaActual = 0;
        foreach ($planificaciones as $plan) {
            $inicio = new \DateTime($plan->fecha_inicio);
            $fin = new \DateTime($plan->fecha_fin);
            $duracion = $inicio->diff($fin)->h;
            $cargaActual += $duracion;
        }

        // Calcular capacidad disponible
        $inicio = new \DateTime($fechaInicio);
        $fin = new \DateTime($fechaFin);
        $dias = $inicio->diff($fin)->days;
        $capacidadTotal = $dias * (float)$centro->capacidad_horas_dia * ((float)$centro->eficiencia_porcentaje / 100);

        $disponible = ($cargaActual + ($dias * 8)) <= $capacidadTotal;

        return [
            'success' => true,
            'disponible' => $disponible,
            'carga_actual' => round($cargaActual, 2),
            'capacidad_disponible' => round($capacidadTotal - $cargaActual, 2),
        ];
    }

    public function getGanttData(?int $centroId = null, ?string $fechaInicio = null, ?string $fechaFin = null): array
    {
        $query = $this->Planificaciones->find()
            ->contain(['OrdenesProduccion.Variantes.Partes'])
            ->contain(['CentrosTrabajo']);

        if ($centroId) {
            $query->where(['centro_trabajo_id' => $centroId]);
        }

        if ($fechaInicio && $fechaFin) {
            $query->where([
                'OR' => [
                    'AND' => [
                        'fecha_inicio <=' => $fechaFin,
                        'fecha_fin >=' => $fechaInicio,
                    ],
                ],
            ]);
        }

        $query->orderBy(['centro_trabajo_id', 'fecha_inicio']);

        return $query->toArray();
    }

    public function calcularCargaTrabajo(int $centroId, string $fechaInicio, string $fechaFin): array
    {
        $centro = $this->CentrosTrabajo->find()
            ->where(['id' => $centroId])
            ->first();

        if (!$centro) {
            return [
                'success' => false,
                'error' => 'Centro no encontrado',
            ];
        }

        $planificaciones = $this->Planificaciones->find()
            ->where([
                'centro_trabajo_id' => $centroId,
                'fecha_inicio >=' => $fechaInicio,
                'fecha_fin <=' => $fechaFin,
                'estado' => 'programado',
            ])
            ->toArray();

        $cargaTotal = 0;
        foreach ($planificaciones as $plan) {
            $inicio = new \DateTime($plan->fecha_inicio);
            $fin = new \DateTime($plan->fecha_fin);
            $duracion = $inicio->diff($fin)->h;
            $cargaTotal += $duracion;
        }

        $inicio = new \DateTime($fechaInicio);
        $fin = new \DateTime($fechaFin);
        $dias = $inicio->diff($fin)->days;
        $capacidadTotal = $dias * (float)$centro->capacidad_horas_dia * ((float)$centro->eficiencia_porcentaje / 100);

        return [
            'success' => true,
            'carga_total_horas' => round($cargaTotal, 2),
            'capacidad_total_horas' => round($capacidadTotal, 2),
            'porcentaje_utilizacion' => $capacidadTotal > 0 ? round(($cargaTotal / $capacidadTotal) * 100, 2) : 0,
        ];
    }
}
```

#### Paso 3.4: Crear Controladores

```php
// src/Controller/Produccion/OrdenesProduccionController.php
<?php

declare(strict_types=1);

namespace App\Controller\Produccion;

use App\Controller\AppController;
use App\Service\OrdenProduccionService;

class OrdenesProduccionController extends AppController
{
    private ?OrdenProduccionService $ordenProduccionService = null;

    public function initialize(): void
    {
        parent::initialize();
        $this->ordenProduccionService = new OrdenProduccionService();
    }

    public function index()
    {
        $this->request->allowMethod('get');

        $estado = $this->request->getQuery('estado');
        $prioridad = $this->request->getQuery('prioridad');
        $fechaDesde = $this->request->getQuery('fecha_desde');
        $fechaHasta = $this->request->getQuery('fecha_hasta');

        $query = $this->OrdenesProduccion->find();

        if ($estado) {
            $query->where(['estado' => $estado]);
        }

        if ($prioridad) {
            $query->where(['prioridad' => $prioridad]);
        }

        if ($fechaDesde) {
            $query->where(['fecha_inicio_programada >=' => $fechaDesde]);
        }

        if ($fechaHasta) {
            $query->where(['fecha_fin_programada <=' => $fechaHasta]);
        }

        $query->contain(['Variantes.Partes', 'Boms']);

        $ordenes = $query->toArray();

        $this->set([
            'success' => true,
            'ordenes' => $ordenes,
        ]);
        $this->set('_serialize', ['success', 'ordenes']);
    }

    public function view($id)
    {
        $this->request->allowMethod('get');

        $orden = $this->OrdenesProduccion->find()
            ->where(['id' => $id])
            ->contain([
                'Variantes.Partes',
                'Variantes.Boms.RutasProduccion',
                'Planificaciones',
            ])
            ->first();

        if (!$orden) {
            $this->set([
                'success' => false,
                'error' => 'Orden no encontrada',
            ]);
            $this->set('_serialize', ['success', 'error']);
            $this->setResponseCode(404);
            return;
        }

        $this->set([
            'success' => true,
            'orden' => $orden,
        ]);
        $this->set('_serialize', ['success', 'orden']);
    }

    public function add()
    {
        $this->request->allowMethod('post');

        $data = $this->request->getData();

        $result = $this->ordenProduccionService->crearOrden($data);

        if ($result['success']) {
            $this->set([
                'success' => true,
                'orden' => $result['orden'],
            ]);
            $this->setResponseCode(201);
        } else {
            $this->set([
                'success' => false,
                'error' => $result['error'],
            ]);
            $this->setResponseCode(400);
        }

        $this->set('_serialize', ['success', 'orden', 'error']);
    }

    public function edit($id)
    {
        $this->request->allowMethod(['put', 'patch']);

        $orden = $this->OrdenesProduccion->find()
            ->where(['id' => $id])
            ->first();

        if (!$orden) {
            $this->set([
                'success' => false,
                'error' => 'Orden no encontrada',
            ]);
            $this->set('_serialize', ['success', 'error']);
            $this->setResponseCode(404);
            return;
        }

        $orden = $this->OrdenesProduccion->patchEntity($orden, $this->request->getData());

        if ($this->OrdenesProduccion->save($orden)) {
            $this->set([
                'success' => true,
                'orden' => $orden,
            ]);
        } else {
            $this->set([
                'success' => false,
                'error' => 'Error al actualizar la orden',
                'errors' => $orden->getErrors(),
            ]);
            $this->setResponseCode(400);
        }

        $this->set('_serialize', ['success', 'orden', 'error', 'errors']);
    }

    public function cambiarEstado($id)
    {
        $this->request->allowMethod('post');

        $nuevoEstado = $this->request->getData('estado');

        $result = $this->ordenProduccionService->cambiarEstado($id, $nuevoEstado);

        if ($result['success']) {
            $this->set([
                'success' => true,
                'orden' => $result['orden'],
            ]);
        } else {
            $this->set([
                'success' => false,
                'error' => $result['error'],
            ]);
            $this->setResponseCode(400);
        }

        $this->set('_serialize', ['success', 'orden', 'error']);
    }

    public function planificarAutomatica($id)
    {
        $this->request->allowMethod('post');

        $fechaInicio = $this->request->getData('fecha_inicio');

        $result = $this->ordenProduccionService->planificarOrdenAutomatica($id, $fechaInicio);

        if ($result['success']) {
            $this->set([
                'success' => true,
                'planificaciones' => $result['planificaciones'],
            ]);
        } else {
            $this->set([
                'success' => false,
                'error' => $result['error'],
            ]);
            $this->setResponseCode(400);
        }

        $this->set('_serialize', ['success', 'planificaciones', 'error']);
    }

    public function dashboard()
    {
        $this->request->allowMethod('get');

        $dashboard = $this->OrdenesProduccion->getDashboard();

        $this->set([
            'success' => true,
            'dashboard' => $dashboard,
        ]);
        $this->set('_serialize', ['success', 'dashboard']);
    }

    public function atrasadas()
    {
        $this->request->allowMethod('get');

        $atrasadas = $this->OrdenesProduccion->getAtrasadas();

        $this->set([
            'success' => true,
            'atrasadas' => $atrasadas,
        ]);
        $this->set('_serialize', ['success', 'atrasadas']);
    }
}
```

### 4. Alertas de Riesgo

#### Riesgo 1: Validación de Transiciones de Estado
**Nivel**: Alto
**Impacto**: Crítico

**Descripción**: Las transiciones de estado deben ser validadas estrictamente para evitar inconsistencias.

**Mitigación**: Implementar máquina de estados en el Service con validación estricta.

#### Riesgo 2: Planificación de Recursos
**Nivel**: Alto
**Impacto**: Alto

**Descripción**: La planificación de recursos debe considerar la disponibilidad de centros de trabajo y evitar conflictos.

**Mitigación**: Implementar verificación de disponibilidad antes de crear planificaciones.

#### Riesgo 3: Cálculo de Tiempos
**Nivel**: Medio
**Impacto**: Medio

**Descripción**: Los cálculos de tiempos deben ser precisos para evitar problemas en la producción.

**Mitigación**: Validar cálculos con datos históricos y ajustar fórmulas según sea necesario.

---

## 🎯 Módulo: Productos

### 1. Resumen Ejecutivo

El módulo de Productos maneja la gestión de partes, variantes, BOMs (Bill of Materials) y rutas de producción. Es crítico para la planificación de la producción.

### 2. Tabla de Mapeo

| Componente Actual | Componente CakePHP | Acción Requerida | Justificación Técnica |
| :--- | :--- | :--- | :--- |
| `Productos/BomController.php` | `App\Controller\Productos\BomController.php` | Crear Controller | CRUD de BOMs |
| `Productos/ComposicionController.php` | `App\Controller\Productos\ComposicionController.php` | Crear Controller | Gestión de composición |
| `Productos/HerramientasBomController.php` | `App\Controller\Productos\HerramientasBomController.php` | Crear Controller | Herramientas BOM |
| `Productos/MaestroImportController.php` | `App\Controller\Productos\MaestroImportController.php` | Crear Controller | Importación masiva |
| `Bom/MaestroImportExportService.php` | `App\Service\MaestroImportExportService.php` | Crear Service | Import/Export BOMs |
| `Partes/PartesVariantesImportService.php` | `App\Service\PartesImportService.php` | Crear Service | Importación de partes |
| `Partes/PartesGeometryRecalculationService.php` | `App\Service\GeometryRecalculationService.php` | Crear Service | Recálculo de geometría |

### 3. Guía de Implementación

#### Paso 3.1: Crear Entities

```php
// src/Model/Entity/Bom.php
<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Bom extends Entity
{
    protected $_accessible = [
        '*' => true,
        'id' => false,
    ];

    protected function _getVersionDisplay(): string
    {
        return "v{$this->version} ({$this->estado})";
    }

    protected function _getRutaCompleta(): ?array
    {
        return $this->rutas_produccion ?? null;
    }
}
```

```php
// src/Model/Entity/Parte.php
<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Parte extends Entity
{
    protected $_accessible = [
        '*' => true,
        'id' => false,
    ];

    protected function _getNombreCompleto(): string
    {
        return "{$this->codigo} - {$this->descripcion}";
    }

    protected function _getStockDisponible(): float
    {
        return $this->stock_actual - $this->stock_reservado;
    }
}
```

#### Paso 3.2: Crear Tables

```php
// src/Model/Table/BomsTable.php
<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Table\TableBase;
use Cake\Validation\Validator;

class BomsTable extends TableBase
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('boms');
        $this->setPrimaryKey('id');

        $this->belongsTo('Partes', [
            'foreignKey' => 'id_parte',
        ]);
        $this->hasMany('Variantes', [
            'foreignKey' => 'bom_id',
        ]);
        $this->hasMany('RutasProduccion', [
            'foreignKey' => 'bom_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->uuid('id')
            ->requirePresence('id_parte', 'create')
            ->numeric('id_parte')
            ->requirePresence('version', 'create')
            ->numeric('version')
            ->requirePresence('estado', 'create')
            ->inList('estado', ['borrador', 'aprobado', 'obsoleto'])
            ->notEmptyString('estado');
    }

    public function crearVersion(int $parteId, array $data): ?array
    {
        try {
            $this->getConnection()->begin();

            // Obtener última versión
            $ultimaBom = $this->find()
                ->where(['id_parte' => $parteId])
                ->orderBy(['version' => 'DESC'])
                ->first();

            $nuevaVersion = 1;
            if ($ultimaBom) {
                $nuevaVersion = $ultimaBom->version + 1;
            }

            // Crear nueva BOM
            $bom = $this->newEmptyEntity();
            $bom->id_parte = $parteId;
            $bom->version = $nuevaVersion;
            $bom->estado = 'borrador';
            $bom = $this->patchEntity($bom, $data);

            if (!$this->save($bom)) {
                throw new \Exception('Error al crear la BOM');
            }

            // Desactivar versión anterior si existe
            if ($ultimaBom) {
                $ultimaBom->estado = 'obsoleto';
                $this->save($ultimaBom);
            }

            $this->getConnection()->commit();

            return [
                'success' => true,
                'bom' => $bom,
            ];
        } catch (\Exception $e) {
            $this->getConnection()->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function aprobar(int $bomId): array
    {
        $bom = $this->find()
            ->where(['id' => $bomId])
            ->first();

        if (!$bom) {
            return [
                'success' => false,
                'error' => 'BOM no encontrada',
            ];
        }

        if ($bom->estado === 'aprobado') {
            return [
                'success' => false,
                'error' => 'BOM ya está aprobada',
            ];
        }

        try {
            $this->getConnection()->begin();

            // Desaprobar versiones anteriores
            $this->updateAll(
                ['estado' => 'obsoleto'],
                ['id_parte' => $bom->id_parte, 'estado' => 'aprobado']
            );

            // Aprobar BOM actual
            $bom->estado = 'aprobado';
            if (!$this->save($bom)) {
                throw new \Exception('Error al aprobar la BOM');
            }

            $this->getConnection()->commit();

            return [
                'success' => true,
                'bom' => $bom,
            ];
        } catch (\Exception $e) {
            $this->getConnection()->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
```

```php
// src/Model/Table/PartesTable.php
<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Table\TableBase;
use Cake\Validation\Validator;

class PartesTable extends TableBase
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('partes');
        $this->setPrimaryKey('id');

        $this->belongsTo('GruposPartes', [
            'foreignKey' => 'grupo_parte_id',
        ]);
        $this->belongsTo('TiposPartes', [
            'foreignKey' => 'tipo_parte_id',
        ]);
        $this->hasMany('Variantes', [
            'foreignKey' => 'id_parte',
        ]);
        $this->hasMany('Boms', [
            'foreignKey' => 'id_parte',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->uuid('id')
            ->requirePresence('codigo', 'create')
            ->notEmptyString('codigo')
            ->requirePresence('descripcion', 'create')
            ->notEmptyString('descripcion')
            ->requirePresence('grupo_parte_id', 'create')
            ->numeric('grupo_parte_id')
            ->requirePresence('tipo_parte_id', 'create')
            ->numeric('tipo_parte_id');
    }

    public function getStock(int $parteId): array
    {
        $parte = $this->find()
            ->where(['id' => $parteId])
            ->first();

        if (!$parte) {
            return [
                'success' => false,
                'error' => 'Parte no encontrada',
            ];
        }

        return [
            'success' => true,
            'parte' => $parte,
            'stock_actual' => $parte->stock_actual,
            'stock_reservado' => $parte->stock_reservado,
            'stock_disponible' => $parte->stock_actual - $parte->stock_reservado,
        ];
    }

    public function actualizarStock(int $parteId, float $cantidad, string $tipo): array
    {
        $parte = $this->find()
            ->where(['id' => $parteId])
            ->first();

        if (!$parte) {
            return [
                'success' => false,
                'error' => 'Parte no encontrada',
            ];
        }

        try {
            $this->getConnection()->begin();

            switch ($tipo) {
                case 'entrada':
                    $parte->stock_actual += $cantidad;
                    break;
                case 'salida':
                    if ($parte->stock_actual < $cantidad) {
                        throw new \Exception('Stock insuficiente');
                    }
                    $parte->stock_actual -= $cantidad;
                    break;
                case 'reservar':
                    $parte->stock_reservado += $cantidad;
                    break;
                case 'desreservar':
                    $parte->stock_reservado -= $cantidad;
                    break;
                default:
                    throw new \Exception('Tipo de movimiento inválido');
            }

            if (!$this->save($parte)) {
                throw new \Exception('Error al actualizar el stock');
            }

            $this->getConnection()->commit();

            return [
                'success' => true,
                'parte' => $parte,
            ];
        } catch (\Exception $e) {
            $this->getConnection()->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
```

#### Paso 3.3: Crear Services

```php
// src/Service/MaestroImportExportService.php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Table\PartesTable;
use App\Model\Table\BomsTable;
use Cake\ORM\TableRegistry;
use Cake\Datasource\EntityInterface;

class MaestroImportExportService extends ServiceBase
{
    private PartesTable $Partes;
    private BomsTable $Boms;

    public function initialize(): void
    {
        $this->Partes = $this->getTableLocator()->get('Partes');
        $this->Boms = $this->getTableLocator()->get('Boms');
    }

    public function exportarPartes(): array
    {
        try {
            $partes = $this->Partes->find()
                ->contain(['GruposPartes', 'TiposPartes'])
                ->toArray();

            $data = [];
            foreach ($partes as $parte) {
                $data[] = [
                    'codigo' => $parte->codigo,
                    'descripcion' => $parte->descripcion,
                    'grupo_parte' => $parte->grupo_parte->nombre ?? '',
                    'tipo_parte' => $parte->tipo_parte->nombre ?? '',
                    'unidad_medida' => $parte->unidad_medida->codigo ?? '',
                    'stock_actual' => $parte->stock_actual,
                    'stock_minimo' => $parte->stock_minimo,
                    'activo' => $parte->activo ? 'SI' : 'NO',
                ];
            }

            return [
                'success' => true,
                'data' => $data,
                'total' => count($data),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function importarPartes(array $data): array
    {
        try {
            $this->Partes->getConnection()->begin();

            $resultados = [
                'exitosos' => 0,
                'fallidos' => 0,
                'errores' => [],
            ];

            foreach ($data as $row) {
                $parte = $this->Partes->find()
                    ->where(['codigo' => $row['codigo']])
                    ->first();

                if (!$parte) {
                    $parte = $this->Partes->newEmptyEntity();
                }

                $grupoParte = $this->getTableLocator()->get('GruposPartes')->find()
                    ->where(['nombre' => $row['grupo_parte']])
                    ->first();

                $tipoParte = $this->getTableLocator()->get('TiposPartes')->find()
                    ->where(['nombre' => $row['tipo_parte']])
                    ->first();

                $unidadMedida = $this->getTableLocator()->get('UnidadesMedidas')->find()
                    ->where(['codigo' => $row['unidad_medida']])
                    ->first();

                $parte = $this->Partes->patchEntity($parte, [
                    'codigo' => $row['codigo'],
                    'descripcion' => $row['descripcion'],
                    'grupo_parte_id' => $grupoParte->id ?? null,
                    'tipo_parte_id' => $tipoParte->id ?? null,
                    'unidad_medida_id' => $unidadMedida->id ?? null,
                    'stock_actual' => $row['stock_actual'] ?? 0,
                    'stock_minimo' => $row['stock_minimo'] ?? 0,
                    'activo' => $row['activo'] === 'SI',
                ]);

                if ($this->Partes->save($parte)) {
                    $resultados['exitosos']++;
                } else {
                    $resultados['fallidos']++;
                    $resultados['errores'][] = [
                        'fila' => $row,
                        'errores' => $parte->getErrors(),
                    ];
                }
            }

            $this->Partes->getConnection()->commit();

            return [
                'success' => true,
                'resultados' => $resultados,
            ];
        } catch (\Exception $e) {
            $this->Partes->getConnection()->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function exportarBom(int $bomId): array
    {
        $bom = $this->Boms->find()
            ->where(['id' => $bomId])
            ->contain(['Partes', 'RutasProduccion'])
            ->first();

        if (!$bom) {
            return [
                'success' => false,
                'error' => 'BOM no encontrada',
            ];
        }

        $data = [
            'bom' => [
                'codigo' => $bom->parte->codigo,
                'descripcion' => $bom->parte->descripcion,
                'version' => $bom->version,
                'estado' => $bom->estado,
            ],
            'componentes' => [],
            'rutas' => [],
        ];

        foreach ($bom->rutas_produccion as $ruta) {
            $data['rutas'][] = [
                'secuencia' => $ruta->secuencia,
                'centro_trabajo' => $ruta->centro_trabajo->codigo ?? '',
                'descripcion' => $ruta->descripcion,
                'tiempo_setup_mins' => $ruta->tiempo_setup_mins,
                'tiempo_proceso_unitario_mins' => $ruta->tiempo_proceso_unitario_mins,
                'tiempo_cola_mins' => $ruta->tiempo_cola_mins,
                'tiempo_movimiento_mins' => $ruta->tiempo_movimiento_mins,
            ];
        }

        return [
            'success' => true,
            'data' => $data,
        ];
    }

    public function importarBom(int $parteId, array $data): array
    {
        try {
            $this->Boms->getConnection()->begin();

            // Crear nueva versión de BOM
            $bom = $this->Boms->newEmptyEntity();
            $bom->id_parte = $parteId;
            $bom->version = 1;
            $bom->estado = 'borrador';

            // Obtener última versión
            $ultimaBom = $this->Boms->find()
                ->where(['id_parte' => $parteId])
                ->orderBy(['version' => 'DESC'])
                ->first();

            if ($ultimaBom) {
                $bom->version = $ultimaBom->version + 1;
            }

            $bom = $this->Boms->patchEntity($bom, [
                'estado' => 'borrador',
            ]);

            if (!$this->Boms->save($bom)) {
                throw new \Exception('Error al crear la BOM');
            }

            // Importar rutas
            foreach ($data['rutas'] as $rutaData) {
                $centroTrabajo = $this->getTableLocator()->get('CentrosTrabajo')->find()
                    ->where(['codigo' => $rutaData['centro_trabajo']])
                    ->first();

                $ruta = $this->getTableLocator()->get('RutasProduccion')->newEmptyEntity();
                $ruta = $this->getTableLocator()->get('RutasProduccion')->patchEntity($ruta, [
                    'bom_id' => $bom->id,
                    'secuencia' => $rutaData['secuencia'],
                    'centro_trabajo_id' => $centroTrabajo->id ?? null,
                    'descripcion' => $rutaData['descripcion'],
                    'tiempo_setup_mins' => $rutaData['tiempo_setup_mins'],
                    'tiempo_proceso_unitario_mins' => $rutaData['tiempo_proceso_unitario_mins'],
                    'tiempo_cola_mins' => $rutaData['tiempo_cola_mins'],
                    'tiempo_movimiento_mins' => $rutaData['tiempo_movimiento_mins'],
                ]);

                if (!$this->getTableLocator()->get('RutasProduccion')->save($ruta)) {
                    throw new \Exception('Error al crear la ruta');
                }
            }

            $this->Boms->getConnection()->commit();

            return [
                'success' => true,
                'bom' => $bom,
            ];
        } catch (\Exception $e) {
            $this->Boms->getConnection()->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
```

### 4. Alertas de Riesgo

#### Riesgo 1: Validación de BOMs
**Nivel**: Alto
**Impacto**: Crítico

**Descripción**: Las BOMs deben ser validadas para evitar errores en la producción.

**Mitigación**: Implementar validaciones estrictas en el Service y Table.

#### Riesgo 2: Importación Masiva
**Nivel**: Medio
**Impacto**: Alto

**Descripción**: La importación masiva debe manejar errores y mantener integridad de datos.

**Mitigación**: Usar transacciones y validar cada fila antes de importar.

---

## 🎯 Módulo: Reportes

### 1. Resumen Ejecutivo

El módulo de Reportes genera informes de producción, inventario, calidad y otros. Requiere integración con librerías de generación de reportes.

### 2. Tabla de Mapeo

| Componente Actual | Componente CakePHP | Acción Requerida | Justificación Técnica |
| :--- | :--- | :--- | :--- |
| `Reportes/ReportesController.php` | `App\Controller\Reportes\ReportesController.php` | Crear Controller | Generación de reportes |
| `Reportes/ListadoIngenieriaExportService.php` | `App\Service\EngineeringExportService.php` | Crear Service | Exportación de ingeniería |

### 3. Guía de Implementación

#### Paso 3.1: Crear Service

```php
// src/Service/EngineeringExportService.php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Table\PartesTable;
use App\Model\Table\VariantesTable;
use App\Model\Table\BomsTable;
use Cake\ORM\TableRegistry;
use Dompdf\Dompdf;
use Dompdf\Options;

class EngineeringExportService extends ServiceBase
{
    private PartesTable $Partes;
    private VariantesTable $Variantes;
    private BomsTable $Boms;

    public function initialize(): void
    {
        $this->Partes = $this->getTableLocator()->get('Partes');
        $this->Variantes = $this->getTableLocator()->get('Variantes');
        $this->Boms = $this->getTableLocator()->get('Boms');
    }

    public function exportarListadoIngenieria(string $formato = 'pdf'): array
    {
        try {
            $partes = $this->Partes->find()
                ->contain(['GruposPartes', 'TiposPartes', 'UnidadesMedida'])
                ->toArray();

            $data = [];
            foreach ($partes as $parte) {
                $variantes = $this->Variantes->find()
                    ->where(['id_parte' => $parte->id])
                    ->contain(['Boms.RutasProduccion'])
                    ->toArray();

                $data[] = [
                    'parte' => $parte,
                    'variantes' => $variantes,
                ];
            }

            if ($formato === 'pdf') {
                return $this->exportarPdf($data);
            } elseif ($formato === 'excel') {
                return $this->exportarExcel($data);
            } else {
                throw new \Exception('Formato no soportado');
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function exportarPdf(array $data): array
    {
        try {
            $dompdf = new Dompdf();
            
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $dompdf->setOptions($options);

            $html = $this->generarHtml($data);
            $dompdf->loadHtml($html);
            $dompdf->render();

            $pdf = $dompdf->output();
            $pdfBase64 = base64_encode($pdf);

            return [
                'success' => true,
                'pdf' => $pdfBase64,
                'filename' => 'listado_ingenieria_' . date('Y-m-d') . '.pdf',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function exportarExcel(array $data): array
    {
        try {
            // Usar PhpSpreadsheet
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Encabezados
            $sheet->setCellValue('A1', 'Código');
            $sheet->setCellValue('B1', 'Descripción');
            $sheet->setCellValue('C1', 'Grupo');
            $sheet->setCellValue('D1', 'Tipo');
            $sheet->setCellValue('E1', 'Variante');
            $sheet->setCellValue('F1', 'BOM');
            $sheet->setCellValue('G1', 'Ruta');

            $row = 2;
            foreach ($data as $parteData) {
                $parte = $parteData['parte'];
                
                if (empty($parteData['variantes'])) {
                    $sheet->setCellValue("A{$row}", $parte->codigo);
                    $sheet->setCellValue("B{$row}", $parte->descripcion);
                    $sheet->setCellValue("C{$row}", $parte->grupo_parte->nombre ?? '');
                    $sheet->setCellValue("D{$row}", $parte->tipo_parte->nombre ?? '');
                    $row++;
                } else {
                    foreach ($parteData['variantes'] as $variante) {
                        $bom = $variante->bom;
                        
                        if (empty($bom->rutas_produccion)) {
                            $sheet->setCellValue("A{$row}", $parte->codigo);
                            $sheet->setCellValue("B{$row}", $parte->descripcion);
                            $sheet->setCellValue("C{$row}", $parte->grupo_parte->nombre ?? '');
                            $sheet->setCellValue("D{$row}", $parte->tipo_parte->nombre ?? '');
                            $sheet->setCellValue("E{$row}", $variante->codigo_variante);
                            $sheet->setCellValue("F{$row}", "v{$bom->version} ({$bom->estado})");
                            $row++;
                        } else {
                            foreach ($bom->rutas_produccion as $ruta) {
                                $sheet->setCellValue("A{$row}", $parte->codigo);
                                $sheet->setCellValue("B{$row}", $parte->descripcion);
                                $sheet->setCellValue("C{$row}", $parte->grupo_parte->nombre ?? '');
                                $sheet->setCellValue("D{$row}", $parte->tipo_parte->nombre ?? '');
                                $sheet->setCellValue("E{$row}", $variante->codigo_variante);
                                $sheet->setCellValue("F{$row}", "v{$bom->version} ({$bom->estado})");
                                $sheet->setCellValue("G{$row}", $ruta->secuencia . " - " . $ruta->descripcion);
                                $row++;
                            }
                        }
                    }
                }
            }

            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $tempFile = tempnam(sys_get_temp_dir(), 'ingenieria');
            $writer->save($tempFile);

            $excel = file_get_contents($tempFile);
            unlink($tempFile);

            return [
                'success' => true,
                'excel' => base64_encode($excel),
                'filename' => 'listado_ingenieria_' . date('Y-m-d') . '.xlsx',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function generarHtml(array $data): string
    {
        $html = '<html><body>';
        $html .= '<h1>Listado de Ingeniería</h1>';
        $html .= '<table border="1">';
        $html .= '<tr><th>Código</th><th>Descripción</th><th>Grupo</th><th>Tipo</th><th>Variante</th><th>BOM</th><th>Ruta</th></tr>';

        foreach ($data as $parteData) {
            $parte = $parteData['parte'];
            
            if (empty($parteData['variantes'])) {
                $html .= "<tr>";
                $html .= "<td>{$parte->codigo}</td>";
                $html .= "<td>{$parte->descripcion}</td>";
                $html .= "<td>{$parte->grupo_parte->nombre ?? ''}</td>";
                $html .= "<td>{$parte->tipo_parte->nombre ?? ''}</td>";
                $html .= "<td>-</td>";
                $html .= "<td>-</td>";
                $html .= "<td>-</td>";
                $html .= "</tr>";
            } else {
                foreach ($parteData['variantes'] as $variante) {
                    $bom = $variante->bom;
                    
                    if (empty($bom->rutas_produccion)) {
                        $html .= "<tr>";
                        $html .= "<td>{$parte->codigo}</td>";
                        $html .= "<td>{$parte->descripcion}</td>";
                        $html .= "<td>{$parte->grupo_parte->nombre ?? ''}</td>";
                        $html .= "<td>{$parte->tipo_parte->nombre ?? ''}</td>";
                        $html .= "<td>{$variante->codigo_variante}</td>";
                        $html .= "<td>v{$bom->version} ({$bom->estado})</td>";
                        $html .= "<td>-</td>";
                        $html .= "</tr>";
                    } else {
                        foreach ($bom->rutas_produccion as $ruta) {
                            $html .= "<tr>";
                            $html .= "<td>{$parte->codigo}</td>";
                            $html .= "<td>{$parte->descripcion}</td>";
                            $html .= "<td>{$parte->grupo_parte->nombre ?? ''}</td>";
                            $html .= "<td>{$parte->tipo_parte->nombre ?? ''}</td>";
                            $html .= "<td>{$variante->codigo_variante}</td>";
                            $html .= "<td>v{$bom->version} ({$bom->estado})</td>";
                            $html .= "<td>{$ruta->secuencia} - {$ruta->descripcion}</td>";
                            $html .= "</tr>";
                        }
                    }
                }
            }
        }

        $html .= '</table>';
        $html .= '</body></html>';

        return $html;
    }
}
```

### 4. Alertas de Riesgo

#### Riesgo 1: Generación de Reportes
**Nivel**: Medio
**Impacto**: Medio

**Descripción**: La generación de reportes puede ser lenta para grandes volúmenes de datos.

**Mitigación**: Implementar generación asíncrona de reportes y caché.

#### Riesgo 2: Formatos de Exportación
**Nivel**: Bajo
**Impacto**: Bajo

**Descripción**: Diferentes formatos pueden requerir librerías diferentes.

**Mitigación**: Usar librerías estándar como Dompdf y PhpSpreadsheet.

---

## 📋 Checklist de Implementación por Módulo

### Módulo Producción
- [ ] Crear Entities (OrdenProduccion, RutaProduccion, CentroTrabajo, Planificacion)
- [ ] Crear Tables (OrdenesProduccionTable, RutasProduccionTable, CentrosTrabajoTable, PlanificacionesTable)
- [ ] Crear Services (OrdenProduccionService, RutaProduccionService, CentroTrabajoService, PlanificacionService)
- [ ] Crear Controladores (OrdenesProduccionController, RutasProduccionController, CentrosTrabajoController, PlanificacionController)
- [ ] Crear tests unitarios
- [ ] Crear tests de integración
- [ ] Migrar datos

### Módulo Productos
- [ ] Crear Entities (Parte, Variante, Bom)
- [ ] Crear Tables (PartesTable, VariantesTable, BomsTable)
- [ ] Crear Services (MaestroImportExportService, PartesImportService, GeometryRecalculationService)
- [ ] Crear Controladores (PartesController, VariantesController, BomsController)
- [ ] Crear tests unitarios
- [ ] Crear tests de integración
- [ ] Migrar datos

### Módulo Reportes
- [ ] Crear Services (EngineeringExportService, ReportesService)
- [ ] Crear Controladores (ReportesController)
- [ ] Crear tests unitarios
- [ ] Migrar datos

---

**Versión**: 1.0
**Fecha**: 2024-01-15
**Autor**: Arquitecto de Software Senior
