---
applyTo: '**'
---
# 📐 Estándares de Codificación - Template Universal

> **Documento de Referencia Obligatoria**
> Última actualización: 4 de abril de 2026
> Versión: 3.0

## 📋 Changelog de Versiones

| Versión | Fecha | Cambios |
|---------|-------|---------|
| 3.0 | 04/04/2026 | Corrección de errores tipográficos, eliminación de caracteres inválidos, estandarización de rutas, agregadas secciones de seguridad, error handling, logging, performance y testing con ejemplos, reglas de infraestructura (Git obligatorio, sin .env.example), estructura mejorada con bootstrap/, exceptions/, contracts/, factories/, storage/, docker/, errors/, navigation/, feature tests, Request/Response/Cache/Logger en core, controllers api/web separados |
| 2.0 | 04/02/2026 | Versión anterior |

---

## 🎯 Objetivo

Este documento establece los **estándares obligatorios** para mantener el código del proyecto limpio, mantenible y escalable. **TODA nueva funcionalidad o refactorización debe seguir estos lineamientos.**

Este template es **genérico y adaptable** a cualquier proyecto PHP. Ajusta las rutas y nombres según la estructura de tu proyecto, pero mantén los principios intactos.

---

## ⚠️ Regla de Oro

### **LÍMITE MÁXIMO: 400-500 LÍNEAS POR ARCHIVO**

Si un archivo excede 400 líneas:
- 🔴 **STOP** - No agregues más código
- 📦 **DIVIDIR** - Separa en módulos/componentes
- ♻️ **REFACTORIZAR** - Aplica patrones de diseño

**Excepciones:** Ninguna. Si crees que necesitas más líneas, estás violando el Principio de Responsabilidad Única (SRP).

---

## 🔧 Reglas de Infraestructura del Proyecto

### **1. Git Obligatorio desde el Inicio**
- ✅ **TODO proyecto debe tener `.git` inicializado desde el día 1**
- ✅ Cada proyecto nuevo empieza con `git init`
- ✅ El repositorio es local: no se usa GitHub/GitLab como intermediario
- ✅ El flujo es directo: **PC local → VPS/Docker** vía git pull/clone

```bash
# Al crear un nuevo proyecto, SIEMPRE ejecutar:
git init
git add .
git commit -m "init: proyecto inicial"
```

### **2. Archivos de Configuración Directos (SIN plantillas)**
- ✅ Crear `.env` **directamente** con las credenciales reales
- ✅ Crear `.gitignore` **directamente**
- ❌ **NO crear** `.env.example`, `.env.template`, `.env.sample` ni similares
- ❌ **NO crear** archivos de configuración "plantilla" para que otro los complete

**¿Por qué?** Los proyectos se configuran en la PC del desarrollador y se despliegan **directamente** al VPS/Docker. No hay un proceso de CI/CD que requiera plantillas, ni se pasa por servicios como GitHub donde las credenciales deban ocultarse en ejemplos.

```bash
# ✅ CORRECTO: Crear .env con valores reales desde el inicio
DB_HOST=localhost
DB_PORT=3306
DB_NAME=mi_proyecto
DB_USER=root
DB_PASS=mi_password_real

# ❌ INCORRECTO: Crear .env.example con placeholders
DB_HOST=localhost
DB_PORT=3306
DB_NAME=your_database_name    # ← Esto NO se necesita
DB_USER=your_username          # ← Esto NO se necesita
DB_PASS=your_password          # ← Esto NO se necesita
```

### **3. Flujo de Despliegue**
```
[PC Local: desarrollo] ──git push──> [VPS: git pull] ──> Docker/Producción
```

- No hay rama `main` que se mergea desde `develop`
- No hay PRs ni code reviews externos
- El `.env` en el VPS se configura **una vez** al desplegar por primera vez

---

## 🎯 Principios de Diseño

- **Máximo 400-500 líneas por archivo** (límite estricto)
- **Separación de responsabilidades** (SRP)
- **Modularidad y reutilización**
- **Assets externalizados** (no código inline)
- **Estructura predecible y escalable**
- **Inyección de dependencias** sobre instanciación directa
- **Git obligatorio**: Todo proyecto debe tener `.git` inicializado desde el día 1
- **Archivos de configuración directos**: Crear `.env`, `.gitignore`, etc. directamente (NO crear `.env.example` ni plantillas similares)

---

## 📁 Estructura Completa

> 💡 **Nota:** Esta es una estructura de referencia genérica. Cada proyecto puede adaptar los nombres de carpetas según su stack tecnológico, pero **los principios de separación de responsabilidades y límites de líneas son inmutables**.

