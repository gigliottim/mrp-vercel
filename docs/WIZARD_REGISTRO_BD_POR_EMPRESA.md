# Wizard de Registro con Creacion de Base de Datos por Empresa

## Objetivo
Permitir que cualquier usuario se registre desde `http://localhost/mrp/register` mediante un wizard y que, al finalizar el registro, se cree automaticamente una base de datos exclusiva para su empresa.

## Alcance Funcional
- Registro autoservicio para nuevos usuarios/empresas.
- Provision automatica de una base de datos por empresa.
- La base creada debe tener el esquema completo del sistema MRP.
- La base creada debe cargarse solo con datos iniciales de:
  - Depositos
  - Unidades de medida
- Convencion obligatoria de nombre de base:
  - `mrp_(nombre_de_empresa)`

## Regla de Naming de la Base de Datos
### Formato
`mrp_(nombre_de_empresa_normalizado)`

### Normalizacion recomendada
- Convertir a minusculas.
- Reemplazar espacios por guion bajo (`_`).
- Remover caracteres especiales/acentos.
- Permitir solo `[a-z0-9_]`.
- Colapsar guiones bajos duplicados.
- Limitar largo final para compatibilidad con PostgreSQL (maximo 63 caracteres por identificador).

### Ejemplos
- `Acme SA` -> `mrp_acme_sa`
- `Metalurgica Nunez` -> `mrp_metalurgica_nunez`
- `Mi Empresa 2026` -> `mrp_mi_empresa_2026`

## Flujo del Wizard (`/register`)
1. Paso 1: Datos de cuenta
- Nombre y apellido del admin.
- Email (unico).
- Password y confirmacion.

2. Paso 2: Datos de empresa
- Nombre legal/comercial de la empresa.
- CUIT/RUC u otro identificador (segun pais).
- Pais/region (opcional para defaults).

3. Paso 3: Confirmacion
- Mostrar nombre de BD a crear (`mrp_xxx`).
- Confirmar terminos y accion de creacion.

4. Paso 4: Provisionamiento
- Crear base de datos.
- Ejecutar esquema completo.
- Insertar seeds minimos: Depositos y Unidades de medida.
- Crear usuario administrador en esa base.
- Mostrar resultado final y redirigir a login.

## Reglas de Negocio
- Una empresa = una base de datos.
- No permitir colision de nombre de base.
- Si el nombre normalizado ya existe, agregar sufijo incremental:
  - `mrp_acme_sa_2`, `mrp_acme_sa_3`, etc.
- El proceso debe ser transaccional a nivel logico:
  - Si falla cualquier paso, dejar estado consistente y registrar error.
- No cargar datos de ejemplo adicionales.

## Requisitos Tecnicos Minimos
- Encoding/locale recomendados en PostgreSQL: `UTF8` y locale estandar del proyecto.
- Respetar el limite de PostgreSQL para nombres de base/identificadores (63 caracteres).
- La base nueva del wizard debe crearse usando scripts SQL del directorio `database\\wizard\\`.
- Registrar auditoria del alta de tenant/empresa en la BD central de control (si aplica).

## Fuente de la BD del Wizard (`database\\wizard\\`)
La estructura y datos iniciales de cada nueva empresa deben salir exclusivamente de estos archivos, en este orden:

1. `database\\wizard\\00_mrp_wizard.sql`
- Estructura base/general requerida para una instancia nueva.

2. `database\\wizard\\01_mrp_depositos.sql`
- Datos y configuracion inicial de Depositos.

3. `database\\wizard\\02_mrp_validaciones.sql`
- Validaciones de Depositos y reglas relacionadas con `http://localhost/mrp/configuracion/depositos-validaciones`.

4. `database\\wizard\\03_mrp_um.sql`
- Datos y configuracion de Unidades de medida.

Regla: no ejecutar seeds adicionales fuera de estos scripts para el alta inicial por wizard.

## Propuesta de Implementacion (Backend)
1. Endpoint `GET /register`
- Render del wizard.

2. Endpoint `POST /register`
- Validar datos de usuario y empresa.
- Generar nombre de DB normalizado con prefijo `mrp_`.
- Resolver colisiones de naming.
- Crear DB fisica.
- Ejecutar scripts SQL de `database\\wizard\\` en el orden `00 -> 01 -> 02 -> 03`.
- Crear admin inicial y asociar empresa.
- Responder exito/error.

3. Servicio sugerido
- `TenantProvisioningService` (o equivalente) con responsabilidades:
  - `buildDatabaseName(companyName)`
  - `createDatabase(dbName)`
  - `applyFullSchema(dbName)`
  - `seedInitialCatalogs(dbName)`
  - `createInitialAdmin(dbName, userData)`

## Criterios de Aceptacion
- Un usuario nuevo puede completar el wizard en `/register` sin intervencion manual.
- Se crea una nueva base con nombre `mrp_(empresa_normalizada)`.
- La nueva base contiene el esquema completo del MRP.
- La nueva base contiene datos solo de Depositos y Unidades de medida.
- El usuario admin puede iniciar sesion con la empresa recien creada.
- Los errores de provisionamiento quedan logueados y no dejan altas inconsistentes.

## Casos de Prueba Minimos
- Registro exitoso con nombre de empresa simple.
- Registro exitoso con nombre que incluye espacios y acentos.
- Colision de nombre de empresa (genera sufijo incremental).
- Falla en migracion/seeding (manejo de rollback logico).
- Verificacion de tablas completas + solo datos iniciales esperados.

## Consideraciones de Seguridad
- Validacion server-side de todos los campos.
- Password hasheado con algoritmo seguro (`password_hash`).
- Sanitizacion estricta para evitar SQL injection en naming de DB.
- No exponer detalles internos de errores SQL al usuario final.

## Resultado Esperado
Con este wizard, cualquier empresa puede autogestionar su alta en el sistema MRP, obteniendo una base de datos propia con estructura completa y datos iniciales controlados (Depositos y Unidades de medida), bajo la convencion `mrp_(nombre_de_empresa)`.
