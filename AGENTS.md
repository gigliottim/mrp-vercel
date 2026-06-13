# AGENTS.md - Guia de Programacion MRP

## Stack Actual del Proyecto

### Backend
| Tecnologia | Version | Notas |
|---|---|---|
| **PHP** | 8.4+ (bitnami/php-fpm:latest) | `declare(strict_types=1)` obligatorio. Usar named args, `str_starts_with`, `str_contains`, `match`, readonly, enums |
| **PostgreSQL** | 17.x (bitnami/postgresql:latest) | Driver `pgsql` + `pdo_pgsql`. Multi-tenant DB-per-tenant |
| **Valkey** | 8.0+ (bitnami/valkey:7.2.x compatible, bitnami/valkey:latest) | Fork de Redis OSS 7.2.4. Extension nativa PHP `\ValkeyClient`. Prefijo `mrp:agent:` |
| **Nginx** | latest (bitnami/nginx:latest) | Reverse proxy + SSL termination |
| **PhpSpreadsheet** | ^5.5 | Min PHP 8.1. Import/Export Excel |
| **Dompdf** | ^3.1 | Generacion PDF desde HTML |

### Frontend (CDN, sin build step)
| Tecnologia | Version | CDN |
|---|---|---|
| **Bootstrap** | 5.3.7 | jsDelivr. Dark/light mode via `data-bs-theme`. Sin jQuery |
| **Font Awesome** | 6.7.2 | Cloudflare |
| **Alpine.js** | 3.14.9 | jsDelivr. Directivas `x-data`, `x-model`, `x-on` |
| **Chart.js** | 4.5.0 | jsDelivr. ESM tree-shakeable. Requiere date adapter |
| **FullCalendar** | 6.1.18 | jsDelivr |
| **Bootstrap Icons** | 1.13.1 | jsDelivr |

