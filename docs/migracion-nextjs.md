## Plan de Migración y Arquitectura Monorepo para MRP

Este documento detalla la estrategia para transformar tu actual sistema MRP en PHP en un monorepo moderno, desplegado en **Vercel** (frontend y API) y **Supabase** (base de datos y autenticación), con **Hono** como framework API ligero y **Next.js 16** (App Router) para todo el proyecto. El objetivo es lograr una **separación clara de responsabilidades**, **modularidad** y **escalabilidad**, facilitando el mantenimiento por una sola persona y permitiendo futuros reemplazos del frontend sin afectar el backend.

> **Nota de versiones (verificadas el 2026-09-06 contra el registro npm y Context7):** el documento original mencionaba "Next.js 14+". La versión estable actual es **Next.js 16.3.4** (requiere Node.js ≥ 20.9.0, LTS actual v24 "Krypton"). Todas las versiones listadas abajo fueron verificadas contra el registro npm.

---

### 0. Versiones Verificadas del Stack (2026-09-06)

| Paquete | Versión | Notas |
|---|---|---|
| **Next.js** | **16.3.4** | App Router estable. Requiere Node ≥ 20.9.0, React 19.2, TypeScript ≥ 5.1. `middleware.ts` renombrado a `proxy.ts` |
| **React** | **19.2.8** | View Transitions, `useEffectEvent`, Activity, React Compiler estable |
| **Hono** | **4.13.7** | Web Standards, compatible con Vercel Edge/Serverless |
| **Node.js (LTS)** | **v24 "Krypton"** (24.20.0) | Next 16 requiere ≥ 20.9.0; usar 24 LTS en Vercel y local |
| **TypeScript** | **5.9.3** | Next 16 requiere ≥ 5.1. NO usar TS 7.x (compilador Go, aún no soportado por Next) |
| **Tailwind CSS** | **4.3.3** | CSS-first, sin `tailwind.config.js` obligatorio |
| **shadcn/ui** | **3.5.0** (CLI `shadcn@latest`) | Componentes copiables sobre Radix UI + Tailwind 4. Compatible con Next.js 16. Init no interactivo: `npx shadcn@latest init -y -d` |
| **@supabase/supabase-js** | **2.115.0** | Cliente JS oficial |
| **@supabase/ssr** | **0.12.6** | Sesiones server-side para Next.js App Router |
| **Zod** | **4.5.4** | Validación de esquemas (API y Server Actions) |
| **Vitest** | **5.0.0** | Testing unitario/integración |
| **Turborepo** | **2.10.12** | Cache de builds del monorepo |
| **@vercel/node** | **12.0.1** | Runtime para funciones serverless de la API |
| **ESLint** | **10.10.0** | Con `eslint-config-next` |

**Cambios breaking de Next.js 16 que afectan este plan:**
- `middleware.ts` → **`proxy.ts`** (renombrado en v16; la función exportada pasa a llamarse `proxy`).
- `experimental.ppr` → reemplazado por la config `cacheComponents`.
- `cacheLife` / `cacheTag` pasan a estables.
- Node 18 ya no es soportado (mínimo 20.9.0).

---

### 1. Visión General y Justificación de la Arquitectura

- **Monorepo**: Un único repositorio que contiene todo el código, simplificando la gestión de versiones y dependencias.
- **Separación en `/frontend`, `/api`, `/backend`**:
  - **`/frontend`**: Aplicación Next.js 16 (App Router) que maneja las vistas, SSR, ISR y lógica de cliente.
  - **`/api`**: API RESTful construida con **Hono 4** (ligero, rápido y compatible con Vercel Edge/Serverless). Esta capa será la única que interactúa con la base de datos y servicios externos.
  - **`/backend`** (o `/core`): Contendrá la lógica de negocio, servicios, utilidades y modelos compartidos, independientes del framework. *Esta capa es consumida por la API y, opcionalmente, por el frontend a través de server actions o llamadas internas.*
- **Next.js como "orquestador"**:
  - Usaremos **Server Components** y **Server Actions** para operaciones que requieran interacción directa con la API (o con la capa de negocio), manteniendo la seguridad y el rendimiento.
  - El frontend se comunica con la API mediante `fetch` (desde client components) o mediante llamadas a server actions que internamente consumen la API o los servicios.
- **Base de datos en Supabase (PostgreSQL 17)** configurada en la región `sa-east-1` (São Paulo, Brasil) para baja latencia.
- **Autenticación y RBAC en Supabase Auth**: usuarios, empresas, roles y permisos se modelan sobre el esquema actual de `mrp_auth` (ver sección 6).
- **UI con shadcn/ui + Tailwind CSS 4**: componentes accesibles (Radix UI) copiados al repo, sin dependencia de runtime, acelerando el desarrollo de las 74 vistas PHP a migrar.
- **Dominio `mimrp.com.ar`** gestionado por Cloudflare (DNS y CDN), apuntando a Vercel.

