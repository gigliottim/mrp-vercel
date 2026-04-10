# 📋 Plan de Migración: Custom MRP → CakePHP 5.3

## 🎯 Resumen Ejecutivo

Este documento presenta el plan de migración completo desde el framework personalizado hacia **CakePHP 5.3**, manteniendo la integridad de las reglas de negocio y la arquitectura multi-tenancy.

### Características Clave del Sistema Actual
- **Multi-tenancy**: Cada tenant tiene su propia base de datos PostgreSQL
- **Arquitectura**: MVC con Services y Repositories
- **PHP**: 8.5
- **Infraestructura**: Nginx + Valkey (debe mantenerse)
- **Base de datos**: PostgreSQL con esquemas personalizados

### Complejidad Estimada
- **Alta**: Debido a la complejidad de las reglas de negocio, multi-tenancy y dependencias cruzadas
- **Estimación**: 8-12 semanas para MVP completo con tests

---

## 🧠 Análisis Arquitectónico

### Patrón de Diseño Actual
```
┌─────────────────────────────────────────────────────────────┐
│                    Controladores                            │
│  (Lógica de presentación y orquestación)                    │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                    Servicios                                │
│  (Lógica de negocio compleja, transaccional)                │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                  Repositories                               │
│  (Acceso a datos personalizado, consultas complejas)        │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                    Modelos                                  │
│  (Entidades con lógica básica y validaciones)               │
└─────────────────────────────────────────────────────────────┘
```

### Mapeo a CakePHP 5.3
```
┌─────────────────────────────────────────────────────────────┐
│  Controladores (CakePHP)                                    │
│  ↓                                                          │
│  Components (autenticación, multi-tenancy)                 │
│  ↓                                                          │
│  Table Objects (CakePHP ORM)                                │
│  ↓                                                          │
│  Entities (CakePHP)                                         │
│  ↓                                                          │
│  Database (PostgreSQL)                                      │
└─────────────────────────────────────────────────────────────┘
```

---

## 📊 Tabla de Mapeo Completa

### 1. Infraestructura Core

| Componente Actual | Componente CakePHP | Acción Requerida | Justificación Técnica |
| :--- | :--- | :--- | :--- |
| `Kernel.php` | `Application.php` | Reemplazar con bootstrap de CakePHP | CakePHP maneja el ciclo de vida |
| `Router.php` | `config/routes.php` | Mapear rutas manuales a CakePHP routing | CakePHP routing más robusto |
| `Request.php` | `Psr\Http\Message\ServerRequestInterface` | Usar request de PSR-7 | CakePHP usa PSR-7 por defecto |
| `Response.php` | `Psr\Http\Message\ResponseInterface` | Usar response de PSR-7 | CakePHP usa PSR-7 por defecto |
| `View.php` | `View` (CakePHP) | Extender ViewHelper o usar helpers | CakePHP tiene sistema de views integrado |
| `Config.php` | `config/app.php` | Migrar a configuración de CakePHP | CakePHP config es más estructurado |
| `Logger.php` | `Cake\Log\Log` | Usar Log de CakePHP | Integración con Monolog |
| `SessionManager.php` | `Cake\Http\Session` | Usar Session de CakePHP | Integración con PSR-7 |
| `DatabaseManager.php` | `Cake\Database\Connection` | Crear conexión dinámica por tenant | Multi-tenancy requiere conexión dinámica |
| `TenantContext.php` | `Component/MultiTenant.php` | Crear Component para tenant | Lógica de tenant debe ser reutilizable |
| `AuthManager.php` | `Controller/Component/AuthComponent` | Usar AuthComponent de CakePHP | Autenticación estándar |
| `Env.php` | `config/app.php` | Migrar a variables de entorno | CakePHP usa dotenv por defecto |

### 2. Modelos y Datos

