# Sidebar desde BD (`mrp_auth`) - Especificacion unica

## Objetivo
Migrar `views/partials/sidebar.php` para que deje de tener items hardcodeados y renderice el menu desde tablas en `mrp_auth`.

La meta es centralizar:

- estructura del menu
- permisos por rol
- excepciones por usuario
- alcance por empresa

Todo se resuelve al login y se cachea en sesion.

## Decisiones de arquitectura

1. Fuente unica de verdad del sidebar: `mrp_auth`.
2. `super_admin` y `admin_empresa` mantienen privilegios administrativos base.
3. Resto de usuarios se controla por ACL de menu (`read` / `write`).
4. `sidebar.php` solo renderiza datos ya resueltos por servicio de autorizacion.
5. Sin hardcode de items nuevos en vista.

## Modelo de datos

### 1) `menu_items`
Define el arbol canonico del sidebar.

Campos sugeridos:

- `id` BIGSERIAL PK
- `code` VARCHAR(120) UNIQUE NOT NULL
- `label` VARCHAR(150) NOT NULL
- `route` VARCHAR(255) NULL
- `icon` VARCHAR(120) NULL
- `section_key` VARCHAR(80) NOT NULL
- `section_label` VARCHAR(120) NOT NULL
- `parent_id` BIGINT NULL REFERENCES `menu_items(id)`
- `sort_order` INT NOT NULL DEFAULT 0
- `is_active` BOOLEAN NOT NULL DEFAULT TRUE
- `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

### 2) `menu_acl`
Define permisos sobre nodos del menu por rol o por usuario.

Campos sugeridos:

- `id` BIGSERIAL PK
- `company_id` BIGINT NOT NULL REFERENCES `companies(id)`
- `menu_item_id` BIGINT NOT NULL REFERENCES `menu_items(id)`
- `subject_type` VARCHAR(10) NOT NULL CHECK (`role` | `user`)
- `subject_id` BIGINT NOT NULL
- `scope` VARCHAR(10) NOT NULL CHECK (`item` | `branch`)
- `permission_level` VARCHAR(10) NOT NULL CHECK (`read` | `write`)
- `effect` VARCHAR(10) NOT NULL DEFAULT `allow` CHECK (`allow` | `deny`)
- `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP

Indice unico recomendado:

- `UNIQUE(company_id, menu_item_id, subject_type, subject_id, scope)`

## Estabilidad de IDs

Para evitar romper permisos al cambiar el menu:

1. `menu_items.id` es tecnico y nunca se recicla.
2. `menu_items.code` es funcional, unico e inmutable.
3. Altas/cambios por `UPSERT` usando `code`.
4. No borrar fisicamente nodos usados en ACL: usar `is_active = false`.
5. Si cambia el concepto funcional, crear nuevo `code` y migrar ACL explicitamente.
6. Prohibido `TRUNCATE` de `menu_items` en migraciones.

## Reglas de autorizacion

### Perfil base

- `super_admin`: acceso total global.
- `admin_empresa`: acceso total dentro de su empresa.
- otros roles: acceso por `menu_acl`.

### Niveles

- `read`: puede ver sidebar y endpoints de lectura.
- `write`: incluye `read` + operaciones de escritura.

### Scope

- `branch`: aplica al nodo y todos sus descendientes.
- `item`: aplica solo al nodo seleccionado.

### Precedencia

1. `super_admin` bypass.
2. `admin_empresa` bypass por empresa activa.
3. ACL por rol (base).
4. ACL por usuario (override).
5. Expandir `branch` a descendientes.
6. Resolver nivel final por nodo (`write` > `read`).
7. Si existe `deny`, `deny` de usuario tiene prioridad sobre cualquier `allow` heredado del rol.

Reglas adicionales para `deny`:

- `deny` con `scope=item` bloquea solo ese nodo.
- `deny` con `scope=branch` bloquea ese nodo y sus descendientes.
- Si hay `allow` y `deny` del mismo sujeto para el mismo nodo, prevalece `deny`.

## CRUD de permisos (UI requerida)

Interfaz tipo tabla con 3 columnas:

1. Arbol
2. Sujeto (Rol o Usuario)
3. Permiso (`read` | `write` | `deny`)

Comportamiento:

- si se asigna a Rol, heredan todos los usuarios de ese rol
- si se asigna a Usuario, aplica solo a ese usuario
- permite excepciones puntuales sin modificar todo el rol

Uso recomendado de `deny`:

- aplicar `deny` cuando un usuario nuevo hereda permisos amplios por rol pero todavia no debe acceder a un item o rama especifica
- esto permite mantener el rol general sin afectar a todos los usuarios del rol

Ejemplo:

- rol `Operador` tiene `write` en `produccion` (branch)
- usuario nuevo `Pedro` hereda ese rol
- se agrega `deny` para `Pedro` en `produccion.ordenes` (item) hasta finalizar su capacitacion

## Flujo en login

1. Usuario autentica y selecciona empresa activa.
2. Se cargan roles del usuario para esa empresa.
3. Se resuelve ACL de menu (rol + usuario).
4. Se calcula arbol permitido (incluyendo expansion `branch`).
5. Se guarda en sesion:
   - `auth.permissions`
   - `auth.sidebar_tree`
   - `auth.sidebar_version` (opcional)

## Integracion en `views/partials/sidebar.php`

`sidebar.php` no define menu; solo renderiza `auth.sidebar_tree`.

Contrato recomendado de datos en sesion:

- `section_label`
- `items[]`
- `id`
- `code`
- `label`
- `route`
- `icon`
- `children[]`
- `permission_level`

## Endpoints sugeridos

- `GET /roles-permisos/arbol`
- `GET /roles-permisos/acl?subject_type=role&subject_id={id}`
- `POST /roles-permisos/acl`
- `PUT /roles-permisos/acl/{id}`
- `DELETE /roles-permisos/acl/{id}`

## Seguridad obligatoria

1. `admin_empresa` no puede deshabilitarse a si mismo.
2. `admin_empresa` no puede eliminarse a si mismo.
3. Debe existir al menos un admin activo por empresa.
4. Usuarios no admin solo pueden cambiar su propia clave.
5. Solo `super_admin` administra todas las empresas y usuarios.

## Plan de implementacion

1. Crear migracion de `menu_items` y `menu_acl` en `mrp_auth`.
2. Cargar seed inicial del menu actual.
3. Implementar `MenuService` para resolver ACL y arbol final.
4. Guardar sidebar resuelto en sesion al login.
5. Refactorizar `views/partials/sidebar.php` para render de sesion.
6. Crear CRUD ACL (arbol + sujeto + permiso).
7. Agregar invalidacion de cache de sidebar al cambiar ACL/menu.
8. Probar permisos por rol y overrides por usuario.

## Criterios de aceptacion

- Sidebar se renderiza 100% desde `mrp_auth`.
- Agregar/reordenar items no requiere editar `sidebar.php`.
- Permisos por rol y por usuario funcionan por empresa.
- `branch` hereda a hijos; `item` no hereda a hermanos.
- `read` permite lectura; `write` habilita escritura.
- Se mantienen reglas de seguridad para admin/super admin.
- `menu_item_id` permanece estable ante cambios del arbol.