**Estado de credenciales (verificado 2026-09-06):**
- **Vercel**: CLI autenticado como `gigliottim` (Vercel CLI 58.10.0). Token disponible en el entorno.
- **Supabase**: `SUPABASE_ACCESS_TOKEN` disponible. Organización `vkwknhfwuqtxswllgieg`. **Costo confirmado: USD 10/mes** por el nuevo proyecto (plan Pro, región `sa-east-1`). Confirmación ID: `BGoZHqqJd2JYMt+cWSDFH7qDeNkZZAwbTytJrHy7r+E=`.
- **Proyecto Supabase MRP**: aún no creado (existen 7 proyectos en la org, ninguno para MRP).

**¿Por qué esta estructura?**
- **Desacoplamiento**: La lógica de negocio reside en `/backend`, la API en `/api` y la presentación en `/frontend`. Cambiar de framework de frontend (ej. a Svelte) solo requeriría reescribir `/frontend`; el resto permanece intacto.
- **Mantenibilidad**: Una sola persona puede trabajar en capas separadas sin riesgo de colisiones. Cada carpeta tiene responsabilidades bien definidas.
- **Escalabilidad**: La API (Hono) puede desplegarse como funciones serverless en Vercel, escalando automáticamente. Next.js maneja el frontend con SSR/ISR.
- **Facilidad de pruebas**: Cada capa se puede testear de forma aislada.

---

### 2. Estructura de Carpetas Detallada (Monorepo)

```
/
├── .vercel/                 # Configuración local de Vercel (ignorar)
├── .vscode/                 # Recomendaciones para editor
├── mrp-php/                 # Código PHP original (solo referencia, se migrará progresivamente)
│   └── ... (todos los archivos PHP)
├── packages/                # (Opcional) Si se usan workspaces, pero aquí usamos carpetas simples
├── frontend/                # Aplicación Next.js 16 (dominio principal)
│   ├── app/                 # App Router de Next.js
│   │   ├── (auth)/          # Rutas agrupadas para autenticación (login, registro)
│   │   ├── (dashboard)/     # Rutas principales del MRP (layout con sidebar, etc.)
│   │   │   ├── page.tsx     # Dashboard principal
│   │   │   ├── inventory/   # Módulo de inventario
│   │   │   ├── production/  # Módulo de producción
│   │   │   ├── sales/       # Módulo de ventas
│   │   │   └── ...
│   │   ├── api/             # **SOLO para endpoints de Next.js (ej. webhooks, revalidate)**
│   │   ├── layout.tsx       # Layout raíz
│   │   ├── globals.css      # Estilos globales (Tailwind 4)
│   │   └── providers.tsx    # Proveedores de contexto (Theme, Auth, etc.)
│   ├── components/          # Componentes reutilizables (UI, formularios, tablas)
│   │   ├── ui/              # Componentes shadcn/ui (button, input, table, dialog, form, etc.)
│   │   ├── modules/         # Componentes específicos por módulo (ej. InventoryTable)
│   │   └── shared/          # Componentes compartidos (layout, headers, footers)
│   ├── lib/                 # Utilidades y configuraciones del frontend
│   │   ├── supabase/        # Clientes de Supabase (server.ts, client.ts, proxy.ts)
│   │   ├── api-client.ts    # Cliente HTTP para consumir la API (fetch con tipado)
│   │   ├── hooks/           # Custom hooks (useAuth, useFetch, etc.)
│   │   ├── utils/           # Funciones auxiliares (formateo, validación, cn())
│   │   └── types/           # Tipos TypeScript compartidos (exportados desde backend)
│   ├── components.json      # Config de shadcn/ui (aliases, Tailwind 4: "tailwind" vacío)
│   ├── proxy.ts             # (Next 16) Renombrado de middleware.ts — refresh de sesión Supabase
│   ├── public/              # Archivos estáticos (favicon, imágenes)
│   ├── next.config.ts       # Configuración de Next.js (cacheComponents, etc.)
│   ├── package.json
│   ├── tsconfig.json
│   └── .env.local           # Variables de entorno (no subir)
│
├── api/                     # API con Hono 4 (desplegada en Vercel como funciones)
│   ├── src/
│   │   ├── index.ts         # Punto de entrada de Hono (router principal)
│   │   ├── routes/          # Definición de rutas por recurso
│   │   │   ├── auth.ts      # /api/auth/*
│   │   │   ├── inventory.ts # /api/inventory/*
│   │   │   ├── production.ts
│   │   │   ├── sales.ts
│   │   │   └── ...
│   │   ├── middleware/      # Middlewares (autenticación, logging, CORS)
│   │   │   ├── auth.ts      # Verificación de JWT/Supabase Auth
│   │   │   └── error.ts     # Manejo global de errores
│   │   ├── lib/             # Utilidades de la API (conexión a Supabase, validaciones)
│   │   │   ├── supabase.ts  # Cliente de Supabase (admin/servidor)
│   │   │   ├── validation/  # Esquemas Zod 4 para validación de requests
│   │   │   └── types/       # Tipos específicos de la API (extienden los del backend)
│   │   └── services/        # (Opcional) Capa de servicios si no usas backend/
│   ├── package.json
│   ├── tsconfig.json
│   ├── vercel.json          # Configuración para Vercel (root del proyecto)
│   └── .env                 # Variables de entorno para la API
│
├── backend/                 # Lógica de negocio y acceso a datos (framework-agnóstico)
│   ├── src/
│   │   ├── core/            # Entidades, value objects, reglas de negocio
│   │   │   ├── entities/    # Clases/objetos de dominio (ej. Product, Order)
│   │   │   ├── repositories/ # Interfaces de repositorios (ej. IProductRepository)
│   │   │   └── services/    # Servicios de dominio (ej. InventoryService)
│   │   ├── infrastructure/  # Implementaciones concretas (Supabase, Redis, etc.)
│   │   │   ├── db/          # Clientes de base de datos (Supabase, Prisma, etc.)
│   │   │   ├── repositories/ # Implementaciones de repositorios usando Supabase
│   │   │   └── external/    # Integraciones con APIs externas (ej. facturación)
│   │   ├── use-cases/       # Casos de uso (orquestan servicios y repositorios)
│   │   │   ├── create-product.ts
│   │   │   ├── update-inventory.ts
│   │   │   └── ...
│   │   ├── shared/          # Utilidades comunes (date, string, logging)
│   │   │   ├── logger.ts
│   │   │   ├── errors.ts    # Clases de error personalizadas
│   │   │   └── validators.ts
│   │   └── index.ts         # Exportaciones públicas para API y frontend (opcional)
│   ├── package.json
│   ├── tsconfig.json
│   └── .env (solo para testing)
│
├── docker-compose.yml       # (Opcional) Para entorno local con Supabase local
├── package.json             # (Raíz) Para scripts de monorepo (npm workspaces)
├── turbo.json               # Turborepo 2.10 para cache de builds
├── README.md
└── .gitignore
```