| Componente Actual | Componente CakePHP | Acción Requerida | Justificación Técnica |
| :--- | :--- | :--- | :--- |
| `BaseTenantModel.php` | `Cake\ORM\Table` | Crear Table base con multi-tenancy | Tablas deben extender Table base |
| `Entidad.php` | `App\Model\Table\EntidadesTable` | Crear Table + Entity | CRUD básico |
| `UnidadMedida.php` | `App\Model\Table\UnidadesMedidasTable` | Crear Table + Entity | CRUD con validaciones |
| `ConfiguracionGeneral.php` | `App\Model\Table\ConfiguracionesTable` | Crear Table + Entity | Configuración por tenant |
| `OrdenProduccion.php` | `App\Model\Table\OrdenesProduccionTable` | Crear Table + Entity | Lógica compleja en Table |
| `Variante.php` | `App\Model\Table\VariantesTable` | Crear Table + Entity | Relación con Partes |
| `Parte.php` | `App\Model\Table\PartesTable` | Crear Table + Entity | Lógica de productos |
| `Bom.php` | `App\Model\Table\BomsTable` | Crear Table + Entity | Estructura de materiales |
| `RutaProduccion.php` | `App\Model\Table\RutasProduccionTable` | Crear Table + Entity | Secuencias de operaciones |
| `CentroTrabajo.php` | `App\Model\Table\CentrosTrabajoTable` | Crear Table + Entity | Capacidad y eficiencia |
| `PlanificacionRecurso.php` | `App\Model\Table\PlanificacionesTable` | Crear Table + Entity | Planificación de recursos |

### 3. Repositories

| Repository Actual | Componente CakePHP | Acción Requerida | Justificación Técnica |
| :--- | :--- | :--- | :--- |
| `SearchRepository.php` | `App\Model\SearchQuery` | Crear clase de consulta | Lógica de búsqueda compleja |
| `PlanificacionRepository.php` | `App\Model\Table\PlanificacionesTable` | Mover lógica a Table | Consultas complejas en Table |
| `CentroTrabajoRepository.php` | `App\Model\Table\CentrosTrabajoTable` | Mover lógica a Table | Estadísticas en Table method |
| `OrdenProduccionRepository.php` | `App\Model\Table\OrdenesProduccionTable` | Mover lógica a Table | Dashboard en Table |
| `RutaProduccionRepository.php` | `App\Model\Table\RutasProduccionTable` | Mover lógica a Table | Validaciones en Table |

### 4. Servicios de Negocio

| Servicio Actual | Componente CakePHP | Acción Requerida | Justificación Técnica |
| :--- | :--- | :--- | :--- |
| `AuthService.php` | `App\Service\AuthService` | Crear Service class | Lógica de autenticación |
| `TenantProvisioningService.php` | `App\Service\TenantProvisioningService` | Crear Service class | Provisioning de tenants |
| `EmpresaUsuariosService.php` | `App\Service\EmpresaUsuariosService` | Crear Service class | Gestión de usuarios |
| `EmpresaUsuariosAclService.php` | `App\Service\AclService` | Crear Service class | Control de acceso |
| `MenuService.php` | `App\Service\MenuService` | Crear Service class | Generación de menú |
| `DashboardSummaryService.php` | `App\Service\DashboardService` | Crear Service class | Estadísticas de dashboard |
| `UnitConversionService.php` | `App\Service\UnitConversionService` | Crear Service class | Conversión de unidades |
| `SearchService.php` | `App\Service\SearchService` | Crear Service class | Búsqueda global |
| `StockService.php` | `App\Service\StockService` | Crear Service class | Gestión de stock |
| `OrdenProduccionService.php` | `App\Service\OrdenProduccionService` | Crear Service class | Lógica de órdenes |
| `RutaProduccionService.php` | `App\Service\RutaProduccionService` | Crear Service class | Gestión de rutas |
| `PlanificacionService.php` | `App\Service\PlanificacionService` | Crear Service class | Planificación avanzada |
| `CentroTrabajoService.php` | `App\Service\CentroTrabajoService` | Crear Service class | Gestión de centros |
| `SugerenciasService.php` | `App\Service\SugerenciasService` | Crear Service class | Sugerencias de stock |
| `MaestroImportExportService.php` | `App\Service\MaestroImportExportService` | Crear Service class | Import/Export masivo |
| `PartesVariantesImportService.php` | `App\Service\PartesImportService` | Crear Service class | Importación de partes |
| `PartesGeometryRecalculationService.php` | `App\Service\GeometryRecalculationService` | Crear Service class | Recálculo de geometría |
| `ListadoIngenieriaExportService.php` | `App\Service\EngineeringExportService` | Crear Service class | Exportación de ingeniería |
| `ValidationResult.php` | `App\Model\ValueObject\ValidationResult` | Crear Value Object | Resultado de validación |

### 5. Controladores

