# Credenciales de Usuario Demo

## Acceso Web

**URL de login:** http://mrp.local/login

### Credenciales

- **Tenant/Empresa:** `demo`
- **Email:** `demo@mrp.local`
- **Contraseña:** `demo123`

## Información de la Empresa

- **Nombre:** Empresa Demo
- **Slug:** demo
- **ID:** 2
- **Estado:** Activo

## Base de Datos del Tenant

- **Host:** 127.0.0.1
- **Puerto:** 3306
- **Base de datos:** mrp_tenant_demo
- **Usuario:** root
- **Contraseña:** (vacía)

## Usuario

- **ID:** 2
- **Nombre:** Usuario Demo
- **Email:** demo@mrp.local
- **Rol:** Administrator
- **Empresa predeterminada:** Empresa Demo

## Permisos del Usuario (10 total)

El usuario demo tiene rol de **Administrator** con todos los permisos:

- `systems.manage` - Gestión del sistema
- `systems.users` - Gestión de usuarios
- `systems.roles` - Gestión de roles
- `tenants.provision` - Aprovisionamiento de tenants
- `tenants.switch` - Cambio entre tenants
- `inventory.catalog.read` - Lectura de catálogo de inventario
- `inventory.catalog.write` - Escritura en catálogo de inventario
- `production.orders` - Gestión de órdenes de producción
- `reports.execution` - Ejecución de reportes
- `security.audit` - Auditoría de seguridad

## Scripts de Validación

### Test de Login
```bash
php tests/demo_login_test.php
```

### Actualizar Contraseña (si es necesario)
```bash
php tests/update_demo_password.php
```

## Archivos SQL Relacionados

- **Seed completo:** `database/seeds/demo_user_seed.sql`
- **Update de password:** `database/seeds/update_demo_password.sql`

## Notas

- La contraseña está hasheada usando `PASSWORD_BCRYPT`
- El usuario tiene acceso completo como administrador
- La base de datos del tenant (`mrp_tenant_demo`) debe ser creada por separado si se requiere funcionalidad específica del tenant
- El sistema soporta multi-tenancy: el mismo usuario puede tener acceso a múltiples empresas