```
project-root/
│
├── app/                          # 🧠 Backend: lógica de la aplicación
│  ├── bootstrap/                 # � Arranque de la aplicación
│  │  ├── app.php               # Inicialización central [150 líneas]
│  │  ├── routes.php            # Definición de rutas [200 líneas]
│  │  └── providers.php         # Registro de servicios [200 líneas]
│  │
│  ├── core/                     # 🔌 Kernel y utilidades globales
│  │  ├── Database.php          # Conexión DB (MySQL/PostgreSQL/SQLite) [200 líneas]
│  │  ├── Router.php            # Enrutador (si no usas framework) [200 líneas]
│  │  ├── Request.php           # Wrapper de $_SERVER/$_REQUEST [150 líneas]
│  │  ├── Response.php          # Wrapper de respuestas HTTP [200 líneas]
│  │  ├── Logger.php            # Sistema de logging [200 líneas]
│  │  ├── Cache.php             # Sistema de caching simple [200 líneas]
│  │  └── helpers.php           # Funciones globales seguras [200 líneas]
│  │
│  ├── controllers/              # ⚡ DELGADOS → Solo orquestan flujo
│  │  ├── api/                  # Controllers para API REST (si aplica)
│  │  │  └── {Entity}Controller.php    # Ej: ApiItemController.php [300 líneas]
│  │  ├── web/                  # Controllers para vistas web
│  │  │  └── {Entity}Controller.php    # Ej: ItemController.php [300 líneas]
│  │  └── ...                       # Un controller por entidad + contexto
│  │  # ✅ Solo coordinan (validar input → llamar service → responder)
│  │  # ❌ NO contienen lógica de negocio ni queries SQL
│  │
│  ├── services/                 # 💼 Lógica de negocio pura
│  │  ├── {entity}/             # Dividir servicios complejos por carpetas
│  │  │  ├── {Entity}Service.php        # Principal [400 líneas]
│  │  │  ├── {Entity}FeatureService.php # Responsabilidad específica [400 líneas]
│  │  │  ├── {Entity}MediaService.php   # Ej: manejo de archivos [400 líneas]
│  │  │  └── ...
│  │  └── {Simple}Service.php   # Servicios simples en raíz [400 líneas]
│  │  # ✅ Contienen reglas de negocio, coordinan repositories
│  │  # ❌ NO acceden directamente a $_POST/$_GET ni a BD
│  │
│  ├── repositories/             # �️ Acceso a datos → solo queries
│  │  ├── {Entity}Repository.php    # Ej: ItemRepository.php [400 líneas]
│  │  └── ...                       # Un repository por tabla/entidad
│  │  # ✅ Solo métodos CRUD + queries específicas
│  │  # ❌ NO contienen lógica de negocio
│  │
│  ├── models/                   # 📋 Entidades → propiedades y relaciones básicas
│  │  ├── {Entity}.php          # Ej: Item.php [300 líneas]
│  │  └── ...
│  │  # ✅ Solo propiedades, getters, setters, formatters simples
│  │  # ❌ NO contienen lógica compleja ni queries
│  │
│  ├── validators/               # ✔️ Validaciones complejas
│  │  ├── {Entity}Validator.php # Ej: ItemValidator.php [300 líneas]
│  │  └── ...
│  │  # ✅ Reglas de validación específicas del dominio
│  │  # ❌ NO modifican datos, solo validan
│  │
│  ├── dto/                      # 📦 Data Transfer Objects
│  │  ├── {Entity}DTO.php       # Ej: ItemDTO.php [200 líneas]
│  │  └── ...
│  │  # ▪️ Estructuras para transferir datos entre capas
│  │  # ▪️ No contienen lógica
│  │
│  ├── middleware/               # 🛡️ Interceptores (auth, logging, CORS)
│  │  ├── AuthMiddleware.php    # [400 líneas]
│  │  ├── CorsMiddleware.php    # [400 líneas]
│  │  ├── RateLimitMiddleware.php # [200 líneas]
│  │  └── ...
│  │
│  ├── exceptions/               # ❌ Excepciones personalizadas
│  │  ├── NotFoundException.php     # [50 líneas]
│  │  ├── UnauthorizedException.php # [50 líneas]
│  │  ├── ValidationException.php   # [100 líneas]
│  │  └── ...
│  │  # ▪️ Excepciones tipadas del dominio
│  │  # ▪️ NO usar \Exception genérico en capa de negocio
│  │
│  ├── contracts/                # 📐 Interfaces y contratos
│  │  ├── {Entity}RepositoryInterface.php  # [100 líneas]
│  │  ├── {Entity}ServiceInterface.php     # [100 líneas]
│  │  └── ...
│  │  # ▪️ Interfaces para inyección de dependencias
│  │  # ▪️ Permitir mocking en tests
│  │
│  ├── factories/                # 🏭 Fábricas de objetos
│  │  ├── {Entity}Factory.php   # Creación compleja de entidades [200 líneas]
│  │  └── ...
│  │  # ▪️ Centralizan lógica de creación de objetos
│  │  # ▪️ Útiles para tests y seeds
│  │
│  └── helpers/                  # 🔧 Utilidades específicas del proyecto
│      ├── StringHelper.php      # [300 líneas]
│      ├── DateHelper.php        # [300 líneas]
│      ├── FileHelper.php        # [300 líneas]
│      └── ...
│
│
├── database/                     # 🛠️ Gestión de base de datos
│  ├── migrations/               # 🔄 Scripts SQL versionados
│  │  ├── 001_initial_schema.sql
│  │  ├── 002_add_{feature}.sql # Nomenclatura: {número}_{descripción}.sql
│  │  └── ...
│  ├── seeds/                    # 🌱 Datos iniciales / prueba
│  │  ├── default_users.sql
│  │  └── sample_data.sql
│  ├── factories/                # 🏭 Factories para tests (PHP)
│  │  ├── UserFactory.php
│  │  └── ...
│  └── schema/                   # 📐 Diagramas o dumps de referencia
│      └── schema.sql            # Schema completo actualizado
│
│
├── public/                       # 🌐 Raíz web pública (único punto de entrada)
│  ├── index.php                 # 👁️ Front Controller (único archivo PHP ejecutable) [200 líneas]
│  │  # ▪️ Solo inicializa app, carga autoloader, ejecuta router
│  │  # ❌ NO contiene lógica de negocio
│  │
│  ├── assets/                   # 🎨 Recursos estáticos
│  │  ├── css/                  # Estilos organizados por capas
│  │  │  ├── core/             # Sistema de diseño base
│  │  │  │  ├── variables.css      # Variables CSS/SCSS [200 líneas]
│  │  │  │  ├── mixins.css         # Mixins reutilizables [200 líneas]
│  │  │  │  ├── reset.css          # CSS reset/normalize [200 líneas]
│  │  │  │  └── typography.css     # Estilos de texto [150 líneas]
│  │  │  │
│  │  │  ├── components/       # Componentes reutilizables [150 líneas c/u]
│  │  │  │  ├── buttons.css
│  │  │  │  ├── cards.css
│  │  │  │  ├── forms.css
│  │  │  │  ├── tables.css
│  │  │  │  ├── modals.css
│  │  │  │  └── ...
│  │  │  │
│  │  │  └── modules/          # Por funcionalidad específica [200 líneas c/u]
│  │  │      ├── items/
│  │  │      │  ├── form-layout.css
│  │  │      │  ├── list.css
│  │  │      │  └── billboard-preview.css
│  │  │      ├── displays/
│  │  │      │  ├── grid.css
│  │  │      │  └── config.css
│  │  │      └── bundles/      # ⚙️ Compilados/minificados (producción)
│  │  │          ├── items.bundle.min.css
│  │  │          └── displays.bundle.min.css
│  │  │
│  │  ├── js/                   # JavaScript modular
│  │  │  ├── core/             # Utilidades globales [200 líneas c/u]
│  │  │  │  ├── utils.js
│  │  │  │  ├── api-client.js
│  │  │  │  └── validation.js
│  │  │  │
│  │  │  ├── components/       # Componentes JS reutilizables [200 líneas c/u]
│  │  │  │  ├── modal.js
│  │  │  │  ├── toast.js
│  │  │  │  ├── datepicker.js
│  │  │  │  ├── uploader.js
│  │  │  │  └── ...
│  │  │  │
│  │  │  └── modules/          # Por módulo funcional [200 líneas c/u]
│  │  │      ├── items/
│  │  │      │  ├── form-validation.js
│  │  │      │  ├── media-upload.js
│  │  │      │  ├── feature-selector.js
│  │  │      │  └── billboard-preview.js
│  │  │      ├── displays/
│  │  │      │  ├── grid.js
│  │  │      │  ├── rotation.js
│  │  │      │  └── config.js
│  │  │      └── bundles/      # ⚙️ Compilados (producción)
│  │  │          ├── items.bundle.min.js
│  │  │          └── displays.bundle.min.js
│  │  │
│  │  ├── images/               # Imágenes del proyecto (logos, iconos)
│  │  │
│  │  └── vendor/               # 📦 Librerías externas (vía CDN o local)
│  │      ├── bootstrap/
│  │      ├── bootstrap-icons/
│  │      └── ...               # ⚠️ Solo librerías frontend, NUNCA PHP
│  │
│  └── uploads/                  # 📤 Archivos subidos por usuarios
│      ├── .gitkeep              # Mantener carpeta en git
│      ├── .htaccess             # Proteger ejecución de PHP
│      ├── photos/
│      ├── documents/
│      └── temp/                 # Temporal, limpieza automática
│
│
├── views/                        # 🖼️ Plantillas de presentación (PHP puro o templating)
│  ├── layouts/                  # 🎭 Plantillas base [200 líneas c/u]
│  │  ├── admin_layout.php      # Layout para panel administrativo
│  │  ├── public_layout.php     # Layout para área pública
│  │  ├── auth_layout.php       # Layout para autenticación
│  │  └── print_layout.php      # Layout para impresión (opcional)
│  │  # ▪️ Solo estructura HTML, incluyen header/footer/nav
│  │  # ❌ NO contienen lógica de negocio
│  │
│  ├── components/               # 🧩 Componentes reutilizables [150 líneas c/u]
│  │  ├── cards/
│  │  │  ├── _stats_card.php
│  │  │  ├── _info_card.php
│  │  │  └── _action_card.php
│  │  ├── forms/
│  │  │  ├── _input_group.php
│  │  │  ├── _select_group.php
│  │  │  ├── _checkbox_group.php
│  │  │  ├── _textarea_group.php
│  │  │  └── _file_upload.php
│  │  ├── tables/
│  │  │  ├── _data_table.php
│  │  │  ├── _pagination.php
│  │  │  └── _filters.php
│  │  └── modals/
│  │      ├── _confirm_modal.php
│  │      ├── _form_modal.php
│  │      └── _info_modal.php
│  │  ├── navigation/
│  │  │  ├── _sidebar.php
│  │  │  ├── _breadcrumb.php
│  │  │  └── _tabs.php
│  │  # ▪️ Prefijo con _ para indicar que son parciales
│  │  # ▪️ Reciben parámetros vía variables PHP
│  │  # ▪️ NO deben acceder a BD ni llamar servicios
│  │
│  ├── pages/                    # 📄 Páginas completas (organizadas por contexto)
│  │  ├── admin/                # Panel administrativo
│  │  │  ├── dashboard/
│  │  │  │  └── index.php     # [200 líneas]
│  │  │  │
│  │  │  ├── items/            # Módulo complejo dividido
│  │  │  │  ├── index.php     # Lista [200 líneas]
│  │  │  │  ├── form/         # Formulario dividido en partes
│  │  │  │  │  ├── index.php         # [150 líneas] estructura principal
│  │  │  │  │  ├── _basic_info.php   # [200 líneas]
│  │  │  │  │  ├── _location.php     # [80 líneas]
│  │  │  │  │  ├── _pricing.php      # [120 líneas]
│  │  │  │  │  ├── _features.php     # [200 líneas]
│  │  │  │  │  ├── _media.php        # [150 líneas]
│  │  │  │  │  └── _sidebar.php      # [120 líneas]
│  │  │  │  └── view.php      # Ver detalle [200 líneas]
│  │  │  │
│  │  │  ├── displays/
│  │  │  │  ├── index.php     # [200 líneas]
│  │  │  │  ├── form.php      # [200 líneas]
│  │  │  │  └── config.php    # [200 líneas]
│  │  │  │
│  │  │  └── users/
│  │  │      ├── index.php     # [200 líneas]
│  │  │      └── form.php      # [200 líneas]
│  │  │
│  │  ├── auth/                 # Autenticación [200 líneas c/u]
│  │  │  ├── login.php
│  │  │  ├── register.php
│  │  │  ├── forgot-password.php
│  │  │  └── reset-password.php
│  │  │
│  │  ├── errors/               # Páginas de error
│  │  │  ├── 404.php
│  │  │  ├── 500.php
│  │  │  └── 403.php
│  │  │
│  │  └── public/               # Área pública [200 líneas c/u]
│  │      ├── home.php
│  │      ├── about.php
│  │      └── contact.php
│  │
│  └── partials/                 # ⚠️ Solo para código legacy
│      └── ...                   # 🚨 MIGRAR a components/ cuando se refactorice
│      # ❌ NO crear nuevos archivos aquí
│
├── storage/                      # 💾 Almacenamiento de la aplicación
│  ├── logs/                     # 📝 Logs de la aplicación
│  │  ├── app.log
│  │  ├── error.log
│  │  └── access.log
│  ├── cache/                    # ⚡ Archivos de cache
│  │  └── .gitkeep
│  ├── sessions/                 # 🔒 Sesiones (si no se usan cookies)
│  │  └── .gitkeep
│  └── temp/                     # 📁 Temporal del sistema
│      └── .gitkeep
│
│
├── scripts/                      # 🛠️ Herramientas de desarrollo
│  ├── validate-code-standards.ps1   # Validador automático de líneas/estructura
│  ├── validate-code-standards.sh    # Versión Unix
│  ├── db-migrate.php                # Ejecuta migraciones desde CLI
│  ├── db-rollback.php               # Rollback de migraciones
│  ├── backup-db.sh                  # Script de respaldo automático
│  ├── generate-docs.php             # Genera documentación automática
│  └── clear-cache.sh                # Limpia cache y logs antiguos
│
├── tests/                        # 🧪 Pruebas automatizadas
│  ├── unit/                     # Tests unitarios (PHPUnit)
│  │  ├── services/
│  │  ├── repositories/
│  │  ├── validators/
│  │  └── models/
│  ├── integration/              # Tests de integración
│  │  ├── controllers/
│  │  └── api/
│  ├── feature/                  # Tests de funcionalidad completa
│  ├── fixtures/                 # Datos de prueba
│  │  ├── sample_data.json
│  │  └── test_users.sql
│  └── bootstrap.php             # Inicialización de tests
│
├── docs/                         # 📚 Documentación técnica
│  ├── REFACTORIZACION_[NOMBRE]_[FECHA].md   # Doc de refactorizaciones
│  ├── RESUMEN_REFACTORIZACIONES_[FECHA].md  # Resumen general
│  ├── API.md                                # Contratos de endpoints
│  ├── DATABASE_SCHEMA.md                    # Esquema de BD
│  └── ARCHITECTURE.md                       # Decisiones arquitectónicas
│
├── .github/                      # 🐙 Configuración GitHub (opcional)
│  ├── instructions/             # Instrucciones para IA/equipo
│  │  └── unik.instructions.md
│  └── workflows/                # CI/CD (GitHub Actions, si aplica)
│      ├── tests.yml
│      └── deploy.yml
│
├── config/                       # ⚙️ Archivos de configuración PHP
│  ├── database.php              # Config específica de BD (opcional si ya está en .env)
│  ├── mail.php                  # Config de email (opcional)
│  └── app.php                   # Config general (opcional si ya está en .env)
│  # ⚠️ NOTA: Si .env cubre toda la configuración, esta carpeta es opcional
│
├── docker/                       # 🐳 Configuración Docker (si aplica)
│  ├── Dockerfile
│  ├── docker-compose.yml
│  └── nginx/
│      └── default.conf
│
├── .env                          # 🔐 Variables de entorno (configuración directa, sin plantillas)
├── .gitignore                    # Ignorar storage, vendor, node_modules
├── .htaccess                     # Configuración Apache (si aplica)
├── composer.json                 # Dependencias PHP (si usas Composer)
├── composer.lock                 # Versiones congeladas de dependencias
├── package.json                  # Dependencias JS (si usas npm/webpack)
└── README.md                     # Instrucciones de instalación y arquitectura
```