| Controlador Actual | Componente CakePHP | Acción Requerida | Justificación Técnica |
| :--- | :--- | :--- | :--- |
| `AuthController.php` | `App\Controller\Api\AuthController` | Crear API Controller | Autenticación API |
| `HomeController.php` | `App\Controller\PagesController` | Usar PagesController | Página de inicio |
| `Admin/*` | `App\Controller\Admin\*` | Crear subnamespace Admin | Admin panel |
| `Produccion/*` | `App\Controller\Produccion\*` | Crear subnamespace Produccion | Módulo producción |
| `Productos/*` | `App\Controller\Productos\*` | Crear subnamespace Productos | Módulo productos |
| `Reportes/*` | `App\Controller\Reportes\*` | Crear subnamespace Reportes | Módulo reportes |
| `Planeamiento/*` | `App\Controller\Planeamiento\*` | Crear subnamespace Planeamiento | Módulo planeamiento |
| `Inventario/*` | `App\Controller\Inventario\*` | Crear subnamespace Inventario | Módulo inventario |
| `Transacciones/*` | `App\Controller\Transacciones\*` | Crear subnamespace Transacciones | Módulo transacciones |
| `Api/*` | `App\Controller\Api\*` | Crear subnamespace Api | API REST |

---

## 🛠️ Guía de Implementación

### Fase 1: Configuración Base (Semana 1-2)

#### Paso 1.1: Instalación de CakePHP 5.3
```bash
# Crear nuevo proyecto CakePHP
composer create-project cakephp/app:5.3.* mrp-cakephp

# Copiar configuración existente
cp -r /home/gigliotti/Proyectos/mrp/config/app.php mrp-cakephp/config/
cp -r /home/gigliotti/Proyectos/mrp/.env mrp-cakephp/config/
```

#### Paso 1.2: Configuración Multi-Tenancy
```php
// config/app.php
'Database' => [
    'default' => [
        'className' => 'Cake\Database\Connection',
        'driver' => 'Cake\Database\Driver\Postgres',
        'persistent' => false,
        'host' => env('DB_HOST', 'localhost'),
        'username' => 'mrp_auth',
        'password' => env('DB_PASSWORD', ''),
        'database' => 'mrp_auth',
        'encoding' => 'utf8',
        'timezone' => 'UTC',
    ],
    'tenant' => [
        'className' => 'App\Database\TenantConnection',
        'driver' => 'Cake\Database\Driver\Postgres',
        'persistent' => false,
        'host' => env('DB_HOST', 'localhost'),
        'username' => env('DB_TENANT_USER', 'mrp_tenant'),
        'password' => env('DB_TENANT_PASSWORD', ''),
        'encoding' => 'utf8',
        'timezone' => 'UTC',
    ],
],
```

#### Paso 1.3: Crear Tenant Connection
```php
// src/Database/TenantConnection.php
<?php

declare(strict_types=1);

namespace App\Database;

use Cake\Database\Connection;
use Cake\Core\Configure;

class TenantConnection extends Connection
{
    protected function _driverClass(): string
    {
        return 'App\Database\Driver\Postgres';
    }

    public function getTenantDatabase(string $tenantId): string
    {
        return Configure::read('Database.tenant.database') . '_' . $tenantId;
    }
}
```

#### Paso 1.4: Crear Driver Personalizado
```php
// src/Database/Driver/Postgres.php
<?php

declare(strict_types=1);

namespace App\Database\Driver;

use Cake\Database\Driver\Postgres as BasePostgres;

class Postgres extends BasePostgres
{
    public function schemaCollection($table = null)
    {
        // Personalizar para multi-tenancy
        return parent::schemaCollection($table);
    }
}
```

### Fase 2: Modelos y Entities (Semana 3-4)

#### Paso 2.1: Crear Base Table
```php
// src/Model/Table/TableBase.php
<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\ORM\Entity;
use Cake\Core\Configure;

class TableBase extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        
        // Configurar schema dinámico por tenant
        $tenantId = $this->getTenantId();
        $this->setSchema('tenant_' . $tenantId);
    }

    protected function getTenantId(): ?string
    {
        // Obtener tenant del contexto
        return Configure::read('Tenant.id');
    }
}
```