**Justificación de la separación**:
- **`frontend/`**: Solo se preocupa por la UI y la interacción con el usuario. No contiene lógica de negocio ni acceso a BD. Todo se obtiene vía API o Server Actions.
- **`api/`**: Capa de presentación de la API. Maneja HTTP, autenticación, validación de entrada y llama a los casos de uso de `backend/`. Es delgada.
- **`backend/`**: Contiene todo el dominio y reglas de negocio. Puede ser consumido tanto por la API como por scripts, workers, etc. Es framework-agnóstico.

---

### 3. Plan de Migración por Fases

Para minimizar riesgos y permitir entregas incrementales, seguiremos este cronograma:

#### Fase 0: Preparación del entorno (1 semana)
- [x] Configurar repositorio con la estructura de carpetas propuesta.
- [x] Configurar proyectos en Vercel y Supabase (región `sa-east-1`, São Paulo).
- [ ] Configurar dominio `mimrp.com.ar` en Cloudflare (apuntando a Vercel). *(pendiente: requiere acceso a Cloudflare — se hará en P5)*
- [x] Migrar la base de datos (esquema y datos) a Supabase usando los dumps de `database/backups/2026-09-06_09-40-12/` (ver sección 6).
- [ ] Establecer pipeline de CI/CD (Vercel despliega automáticamente desde main). *(pendiente: se hará en P2)*

**Estado P1 (2026-09-06):** Proyecto Supabase `ooyiahzawilmdfualggx` creado (sa-east-1, USD 10/mes). Esquema auth (9 tablas) + tenant (26 tablas) migrados con RLS por `company_id`. Custom Access Token Hook activo (claims `company_id` + `user_role`). 3 usuarios migrados con password temporal `Temporal123!` (deben cambiarla en el primer login). Trigger `fn_super_admin_auto_link` corregido (rol "Super Administrador") y verificado. Monorepo scaffolded (frontend Next.js 16.3.4 + shadcn/ui, api Hono 4.13.7, backend). Login funcional verificado (JWT claims OK, aislamiento RLS OK). Pendiente: P2 (API Hono).

#### Fase 1: Migración de modelos y lógica de negocio (2-3 semanas)
- Analizar el código PHP existente y extraer las entidades principales (Partes, Órdenes de Producción, BOM, Inventario, etc.).
- Implementar en `/backend/src/core/entities` y `/backend/src/core/services`.
- Implementar repositorios abstractos y su implementación con Supabase (usando `@supabase/supabase-js` 2.115).
- Escribir casos de uso básicos (CRUD).
- **No se toca aún el frontend**, solo se valida con pruebas unitarias e integración (Vitest 5).

#### Fase 2: API REST con Hono 4 (1-2 semanas)
- [x] Desarrollar la API en `/api` exponiendo endpoints para cada recurso.
- [x] Implementar middlewares: autenticación (JWT de Supabase), CORS, manejo de errores.
- [x] Conectar los casos de uso de `/backend` en los handlers de Hono.
- [x] Probar la API con herramientas como Postman o tests automatizados (Vitest 5, 35 tests de integración contra Supabase real).
- [x] Configurar `vercel.json` para que las rutas `/api/*` sean manejadas por Hono.

**Estado P2 (2026-09-06):** API Hono 4.13.7 desplegada en Vercel como proyecto **`mrp-api`** (alias `https://api.mimrp.com.ar`, ya propagado). CRUD de módulos maestros (unidades de medida, tipos de partes, grupos de partes, tipos de depósitos, validaciones, entidades, almacenes, configuración) con auth JWT + claims `company_id`/`user_role`. Provisioning de empresas (alta/baja) con `seed_company()` (6 depósitos, 2 almacenes, 15 validaciones, 29 unidades — datos reales de los wizard scripts). Fixes multi-tenant: constraints UNIQUE por `company_id` (migración 14), RLS `user_company` (migraciones 13+15).