---

## 📏 Límites por Tipo de Archivo (Referencia Rápida)

| Tipo de Archivo | Límite Recomendado | Tolerancia | Acción si excede |
|-----------------|-------------------|------------|------------------|
| **Controller** | 300 líneas | 350 | Extraer a Services |
| **Service** | 400 líneas | 450 | Dividir responsabilidades |
| **Repository** | 400 líneas | 450 | Dividir queries complejas |
| **Model** | 300 líneas | 350 | Extraer comportamiento |
| **Validator** | 300 líneas | 350 | Dividir por contexto |
| **Middleware** | 400 líneas | 450 | Dividir en middlewares específicos |
| **Helper** | 300 líneas | 350 | Separar utilidades |
| **DTO** | 200 líneas | 250 | Dividir en DTOs específicos |
| **Vista Principal** | 200 líneas | 250 | Dividir en includes/componentes |
| **Componente Vista** | 150 líneas | 180 | Simplificar o dividir |
| **Layout** | 200 líneas | 250 | Extraer parciales |
| **JavaScript** | 300 líneas | 350 | Modularizar |
| **CSS** | 400 líneas | 450 | Dividir por componentes |

---

## 📜 Reglas de Oro

### **1. Límite Máximo Estricto**
- **Máximo absoluto:** 400-500 líneas por archivo
- Si excedes 350 líneas, **STOP** y divide antes de continuar
- Excepciones: **Ninguna**

### **2. Separación de Responsabilidades**
- **Controllers:** Solo coordinan (validar input → llamar service → responder)
- **Services:** Solo lógica de negocio
- **Repositories:** Solo queries SQL
- **Models:** Solo propiedades y relaciones
- **Views:** Solo presentación (sin lógica)

### **3. Assets externalizados**
- **❌ PROHIBIDO:** Código JS/CSS inline en vistas
- **✅ CORRECTO:** Archivos externos en `public/assets/`

### **4. Convenciones de Naming**

#### **Archivos PHP:**
- Controllers: `{Entity}Controller.php`
- Services: `{Entity}Service.php` o `{Entity}{Feature}Service.php`
- Repositories: `{Entity}Repository.php`
- Models: `{Entity}.php`
- Validators: `{Entity}Validator.php`
- DTOs: `{Entity}DTO.php`
- Helpers: `{Purpose}Helper.php`
- Middleware: `{Purpose}Middleware.php`

#### **Vistas:**
- Vista principal: `{entity}.php` o `index.php`
- Parcial/Include: `_{descriptive}.php` (con prefijo `_`)
- Layout: `{context}_layout.php`
- Componente: `_{component}.php`

#### **JavaScript:**
- Module: `{entity}-{purpose}.js` (ej: `item-form-validation.js`)
- Component: `{component}.js` (ej: `modal.js`)
- Utility: `{purpose}.js` (ej: `utils.js`)

#### **CSS:**
- Module: `{entity}-{aspect}.css` (ej: `item-form-layout.css`)
- Component: `{component}.css` (ej: `buttons.css`)
- Core: `{system}.css` (ej: `variables.css`)

---

## 🚫 Anti-patrones Prohibidos

### **1. God Object**
```php
// ❌ PROHIBIDO
class ItemController {
    // 2000 líneas con TODA la lógica
}
```