#### Paso 2.2: Crear Entities
```php
// src/Model/Entity/Entidad.php
<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Entidad extends Entity
{
    protected $_accessible = [
        '*' => true,
        'id' => false,
    ];

    protected function _getId(): string
    {
        return $this->id ??= $this->generateId();
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
```

#### Paso 2.3: Crear Table Objects
```php
// src/Model/Table/EntidadesTable.php
<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Table\TableBase;
use Cake\ORM\Query;
use Cake\Validation\Validator;

class EntidadesTable extends TableBase
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        
        $this->setTable('entidades');
        $this->setPrimaryKey('id');
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->uuid('id')
            ->requirePresence('codigo', 'create')
            ->notEmptyString('codigo')
            ->requirePresence('nombre', 'create')
            ->notEmptyString('nombre');
    }

    public function findByCodigo(string $codigo): ?array
    {
        return $this->find()
            ->where(['codigo' => $codigo])
            ->toArray();
    }
}
```

### Fase 3: Servicios de Negocio (Semana 5-6)

#### Paso 3.1: Crear Service Base
```php
// src/Service/ServiceBase.php
<?php

declare(strict_types=1);

namespace App\Service;

use Cake\ORM\TableRegistry;

class ServiceBase
{
    protected array $tables = [];

    protected function getTable(string $alias): \Cake\ORM\Table
    {
        if (!isset($this->tables[$alias])) {
            $this->tables[$alias] = TableRegistry::getTableLocator()->get($alias);
        }
        return $this->tables[$alias];
    }
}
```

#### Paso 3.2: Crear AuthService
```php
// src/Service/AuthService.php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Table\UsuariosTable;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

class AuthService extends ServiceBase
{
    private ?array $currentUser = null;

    public function login(string $email, string $password): ?array
    {
        $usuarios = $this->getTable('Usuarios');
        
        $usuario = $usuarios->find()
            ->where(['email' => $email])
            ->first();
        
        if (!$usuario) {
            return null;
        }

        if (!password_verify($password, $usuario->password)) {
            return null;
        }

        if (!$usuario->activo) {
            return null;
        }

        $this->currentUser = $usuario;

        return [
            'usuario' => $usuario,
            'token' => $this->generateToken($usuario),
        ];
    }

    public function logout(): void
    {
        $this->currentUser = null;
    }

    public function getUser(): ?array
    {
        return $this->currentUser;
    }

    public function checkPermission(string $resource, string $action): bool
    {
        if (!$this->currentUser) {
            return false;
        }

        $acl = $this->getTable('Acl');
        
        return $acl->check(
            $this->currentUser['empresa_id'],
            $this->currentUser['rol_id'],
            $resource,
            $action
        );
    }

    private function generateToken(array $usuario): string
    {
        $token = bin2hex(random_bytes(32));
        
        // Guardar token en Valkey
        $redis = new \Predis\Client([
            'host' => env('VALKEY_HOST', '127.0.0.1'),
            'port' => env('VALKEY_PORT', 6379),
        ]);
        
        $redis->setex(
            'auth_token:' . $token,
            3600, // 1 hora
            json_encode($usuario)
        );
        
        return $token;
    }
}
```