**Estado P3 (2026-09-06):** API transaccional desplegada en Vercel (`mrp-api`, v0.3.0, 61 tests). Endpoints: partes (con filtro `q`), variantes (con stock y FK validation), BOM atómico (`crear_bom` RPC), centros de trabajo, rutas de producción, órdenes de producción (máquina de estados completa + `next_numero_orden`), movimientos de inventario (stock atómico vía `movimiento_inventario` RPC), compras con recepción (`recibir_compra` RPC), planificación de recursos (validación de solapamiento). Fixes: trigger `set_timestamp_ordenes` roto en el dump original (migración 18), `usuario_id` bigint legacy (migración 20), Zod 4 `.regex()`/`.record()`. **Dominios**: `mimrp.com.ar` (frontend, propagando), `api.mimrp.com.ar` (API, ✅ funcionando), `chauexcel.com.ar` y `mrpsimple.ar` (redirect 308 → `mimrp.com.ar`, propagados). Pendiente: P4/P5 (frontend).

#### Fase 3: Frontend Next.js 16 (3-4 semanas)
- [x] Crear la estructura de páginas y layouts en `/frontend` con **shadcn/ui** (`npx shadcn@latest init -y -d` + `npx shadcn@latest add` por componente: table, dialog, form, select, etc.).
- [x] Implementar la autenticación (usando `@supabase/ssr` 0.12 con `proxy.ts` para manejo de sesiones en Next.js 16).
- [x] Consumir la API desde el frontend (usando `fetch` en Server Components y Client Components).
- [x] Migrar las vistas PHP una por una, reutilizando componentes shadcn/ui (tablas, formularios, modales):
  1. [x] Módulo de inventario (listado, creación, edición).
  2. [ ] Módulo de producción. *(pendiente: P5)*
  3. [ ] Módulo de ventas. *(pendiente: P5)*
  4. [ ] Dashboard y reportes. *(dashboard con KPIs OK; reportes en P5)*
- [x] Mantener el diseño actual (dark/light mode con `next-themes` + shadcn/ui).
- Durante esta fase, el sistema PHP sigue en producción hasta que se complete la migración.

**Estado P4 (2026-09-06):** Frontend desplegado en Vercel (`mrp-frontend`, alias `https://mimrp.com.ar` — DNS propagado). Layout dashboard con sidebar dinámico (menú por ACL vía `/api/v1/menu`), navbar con dark/light + logout, KPIs en `/`. 8 módulos CRUD: unidades de medida, tipos de partes, grupos de partes, tipos de depósitos, validaciones de movimientos, entidades, almacenes, configuración general (patrón DataTable + CrudPage + react-hook-form/zod). Fixes: shadcn/ui 3.5 usa Base UI (`render` en vez de `asChild`), separación server/client de sesión (`lib/session.ts` + `lib/use-session.ts`), deps explícitas en `frontend/package.json`. **Dominios verificados**: `mimrp.com.ar` → frontend (200), `api.mimrp.com.ar` → API (unidades: 29, menu: 33 items), `chauexcel.com.ar`/`mrpsimple.ar` → redirect 307 a `mimrp.com.ar`.

**Estado P5 (2026-09-06):** Frontend transaccional completado (18 páginas dinámicas). Módulos nuevos: partes (con búsqueda), gestor de variantes (con stock y punto de pedido), BOM (form con componentes dinámicos), órdenes de producción (máquina de estados con botones de transición), compras (con recepción automática de stock), movimientos de inventario (registro con actualización atómica), centros de trabajo, rutas de producción, planificación de recursos (validación de solapamiento). **Repo GitHub**: `https://github.com/gigliottim/mrp-vercel` (rama `main`, CI/CD via Vercel). **Verificado E2E**: 18/18 rutas HTTP 200 en producción + dialog de formulario renderizando. **Bugfix crítico aplicado** (`9ddda64`): 7 páginas daban 500 por pasar funciones anónimas inline de Server Components a Client Components — resuelto con `formExtraProps` serializable en `CrudPage`, referencias directas a forms, `noop` en `actions.ts` (`'use server'`) y precomputación de nombres en server para columnas. Pendiente: módulos de reportes, empresa/usuarios/roles/permisos y planeamiento (sugerencias MRP) — y cambiar passwords temporales de los 3 usuarios.

#### Fase 4: Pruebas, optimización y corte (1-2 semanas)
- Pruebas end-to-end (Cypress/Playwright) para validar flujos críticos.
- Configurar ISR/SSG donde sea posible para mejorar rendimiento (con `cacheLife`/`cacheTag` estables en Next 16).
- Migrar datos finales, sincronizar (si hay cambios en producción).
- Cambiar el DNS de Cloudflare para apuntar a Vercel (corte gradual con weighted routing si es necesario).
- Desactivar el sistema PHP.

---

### 4. Configuración de Vercel y Supabase en Brasil

- **Supabase**:
  - Crear proyecto en la región `South America (São Paulo)` → `sa-east-1`.
  - Obtener las URL y claves (anon/publishable, service_role).
  - Configurar políticas RLS (Row Level Security) según el modelo de permisos del MRP (ver sección 6).