### **2. Código Inline**
```php
// ❌ PROHIBIDO
<script>
    // 500 líneas de JS aquí
</script>

<style>
    /* 300 líneas de CSS aquí */
</style>
```

### **3. Queries en Controllers**
```php
// ❌ PROHIBIDO
public function getItems() {
    $result = Database::query("SELECT * FROM items...");
}
```

### **4. Lógica de Negocio en Vistas**
```php
// ❌ PROHIBIDO
<?php
if ($user->role === 'admin' && $item->status === 'active' && ...) {
    // 50 líneas de lógica compleja
}
?>
```

---

## 🔄 Flujo de Implementación

### **Para Nueva Funcionalidad:**

1. **Planificar estructura modular**
   - [ ] Identificar capas afectadas
   - [ ] Estimar líneas por archivo
   - [ ] Diseñar división si es complejo

2. **Implementar en orden (bottom-up)**
   - [ ] Migración DB (si aplica)
   - [ ] Model
   - [ ] Repository
   - [ ] Service
   - [ ] Controller
   - [ ] View (modularizada)
   - [ ] Assets (JS/CSS externos)

3. **Validar límites**
   - [ ] Ejecutar `scripts/validate-code-standards.sh`
   - [ ] Ningún archivo excede 400 líneas
   - [ ] Tests pasan

4. **Documentar**
   - [ ] Actualizar README si aplica
   - [ ] Crear doc de refactorización si es complejo
   - [ ] Actualizar API.md si hay endpoints

---

## 📊 Ejemplo de Módulo Complejo

### **Feature: "Gestión de Items con Billboard"**

```
Backend:
├── app/services/item/
│  ├── ItemService.php (200 líneas)
│  ├── ItemFeatureService.php (150 líneas)
│  ├── ItemMediaService.php (180 líneas)
│  └── ItemBillboardService.php (150 líneas)
├── app/repositories/
│  └── ItemRepository.php (300 líneas)

Frontend:
├── views/pages/admin/items/form/
│  ├── index.php (150 líneas)
│  ├── _basic_info.php (100 líneas)
│  ├── _location.php (80 líneas)
│  ├── _pricing.php (120 líneas)
│  ├── _features.php (200 líneas)
│  ├── _media.php (150 líneas)
│  └── _sidebar.php (120 líneas)

Assets:
├── public/assets/js/modules/items/
│  ├── form-validation.js (200 líneas)
│  ├── media-upload.js (180 líneas)
│  ├── feature-selector.js (150 líneas)
│  └── billboard-preview.js (200 líneas)
├── public/assets/css/modules/items/
│  ├── form-layout.css (150 líneas)
│  ├── feature-grid.css (120 líneas)
│  └── billboard-selector.css (100 líneas)
```

**Total:** ~2,650 líneas organizadas en **18 archivos modulares**
**Promedio:** ~147 líneas por archivo

---

## 🎓 Principios SOLID Aplicados

- **S**ingle Responsibility: Un archivo = una responsabilidad
- **O**pen/Closed: Extensible sin modificar código existente
- **L**iskov Substitution: Interfaces y abstracciones reutilizables
- **I**nterface Segregation: Contratos específicos
- **D**ependency Inversion: Depender de abstracciones, no implementaciones

---

## 🔒 Seguridad (Obligatorio)

### **1. SQL Injection**
- ✅ Usar **siempre** prepared statements (query parameterized)
- ❌ NUNCA concatenar inputs del usuario en queries SQL

```php
// ✅ CORRECTO
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

// ❌ PROHIBIDO
$query = "SELECT * FROM users WHERE email = '" . $_POST['email'] . "'";
```

### **2. XSS (Cross-Site Scripting)**
- ✅ Escapar TODA salida HTML con `htmlspecialchars()`
- ❌ NUNCA imprimir datos del usuario sin sanitizar

```php
// ✅ CORRECTO
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');

// ❌ PROHIBIDO
echo $_POST['comment'];
```

### **3. CSRF Protection**
- ✅ Incluir token CSRF en todos los formularios
- ✅ Validar token en el backend antes de procesar

```php
// Generar token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Validar token
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    throw new \Exception('Invalid CSRF token');
}
```

### **4. File Upload Security**
- ✅ Validar tipo MIME, no solo extensión
- ✅ Limitar tamaño máximo
- ✅ Usar nombres de archivo aleatorios
- ❌ NUNCA permitir ejecución de archivos subidos

```php
// ✅ CORRECTO
$finfo = new \finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowed = ['image/jpeg', 'image/png', 'application/pdf'];

if (!in_array($mime, $allowed)) {
    throw new \Exception('Invalid file type');
}

if ($file['size'] > 10 * 1024 * 1024) { // 10MB
    throw new \Exception('File too large');
}

$filename = bin2hex(random_bytes(16)) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
```

### **5. Password Hashing**
- ✅ Usar `password_hash()` con PASSWORD_ARGON2ID
- ❌ NUNCA usar MD5, SHA1 o texto plano

```php
// Hashear
$hash = password_hash($password, PASSWORD_ARGON2ID);

// Verificar
if (password_verify($password, $hash)) {
    // OK
}
```

---

## ⚠️ Manejo de Errores

### **1. Excepciones Personalizadas**
```php
<?php
namespace App\Exceptions;

class NotFoundException extends \Exception
{
    public function __construct(string $entity = 'Resource')
    {
        parent::__construct("{$entity} not found", 404);
    }
}

class UnauthorizedException extends \Exception
{
    public function __construct(string $message = 'Unauthorized')
    {
        parent::__construct($message, 401);
    }
}
```

### **2. Error Handler Global**
```php
// En core/config.php o similar
set_exception_handler(function (\Throwable $e) {
    // Log del error
    error_log($e->getMessage());

    // Response apropiada según contexto
    if (isApiRequest()) {
        http_response_code($e->getCode() ?: 500);
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        // Vista de error para usuarios
        include 'views/pages/error.php';
    }
});
```

### **3. Validación Temprana (Fail Fast)**
```php
// ✅ CORRECTO: Validar al inicio, fallar rápido
public function update(int $id, array $data): bool
{
    if ($id <= 0) {
        throw new \InvalidArgumentException('Invalid ID');
    }

    if (empty($data)) {
        throw new \InvalidArgumentException('No data provided');
    }

    // ... lógica normal
}
```

---

## 📝 Logging

### **1. Logger Simple**
```php
<?php
namespace App\Core;

class Logger
{
    private const LOG_FILE = __DIR__ . '/../../logs/app.log';

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    private static function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        $logLine = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        file_put_contents(self::LOG_FILE, $logLine, FILE_APPEND | LOCK_EX);
    }
}
```

### **2. Uso**
```php
use App\Core\Logger;

// En Services
Logger::info("User created", ['user_id' => $userId, 'email' => $email]);
Logger::error("Payment failed", ['order_id' => $orderId, 'reason' => $error]);
```

### **3. Estructura de Logs**
```
logs/
├── app.log          # Log general
├── error.log        # Solo errores
├── access.log       # Requests HTTP
└── debug.log        # Debug (solo development)
```

---

## ⚡ Performance (Mejores Prácticas)

### **1. Database: Evitar N+1 Queries**
```php
// ❌ PROHIBIDO: N+1 queries
$items = $itemRepo->findAll();
foreach ($items as $item) {
    $category = $categoryRepo->findById($item['category_id']); // Query por cada item
}

// ✅ CORRECTO: JOIN o eager loading
$items = $itemRepo->findAllWithCategories(); // Single query con JOIN
```

### **2. Indexar Columnas de Búsqueda**
```sql
-- ✅ Agregar índices en columnas usadas en WHERE, ORDER BY, JOIN
CREATE INDEX idx_items_status ON items(status);
CREATE INDEX idx_items_created ON items(created_at);
CREATE INDEX idx_users_email ON users(email);
```

### **3. Caching Simple**
```php
<?php
class Cache
{
    private static array $store = [];

    public static function remember(string $key, callable $callback, int $ttl = 3600)
    {
        if (isset(self::$store[$key]) && self::$store[$key]['expires'] > time()) {
            return self::$store[$key]['data'];
        }

        $data = $callback();
        self::$store[$key] = [
            'data' => $data,
            'expires' => time() + $ttl
        ];

        return $data;
    }
}

// Uso
$categories = Cache::remember('all_categories', fn() => $categoryRepo->findAll(), 3600);
```