#### Paso 3.3: Crear TenantProvisioningService
```php
// src/Service/TenantProvisioningService.php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\ServiceBase;
use Cake\Core\Configure;
use PDO;

class TenantProvisioningService extends ServiceBase
{
    public function provisionTenant(string $empresaId, string $databaseName): bool
    {
        try {
            $authDb = $this->getAuthDatabaseConnection();
            
            // Crear usuario del tenant
            $authDb->exec("CREATE USER mrp_{$empresaId} WITH PASSWORD '{$this->generatePassword()}'");
            
            // Crear base de datos
            $authDb->exec("CREATE DATABASE {$databaseName} OWNER mrp_{$empresaId}");
            
            // Crear schema
            $authDb->exec("CREATE SCHEMA tenant_{$empresaId} AUTHORIZATION mrp_{$empresaId}");
            
            // Grant privileges
            $authDb->exec("GRANT ALL PRIVILEGES ON DATABASE {$databaseName} TO mrp_{$empresaId}");
            $authDb->exec("GRANT ALL PRIVILEGES ON SCHEMA tenant_{$empresaId} TO mrp_{$empresaId}");
            
            // Actualizar registro en base de datos maestra
            $this->updateTenantRecord($empresaId, $databaseName);
            
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to provision tenant: " . $e->getMessage());
        }
    }

    public function deprovisionTenant(string $empresaId): bool
    {
        try {
            $authDb = $this->getAuthDatabaseConnection();
            
            // Revocar privileges
            $authDb->exec("REVOKE ALL PRIVILEGES ON DATABASE mrp_{$empresaId} FROM mrp_{$empresaId}");
            $authDb->exec("REVOKE ALL PRIVILEGES ON SCHEMA tenant_{$empresaId} FROM mrp_{$empresaId}");
            
            // Eliminar usuario
            $authDb->exec("DROP USER IF EXISTS mrp_{$empresaId}");
            
            // Eliminar base de datos
            $authDb->exec("DROP DATABASE IF EXISTS mrp_{$empresaId}");
            
            // Actualizar registro
            $this->updateTenantRecord($empresaId, null);
            
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to deprovision tenant: " . $e->getMessage());
        }
    }

    private function getAuthDatabaseConnection(): PDO
    {
        return new PDO(
            "pgsql:host=" . env('DB_HOST') . ";dbname=mrp_auth",
            env('DB_USER'),
            env('DB_PASSWORD')
        );
    }

    private function generatePassword(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function updateTenantRecord(string $empresaId, ?string $databaseName): void
    {
        $empresas = $this->getTable('Empresas');
        
        $empresa = $empresas->find()
            ->where(['id' => $empresaId])
            ->first();
        
        if (!$empresa) {
            throw new \Exception("Empresa not found: {$empresaId}");
        }
        
        $empresa->database_name = $databaseName;
        $empresas->save($empresa);
    }
}
```

### Fase 4: Controladores (Semana 7)

#### Paso 4.1: Crear Base Controller
```php
// src/Controller/AppController.php
<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;

class AppController extends Controller
{
    public function initialize(): void
    {
        parent::initialize();
        
        $this->loadComponent('RequestHandler');
        $this->loadComponent('Flash');
        
        // Cargar AuthComponent
        $this->loadComponent('Auth', [
            'authorize' => ['Controller'],
            'authError' => '¿Qué está haciendo aquí?',
            'authenticate' => [
                'Form' => [
                    'fields' => [
                        'username' => 'email',
                        'password' => 'password'
                    ]
                ]
            ],
            'loginAction' => [
                'controller' => 'Auth',
                'action' => 'login'
            ],
            'unauthorizedRedirect' => $this->referer()
        ]);
    }

    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        
        // Configurar tenant
        $this->setupTenant();
    }

    protected function setupTenant(): void
    {
        $tenantId = $this->request->getSession()->read('Tenant.id');
        
        if (!$tenantId) {
            // Intentar obtener de JWT o header
            $tenantId = $this->request->getHeaderLine('X-Tenant-Id');
        }
        
        if ($tenantId) {
            Configure::write('Tenant.id', $tenantId);
            
            // Configurar conexión de tenant
            $tenantDb = Configure::read('Database.tenant.database') . '_' . $tenantId;
            Configure::write('Database.tenant.database', $tenantDb);
        }
    }

    public function isAuthorized($user)
    {
        // Verificar permisos
        $action = $this->request->getParam('action');
        $controller = $this->name;
        
        $acl = $this->getTableLocator()->get('Acl');
        
        return $acl->check(
            $user['empresa_id'],
            $user['rol_id'],
            $controller,
            $action
        );
    }

    protected function sendJsonResponse($data, int $status = 200): void
    {
        $this->setResponse($this->getResponse()
            ->withType('application/json')
            ->withStatus($status)
            ->withBody($this->response->getBody()->write(json_encode($data))));
    }
}
```