- **Vercel**:
  - Crear proyecto y asociar el repositorio.
  - En `Settings > General` → `Region` seleccionar `South America (São Paulo)` para reducir latencia.
  - Configurar variables de entorno: `SUPABASE_URL`, `SUPABASE_ANON_KEY` (o `NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY`), `SUPABASE_SERVICE_ROLE_KEY`, `JWT_SECRET`, etc.
  - Usar el plan Pro para disfrutar de funciones serverless sin limitaciones de tiempo.

**Monorepo en Vercel (corrección al formato legacy):** el `vercel.json` con `builds`/`routes` del documento original está **obsoleto** — Vercel ya no usa ese formato para monorepos. La forma actual es:

1. **Un proyecto Vercel por subproyecto** (`frontend` y `api`), cada uno con su propio `vercel.json` indicando su raíz:
   ```json
   // frontend/vercel.json
   { "framework": "nextjs" }
   ```
   ```json
   // api/vercel.json
   { "framework": "other" }
   ```
2. En el dashboard de Vercel, al conectar el repo, se configura `Root Directory` = `frontend` (o `api`) por proyecto.
3. El enrutado entre frontend y API se resuelve con **rewrites en `next.config.ts`** del frontend:
   ```ts
   // frontend/next.config.ts
   const nextConfig = {
     async rewrites() {
       return [
         { source: '/api/:path*', destination: 'https://api.mimrp.com.ar/api/:path*' }
       ]
     }
   }
   export default nextConfig
   ```
   (o, si la API se despliega en el mismo dominio, apuntar al deployment de la API).

---

### 5. Recomendaciones para Mantener la Modularidad y Evitar Roturas

- **Tipado fuerte**: Usar TypeScript 5.9 en todo el monorepo. Definir interfaces y tipos compartidos en `backend/src/shared/types` que pueden ser importados por `api` y `frontend` (mediante `paths` en tsconfig o publicando un paquete interno).
- **Inversión de dependencias**: La capa `backend` no debe depender de `api` ni de `frontend`. La API y el frontend dependen de `backend`. Usar inyección de dependencias para facilitar pruebas y cambios.
- **Pruebas automatizadas**: Escribir pruebas unitarias para casos de uso y servicios, pruebas de integración para la API (usando Vitest 5), y pruebas e2e para el frontend. Esto da confianza al hacer cambios.
- **Manejo de errores consistente**: Definir clases de error personalizadas en `backend` y mapearlas a códigos HTTP en la API.
- **Variables de entorno**: Separar por entorno (development, preview, production) y no hardcodear valores.
- **Documentación**: Mantener un `README.md` con instrucciones claras para levantar el entorno, migrar datos, y desplegar.
- **Uso de Server Actions en Next.js**: Para operaciones que requieran interacción directa con la base de datos sin pasar por la API (ej. revalidación), pero **siempre** delegando en los casos de uso de `backend` (así se mantiene la lógica centralizada). Esto ofrece una capa extra de seguridad y reduce el número de requests.
- **Versionado semántico de la API**: Aunque no sea pública, mantener versiones (ej. `/api/v1/...`) permite cambios graduales.
- **Migración gradual**: Mantener el código PHP en `./mrp-php` como referencia. Durante la migración, si hay funcionalidades críticas que no se han migrado, se pueden llamar desde la API mediante un adaptador (por ejemplo, ejecutando scripts PHP desde Node, pero no recomendado). Mejor es migrar módulo por módulo y desactivar en PHP las partes ya migradas.

#### 5.1 shadcn/ui como base de UI (decisión tomada)

**Sí conviene usar shadcn/ui** para el frontend. Justificación verificada en Context7:

- **Compatible con Next.js 16 + Tailwind 4**: el CLI oficial (`npx shadcn@latest init -y -d`) soporta el template Next.js con preset `base-nova` y Tailwind v4 (en `components.json` la propiedad `tailwind` queda vacía).
- **Código copiado, no dependencia**: los componentes se copian al repo (`components/ui/`), sin runtime adicional — se pueden customizar libremente (crítico para replicar el diseño actual del MRP con dark/light mode).
- **Accesibilidad gratis**: construidos sobre Radix UI (dialog, select, dropdown, tabs, etc.).
- **Acelera la migración de las 74 vistas PHP**: tablas, formularios, modales y selects ya resueltos; solo queda mapear datos.
- **Sin lock-in**: al ser código propio, se puede reemplazar cualquier componente sin migración.

**Componentes a instalar en Fase 3** (vía `npx shadcn@latest add`): `button`, `input`, `label`, `table`, `dialog`, `select`, `form` (react-hook-form + zod), `dropdown-menu`, `tabs`, `badge`, `card`, `alert-dialog`, `toast`/`sonner`, `sheet`, `tooltip`, `skeleton`, `pagination`, `checkbox`, `switch`, `calendar` (para fechas), `chart` (para reportes con Chart.js o recharts).

---

### 6. Modelo de Datos: Migración de `mrp_auth` + Tenant a Supabase

