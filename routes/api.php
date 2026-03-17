<?php

use App\Controllers\Api\DebugController;
use App\Controllers\Api\FixController;
use App\Controllers\Api\RevertController;
use App\Controllers\Api\HealthController;
use App\Controllers\Api\SearchController;
use App\Controllers\Admin\DepositosValidacionesController;

/** @var \App\Core\Routing\Router $router */
$router->get('/api/v1/health', [HealthController::class, '__invoke']);

// Debug endpoints (desarrollo)
$router->get('/api/v1/debug/tenant', [DebugController::class, 'tenant']);
$router->get('/api/v1/debug/databases', [DebugController::class, 'databases']);
$router->get('/api/v1/debug/companies', [DebugController::class, 'companies']);
$router->get('/api/v1/debug/fix-database-name', [FixController::class, 'fixDatabaseName']);
$router->get('/api/v1/debug/revert-database-name', [RevertController::class, 'revertDatabaseName']);

// Search API endpoints
$router->post('/api/v1/search/variantes', [SearchController::class, 'searchVariantes']);
$router->get('/api/v1/search/variantes/:id', [SearchController::class, 'getVariante']);
$router->post('/api/v1/search/partes', [SearchController::class, 'searchPartes']);
$router->post('/api/v1/search/tipos-partes', [SearchController::class, 'searchTiposParte']);
$router->post('/api/v1/search/centros-trabajo', [SearchController::class, 'searchCentrosTrabajo']);

// BOM API endpoints
$router->get('/api/v1/bom/variantes/{id}/nivel1', [SearchController::class, 'getBomNivel1']);

// Depósitos - Validaciones de movimientos
$router->get('/api/v1/depositos-validaciones/{origenId}/destinos', [DepositosValidacionesController::class, 'getDestinosPermitidos']);
$router->post('/api/v1/depositos-validaciones/validar', [DepositosValidacionesController::class, 'validarMovimiento']);