#### Paso 4.2: Crear AuthController
```php
// src/Controller/Api/AuthController.php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController;
use App\Service\AuthService;
use Cake\Core\Configure;

class AuthController extends AppController
{
    private ?AuthService $authService = null;

    public function initialize(): void
    {
        parent::initialize();
        $this->authService = new AuthService();
    }

    public function login()
    {
        $this->request->allowMethod(['post']);
        
        $data = $this->request->getData();
        
        $result = $this->authService->login(
            $data['email'] ?? null,
            $data['password'] ?? null
        );
        
        if ($result) {
            $tenantId = $result['usuario']['empresa_id'];
            
            // Guardar tenant en sesión
            $this->request->getSession()->write('Tenant.id', $tenantId);
            $this->request->getSession()->write('Auth.Usuario', $result['usuario']);
            
            return $this->sendJsonResponse([
                'success' => true,
                'token' => $result['token'],
                'usuario' => $result['usuario'],
            ]);
        }
        
        return $this->sendJsonResponse([
            'success' => false,
            'message' => 'Credenciales inválidas',
        ], 401);
    }

    public function logout()
    {
        $this->request->allowMethod(['post']);
        
        $this->authService->logout();
        
        $this->request->getSession()->delete('Tenant.id');
        $this->request->getSession()->delete('Auth.Usuario');
        
        return $this->sendJsonResponse([
            'success' => true,
            'message' => 'Sesión cerrada',
        ]);
    }

    public function me()
    {
        $usuario = $this->request->getSession()->read('Auth.Usuario');
        
        if (!$usuario) {
            return $this->sendJsonResponse([
                'success' => false,
                'message' => 'No autorizado',
            ], 401);
        }
        
        return $this->sendJsonResponse([
            'success' => true,
            'usuario' => $usuario,
        ]);
    }
}
```

### Fase 5: API REST (Semana 8)

#### Paso 5.1: Crear API Base Controller
```php
// src/Controller/Api/AppController.php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AppController as BaseAppController;

class AppController extends BaseAppController
{
    public function initialize(): void
    {
        parent::initialize();
        
        $this->loadComponent('RequestHandler');
        
        $this->setResponse($this->getResponse()
            ->withType('application/json'));
    }

    protected function sendSuccessResponse($data = null, int $code = 200): void
    {
        $response = [
            'success' => true,
            'data' => $data,
        ];
        
        $this->setResponse($this->getResponse()
            ->withStatus($code)
            ->withBody($this->response->getBody()->write(json_encode($response))));
    }

    protected function sendErrorResponse(string $message, int $code = 400): void
    {
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
            ],
        ];
        
        $this->setResponse($this->getResponse()
            ->withStatus($code)
            ->withBody($this->response->getBody()->write(json_encode($response))));
    }

    protected function sendValidationErrors(array $errors): void
    {
        $this->sendErrorResponse('Validación fallida', 422);
    }
}
```

#### Paso 5.2: Crear EntidadesController
```php
// src/Controller/Api/EntidadesController.php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\AppController;
use Cake\ORM\TableRegistry;

class EntidadesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Entidades = $this->getTableLocator()->get('Entidades');
    }

    public function index()
    {
        $entidades = $this->Entidades->find()
            ->where(['activo' => true])
            ->toArray();
        
        $this->sendSuccessResponse($entidades);
    }

    public function view($id)
    {
        $entidad = $this->Entidades->find()
            ->where(['id' => $id])
            ->first();
        
        if (!$entidad) {
            $this->sendErrorResponse('Entidad no encontrada', 404);
            return;
        }
        
        $this->sendSuccessResponse($entidad);
    }

    public function add()
    {
        $entidad = $this->Entidades->newEmptyEntity();
        
        $entidad = $this->Entidades->patchEntity($entidad, $this->request->getData());
        
        if ($this->Entidades->save($entidad)) {
            $this->sendSuccessResponse($entidad, 201);
            return;
        }
        
        $this->sendValidationErrors($entidad->getErrors());
    }

    public function edit($id)
    {
        $entidad = $this->Entidades->find()
            ->where(['id' => $id])
            ->first();
        
        if (!$entidad) {
            $this->sendErrorResponse('Entidad no encontrada', 404);
            return;
        }
        
        $entidad = $this->Entidades->patchEntity($entidad, $this->request->getData());
        
        if ($this->Entidades->save($entidad)) {
            $this->sendSuccessResponse($entidad);
            return;
        }
        
        $this->sendValidationErrors($entidad->getErrors());
    }

    public function delete($id)
    {
        $entidad = $this->Entidades->find()
            ->where(['id' => $id])
            ->first();
        
        if (!$entidad) {
            $this->sendErrorResponse('Entidad no encontrada', 404);
            return;
        }
        
        if ($this->Entidades->delete($entidad)) {
            $this->sendSuccessResponse(null, 204);
            return;
        }
        
        $this->sendErrorResponse('Error al eliminar', 500);
    }
}
```

### Fase 6: Validación y Tests (Semana 9-10)