> **Fuente de verdad**: dumps de `database/backups/2026-09-06_09-40-12/` (`mrp_auth_schema_*.sql`, `mrp_auth_full_*.sql`, `mrp_tunna_schema_*.sql`). PostgreSQL 18.3 origen → Supabase PostgreSQL 17.

#### 6.1 Arquitectura actual (PHP) vs. destino (Supabase)

| Concepto actual (PHP) | Destino (Supabase) |
|---|---|
| BD compartida `mrp_auth` (usuarios, empresas, roles, permisos, menú) | Esquema `public` de Supabase + **Supabase Auth** (`auth.users`) |
| BD por tenant (`mrp_tunna`, `mrp_<slug>`) con conexión dinámica vía `TenantContext` | **Un solo esquema** con columna `company_id` en cada tabla + **RLS** (Row Level Security) |
| Sesiones PHP + `AuthManager` | **Supabase Auth** (JWT) + `@supabase/ssr` |
| `BaseTenantModel` resolviendo conexión por empresa | RLS con `company_id` desde JWT claims (ver 6.3) |
| `company_databases` (host/port/db/user/password por empresa) | **Eliminada** — ya no hay conexiones dinámicas; RLS aísla por `company_id` |

#### 6.2 Mapeo de tablas de `mrp_auth` → Supabase

| Tabla actual | Tabla destino | Notas |
|---|---|---|
| `users` (id bigint, name, email, password, 2FA, default_company_id) | **`auth.users`** (Supabase) + tabla `public.profiles` | `email` → `auth.users.email`; `name` → `profiles.full_name`; `default_company_id` → `profiles.default_company_id`; password/2FA → gestionados por Supabase Auth |
| `companies` (id, name, slug, tax_id, contact_email, status) | `public.companies` | Igual, con `id uuid` (o mantener bigint con `identity`) |
| `company_databases` | **Eliminada** | RLS reemplaza la conexión dinámica por tenant |
| `roles` (id, name, guard_name) | `public.roles` | Datos actuales: `Super Administrador`, `Administrador`, `Supervisor`, `Usuario` |
| `permissions` (id, name, module) | `public.permissions` | Formato `module.action` (ej. `systems.manage`, `production.orders`) |
| `role_has_permissions` (role_id, permission_id) | `public.role_has_permissions` | Igual |
| `user_company` (user_id, company_id, role_id) | `public.user_company` | **Clave del multi-tenant**: rol por empresa. `user_id` → `uuid` de `auth.users` |
| `user_has_roles` (user_id, role_id) | `public.user_has_roles` | Roles globales (ej. super_admin) |
| `menu_items` (code, label, route, icon, section_key, parent_id, sort_order) | `public.menu_items` | Igual |
| `menu_acl` (company_id, menu_item_id, subject_type, subject_id, scope, permission_level, effect) | `public.menu_acl` | ACL por empresa: `subject_type` = `role`/`user`, `effect` = `allow`/`deny`, `permission_level` = `read`/`write` |
| `audit_logs` | `public.audit_logs` | Igual, `user_id` → uuid |
| `personal_access_tokens` | **Eliminada** | Supabase Auth emite sus propios JWT |
| `schema_migrations` | Migraciones Supabase (`supabase/migrations/`) | Reemplazada por el CLI de Supabase |
| `fn_super_admin_auto_link` (trigger) | Trigger equivalente en Supabase | Al crear una empresa, vincula automáticamente al super admin (`martin@unik.ar`) con rol `super_admin` |

#### 6.3 Multi-tenant con RLS (reemplaza `TenantContext` + `BaseTenantModel`)

El patrón recomendado por Supabase para multi-tenant (verificado en Context7) es **JWT claims + RLS**:

1. **Custom Access Token Hook** (PL/pgSQL) que inyecta `company_id` y `role` en el JWT al hacer login:
   ```sql
   create or replace function public.custom_access_token_hook(event jsonb)
   returns jsonb language plpgsql stable as $$
   declare
     claims jsonb;
     v_company_id bigint;
     v_role_name text;
   begin
     select uc.company_id, r.name
       into v_company_id, v_role_name
       from public.user_company uc
       join public.roles r on r.id = uc.role_id
      where uc.user_id = (event->>'user_id')::uuid
      limit 1;

     claims := event->'claims';
     if v_company_id is not null then
       claims := jsonb_set(claims, '{company_id}', to_jsonb(v_company_id));
       claims := jsonb_set(claims, '{role}', to_jsonb(v_role_name));
     end if;
     event := jsonb_set(event, '{claims}', claims);
     return event;
   end;
   $$;
   ```
   (Configurar en Supabase Dashboard → Auth → Hooks → Custom Access Token.)

2. **Políticas RLS por empresa** en cada tabla de negocio (las tablas del tenant `mrp_tunna`: `partes`, `bom_cabecera`, `bom_detalle`, `ordenes_produccion`, `rutas_produccion`, `centros_trabajo`, `planificacion_recursos`, `compras`, `movimientos_stock`, `movimientos_inventario`, `unidades_medida`, `variantes`, etc.):
   ```sql
   alter table public.partes enable row level security;

   create policy "partes_tenant_select" on public.partes
     for select to authenticated
     using (company_id = (auth.jwt() ->> 'company_id')::bigint);

   create policy "partes_tenant_insert" on public.partes
     for insert to authenticated
     with check (company_id = (auth.jwt() ->> 'company_id')::bigint);
   ```
   > **Nota**: cada tabla del tenant actual debe recibir una columna `company_id bigint not null` (con índice) durante la migración.

