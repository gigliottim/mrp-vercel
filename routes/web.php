<?php

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\Admin\CentrosTrabajoController;
use App\Controllers\Admin\GruposPartesController;
use App\Controllers\Admin\TiposPartesController;
use App\Controllers\Admin\TiposDepositosController;
use App\Controllers\Admin\DepositosValidacionesController;
use App\Controllers\Admin\ConfiguracionController;
use App\Controllers\Admin\EntidadesController;
use App\Controllers\Admin\UnidadesMedidaController;
use App\Controllers\Admin\PartesVariantesController;
use App\Controllers\Admin\PartesImportController;
use App\Controllers\Admin\EmpresaUsuariosController;
use App\Controllers\Planeamiento\SugerenciasController;
use App\Controllers\Planeamiento\OrdenesController;
use App\Controllers\Produccion\EjecucionController;
use App\Controllers\Produccion\OperacionesController;
use App\Controllers\Produccion\CentrosTrabajoController as ProduccionCentrosController;
use App\Controllers\Produccion\RutasProduccionController;
use App\Controllers\Produccion\OrdenesProduccionController;
use App\Controllers\Produccion\PlanificacionController;
use App\Controllers\Productos\BomController;
use App\Controllers\Productos\ComposicionController;
use App\Controllers\Productos\HerramientasBomController;
use App\Controllers\Productos\MaestroImportController;
use App\Controllers\Inventario\CriticoController;
use App\Controllers\Transacciones\MovimientosPartesController;
use App\Controllers\Transacciones\ComprasController;
use App\Controllers\Reportes\ReportesController;
use App\Core\Http\Response;