### Infraestructura
| Componente | Detalle |
|---|---|
| **Docker** | `bitnami/*:latest` (sin versionar - riesgo de reproducibilidad) |
| **Deploy** | SSH + zip + docker compose (deploy.sh) |
| **Dominio** | mimrp.com.ar (SSL Let's Encrypt) |
| **App Version** | 45.0.14 (build 4194) |

### AI
| Componente | Version |
|---|---|
| **Ollama Cloud** | OpenAI-compatible API |
| **Modelo primario** | qwen3.5:397b |
| **Modelo general** | gemini-3-flash-preview |

---

## Arquitectura MVC (Custom Framework)

### Request Lifecycle
```
public/index.php
  -> bootstrap/app.php
    -> bootstrap/autoload.php (PSR-4 custom + composer)
    -> Env::load('.env')
    -> Config::load('config/')
    -> Router instantiated
    -> routes/web.php + routes/api.php
    -> new Kernel($router)
  -> Request::capture()
  -> Kernel::handle($request)
    -> MiddlewarePipeline (Session -> Authenticate -> SecurityHeaders)
    -> Router::dispatch($request)
      -> Controller method
      -> Response (html o json)
  -> Response::send()
```

### Estructura de Directorios (Convencion)
```
app/
  core/           -> Framework: Kernel, Router, Request, Response, Config, Env, View, Session, Logger, Auth, Middleware
  controllers/    -> Delgados: delegan a Services. Agrupados por modulo (Admin/, Api/, Produccion/, etc.)
  models/          -> BaseTenantModel (Active Record lite con PDO). Solo acceso a datos.
  services/        -> Logica de negocio. Agrupados por modulo.
  Repositories/    -> Consultas complejas (Parcial: solo Produccion/ y SearchRepository)
  middleware/      -> SessionMiddleware, AuthenticateMiddleware, SecurityHeadersMiddleware
views/
  layouts/         -> app.php (principal), auth.php, public.php
  pages/           -> Vistas por modulo
  partials/        -> header, footer, navbar, sidebar
  components/      -> Componentes reutilizables
routes/
  web.php          -> Rutas web
  api.php          -> Rutas API (prefijo /api)
config/
  app.php          -> Version, timezone, locale
  database.php     -> Conexiones: default, mrp_auth, tenant
  valkey.php       -> Valkey config (prefix, TTL, rate limit)
  agent_ai.php     -> AI agent config (modelos, prompts, validacion)
  cdn.php          -> Versiones CDN
bootstrap/
  app.php          -> Kernel bootstrap
  autoload.php     -> PSR-4 custom (case-insensitive para Linux)
  helpers.php      -> Funciones globales (base_path(), env(), config(), etc.)
```

### Multi-Tenancy
- **Auth DB** (`mrp_auth`): Compartida. Usuarios, empresas, `company_databases`
- **Tenant DB**: Una PostgreSQL por empresa. Se resuelve dinamicamente via `TenantContext::get()`
- `BaseTenantModel`: Resuelve conexion tenant automaticamente
- `AuthenticateMiddleware`: Establece `TenantContext` despues del login

---

## Principios SOLID - Reglas Obligatorias

### S - Single Responsibility Principle
- **Controllers**: SOLO reciben Request, delegan a Service, devuelven Response. Cero logica de negocio.
- **Models**: SOLO acceso a datos (CRUD via PDO). Sin logica de negocio.
- **Services**: SOLO logica de negocio. Un Service por caso de uso o entidad dominio.
- **Repositories**: SOLO consultas complejas que no caben en Model. Cuando un query involucra JOINs, agregaciones, o multiples tablas.

```php
// CORRECTO
class OrdenProduccionController extends Controller
{
    public function store(Request $request): Response
    {
        $service = new OrdenProduccionService();
        $result = $service->crear($request->all());
        return $this->json($result);
    }
}

// INCORRECTO - logica en controller
class OrdenProduccionController extends Controller
{
    public function store(Request $request): Response
    {
        $model = new OrdenProduccion();
        $data = $request->all();
        // validaciones, calculos, etc. AQUI NO
        $model->create($data);
        return $this->json(['status' => 'ok']);
    }
}
```

### O - Open/Closed Principle
- **Middleware**: Implementar `MiddlewareInterface` para agregar middleware sin modificar Kernel.
- **Config**: Nuevos modulos se agregan via config, no modificando core.
- **Services**: Extender via interfaces, no modificando clases existentes.

### L - Liskov Substitution Principle
- Toda subclase de `Controller` debe poder usarse donde se espera `Controller`.
- Toda subclase de `BaseTenantModel` debe implementar `getTable()` y comportarse como el base.
- No sobreescribir metodos del base con comportamiento incompatible.

### I - Interface Segregation Principle
- Crear interfaces pequenas y especificas por contrato.
- `MiddlewareInterface` es el ejemplo actual: un solo metodo `handle()`.
- **Regla**: Si una interface tiene > 4 metodos, dividirla.

### D - Dependency Inversion Principle
- Controllers dependen de Services (abstracciones), no de Models directamente para logica.
- Services reciben dependencias por constructor cuando sea posible.
- Evitar `new` directo en cadenas largas. Preferir inyeccion via constructor.

```php
// CORRECTO - DIP
class SugerenciasService
{
    public function __construct(
        private readonly PlaneamientoRepository $repo,
        private readonly StockService $stockService,
    ) {}
}

// MEJORABLE - new directo
class SugerenciasService
{
    public function sugerir(): array
    {
        $repo = new PlaneamientoRepository(); // Acoplado
        $stock = new StockService();           // Acoplado
    }
}
```

---

## Convenciones de Codigo PHP 8.4+

### Obligatorio
- `declare(strict_types=1);` al inicio de cada archivo PHP
- Namespaces PSR-4: `App\Core\...`, `App\Controllers\...`, `App\Models\...`, `App\Services\...`
- Tipos de retorno explicitos en todos los metodos
- Propiedades con tipo (`protected PDO $connection`, `private readonly string $table`)
- Usar `readonly` en propiedades que no cambian despues de la construccion
- `match` en lugar de `switch` cuando sea apropiado
- Named arguments cuando mejora legibilidad
- Nullsafe operator `?->` para cadenas opcionales
- `str_starts_with()`, `str_ends_with()`, `str_contains()` en lugar de strpos
- Enums nativos para constantes agrupadas

### Nombres
- Controllers: `XxxController.php` (PascalCase)
- Models: `XxxModel.php` o nombre de entidad (PascalCase, singular)
- Services: `XxxService.php` (PascalCase)
- Repositories: `XxxRepository.php` (PascalCase)
- Metodos: camelCase (`findActive()`, `calcularTotal()`)
- Constantes: UPPER_SNAKE_CASE
- Variables/propiedades: camelCase

### BaseTenantModel (Active Record)
```php
class MiModelo extends BaseTenantModel
{
    protected function getTable(): string
    {
        return 'mi_tabla';
    }
}

// Metodos disponibles: find(), all(), create(), update(), delete()
// Para queries custom, crear un Repository
```

### Controllers
```php
class MiController extends Controller
{
    public function index(Request $request): Response
    {
        $service = new MiService();
        $data = $service->listar($request->query('page', 1));
        return $this->render('pages/mi_modulo/index', ['items' => $data]);
    }

    public function api(Request $request): Response
    {
        $service = new MiService();
        return $this->json($service->procesar($request->all()));
    }
}
```

### Views (PHP nativo)
```php
<!-- Layout con secciones -->
<?php $this->section('title', 'Mi Pagina') ?>

<!-- Alpine.js para interactividad -->
<div x-data="{ open: false }">
    <button @click="open = !open">Toggle</button>
</div>

<!-- Bootstrap 5.3 para estilos -->
<div class="card" data-bs-theme="light">
    <div class="card-body">...</div>
</div>
```

---

## Versiones: Verificar Antes de Programar

**IMPORTANTE**: Antes de usar cualquier feature de lenguaje o libreria, verificar que es compatible con la version instalada usando Context7 MCP.

### Checklist por Version

#### PHP 8.4+
- [ ] `readonly` classes (PHP 8.2+)
- [ ] `enum` (PHP 8.1+)
- [ ] `match` expression (PHP 8.0+)
- [ ] Named arguments (PHP 8.0+)
- [ ] Nullsafe operator `?->` (PHP 8.0+)
- [ ] `str_starts_with`, `str_contains`, `str_ends_with` (PHP 8.0+)
- [ ] Constructor property promotion (PHP 8.0+)
- [ ] `never` return type (PHP 8.1+)
- [ ] `intersection types` (PHP 8.1+)
- [ ] `readonly` properties (PHP 8.1+)
- [ ] Fibers (PHP 8.1+)
- [ ] Asymmetric visibility (PHP 8.4 draft - NO usar aun)
- [ ] Property hooks (PHP 8.4+)

#### Bootstrap 5.3.7
- [ ] Dark mode via `data-bs-theme="dark"` (5.3+)
- [ ] Sin jQuery - todo vanilla JS (5.0+)
- [ ] CSS custom properties / variables (5.0+)
- [ ] `.icon-link` helper (5.3+)
- [ ] Focus ring helper (5.3+)

#### Alpine.js 3.14.9
- [ ] `x-data`, `x-model`, `x-on`, `x-show`, `x-if`, `x-for`
- [ ] `$store()` para estado global
- [ ] `Alpine.store()`, `Alpine.data()`
- [ ] Plugins: `@alpinejs/collapse`, `@alpinejs/persist`

#### Chart.js 4.5.0
- [ ] ESM, tree-shakeable
- [ ] Registrar controllers/elements/scales explicitamente
- [ ] Requiere date adapter para time scale (`chartjs-adapter-date-fns`)
- [ ] `scales` en lugar de `xAxes`/`yAxes` (v4 API)

#### Valkey (compatible Redis OSS 7.2)
- [ ] Extension nativa `\ValkeyClient` (NO usar phpredis/redis extension)
- [ ] Comandos: `connect()`, `auth()`, `get()`, `set()`, `setex()`, `del()`, `hGetAll()`, `hMSet()`, `hGet()`, `expire()`
- [ ] Prefijo `mrp:agent:` en todas las claves
- [ ] Valkey 8.x+ y 9.x+ son compatibles con protocolo Redis 7.2

#### PhpSpreadsheet ^5.5
- [ ] Min PHP 8.1
- [ ] Usar `IOFactory::createReader()`, `IOFactory::createWriter()`
- [ ] No usar funciones deprecadas de PHPExcel

#### Dompdf ^3.1
- [ ] Renderizado HTML + CSS 2.1
- [ ] Soporte limitado para CSS3 (flexbox parcial)
- [ ] Usar tablas para layouts complejos si falla flexbox

---

## Reglas Especificas del Proyecto

1. **Multi-tenant**: Siempre usar `BaseTenantModel` para datos de tenant. Nunca consultar Auth DB para datos de negocio.
2. **Valkey**: Usar `ValkeyClient` (wrapper en `app/core/Database/ValkeyClient.php`), no la extension directa.
3. **Rutas**: Definir en `routes/web.php` (web) o `routes/api.php` (API). Formato: `$router->get('/ruta', [Controller::class, 'method'])`.
4. **Vistas**: PHP nativo en `views/`. Layouts en `views/layouts/`. Partials en `views/partials/`.
5. **Sin build step**: Todo frontend via CDN. CSS custom en `public/assets/css/`. JS custom en `public/assets/js/`.
6. **Sin DI container**: Services y Models se instancian con `new` en Controllers. Seguir DIP lo mas posible.
7. **Config**: Toda configuracion en `config/`. Valores sensibles en `.env`, leidos con `env()`.
8. **Logging**: Usar `Logger::getInstance()->error/warning/info()`. No `error_log()` nativo.
9. **Seguridad**: Nunca commitear `.env`, passwords, API keys. Usar `env()` para todo dato sensible.
10. **SQL**: Siempre prepared statements (PDO). Nunca concatenar strings para queries.

---

## Uso de Context7 MCP

Antes de programar cualquier feature que use una libreria del stack, consultar Context7 para obtener la documentacion actualizada de la version correcta:

```
1. resolve-library-id: Buscar la libreria (ej: "PhpSpreadsheet", "Bootstrap", "Chart.js")
2. get-library-docs: Obtener docs del library ID con topic especifico y mode="code"
3. Verificar que la feature exista en la version usada antes de implementar
```

### Library IDs de Context7 ya resueltos
| Libreria | Context7 ID |
|---|---|
| PHP Manual | `/websites/php_net_manual_en` |
| Valkey | `/websites/valkey_io` |
| PostgreSQL | `/websites/postgresql_current` |
| PhpSpreadsheet | `/phpoffice/phpspreadsheet` |
| Dompdf | `/dompdf/dompdf` |
| Alpine.js | `/websites/alpinejs_dev` |
| Bootstrap 5.3 | `/websites/getbootstrap_5_3` |
| Chart.js | `/chartjs/chart.js` |

### Flujo de Trabajo
1. Leer este AGENTS.md antes de empezar
2. Verificar versiones de las tecnologias involucradas
3. Consultar Context7 si hay duda sobre API o feature de una libreria
4. Seguir MVC + SOLID estrictamente
5. Usar `declare(strict_types=1)` en todo archivo PHP nuevo
6. Probar con PHP 8.4+ features solo (no usar features de PHP 8.5 que no esten estables)