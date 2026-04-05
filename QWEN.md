# MRP — Sistema de Planificación de Recursos de Manufact

## Project Overview

**MRP** (Manufacturing Resource Planning) is a web-based application built with vanilla PHP (no framework) for managing manufacturing operations including parts management, bills of materials (BOM), suppliers, production orders, inventory movements, and work centers.

The application is deployed to a VPS (`mimrp.com.ar`) using a custom **LEPP stack** (Linux + Nginx + PHP-FPM + PostgreSQL) orchestrated via Docker Compose. It features a multi-tenant architecture with PostgreSQL, Valkey (Redis-compatible) caching, and an integrated **AI Agent** powered by LLM APIs (Ollama Cloud / Qwen).

### Key Technologies

| Layer | Technology |
|-------|-----------|
| **Language** | PHP 8.5 (strict types) |
| **Web Server** | Nginx (Bitnami) |
| **PHP Runtime** | PHP-FPM (Bitnami) |
| **Database** | PostgreSQL (Bitnami), multi-tenant |
| **Cache** | Valkey (Bitnami, Redis-compatible) |
| **Frontend** | Alpine.js, Bootstrap Icons, vanilla CSS |
| **AI Agent** | Ollama Cloud API (qwen3.5:cloud, gemini-3-flash-preview) |
| **Deployment** | Custom `deploy.sh` → VPS via SSH + Docker Compose |
| **Version Control** | Git (local repo, deployed directly to VPS) |

### Dependencies (Composer)

- `phpoffice/phpspreadsheet` — Excel/CSV import-export
- `dompdf/dompdf` — PDF generation

---

## Architecture

### Directory Structure

```
project-root/
├── app/                          # Application logic
│   ├── bootstrap/                # App initialization
│   ├── core/                     # Kernel (Database, Router, Auth, Config, Logging)
│   ├── controllers/              # Thin controllers (coordinate only)
│   ├── services/                 # Business logic
│   ├── repositories/             # Database access (queries only)
│   ├── models/                   # Entity classes (properties + relations)
│   ├── validators/               # Domain validation rules
│   ├── dto/                      # Data Transfer Objects
│   ├── middleware/               # Auth, CORS, session
│   └── helpers/                  # Project-specific utilities
│
├── agenteAI/                     # AI Agent (centralized module)
│   ├── backend/                  # PHP: controllers, services, repositories
│   ├── frontend/                 # JS, CSS, views
│   ├── database/migrations/      # Agent-specific DB migrations
│   └── docs/                     # Agent documentation
│
├── public/                       # Web root (only entry point)
│   ├── index.php                 # Front controller
│   ├── assets/                   # CSS/JS organized by layers
│   └── agenteAI/                 # Agent web-accessible assets
│
├── views/                        # PHP templates
│   ├── layouts/                  # Base layouts
│   ├── components/               # Reusable view components
│   └── pages/                    # Full pages (admin, auth, public)
│
├── config/                       # PHP configuration files
├── database/migrations/          # SQL migration scripts
├── routes/                       # Route definitions (api.php, web.php)
├── storage/                      # Logs, cache, sessions
├── scripts/                      # Dev tools (validators, migrations)
├── docs/                         # Technical documentation
├── docker/                       # Docker configs (nginx, php-fpm)
└── tests/                        # PHPUnit tests
```

### Multi-Tenant Database

The application uses a multi-tenant architecture where each company has its own database. The tenant database name is resolved at runtime from `mrp_auth.company_databases`.

Key tables in the tenant database:
- `partes` — Parts/items master
- `variantes` — Stockable variants of parts
- `bom_cabecera` / `bom_detalle` — Bills of Materials
- `entidades` — Suppliers (tipo='PROVEEDOR') and clients
- `compras` — Purchase records
- `ordenes_produccion` — Production orders
- `movimientos_stock` / `movimientos_inventario` — Stock/inventory movements
- `centros_trabajo` — Work centers
- `unidades_medida` — Units of measure
- `tipos_partes` / `grupos_partes` — Part categorization
- `agent_conversations` / `agent_messages` / `agent_ai_logs` — AI agent data

### Autoloader

Custom PSR-4-like autoloader in `bootstrap/autoload.php` with:
- `App\*` → `app/` (case-insensitive resolution for mixed-case dirs)
- `App\AgenteAI\Backend\*` → `agenteAI/backend/` (case-insensitive)

---

## AI Agent

The **Agente AI** is an LLM-powered chatbot that helps users create parts, BOMs, suppliers, and materials through a conversational interface.

### Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/agent` | Full-page chat view |
| POST | `/api/v1/agent/message` | Send message to AI |
| POST | `/api/v1/agent/confirm` | Confirm and save collected data |
| GET | `/api/v1/agent/suggestions` | Get predefined suggestion chips |
| GET | `/api/v1/agent/config` | Check if agent is online/configured |

### Architecture