/** @var \App\Core\Routing\Router $router */
$router->get('/', [HomeController::class, 'index']);
$router->get('/dashboard', [HomeController::class, 'dashboard']);
$router->get('/menu', [HomeController::class, 'menu']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/login', [AuthController::class, 'authenticate']);
$router->post('/logout', [AuthController::class, 'logout']);

// Planeamiento MRP
$router->get('/planeamiento/sugerencias', [SugerenciasController::class, 'index']);
$router->get('/planeamiento/ordenes', [OrdenesController::class, 'index']);

// Producción
$router->get('/produccion', [OperacionesController::class, 'index']); // Dashboard principal
$router->get('/produccion/ejecucion', [EjecucionController::class, 'index']);
$router->get('/produccion/operaciones', [OperacionesController::class, 'index']); // Alias para compatibilidad

// Producción - Centros de Trabajo (nuevo módulo)
$router->get('/produccion/centros-trabajo', [ProduccionCentrosController::class, 'index']);
$router->get('/produccion/centros-trabajo/create', [ProduccionCentrosController::class, 'create']);
$router->post('/produccion/centros-trabajo', [ProduccionCentrosController::class, 'store']);
$router->get('/produccion/centros-trabajo/{id}', [ProduccionCentrosController::class, 'show']);
$router->get('/produccion/centros-trabajo/{id}/edit', [ProduccionCentrosController::class, 'edit']);
$router->put('/produccion/centros-trabajo/{id}', [ProduccionCentrosController::class, 'update']);
$router->delete('/produccion/centros-trabajo/{id}', [ProduccionCentrosController::class, 'destroy']);

// Producción - Rutas de Producción
$router->get('/produccion/rutas', [RutasProduccionController::class, 'index']);
$router->get('/produccion/rutas/create', [RutasProduccionController::class, 'create']);
$router->post('/produccion/rutas', [RutasProduccionController::class, 'store']);
$router->get('/produccion/rutas/{id}', [RutasProduccionController::class, 'show']);
$router->get('/produccion/rutas/{id}/edit', [RutasProduccionController::class, 'edit']);
$router->put('/produccion/rutas/{id}', [RutasProduccionController::class, 'update']);
$router->delete('/produccion/rutas/{id}', [RutasProduccionController::class, 'destroy']);
$router->get('/produccion/rutas/{id}/editor', [RutasProduccionController::class, 'editor']);
$router->post('/produccion/rutas/{id}/operaciones', [RutasProduccionController::class, 'addOperacion']);
$router->put('/produccion/rutas/operaciones/{opId}', [RutasProduccionController::class, 'updateOperacion']);
$router->delete('/produccion/rutas/operaciones/{opId}', [RutasProduccionController::class, 'deleteOperacion']);

// Producción - Órdenes de Producción
$router->get('/produccion/ordenes', [OrdenesProduccionController::class, 'index']);
$router->get('/produccion/ordenes/create', [OrdenesProduccionController::class, 'create']);
$router->post('/produccion/ordenes', [OrdenesProduccionController::class, 'store']);
$router->get('/produccion/ordenes/{id}', [OrdenesProduccionController::class, 'show']);
$router->get('/produccion/ordenes/{id}/edit', [OrdenesProduccionController::class, 'edit']);
$router->put('/produccion/ordenes/{id}', [OrdenesProduccionController::class, 'update']);
$router->delete('/produccion/ordenes/{id}', [OrdenesProduccionController::class, 'destroy']);
$router->post('/produccion/ordenes/{id}/liberar', [OrdenesProduccionController::class, 'liberar']);
$router->post('/produccion/ordenes/{id}/iniciar', [OrdenesProduccionController::class, 'iniciar']);
$router->post('/produccion/ordenes/{id}/completar', [OrdenesProduccionController::class, 'completar']);

// Producción - Planificación
$router->get('/produccion/planificacion', [PlanificacionController::class, 'index']);
$router->get('/produccion/planificacion/gantt', [PlanificacionController::class, 'gantt']);
$router->post('/produccion/planificacion/calcular', [PlanificacionController::class, 'calcular']);
$router->post('/produccion/planificacion/asignar', [PlanificacionController::class, 'asignarRecursos']);

// Produccion - Centros de trabajo (legacy - mantener compatibilidad)
$router->get('/produccion/centros', [CentrosTrabajoController::class, 'index']);
$router->get('/produccion/centros/{id}/editar', [CentrosTrabajoController::class, 'edit']);
$router->post('/produccion/centros', [CentrosTrabajoController::class, 'store']);
$router->put('/produccion/centros/{id}', [CentrosTrabajoController::class, 'update']);
$router->delete('/produccion/centros/{id}', [CentrosTrabajoController::class, 'destroy']);

// Configuracion - Grupos de partes
$router->get('/configuracion/grupos-partes', [GruposPartesController::class, 'index']);
$router->get('/configuracion/grupos-partes/{id}/editar', [GruposPartesController::class, 'edit']);
$router->post('/configuracion/grupos-partes', [GruposPartesController::class, 'store']);
$router->put('/configuracion/grupos-partes/{id}', [GruposPartesController::class, 'update']);
$router->delete('/configuracion/grupos-partes/{id}', [GruposPartesController::class, 'destroy']);

// Inventario - Stock Crítico
$router->get('/inventario/critico', [CriticoController::class, 'index']);

// Transacciones - Movimientos de Partes
$router->get('/transacciones/movimientos-partes', [MovimientosPartesController::class, 'index']);
$router->post('/transacciones/movimientos-partes', [MovimientosPartesController::class, 'store']);

// Reportes
$router->get('/reportes/destino-partes', [ReportesController::class, 'destinoPartes']);
$router->get('/reportes/listado-ingenieria', [ReportesController::class, 'listadoIngenieria']);
$router->get('/reportes/planificacion-produccion', [ReportesController::class, 'planificacionProduccion']);
$router->get('/reportes/resumen-grupos', [ReportesController::class, 'resumenGrupos']);

// Alias para compatibilidad con URLs antiguas
$router->get('/inventario/grupos', static function ($request) {
    return Response::redirect(url('/configuracion/grupos-partes'));
});
$router->get('/inventario/grupos/{id}/editar', static function ($request, $id) {
    return Response::redirect(url('/configuracion/grupos-partes/' . $id . '/editar'));
});
$router->get('/productos/variantes', static function ($request) {
    return Response::redirect(url('/productos/partes?tab=variantes'));
});

// Configuracion - Tipos de partes
$router->get('/configuracion/tipos-partes', [TiposPartesController::class, 'index']);
$router->get('/configuracion/tipos-partes/{id}/editar', [TiposPartesController::class, 'edit']);
$router->post('/configuracion/tipos-partes', [TiposPartesController::class, 'store']);
$router->put('/configuracion/tipos-partes/{id}', [TiposPartesController::class, 'update']);
$router->delete('/configuracion/tipos-partes/{id}', [TiposPartesController::class, 'destroy']);

// Configuracion - Tipos de depósito
$router->get('/configuracion/tipos-depositos', [TiposDepositosController::class, 'index']);
$router->get('/configuracion/tipos-depositos/{id}/editar', [TiposDepositosController::class, 'edit']);
$router->post('/configuracion/tipos-depositos', [TiposDepositosController::class, 'store']);
$router->put('/configuracion/tipos-depositos/{id}', [TiposDepositosController::class, 'update']);
$router->delete('/configuracion/tipos-depositos/{id}', [TiposDepositosController::class, 'destroy']);

// Configuracion - Validaciones de movimientos entre depósitos
$router->get('/configuracion/depositos-validaciones', [DepositosValidacionesController::class, 'index']);
$router->get('/configuracion/depositos-validaciones/{id}/editar', [DepositosValidacionesController::class, 'edit']);
$router->put('/configuracion/depositos-validaciones/{id}', [DepositosValidacionesController::class, 'update']);
$router->delete('/configuracion/depositos-validaciones/{id}', [DepositosValidacionesController::class, 'destroy']);

// Configuracion - General
$router->get('/configuracion/general', [ConfiguracionController::class, 'index']);
$router->post('/configuracion/general', [ConfiguracionController::class, 'update']);
$router->post('/configuracion/general/recalcular-dimensiones-partes', [ConfiguracionController::class, 'recalculatePartesGeometry']);

// Configuracion - Entidades (Clientes/Proveedores)
$router->get('/configuracion/entidades', [EntidadesController::class, 'index']);
$router->get('/configuracion/entidades/{id}/editar', [EntidadesController::class, 'edit']);
$router->post('/configuracion/entidades', [EntidadesController::class, 'store']);
$router->put('/configuracion/entidades/{id}', [EntidadesController::class, 'update']);
$router->delete('/configuracion/entidades/{id}', [EntidadesController::class, 'destroy']);

// Empresa y Usuarios
$router->get('/empresa-usuarios/empresa', [EmpresaUsuariosController::class, 'empresa']);
$router->get('/empresa-usuarios/empresa/{id}/editar', [EmpresaUsuariosController::class, 'empresaEdit']);
$router->post('/empresa-usuarios/empresa', [EmpresaUsuariosController::class, 'empresaStore']);
$router->put('/empresa-usuarios/empresa/{id}', [EmpresaUsuariosController::class, 'empresaUpdate']);
$router->delete('/empresa-usuarios/empresa/{id}', [EmpresaUsuariosController::class, 'empresaDestroy']);

$router->get('/empresa-usuarios/usuarios', [EmpresaUsuariosController::class, 'usuarios']);
$router->get('/empresa-usuarios/usuarios/{id}/editar', [EmpresaUsuariosController::class, 'usuariosEdit']);
$router->post('/empresa-usuarios/usuarios', [EmpresaUsuariosController::class, 'usuariosStore']);
$router->put('/empresa-usuarios/usuarios/{id}', [EmpresaUsuariosController::class, 'usuariosUpdate']);
$router->delete('/empresa-usuarios/usuarios/{id}', [EmpresaUsuariosController::class, 'usuariosDestroy']);

$router->get('/empresa-usuarios/roles', [EmpresaUsuariosController::class, 'roles']);
$router->get('/empresa-usuarios/roles/{id}/editar', [EmpresaUsuariosController::class, 'rolesEdit']);
$router->post('/empresa-usuarios/roles', [EmpresaUsuariosController::class, 'rolesStore']);
$router->put('/empresa-usuarios/roles/{id}', [EmpresaUsuariosController::class, 'rolesUpdate']);
$router->delete('/empresa-usuarios/roles/{id}', [EmpresaUsuariosController::class, 'rolesDestroy']);

$router->get('/empresa-usuarios/permisos', [EmpresaUsuariosController::class, 'permisos']);
$router->get('/empresa-usuarios/permisos/{id}/editar', [EmpresaUsuariosController::class, 'permisosEdit']);
$router->post('/empresa-usuarios/permisos', [EmpresaUsuariosController::class, 'permisosStore']);
$router->put('/empresa-usuarios/permisos/{id}', [EmpresaUsuariosController::class, 'permisosUpdate']);
$router->delete('/empresa-usuarios/permisos/{id}', [EmpresaUsuariosController::class, 'permisosDestroy']);

// Roles/Permisos (endpoints sugeridos)
$router->get('/roles-permisos/arbol', [EmpresaUsuariosController::class, 'permisosTree']);
$router->get('/roles-permisos/acl', [EmpresaUsuariosController::class, 'permisosAcl']);
$router->post('/roles-permisos/acl', [EmpresaUsuariosController::class, 'permisosStore']);
$router->post('/roles-permisos/acl/bulk', [EmpresaUsuariosController::class, 'permisosStoreBulk']);
$router->get('/roles-permisos/acl/{id}/editar', [EmpresaUsuariosController::class, 'permisosEdit']);
$router->put('/roles-permisos/acl/{id}', [EmpresaUsuariosController::class, 'permisosUpdate']);
$router->delete('/roles-permisos/acl/{id}', [EmpresaUsuariosController::class, 'permisosDestroy']);

// Configuracion - Unidades de medida
$router->get('/configuracion/unidades', [UnidadesMedidaController::class, 'index']);
$router->get('/configuracion/unidades/{id}/editar', [UnidadesMedidaController::class, 'edit']);
$router->post('/configuracion/unidades', [UnidadesMedidaController::class, 'store']);
$router->put('/configuracion/unidades/{id}', [UnidadesMedidaController::class, 'update']);
$router->delete('/configuracion/unidades/{id}', [UnidadesMedidaController::class, 'destroy']);

// Productos - BOM y Composición
$router->get('/productos/bom', [BomController::class, 'index']);
$router->get('/productos/maestro', [ComposicionController::class, 'maestro']);

// Productos - Importar/Exportar Maestro BOM
$router->get('/productos/maestro/importar', [MaestroImportController::class, 'index']);
$router->get('/productos/maestro/importar/template', [MaestroImportController::class, 'downloadTemplate']);
$router->get('/productos/maestro/exportar', [MaestroImportController::class, 'export']);
$router->post('/productos/maestro/importar', [MaestroImportController::class, 'import']);

// Productos - Herramientas BOM
$router->get('/productos/copiar-componentes', [HerramientasBomController::class, 'copiarComponentes']);
$router->post('/productos/copiar-componentes', [HerramientasBomController::class, 'ejecutarCopiarComponentes']);
$router->get('/productos/reemplazar-partes', [HerramientasBomController::class, 'reemplazarPartes']);
$router->post('/productos/reemplazar-partes', [HerramientasBomController::class, 'ejecutarReemplazarPartes']);
$router->post('/productos/maestro/materiales', [ComposicionController::class, 'addItem']);
$router->post('/productos/maestro/materiales/validar-candidatos', [ComposicionController::class, 'validateCandidates']);
$router->get('/productos/maestro/materiales/{id}/editar', [ComposicionController::class, 'editItem']);
$router->put('/productos/maestro/materiales/{id}', [ComposicionController::class, 'updateItem']);
$router->delete('/productos/maestro/materiales/{id}', [ComposicionController::class, 'deleteItem']);

// Productos - Partes y variantes (vista tradicional)
$router->get('/productos/partes', [PartesVariantesController::class, 'index']);
$router->post('/productos/partes', [PartesVariantesController::class, 'storePart']);
$router->get('/productos/partes/{id}/editar', [PartesVariantesController::class, 'editPart']);
$router->put('/productos/partes/{id}', [PartesVariantesController::class, 'updatePart']);
$router->delete('/productos/partes/{id}', [PartesVariantesController::class, 'destroyPart']);

// Productos - Importacion de partes y variantes
$router->get('/productos/partes/importar', [PartesImportController::class, 'index']);
$router->get('/productos/partes/importar/template', [PartesImportController::class, 'downloadTemplate']);
$router->post('/productos/partes/importar', [PartesImportController::class, 'import']);

// Productos - Manager moderno (nueva interfaz)
$router->get('/productos/partes/manager', [PartesVariantesController::class, 'manager']);
$router->post('/productos/partes/manager', [PartesVariantesController::class, 'managerStorePart']);
$router->get('/productos/partes/manager/{id}', [PartesVariantesController::class, 'managerShow']);
$router->get('/productos/partes/manager/{id}/editar', [PartesVariantesController::class, 'managerEdit']);
$router->get('/productos/partes/manager/{idParte}/variantes/{idVariante}', [PartesVariantesController::class, 'managerShowVariant']);
$router->get('/productos/partes/manager/{idParte}/variantes/{idVariante}/editar', [PartesVariantesController::class, 'managerEditVariant']);
$router->put('/productos/partes/manager/{id}', [PartesVariantesController::class, 'managerUpdatePart']);

// Variantes de una parte específica
$router->post('/productos/partes/{idParte}/variantes', [PartesVariantesController::class, 'storeVariant']);
$router->get('/productos/partes/{idParte}/variantes/{id}/editar', [PartesVariantesController::class, 'editVariant']);
$router->put('/productos/partes/{idParte}/variantes/{id}', [PartesVariantesController::class, 'updateVariant']);
$router->delete('/productos/partes/{idParte}/variantes/{id}', [PartesVariantesController::class, 'destroyVariant']);

// Transacciones - Compras (Módulo Simple)

// Agente AI
use App\AgenteAI\Backend\Controllers\AgentController;

$router->get('/agent', [AgentController::class, 'showChat']);

$router->get('/compras', [ComprasController::class, 'index']);
$router->get('/compras/create', [ComprasController::class, 'create']);
$router->post('/compras/store', [ComprasController::class, 'store']);