### **4. Paginación Obligatoria**
```php
// ✅ CORRECTO: Siempre paginar listados
public function findAll(int $page = 1, int $perPage = 20, array $filters = []): array
{
    $offset = ($page - 1) * $perPage;

    $query = "SELECT * FROM items WHERE 1=1";
    // ... aplicar filtros ...
    $query .= " LIMIT {$perPage} OFFSET {$offset}";

    return Database::fetchAll($query);
}
```

### **5. Optimizar Assets**
- ✅ Minificar CSS/JS para producción
- ✅ Usar lazy loading para imágenes
- ✅ Comprimir imágenes antes de subir
- ✅ Usar CDN para archivos estáticos

---

## 🧪 Testing con Ejemplos

### **1. Test Unitario de Service**
```php
<?php
namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\EntityService;

class EntityServiceTest extends TestCase
{
    private EntityService $service;

    protected function setUp(): void
    {
        $this->service = new EntityService();
    }

    public function test_create_valid_entity(): void
    {
        $data = [
            'title' => 'Test Entity',
            'description' => 'Test Description',
            'status' => 'active'
        ];

        $id = $this->service->create($data);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function test_create_fails_without_title(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->create(['description' => 'No title']);
    }

    public function test_find_nonexistent_entity(): void
    {
        $result = $this->service->findById(99999);
        $this->assertNull($result);
    }
}
```

### **2. Test de Integración de Controller**
```php
<?php
namespace Tests\Integration\Controllers;

use PHPUnit\Framework\TestCase;

class EntityControllerTest extends TestCase
{
    public function test_get_index_returns_200(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/entities';

        ob_start();
        // Ejecutar router/controller
        $output = ob_get_clean();

        $this->assertResponseCode(200);
        $data = json_decode($output, true);
        $this->assertArrayHasKey('success', $data);
    }
}
```

### **3. Test de Validator**
```php
<?php
namespace Tests\Unit\Validators;

use PHPUnit\Framework\TestCase;
use App\Validators\EntityValidator;

class EntityValidatorTest extends TestCase
{
    private EntityValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new EntityValidator();
    }

    public function test_valid_data_passes(): void
    {
        $data = ['title' => 'Valid', 'status' => 'active'];
        $result = $this->validator->validate($data);
        $this->assertEquals($data, $result);
    }

    /**
     * @dataProvider invalidTitleProvider
     */
    public function test_invalid_title_fails(array $data): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validate($data);
    }

    public static function invalidTitleProvider(): array
    {
        return [
            'empty title' => [['title' => '']],
            'too long title' => [['title' => str_repeat('a', 256)]],
            'missing title' => [[]],
        ];
    }
}
```

### **4. Coverage Mínimo Requerido**
| Capa | Coverage Mínimo |
|------|----------------|
| Services | 80% |
| Validators | 90% |
| Repositories | 70% |
| Controllers | 60% |
| Models | 70% |

---

## 🔄 Flujo de Capas (Diagrama)

```
┌─────────────────────────────────────────────────────┐
│                    HTTP Request                      │
└──────────────────────┬──────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────┐
│                   Controller                         │
│  (Validar input, coordinar, responder)              │
└──────────────────────┬──────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────┐
│                   Service                            │
│  (Lógica de negocio, reglas, validaciones)          │
└──────────────────────┬──────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────┐
│                   Repository                         │
│  (Queries SQL, acceso a datos)                      │
└──────────────────────┬──────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────┐
│                   Database                           │
└─────────────────────────────────────────────────────┘
```

**Regla:** Cada capa solo comunica con la capa inmediatamente inferior.
- ❌ Controller → Database (saltar Service/Repository)
- ❌ View → Service (usar Controller)
- ✅ Controller → Service → Repository → Database

---

## 🌐 Convenciones de API REST (Si aplica)

### **1. Endpoints**
```
GET    /api/{resource}          # Listar (paginado)
GET    /api/{resource}/{id}     # Obtener uno
POST   /api/{resource}          # Crear
PUT    /api/{resource}/{id}     # Actualizar completo
PATCH  /api/{resource}/{id}     # Actualizar parcial
DELETE /api/{resource}/{id}     # Eliminar
```

### **2. Formato de Respuesta**
```php
// Success
{
    "success": true,
    "data": { ... },
    "message": "Operation completed"
}

// Error
{
    "success": false,
    "error": "Error message",
    "errors": { "field": "validation error" }  // opcional
}

// Paginated
{
    "success": true,
    "data": [...],
    "pagination": {
        "current_page": 1,
        "per_page": 20,
        "total": 100,
        "last_page": 5
    }
}
```

### **3. HTTP Status Codes**
| Code | Uso |
|------|-----|
| 200 | GET exitoso |
| 201 | Recurso creado (POST) |
| 204 | DELETE exitoso sin body |
| 400 | Request inválido |
| 401 | No autenticado |
| 403 | Sin permisos |
| 404 | Recurso no encontrado |
| 422 | Error de validación |
| 500 | Error del servidor |

---

## 🚀 Deployment Checklist

- [ ] ✅ Variables de entorno configuradas (`.env.production`)
- [ ] ✅ `APP_ENV=production` y `APP_DEBUG=false`
- [ ] ✅ Migraciones ejecutadas en producción
- [ ] ✅ Assets minificados (CSS/JS)
- [ ] ✅ Logs configurados y con permisos
- [ ] ✅ HTTPS habilitado
- [ ] ✅ Backups de DB programados
- [ ] ✅ Rate limiting configurado
- [ ] ✅ Validación de estándares ejecutada
- [ ] ✅ Todos los tests pasan
- [ ] ✅ Documentación de API actualizada

---

## 🏗️ Patrones de Arquitectura (Ejemplos de Código)

### **1. Controller (Delgado)**

**✅ CORRECTO:**
```php
<?php
namespace App\Controllers;

use App\Services\EntityService;

class EntityController
{
    private EntityService $entityService;

    public function __construct() {
        $this->entityService = new EntityService();
    }

    public function create(): void
    {
        // 1. Validar entrada (10-15 líneas)
        $data = $this->validateInput($_POST);

        // 2. Delegar a service (1 línea)
        $entityId = $this->entityService->create($data);

        // 3. Responder (1-3 líneas)
        $this->jsonResponse(['success' => true, 'id' => $entityId]);
    }

    private function validateInput(array $data): array
    {
        // Validación básica
        if (empty($data['title'])) {
            throw new \Exception('Title is required');
        }
        return $data;
    }

    private function jsonResponse(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    // Total: ~30-40 líneas por método
}
```

**❌ INCORRECTO:**
```php
public function create(): void
{
    // 200+ líneas de lógica de negocio aquí
    // Validaciones complejas
    // Queries SQL directas
    // Procesamiento de archivos
    // etc...
}
```

---

### **2. Service (Lógica de Negocio)**

**✅ CORRECTO:**
```php
<?php
namespace App\Services;

use App\Repositories\EntityRepository;
use App\Validators\EntityValidator;

class EntityService
{
    private EntityRepository $repository;
    private EntityValidator $validator;

    public function __construct() {
        $this->repository = new EntityRepository();
        $this->validator = new EntityValidator();
    }

    public function create(array $data): int
    {
        // 1. Validar datos
        $validated = $this->validator->validate($data);

        // 2. Aplicar reglas de negocio
        $validated = $this->applyBusinessRules($validated);

        // 3. Guardar en BD vía repository
        return $this->repository->create($validated);
    }

    public function update(int $id, array $data): bool
    {
        $existing = $this->repository->findById($id);
        if (!$existing) {
            throw new \Exception('Entity not found');
        }

        $validated = $this->validator->validate($data);
        $validated = $this->applyBusinessRules($validated);

        return $this->repository->update($id, $validated);
    }

    private function applyBusinessRules(array $data): array
    {
        // Lógica de negocio específica
        // Ej: calcular descuentos, aplicar restricciones, etc.
        return $data;
    }

    // Máximo 400 líneas en total
}
```