3. **RBAC por rol**: políticas adicionales que consultan `(auth.jwt() ->> 'role')` para operaciones de escritura (ej. solo `Administrador`/`Supervisor` insertan órdenes de producción).

4. **Menú dinámico**: `menu_items` + `menu_acl` se consultan con RLS por `company_id`; el sidebar se renderiza en Server Components con el menú filtrado por los claims del JWT.

#### 6.4 Migración de datos (Fase 0)

```bash
# 1. Crear proyecto Supabase (región sa-east-1) y aplicar el esquema migrado
supabase init
supabase link --project-ref <ref>
supabase db push

# 2. Importar datos desde los dumps (adaptando tipos: bigint → uuid en users)
psql "$SUPABASE_DB_URL" -f database/backups/2026-09-06_09-40-12/mrp_auth_full_2026-09-06_09-40-12.sql

# 3. Crear usuarios en Supabase Auth (password hashing distinto: bcrypt de Supabase)
#    → script de migración que inserta en auth.users y luego en public.profiles

# 4. Importar tablas del tenant (mrp_tunna) agregando company_id
psql "$SUPABASE_DB_URL" -f database/backups/2026-09-06_09-40-12/mrp_tunna_full_2026-09-06_09-40-12.sql
```

**Consideraciones críticas:**
- Los `id` de `users` (bigint) deben mapearse a los `uuid` de `auth.users`; `user_company.user_id` y `audit_logs.user_id` deben actualizarse en consecuencia.
- `password` (hash PHP) **no es migrable** a Supabase Auth: los usuarios deberán hacer reset de contraseña, o se migra con el hook de migración de Supabase (importar usuarios con `supabase_auth_admin`).
- El trigger `fn_super_admin_auto_link` se recrea tal cual (con `ON CONFLICT DO NOTHING`).

#### 6.5 Ciclo de vida de empresas (alta / baja) — reemplazo de `TenantProvisioningService`

> **Contexto**: en PHP, el alta de empresa creaba una **BD física** (`CREATE DATABASE mrp_<slug>`), aplicaba 4 scripts wizard (`00_mrp_wizard.sql`, `01_mrp_depositos.sql`, `02_mrp_validaciones.sql`, `03_mrp_um.sql`) y registraba la conexión en `company_databases`. En Supabase **no se crean bases de datos por empresa** (no es posible ni deseable): el multi-tenant se resuelve con `company_id` en cada tabla + RLS (ya implementado en P1). El alta pasa de "crear BD + 4 scripts + 7 registros" a "1 fila + 1 usuario Auth + 3 INSERTs de seed", todo en una transacción.

**Alta de empresa** (reemplaza `TenantProvisioningService::provision()`):

```ts
// Server Action / API route (solo service_role, nunca anon)
// 1. Crear la empresa (el trigger fn_super_admin_auto_link vincula a Martin automáticamente)
const { data: company, error: e1 } = await supabase
  .from('companies')
  .insert({ name, slug, tax_id, contact_email, status: 'active' })
  .select()
  .single()

// 2. Crear el usuario admin en Supabase Auth
const { data: user, error: e2 } = await supabase.auth.admin.createUser({
  email,
  password,
  email_confirm: true,
  user_metadata: { name: fullName },
})

// 3. Vincular admin a la empresa con rol Administrador
await supabase.from('user_company').insert({
  user_id: user.id,
  company_id: company.id,
  role_id: 2, // Administrador
})

// 4. Sembrar datos base de la empresa (reemplaza los 4 scripts wizard)
//    → función SQL idempotente seed_company(company_id) o INSERTs agrupados:
await supabase.from('unidades_medida').insert([...])        // 29 unidades
await supabase.from('tipos_depositos').insert([...])        // 7 tipos
await supabase.from('tipos_depositos_movimientos').insert([...]) // 18 validaciones
```

**Baja de empresa** (reemplaza `deleteCompany()`):

```ts
// 1. Borrar datos de negocio (service_role ignora RLS)
await supabase.from('partes').delete().eq('company_id', id)
// ... todas las tablas tenant (26)

// 2. Borrar ACL, vínculos y empresa
await supabase.from('menu_acl').delete().eq('company_id', id)
await supabase.from('user_company').delete().eq('company_id', id)
await supabase.from('companies').delete().eq('id', id)

// 3. (Opcional) Banear usuarios exclusivos de la empresa
await supabase.auth.admin.updateUserById(uid, { ban_duration: '876000h' })
```

**Ventajas sobre el diseño PHP** (verificado contra `TenantProvisioningService.php`):

| Aspecto | PHP (mrp_auth + BD por empresa) | Supabase (una BD + RLS) |
|---|---|---|
| Alta de empresa | `CREATE DATABASE` + 4 scripts + 7 INSERTs en mrp_auth | 1 INSERT + 1 usuario Auth + 3 INSERTs de seed |
| Migraciones | Por BD física (N veces) | **Una sola migración** para todas las empresas |
| Aislamiento | Físico (BD separada) | Lógico (RLS por `company_id` en JWT) |
| Conexiones | Pool N+1, imposible en serverless | **1 pool** (Supabase Pooler) para todas |
| Seguridad | Password de BD en `company_databases` | JWT firmado, sin credenciales de BD en la app |
| Escala | Límite de conexiones por BD | Serverless-friendly (Vercel) |
| Backup | Por BD | Un solo backup del proyecto |