#### Paso 6.1: Crear Test Base
```php
// tests/TestCase/Controller/Api/ApiTestCase.php
<?php

declare(strict_types=1);

namespace App\TestCase\Controller\Api;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class ApiTestCase extends TestCase
{
    use IntegrationTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->useTestSessions();
    }

    protected function loginAs(array $usuario): void
    {
        $this->session([
            'Tenant.id' => $usuario['empresa_id'],
            'Auth.Usuario' => $usuario,
        ]);
    }

    protected function assertSuccessResponse($response): void
    {
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
    }

    protected function assertErrorResponse($response, int $code = 400): void
    {
        $this->assertEquals($code, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
    }
}
```

#### Paso 6.2: Crear Test de Auth
```php
// tests/TestCase/Controller/Api/AuthControllerTest.php
<?php

declare(strict_types=1);

namespace App\TestCase\Controller\Api;

use App\TestCase\Controller/Api/ApiTestCase;

class AuthControllerTest extends ApiTestCase
{
    public function testLoginSuccess(): void
    {
        $usuario = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'empresa_id' => '123',
            'rol_id' => 'admin',
            'activo' => true,
        ];
        
        // Hash password
        $usuario['password'] = password_hash('password123', PASSWORD_DEFAULT);
        
        // Insertar usuario en base de datos de prueba
        $usuarios = $this->getTableLocator()->get('Usuarios');
        $usuarios->save($usuarios->newEntity($usuario));
        
        $this->post('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $this->assertSuccessResponse($this->_response);
        
        $this->assertSession('123', 'Tenant.id');
    }

    public function testLoginInvalidCredentials(): void
    {
        $this->post('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);
        
        $this->assertEquals(401, $this->_response->getStatusCode());
        
        $data = json_decode($this->_response->getBody(), true);
        $this->assertFalse($data['success']);
    }

    public function testLogout(): void
    {
        $this->session([
            'Tenant.id' => '123',
            'Auth.Usuario' => [
                'id' => '1',
                'email' => 'test@example.com',
            ],
        ]);
        
        $this->post('/api/auth/logout');
        
        $this->assertSuccessResponse($this->_response);
        
        $this->assertSession(null, 'Tenant.id');
    }
}
```

### Fase 7: Despliegue (Semana 11-12)

#### Paso 7.1: Configuración Nginx
```nginx
# Configuración para CakePHP
server {
    listen 80;
    server_name mrp.example.com;
    root /var/www/mrp-cakephp/webroot;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Valkey configuration
    location /valkey {
        proxy_pass http://valkey:6379;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
```

#### Paso 7.2: Docker Compose
```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    volumes:
      - ./config:/var/www/html/config
      - ./webroot:/var/www/html/webroot
    environment:
      - APP_ENV=production
      - DB_HOST=database
      - DB_USER=mrp
      - DB_PASSWORD=${DB_PASSWORD}
      - VALKEY_HOST=valkey
    depends_on:
      - database
      - valkey

  database:
    image: postgres:15
    environment:
      - POSTGRES_DB=mrp_auth
      - POSTGRES_USER=mrp
      - POSTGRES_PASSWORD=${DB_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data

  valkey:
    image: valkey/valkey:7
    volumes:
      - valkey_data:/data

volumes:
  postgres_data:
  valkey_data:
```

---

## ⚠️ Alertas de Riesgo

### 1. Multi-Tenancy y Conexiones Dinámicas

**Riesgo**: Alto
**Impacto**: Crítico

**Descripción**: El sistema actual usa conexiones dinámicas a bases de datos por tenant. CakePHP por defecto usa conexiones estáticas.

**Mitigación**:
- Crear conexión personalizada que se configure dinámicamente
- Usar middleware para detectar tenant y configurar conexión
- Implementar caché de conexiones para evitar re-conexión en cada request

**Recomendación**: Implementar conexión dinámica en `Application::bootstrap()` y usar `ConnectionManager::setConfig()`.

### 2. Lógica de Negocio Compleja

**Riesgo**: Medio
**Impacto**: Alto

**Descripción**: Muchos servicios tienen lógica de negocio compleja que requiere validaciones específicas.

**Mitigación**:
- Migrar lógica de negocio a Services separados
- Usar Value Objects para validaciones complejas
- Implementar Domain Events para operaciones asíncronas