---

### **3. Repository (Acceso a Datos)**

**✅ CORRECTO:**
```php
<?php
namespace App\Repositories;

use App\Core\Database;

class EntityRepository
{
    public function create(array $data): int
    {
        return Database::query(
            "INSERT INTO entities (title, description, status, created_at)
             VALUES (?, ?, ?, NOW())",
            [$data['title'], $data['description'], $data['status'] ?? 'active']
        );
    }

    public function findById(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM entities WHERE id = ?",
            [$id]
        );
    }

    public function findAll(array $filters = []): array
    {
        $query = "SELECT * FROM entities WHERE 1=1";
        $params = [];

        // Aplicar filtros dinámicos
        if (!empty($filters['status'])) {
            $query .= " AND status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $query .= " AND (title LIKE ? OR description LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }

        return Database::fetchAll($query, $params);
    }

    public function update(int $id, array $data): bool
    {
        return Database::query(
            "UPDATE entities SET title = ?, description = ?, status = ?, updated_at = NOW()
             WHERE id = ?",
            [$data['title'], $data['description'], $data['status'], $id]
        ) > 0;
    }

    public function delete(int $id): bool
    {
        return Database::query(
            "DELETE FROM entities WHERE id = ?",
            [$id]
        ) > 0;
    }

    //  Solo métodos de acceso a datos
    // Sin lógica de negocio
}
```

**❌ INCORRECTO:**
```php
public function create(array $data): int
{
    // Validaciones complejas aquí
    // Lógica de negocio aquí
    // Cálculos complejos aquí
    // ...
    // Query SQL al final
}
```

---

### **4. Model (Ligero)**

**✅ CORRECTO:**
```php
<?php
namespace App\Models;

class Entity
{
    public int $id;
    public string $title;
    public ?string $description;
    public string $status;
    public \DateTime $createdAt;
    public array $attributes = [];

    // Solo getters, setters y formatters simples
    public function getFormattedDate(): string
    {
        return $this->createdAt->format('d/m/Y H:i');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s')
        ];
    }

    // Máximo 300 líneas
}
```

---

### **5. Validator**

**✅ CORRECTO:**
```php
<?php
namespace App\Validators;

class EntityValidator
{
    public function validate(array $data): array
    {
        $errors = [];

        // Validar title
        if (empty($data['title'])) {
            $errors['title'] = 'Title is required';
        } elseif (strlen($data['title']) > 255) {
            $errors['title'] = 'Title must be less than 255 characters';
        }

        // Validar description
        if (!empty($data['description']) && strlen($data['description']) > 1000) {
            $errors['description'] = 'Description must be less than 1000 characters';
        }

        // Validar status
        $validStatuses = ['active', 'inactive', 'draft'];
        if (!empty($data['status']) && !in_array($data['status'], $validStatuses)) {
            $errors['status'] = 'Invalid status';
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(json_encode($errors));
        }

        return $data;
    }

    // Máximo 300 líneas
}
```

---

## 🚀 Script de Validación Automática

### **PowerShell (scripts/validate-code-standards.ps1)**

```powershell
# Validar estándares de código
param(
    [string]$Path = ".",
    [int]$MaxLines = 400,
    [string[]]$Extensions = @("*.php", "*.js", "*.css")
)

Write-Host "🔍 Validando estándares de código..." -ForegroundColor Cyan

$violations = @()

foreach ($ext in $Extensions) {
    Get-ChildItem -Path $Path -Recurse -Filter $ext | ForEach-Object {
        $lines = (Get-Content $_.FullName | Measure-Object -Line).Lines
        $relativePath = $_.FullName.Replace($PWD, ".").Replace("\", "/")

        if ($lines -gt $MaxLines) {
            $violations += [PSCustomObject]@{
                Archivo = $relativePath
                Líneas = $lines
                Exceso = $lines - $MaxLines
                Severidad = if ($lines -gt 600) { "🔴 CRÍTICO" }
                           elseif ($lines -gt $MaxLines) { "🟠 ALTA" }
                           else { "🟡 MEDIA" }
            }
        }
    }
}

if ($violations.Count -eq 0) {
    Write-Host "✅ ¡ Todos los archivos cumplen con los estándares!" -ForegroundColor Green
    exit 0
} else {
    Write-Host "❌ Se encontraron $($violations.Count) violaciones:" -ForegroundColor Red
    $violations | Sort-Object -Property Líneas -Descending | Format-Table -AutoSize
    Write-Host ""
    Write-Host "💡 Refactoriza estos archivos antes de continuar." -ForegroundColor Yellow
    exit 1
}
```

### **Bash (scripts/validate-code-standards.sh)**

```bash
#!/bin/bash
# Validar estándares de código

MAX_LINES=400
PATH_TO_CHECK=${1:-.}

echo "🔍 Validando estándares de código..."

violations=0

# Buscar archivos PHP, JS, CSS
for file in $(find "$PATH_TO_CHECK" -type f \( -name "*.php" -o -name "*.js" -o -name "*.css" \)); do
    lines=$(wc -l < "$file")

    if [ "$lines" -gt "$MAX_LINES" ]; then
        excess=$((lines - MAX_LINES))

        if [ "$lines" -gt 600 ]; then
            severity="🔴 CRÍTICO"
        else
            severity="🟠 ALTA"
        fi

        echo "$severity - $file: $lines líneas (exceso: +$excess)"
        violations=$((violations + 1))
    fi
done

if [ "$violations" -eq 0 ]; then
    echo "✅ ¡ Todos los archivos cumplen con los estándares!"
    exit 0
else
    echo ""
    echo "❌ Se encontraron $violations violaciones"
    echo "💡 Refactoriza estos archivos antes de continuar."
    exit 1
fi
```

### **Uso:**

```bash
# PowerShell
.\scripts\validate-code-standards.ps1
.\scripts\validate-code-standards.ps1 -Path "app/services" -MaxLines 450

# Bash
./scripts/validate-code-standards.sh
./scripts/validate-code-standards.sh app/services
```

---

## 📋 Template para Planificar Nueva Funcionalidad

Copia este template cuando implementes una nueva feature:

```markdown
## Funcionalidad: [NOMBRE DE LA FUNCIONALIDAD]

### 1. Análisis Inicial
- [ ] **Complejidad:** Simple / Media / Compleja
- [ ] **Capas afectadas:**
  - [ ] Controller
  - [ ] Service
  - [ ] Repository
  - [ ] Model
  - [ ] View
  - [ ] JavaScript
  - [ ] CSS
- [ ] **Archivos a crear:** (listar)
- [ ] **Archivos a modificar:** (listar)
- [ ] **Migraciones DB:** Sí / No

### 2. Estructura Propuesta

```
[Pegar árbol de archivos que se crearán]
Ejemplo:
Backend:
├── app/services/{entity}/
│  └── {Entity}Service.php (estimado: 250 líneas)
├── app/repositories/
│  └── {Entity}Repository.php (estimado: 200 líneas)
Frontend:
├── views/pages/admin/{entity}/
│  ├── index.php (estimado: 150 líneas)
│  └── form.php (estimado: 180 líneas)
Assets:
├── public/assets/js/modules/{entity}/
│  └── form-validation.js (estimado: 200 líneas)
└── public/assets/css/modules/{entity}/
    └── form-layout.css (estimado: 150 líneas)