**Trade-offs honestos:**
- **Aislamiento lógico vs físico**: un bug en una política RLS podría exponer datos entre empresas. Mitigación: políticas probadas (verificadas en P1), `service_role` solo en server, auditoría.
- **Seed de datos base**: los 4 scripts wizard se convierten en **una función SQL idempotente** `seed_company(company_id)` que se ejecuta una vez por alta (no por deploy).
- **Límite de Supabase**: no hay `CREATE DATABASE` desde la app — no se necesita: el aislamiento por fila es el patrón oficial de Supabase para multi-tenant (verificado en Context7).

---

### 7. Autenticación con Supabase en Next.js 16

Usar `@supabase/ssr` 0.12 con el patrón oficial (verificado en Context7):

1. **`frontend/lib/supabase/server.ts`** — cliente para Server Components:
   ```ts
   import { createServerClient } from '@supabase/ssr'
   import { cookies } from 'next/headers'

   export async function createClient() {
     const cookieStore = await cookies()
     return createServerClient(
       process.env.NEXT_PUBLIC_SUPABASE_URL!,
       process.env.NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY!,
       {
         cookies: {
           getAll() { return cookieStore.getAll() },
           setAll(cookiesToSet) {
             try {
               cookiesToSet.forEach(({ name, value, options }) =>
                 cookieStore.set(name, value, options))
             } catch { /* ignorar: llamado desde Server Component */ }
           },
         },
       }
     )
   }
   ```

2. **`frontend/proxy.ts`** — (Next 16: `middleware.ts` fue renombrado a `proxy.ts`) refresh de sesión:
   ```ts
   import { createServerClient } from '@supabase/ssr'
   import { NextResponse, type NextRequest } from 'next/server'

   export async function proxy(request: NextRequest) {
     let supabaseResponse = NextResponse.next({ request })
     const supabase = createServerClient(
       process.env.NEXT_PUBLIC_SUPABASE_URL!,
       process.env.NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY!,
       {
         cookies: {
           getAll() { return request.cookies.getAll() },
           setAll(cookiesToSet, headers) {
             cookiesToSet.forEach(({ name, value }) => request.cookies.set(name, value))
             supabaseResponse = NextResponse.next({ request })
             cookiesToSet.forEach(({ name, value, options }) =>
               supabaseResponse.cookies.set(name, value, options))
             Object.entries(headers).forEach(([key, value]) =>
               supabaseResponse.headers.set(key, value))
           },
         },
       }
     )
     const { data: { user } } = await supabase.auth.getUser()
     if (!user && !request.nextUrl.pathname.startsWith('/login')) {
       const url = request.nextUrl.clone()
       url.pathname = '/login'
       return NextResponse.redirect(url)
     }
     return supabaseResponse
   }

   export const config = {
     matcher: ['/((?!_next/static|_next/image|favicon.ico|.*\\.(?:svg|png|jpg|jpeg|gif|webp)$).*)']
   }
   ```

3. **Login**: `supabase.auth.signInWithPassword({ email, password })` — el Custom Access Token Hook inyecta `company_id` y `role` en el JWT automáticamente.

---

### 8. Consideraciones sobre el Dominio y Cloudflare

- **DNS**: En Cloudflare, crear un registro A (o CNAME) para `mimrp.com.ar` apuntando a los servidores de Vercel (`cname.vercel-dns.com`).
- **SSL**: Cloudflare ofrece certificados gratuitos, pero Vercel también. Se puede configurar el SSL en modo "Full (strict)" para mayor seguridad.
- **Caching**: Cloudflare puede cachear respuestas estáticas. Para API, desactivar cache o usar cabeceras apropiadas.
- **Rutas**: Configurar en Vercel que todas las rutas sean manejadas por Next.js, excepto `/api/*` que van a Hono (ver rewrites en sección 4).

---

### Conclusión

Esta arquitectura proporciona una base sólida, mantenible y escalable. La clara separación de responsabilidades permite que una sola persona pueda trabajar en diferentes áreas sin miedo a romper otras, y el uso de tecnologías modernas (Next.js 16, Hono 4, Supabase) asegura un rendimiento óptimo y facilidad de despliegue. La migración gradual desde PHP reduce el riesgo y permite entregar valor rápidamente.

**Próximos pasos inmediatos**:
1. Configurar repositorio con la estructura de carpetas.
2. Crear proyectos en Supabase y Vercel (región `sa-east-1`).
3. Migrar la base de datos desde `database/backups/2026-09-06_09-40-12/` (esquema + datos + usuarios a Supabase Auth).
4. Implementar el Custom Access Token Hook y las políticas RLS multi-tenant.

Si necesitas detalles adicionales sobre algún aspecto (ej. autenticación específica, migración de datos, configuración de Cloudflare), estaré encantado de ampliarlo.