- **Controller**: `agenteAI/backend/controllers/AgentController.php`
- **Service**: `AgentService` orchestrates AI calls, validation, caching
- **AI Client**: `AiClient` — HTTP client for Ollama/DashScope APIs (OpenAI-compatible format)
- **Validation**: `ResponseValidator` — checks AI responses against field rules
- **Conversation**: `ConversationService` + `ConversationRepository` — manages sessions via Valkey
- **Frontend**: Alpine.js components (`agent_chat.js`, `agent_floating.js`) with floating WhatsApp-style button

### Configuration

Environment variables in `.env`:
```env
AGENT_AI_MODE=api
AGENT_AI_API_ENDPOINT=https://api.ollama.com/v1/chat/completions
AGENT_AI_API_MODEL=qwen3.5:cloud
AGENT_AI_API_KEY=<key>
```

See `agenteAI/README.md` for full documentation.

---

## Building and Running

### Local Development (Docker)

```bash
# Start the full stack (Nginx + PHP-FPM + PostgreSQL + Valkey)
docker compose up -d

# View logs
docker compose logs -f php-fpm
docker compose logs -f nginx

# Stop
docker compose down
```

### Deployment to VPS

```bash
# Run the automated deploy script
./deploy.sh

# Or with custom parameters
./deploy.sh --host 181.13.244.35 --port 5073 --user root
```

The deploy script:
1. Auto-bumps version/build number in `config/app.php`
2. Creates a mandatory git commit
3. Zips the project (app, bootstrap, config, public, routes, views, vendor, agenteAI, etc.)
4. Uploads to VPS (`/opt/mrp/`)
5. Unzips and sets permissions
6. Runs database migrations via `migrate_database.php`
7. Restarts containers if infrastructure changed

### Database Migrations

```bash
# Run migrations locally
php migrate_database.php --path=database/migrations --skip-existing

# On VPS (inside container)
docker compose exec -T php-fpm php /app/migrate_database.php --path=/app/database/migrations --skip-existing
```

### Running Tests

```bash
# If PHPUnit is installed
./vendor/bin/phpunit

# With coverage
./vendor/bin/phpunit --coverage-html coverage/
```

### Validate Code Standards

```bash
# Bash
./scripts/validate-code-standards.sh

# PowerShell
.\scripts\validate-code-standards.ps1
```

---

## Development Conventions

### Coding Standards (from `.github/instructions/unik.instructions.md`)

- **Max 400-500 lines per file** — split into modules if exceeded
- **SRP** — one responsibility per file
- **Thin controllers** — controllers only coordinate (validate → call service → respond)
- **Services** contain pure business logic
- **Repositories** contain only SQL queries
- **Models** are lightweight entities (getters, setters, formatters only)
- **No inline JS/CSS** — all assets externalized in `public/assets/`
- **Namespace convention**: `App\{Layer}\{Entity}Component`
- **File naming**: `{Entity}{ComponentType}.php` (PascalCase)

### Architecture Patterns

- **Repository Pattern** — abstracts data access
- **Service Layer** — encapsulates business logic
- **DTO** — transfers data between layers
- **Dependency Injection** — via constructor
- **Front Controller** — single entry point (`public/index.php`)

### Git Workflow

- Local repository only (no GitHub/GitLab)
- Direct deploy: PC → VPS via `deploy.sh`
- No PRs or external code reviews
- `.env` files are committed with real credentials (no `.env.example`)

### Database

- PostgreSQL with multi-tenant isolation
- Raw SQL migrations (no ORM)
- Migration naming: `{YYYY_MM_DD}_{NNN}_{description}.sql`

---

## Key Files

| File | Purpose |
|------|---------|
| `bootstrap/autoload.php` | Custom PSR-4 autoloader with case-insensitive resolution |
| `bootstrap/helpers.php` | Global helper functions (`config()`, `env()`, `view()`, `esc()`, etc.) |
| `routes/web.php` | Web route definitions |
| `routes/api.php` | API route definitions |
| `config/agent_ai.php` | AI agent configuration (prompts, validation rules, suggestions) |
| `config/valkey.php` | Valkey cache configuration |
| `migrate_database.php` | CLI migration runner |
| `deploy.sh` | Automated VPS deployment script |
| `docker-compose.yml` | LEPP stack orchestration |
| `agenteAI/backend/services/AgentService.php` | AI agent orchestrator (with real DB CRUD for parts, suppliers, BOM, materials) |

---

## Production Environment

- **URL**: `https://mimrp.com.ar`
- **VPS**: `181.13.244.35:5073`
- **Remote path**: `/opt/mrp/`
- **Containers**: `lepp-nginx`, `lepp-php-fpm`, `lepp-postgresql`, `lepp-valkey`
- **Auth DB**: `mrp_auth` (single, shared)
- **Tenant DB**: Resolved dynamically per company
- **Timezone**: `America/Argentina/Buenos_Aires`