```

### 3. Límites de Líneas Planificados

| Archivo | Estimado | Límite | Estado |
|---------|----------|--------|--------|
| {Entity}Controller.php | XXX | 300 | │|
| {Entity}Service.php | XXX | 400 | │|
| {Entity}Repository.php | XXX | 400 | │|
| view.php | XXX | 200 | │|
|  Script.js | XXX | 300 | │|
| styles.css | XXX | 400 | │|

### 4. Checklist de Implementación

**Fase 1: Base de Datos**
- [ ] Crear migración SQL
- [ ] Ejecutar migración en dev
- [ ] Verificar integridad de datos

**Fase 2: Backend (bottom-up)**
- [ ] Crear Model
- [ ] Crear Repository (CRUD básico)
- [ ] Crear Service (lógica de negocio)
- [ ] Crear Validator
- [ ] Crear Controller (coordinación)
- [ ] Verificar límites de líneas

**Fase 3: Frontend**
- [ ] Crear layout/estructura principal
- [ ] Dividir en componentes reutilizables
- [ ] Implementar formularios
- [ ] Implementar listados/tablas
- [ ] Verificar límites de líneas

**Fase 4: Assets**
- [ ] Crear JavaScript modularizado
- [ ] Crear CSS modularizado
- [ ] Vincular assets en vistas
- [ ] Verificar límites de líneas

**Fase 5: Testing**
- [ ]  Tests unitarios (Services, Validators)
- [ ]  Tests de integración (Controllers)
- [ ]  Tests de UI (opcional)
- [ ] Validar en navegadores

**Fase 6:  Documentación**
- [ ] Actualizar README
- [ ] Documentar endpoints (API.md)
- [ ] Crear doc de refactorización si aplica
- [ ] Actualizar CHANGELOG

### 5. Validación Final

- [ ] ✅ Ningún archivo excede 400 líneas
- [ ] ✅ Código sigue patrones establecidos
- [ ] ✅ Assets externalizados (no inline)
- [ ] ✅ Tests pasan
- [ ] ✅ Documentación actualizada
- [ ] ✅ Script `validate-code-standards` ejecutado
- [ ] ✅ Code review realizado

### 6. Notas Adicionales

[Espacio para consideraciones especiales, dependencias externas, etc.]

---

**Fecha de inicio:** [DD/MM/YYYY]
**Fecha estimada de finalización:** [DD/MM/YYYY]
**Desarrollador(es):** [Nombre(s)]
```

---

## 💡 Preguntas Frecuentes (FAQ)

### **P: ¿Puedo exceder temporalmente el límite mientras desarrollo?**
**R:** No. Planifica la estructura modular desde el principio. Es más fácil prevenir que refactorizar después.

### **P: ¿Qué hago si un archivo legacy tiene 1000+ líneas?**
**R:** No agregues más código. Primero refactoriza siguiendo este documento como guía.

### **P: ¿Cuándo usar Service vs Repository?**
**R:**
- **Repository:**  Solo acceso a datos (queries SQL, CRUD básico)
- **Service:** Lógica de negocio que coordina uno o más repositories

### **P: ¿Puedo tener lógica en los Models?**
**R:**  Solo comportamiento simple relacionado directamente con la entidad (getters, setters, formatters). Lógica compleja va en Services.

### **P: ¿Debo crear un Service para operaciones simples?**
**R:** Si es un CRUD simple sin lógica de negocio, puedes usar directamente el Repository desde el Controller. Para lógica compleja, siempre usa Service.

### **P: ¿Cómo manejo archivos muy complejos (ej: 600+ líneas)?**
**R:** Divide inmediatamente:
1. Identifica responsabilidades separadas
2. Crea archivos específicos por responsabilidad
3. Refactoriza extrayendo código
4. Actualiza imports/referencias

### **P: ¿Qué pasa si necesito más de 400 líneas para una funcionalidad?**
**R:** Eso significa que la funcionalidad tiene múltiples responsabilidades. Divídela en módulos especializados (ver ejemplos en este documento).

### **P: ¿Debo seguir estos estándares en prototipos rápidos?**
**R:** Sí. Es más fácil mantener disciplina desde el inicio que refactorizar después. Los límites ayudan a escribir código más claro.

### **P: ¿Puedo usar frameworks que no siguen esta estructura?**
**R:** Sí, pero adapta los principios (límites de líneas, separación de responsabilidades, modularización) a la estructura del framework.

---

## 🔄 Git:  Commits Organizados Después de Refactorización

### **⚠️ IMPORTANTE: Crear  Commits Automáticamente**

Cuando completes una refactorización significativa, **crea  Commits organizados automáticamente** siguiendo esta estructura:

### **Estructura de 4  Commits:**

#### **1. Commit: Módulos/Archivos Creados**
```bash
git add [carpeta_de_modulos/]
git commit -m "refactor: crear módulos especializados de [funcionalidad]

- [Modulo1].php (XXX líneas) - [Responsabilidad]
- [Modulo2].php (XXX líneas) - [Responsabilidad]
- [Modulo3].php (XXX líneas) - [Responsabilidad]

Refactoriza X,XXX líneas de código monolítico en N módulos especializados.
Cada módulo tiene una responsabilidad única siguiendo principios SOLID.
Mantiene 100% de retrocompatibilidad.

Refs: #refactoring"
```

#### **2. Commit: Actualización de Configuración/Vistas**
```bash
git add [archivos_de_configuracion_o_vistas]
git commit -m "refactor: actualizar carga de módulos en [componente]

Reemplaza archivos monolíticos por nuevos módulos especializados:
- Elimina: [archivo_legacy1], [archivo_legacy2]
- Agrega: N módulos en [carpeta/]

Los módulos se cargan en orden de dependencias.
Archivos legacy respaldados en carpeta _backup/ si es necesario.

Refs: #refactoring"
```

#### **3. Commit:  Documentación Técnica Detallada**
```bash
git add docs/REFACTORIZACION_[NOMBRE]_[FECHA].md
git commit -m "docs: agregar  Documentación de refactorización [nombre]

 Documentación técnica que incluye:
- Métricas: X,XXX / Y,YYY líneas (-Z%)
- Arquitectura modular con diagramas
- Descripción de cada módulo
- Flujo de inicialización
- Mejoras en mantenibilidad y performance
- Comparativas antes/después
- Testing checklist

Refs: #refactoring #documentation"
```

#### **4. Commit: Resumen General Actualizado**
```bash
git add docs/RESUMEN_REFACTORIZACIONES_[FECHA].md
git commit -m "docs: actualizar resumen general con [nombre]

Actualiza métricas totales:
- Total refactorizado: X,XXX / Y,YYY líneas (N módulos)
- Promedio por archivo: XXX / YYY líneas
- Archivos que excedían límites: X / 0

Agrega sección de [funcionalidad]:
- N módulos especializados creados
- Responsabilidades separadas
- 100% retrocompatibilidad

Refs: #refactoring #documentation"
```

### **Convenciones de Mensajes Commit:**

- **Prefijo `refactor:`** - Cambios de código/arquitectura
- **Prefijo `docs:`** -  Documentación
- **Prefijo `feat:`** - Nueva funcionalidad
- **Prefijo `fix:`** - Corrección de bugs
- **Línea 1:** Máximo 72 caracteres
- **Cuerpo:** Lista de cambios con bullets
- **Referencias:** `Refs: #refactoring`, `Refs: #issue-123`

### **Verificación Post-Commit:**

```bash
# Ver últimos  Commits
git log --oneline -5

# Ver detalles de un commit
git show --stat [commit_hash]

# Verificar estado limpio
git status
```

### **⚠️ NOTA CRÍTICA:**

- ✅ Los Commits deben crearse **automáticamente al finalizar refactorización**
- ✅ **No esperar** a que el usuario lo solicite explícitamente
- ✅ Seguir **siempre** la estructura de 4 Commits
- ✅ Usar mensajes descriptivos siguiendo **Conventional Commits**

---

## 📝 Checklist Pre-Commit

Antes de hacer `git commit`, verifica:

- [ ] ✅ Ningún archivo excede 400 líneas
- [ ] ✅ Controllers delgados (solo coordinan)
- [ ] ✅ Services con lógica de negocio
- [ ] ✅ Repositories con solo queries
- [ ] ✅ Models ligeros
- [ ] ✅ Vistas modularizadas
- [ ] ✅ Assets externalizados (no inline)
- [ ] ✅ Nombres siguen convenciones
- [ ] ✅ Estructura de carpetas correcta
- [ ] ✅ Tests creados/actualizados
- [ ] ✅ Documentación actualizada
- [ ] ✅ Script de validación ejecutado
- [ ] ✅ Commits organizados (si es refactorización)

---

## 🚀 Comandos Útiles

### **Validar Estándares:**
```powershell
# Windows (PowerShell)
.\scripts\validate-code-standards.ps1

# Con parámetros personalizados
.\scripts\validate-code-standards.ps1 -Path "app/services" -MaxLines 450

# Unix/Linux/Mac (Bash)
./scripts/validate-code-standards.sh
./scripts/validate-code-standards.sh app/services
```