**Recomendación**: Crear capa de Services que extienda `ServiceBase` y mantenga lógica de negocio separada de controllers.

### 3. Validaciones y Reglas de Negocio

**Riesgo**: Medio
**Impacto**: Medio

**Descripción**: Muchas validaciones están hardcodeadas en controllers o services.

**Mitigación**:
- Migrar validaciones a Entities usando `validationDefault()`
- Crear custom validators para reglas complejas
- Implementar validaciones en Services para lógica transaccional

**Recomendación**: Usar `Cake\Validation\Validator` para validaciones de Entities y Services para validaciones transaccionales.

### 4. Rendimiento y Cache

**Riesgo**: Medio
**Impacto**: Medio

**Descripción**: El sistema actual usa Valkey para cache. CakePHP tiene integración con cache pero requiere configuración.

**Mitigación**:
- Configurar Valkey como cache engine en `config/app.php`
- Usar cache para queries pesados
- Implementar cache de menús y configuraciones

**Recomendación**: Usar `Cache::remember()` para queries pesados y configurar Valkey como cache engine.

### 5. Migración de Datos

**Riesgo**: Alto
**Impacto**: Crítico

**Descripción**: Migrar datos de sistema legacy a CakePHP puede ser complejo.

**Mitigación**:
- Crear scripts de migración de datos
- Validar integridad referencial
- Implementar rollback en caso de error

**Recomendación**: Usar CakePHP Migrations plugin y crear scripts de data migration separados.

### 6. Seguridad

**Riesgo**: Medio
**Impacto**: Crítico

**Descripción**: Autenticación y autorización deben ser seguras.

**Mitigación**:
- Usar AuthComponent de CakePHP
- Implementar rate limiting
- Usar HTTPS en producción
- Validar permisos en cada request

**Recomendación**: Implementar middleware para rate limiting y usar `AuthComponent` con JWT para API.

### 7. Testing

**Riesgo**: Medio
**Impacto**: Medio

**Descripción**: Testing de sistema legacy puede ser complejo.

**Mitigación**:
- Crear tests unitarios para cada service
- Crear tests de integración para endpoints API
- Usar test doubles para dependencias externas

**Recomendación**: Usar PHPUnit y CakePHP Test Traits para testing.

---

## 📋 Checklist de Implementación

### Fase 1: Configuración Base
- [ ] Instalar CakePHP 5.3
- [ ] Configurar multi-tenancy
- [ ] Configurar Valkey
- [ ] Configurar Nginx
- [ ] Configurar Docker Compose

### Fase 2: Modelos y Entities
- [ ] Crear BaseTable
- [ ] Crear Entities base
- [ ] Crear Tables para entidades simples
- [ ] Crear Tables para entidades complejas
- [ ] Implementar validaciones

### Fase 3: Servicios de Negocio
- [ ] Crear ServiceBase
- [ ] Migrar AuthService
- [ ] Migrar TenantProvisioningService
- [ ] Migrar EmpresaUsuariosService
- [ ] Migrar resto de Services

### Fase 4: Controladores
- [ ] Crear AppController
- [ ] Migrar AuthController
- [ ] Migrar controladores de API
- [ ] Migrar controladores de Admin
- [ ] Migrar controladores de Producción

### Fase 5: Validación
- [ ] Crear tests unitarios
- [ ] Crear tests de integración
- [ ] Crear tests de API
- [ ] Implementar CI/CD

### Fase 6: Despliegue
- [ ] Configurar Nginx
- [ ] Configurar Docker Compose
- [ ] Configurar Valkey
- [ ] Configurar monitoring
- [ ] Configurar backups

---

## 📚 Recursos Adicionales

### Documentación
- [CakePHP 5.3 Documentation](https://book.cakephp.org/5/en/)
- [CakePHP Migrations](https://github.com/cakephp/migrations)
- [CakePHP DebugKit](https://github.com/cakephp/debug_kit)

### Herramientas
- [CakePHP CodeSniffer](https://github.com/cakephp/cakephp-codesniffer)
- [CakePHP Fixture Plugin](https://github.com/cakephp/fixture-factory)

### Comunidad
- [CakePHP Slack](https://cakesf.herokuapp.com/)
- [CakePHP Forum](https://discourse.cakephp.org/)

---

**Versión**: 1.0
**Fecha**: 2024-01-15
**Autor**: Arquitecto de Software Senior