### **Ejecutar Tests:**
```bash
# PHPUnit (si está instalado)
./vendor/bin/phpunit

# Con coverage
./vendor/bin/phpunit --coverage-html coverage/

# Solo un test específico
./vendor/bin/phpunit tests/unit/Services/EntityServiceTest.php
```

### **Migrar Base de Datos:**
```bash
# Ejecutar migración
php scripts/db-migrate.php

# Rollback última migración
php scripts/db-rollback.php

# Ver status de migraciones
php scripts/db-status.php
```

### **Backup de Base de Datos:**
```bash
# Unix/Linux
./scripts/backup-db.sh

# Windows (usando MySQL)
mysqldump -u root -p database_name > backup_$(date +%Y%m%d).sql
```

### **Pre-commit Automático (Git Hook):**

Crear archivo `.git/hooks/pre-commit`:

```bash
#!/bin/bash
# Pre-commit hook: validar estándares antes de commit

echo "🔍 Validando estándares de código..."

# Ejecutar validador
./scripts/validate-code-standards.sh

# Si falla, abortar commit
if [ $? -ne 0 ]; then
    echo "❌ Commit abortado: archivos exceden límites"
    echo "💡 Refactoriza los archivos y vuelve a intentar"
    exit 1
fi

echo "✅ Validación exitosa"
exit 0
```

Dar permisos de ejecución:
```bash
chmod +x .git/hooks/pre-commit
```

---

## 🎯 Recordatorio Final

> **"Un archivo de 400 líneas bien estructurado es infinitamente mejor que uno de 2000 líneas mezclando responsabilidades."**

### **Si tienes dudas, pregúntate:**

1. ¿Este archivo tiene **una sola responsabilidad clara**?
2. ¿Puedo explicar qué hace este archivo en **una frase**?
3. ¿Otro desarrollador puede entenderlo en **menos de 5 minutos**?

**Si respondiste "no" a alguna, refactoriza.**

---

## 📚 Recursos Adicionales

### **Patrones de Diseño:**
- **Repository Pattern:** Abstrae acceso a datos de la lógica de negocio
- **Service Layer:** Encapsula lógica de negocio compleja
- **DTO (Data Transfer Objects):** Transporta datos entre capas sin lógica
- **Factory Pattern:** Crea objetos complejos de forma centralizada
- **Dependency Injection:** Inyecta dependencias en lugar de instanciarlas

### **Principios SOLID:**
- **S**ingle Responsibility: Una clase/archivo = una responsabilidad
- **O**pen/Closed: Abierto a extensión, cerrado a modificación
- **L**iskov Substitution: Subclases deben ser intercambiables
- **I**nterface Segregation: Interfaces específicas, no genéricas
- **D**ependency Inversion: Depender de abstracciones, no implementaciones

### **PSR Standards (PHP):**
- **PSR-1:** Basic Coding Standard
- **PSR-4:** Autoloading Standard
- **PSR-12:** Extended Coding Style Guide
- **PSR-7:** HTTP Message Interface (si trabajas con APIs)

### **Clean Code (Robert C. Martin):**
- Nombres de Scriptivos
- Funciones pequeñas y específicas
- Comentarios  Solo cuando sea necesario
- No duplicar código (DRY - Don't Repeat Yourself)
- Testing exhaustivo

---

## 📄 Estructura de .env (Ejemplo)

> ⚠️ **IMPORTANTE**: Este archivo se crea **directamente con valores reales**, no es una plantilla.
> Cada entorno (PC local, VPS) tiene su propio `.env` con sus credenciales específicas.

```env
# Configuración de Base de Datos
DB_HOST=localhost
DB_PORT=3306
DB_NAME=project_db
DB_USER=root
DB_PASS=password

# Entorno
APP_ENV=development  # development | production | testing
APP_DEBUG=true
APP_URL=http://localhost:8000

# Seguridad
APP_KEY=your-secret-key-here
JWT_SECRET=your-jwt-secret-here

# Email (opcional)
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=your-email@gmail.com
MAIL_PASS=your-password

# Uploads
UPLOAD_MAX_SIZE=10485760  # 10MB en bytes
ALLOWED_EXTENSIONS=jpg,jpeg,png,pdf,docx

# API Keys (si aplica)
API_KEY_EXTERNAL_SERVICE=your-api-key
```

---

## 🗂│.gitignore (Ejemplo)

```gitignore
# Variables de entorno (NO commitear si contiene credenciales sensibles)
# Nota: En este flujo de trabajo, .env SÍ se incluye en el repo
# ya que el despliegue es directo PC → VPS sin pasar por GitHub
# .env

# Dependencias
/vendor/
/node_modules/
/composer.phar

# Uploads y archivos temporales
/public/uploads/*
!/public/uploads/.gitkeep
/public/temp/

# Logs
/logs/*.log
*.log

# Cache
/cache/
/tmp/

# IDE
.vscode/
.idea/
*.swp
*.swo
*~

# OS
.DS_Store
Thumbs.db
desktop.ini

# Build artifacts
/dist/
/build/
*.min.js
*.min.css

#  Tests
/coverage/
/.phpunit.cache

# Backups
*.backup
*.bak
*.sql.gz
```

---

## 📖 README.md (Template)

```markdown
# Nombre del Proyecto

> Breve descripción del proyecto

## 🚀 Instalación

### Requisitos
- PHP >= 8.0
- MySQL/PostgreSQL >= 5.7
- Composer (opcional)
- Node.js >= 16 (si usas build tools)

### Pasos

1. **Clonar repositorio**
   ```bash
   git clone https://github.com/usuario/proyecto.git
   cd proyecto
   ```

2. **Configurar variables de entorno**
   ```bash
   # El archivo .env ya existe con valores reales
   # Solo ajustar si las credenciales del entorno son distintas
   nano .env
   ```

3. **Instalar dependencias**
   ```bash
   composer install  # Si usas Composer
   npm install       # Si usas Node.js
   ```

4. **Crear base de datos**
   ```bash
   mysql -u root -p
   CREATE DATABASE project_db;
   ```

5. **Ejecutar migraciones**
   ```bash
   php scripts/db-migrate.php
   ```

6. **Iniciar servidor**
   ```bash
   php -S localhost:8000 -t public
   ```

7. **Abrir en navegador**
   ```
   http://localhost:8000
   ```

## 📁 Estructura del Proyecto

Ver [estructura-template.md](estructura-template.md) para  Documentación completa de arquitectura.

```
project/
├── app/          # Backend (Controllers, Services, Repositories)
├── public/       # Assets y punto de entrada
├── views/        # Plantillas HTML/PHP
├── database/     # Migraciones SQL
├── tests/        # Tests automatizados
└── docs/         #  Documentación
```

## 🧪 Testing

```bash
# Ejecutar todos los tests
./vendor/bin/phpunit

# Con coverage
./vendor/bin/phpunit --coverage-html coverage/
```

## 🛠️ Validar Estándares

```bash
# Validar que ningún archivo exceda 400 líneas
.\scripts\validate-code-standards.ps1
```

## 📝 Contribuir

1. Fork el proyecto
2. Crea una rama (`git checkout -b feature/nueva-funcionalidad`)
3. Sigue los estándares en este documento
4. Commit cambios (`git commit -m 'feat: agregar nueva funcionalidad'`)
5. Push a la rama (`git push origin feature/nueva-funcionalidad`)
6. Abre un Pull Request

## 📄 Licencia

[MIT](LICENSE) - ver archivo LICENSE para detalles

## 👥 Autores

- **Tu Nombre** - [GitHub](https://github.com/usuario)

## 🙏 Agradecimientos

- [Lista de librerías/recursos utilizados]
```

---

**Template creado:** 4 de febrero de 2026
**Última revisión:** 4 de abril de 2026
**Versión:** 3.0
**Próxima revisión:** Cada 3 meses o cuando se detecten nuevos anti-patrones

---

> **💡 Recuerda:** Un código bien estructurado es más fácil de mantener, testear y escalar.
> **La modularidad y los límites de líneas son tus mejores aliados.**
>
> **¿Preguntas?** Consulta este documento **SIEMPRE** antes de crear o modificar código.
