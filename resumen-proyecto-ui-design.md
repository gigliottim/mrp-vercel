# Resumen completo del proyecto MRP para diseño de interfaz HTML5

> **Propósito de este documento**: Servir de base para realizar un diseño completo de toda la interfaz gráfica del sistema MRP en HTML5. Incluye la estructura general, los módulos funcionales, entidades, flujos de pantalla, formularios, tablas, reportes, componentes reutilizables y decisiones de UX/UI ya existentes.

---

## 1. Información general del proyecto

| Campo | Valor |
|-------|-------|
| Nombre | MRP · Control de Producción e Inventario |
| Versión actual | 53.9.0 (build 4291) |
| Dominio | mimrp.com.ar |
| Entorno | Docker: PHP 8.4+, PostgreSQL 17, Valkey 8+, Nginx |
| Arquitectura | MVC custom framework (sin build step) |
| Multi-tenancy | Una base de datos PostgreSQL por empresa (tenant) |
| Auth DB | `mrp_auth` (usuarios, empresas, roles, ACL de menú) |

### 1.1 Stack frontend

| Tecnología | Versión | Uso |
|------------|---------|-----|
| Bootstrap | 5.3.7 | Grids, componentes, formularios, tablas, modales |
| Font Awesome | 6.7.2 | Iconografía general |
| Bootstrap Icons | 1.13.1 | Iconografía adicional |
| Alpine.js | 3.14.9 | Estado reactivo en cliente (manager, maestro, rutas, Gantt, permisos, agente AI) |
| Chart.js | 4.5.0 | Gráficos (dashboard, reportes) |
| FullCalendar | 6.1.18 | Calendarios / planificación |
| CSS custom | `public/assets/css/` | Variables, estilos globales, módulos y componentes |
| JS custom | `public/assets/js/` | `main.js`, `SearchClient.js`, módulos específicos |

### 1.2 Estructura de vistas

```
views/
  layouts/
    app.php       → Layout principal con sidebar + header + menú unificado
    auth.php      → Layout de login/register (público con header minimal)
    public.php    → Landing pública
  partials/
    header.php    → Header superior con usuario, tenant, logout, abrir menú (Ctrl+K)
    sidebar.php   → Menú lateral jerárquico basado en permisos ACL
    navbar.php    → Navbar pública simple
    footer.php    → Footer global
    unified_menu_modal.php → Menú tipo "Command Palette" con búsqueda por módulo
  components/
    agentAI/_agent_floating_button.php → Botón flotante del agente AI (Luchi)
  pages/
    home.php, dashboard.php, menu.php
    auth/login.php, auth/register.php
    productos/..., produccion/..., planeamiento/..., reportes/...
    transacciones/..., inventario/..., compras/..., admin/...
```

### 1.3 Navegación principal

La navegación se resuelve dinámicamente desde `MenuService` basado en roles/permisos ACL. Las secciones principales son:

1. **Panel** → Inicio / Dashboard / Mapa del sistema
2. **Taller** → Producción (dashboard, centros, rutas, órdenes, planificación, ejecución)
3. **Catálogo de Productos** → Partes, variantes, BOM, maestro, importaciones, herramientas
4. **Planificación y Compras** → Sugerencias MRP, órdenes planificadas
5. **Reportes** → Destino de partes, listado de ingeniería, planificación de producción, resumen por grupos
6. **Administración** → Catálogos (tipos, grupos, unidades, entidades, depósitos, centros), configuración general
7. **Empresa y Usuarios** → Empresa, usuarios, roles, permisos ACL

Además existe:
- **Agente AI** (`/agent`) → Chat de página completa
- **Botón flotante Luchi** en todas las páginas con layout `app.php`

---

## 2. Fundamentos de UX/UI ya implementados

### 2.1 Layout principal (`app.php`)

- Header fijo con: botón de menú, badge de entorno (no-producción), dropdown de usuario y logout.
- Sidebar izquierdo con secciones colapsables, badge WIP en módulos de producción, y tarjeta de info del tenant.
- Contenido principal centrado con ancho flexible.
- Menú unificado tipo modal-overlay (Ctrl+K) con búsqueda en vivo, categorías y tarjetas.
- Footer al final del scroll.
- Soporte dark/light mode por `data-bs-theme` (aunque el uso predominante es claro).

### 2.2 Paleta de colores semántica

| Color | Uso típico |
|-------|------------|
| Primary | Acciones principales, links, dashboard |
| Success | Fabricable, stock normal, completada |
| Warning | Parcial, advertencia, liberada, atrasada |
| Danger | Sin stock, crítico, cancelada, urgente |
| Info | Planificada, interno, centros |
| Secondary | Cerrada, borrador, reportes |
| Dark | Administración, empresa-usuarios |

### 2.3 Patrones de componentes comunes

- **KPI Cards**: tarjetas con icono, valor grande y descripción pequeña.
- **Tablas responsive**: `.table`, `.table-hover`, badges de estado, botones de acción.
- **Formularios en cards**: agrupados por secciones, validaciones inline con `.is-invalid`.
- **Búsqueda tipo-ahead**: `SearchClient.js` contra `/api/v1/search/variantes`, `/api/v1/search/partes`, etc.
- **Modales Bootstrap**: confirmación de eliminación, edición inline, selección de variantes.
- **Accordions**: sugerencias MRP, reportes de composición, detalle de grupos.
- **Timelines**: estados de órdenes de producción.
- **Barras de progreso**: avance de órdenes, cobertura de stock, carga de centros.
- **Badges**: estados, prioridades, roles, WIP.
- **Botones de acción agrupados**: ver, editar, eliminar, maestro, destino de partes.

---

## 3. Módulo: Autenticación y onboarding

### 3.1 Funcionalidad

- Registro autoservicio de empresa: crea tenant, base de datos, usuario admin y vinculación en `mrp_auth`.
- Login en dos pasos: credenciales → si hay múltiples empresas, selección de tenant.
- Logout con redirección a login.

### 3.2 Pantallas

| Ruta | Vista | Propósito |
|------|-------|-----------|
| `GET /login` | `pages/auth/login.php` | Formulario de credenciales + selección de empresa |
| `GET /register` | `pages/auth/register.php` | Wizard de alta de empresa con pasos 1-3 y resumen de provisioning |
| `POST /login` | - | Autenticación y selección de tenant |
| `POST /logout` | - | Cierre de sesión |

### 3.3 Formularios / elementos de UI

- Login: email, password, errores inline, recordatorio de no cambio de empresa en sesión.
- Selección de empresa: select con `slug` y nombre de cada company.
- Register: admin_name, lastname, email, password, confirmation, company_name, tax_id, country, términos.
- Wizard register: progress bar, preview del nombre de base, resumen de provisioning con badges success/danger.

---

## 4. Módulo: Panel / Dashboard / Mapa del sistema

### 4.1 Funcionalidad

- Mostrar métricas clave para la toma de decisiones diarias.
- Distribución por estado de órdenes de producción.
- Cumplimiento operativo estimado.
- Resumen MRP sugerencias.
- Mapa visual de todos los módulos accesibles por el usuario.

### 4.2 Pantallas

| Ruta | Vista | Propósito |
|------|-------|-----------|
| `GET /` | `pages/home.php` | Landing pública con hero, features, problemas vs solución, CTA |
| `GET /dashboard` | `pages/dashboard.php` | KPIs ejecutivos + gráficos simples |
| `GET /menu` | `pages/menu.php` | Grid visual de módulos agrupados por sección |

### 4.3 Elementos de UI

- Dashboard:
  - 8 KPI cards en dos filas de 4: ordenes activas, urgentes, atrasadas, en operación, stock crítico, advertencia, compras del mes, gasto del mes.
  - Barra de distribución por estado.
  - Anillo de cumplimiento operativo.
  - 3 cards de resumen MRP.
  - Checklist ejecutivo diario con 3 tarjetas informativas.
- Mapa del sistema:
  - Encabezado con título y botón volver.
  - Secciones con icono, color y contador de items.
  - Grid responsive 4-3-2 columnas.
  - Tarjetas con icono grande, label y estado WIP.

---

## 5. Módulo: Productos / BOM / Composición

### 5.1 Funcionalidad

- Catálogo de partes y variantes.
- Maestro de BOM: estructura jerárquica padre-hijo de componentes.
- Importación/exportación masiva de BOMs por CSV.
- Herramientas: copiar componentes entre variantes, reemplazar partes en múltiples maestros.
- Cálculo automático de superficie/volumen por dimensiones.

### 5.2 Entidades principales

| Entidad | Tabla | Campos clave |
|---------|-------|--------------|
| Parte | `partes` | código, detalle, tipo, grupo, UM compra, UM uso, factor, dimensiones, superficie, volumen, activo |
| Variante | `variantes` | id_parte, codigo_variante, detalle, estado, stock_actual, punto_pedido, lote_minimo, peso, ubicación |
| BOM Cabecera | `bom_cabecera` | variante_padre_id, versión, activa, fecha_efectiva |
| BOM Detalle | `bom_detalle` | bom_id, variante_componente_id, cantidad_necesaria, unidad_medida_id |

### 5.3 Pantallas

| Ruta | Vista | Propósito |
|------|-------|-----------|
| `GET /productos/bom` | `pages/productos/bom/index.php` | Listado de BOMs activas |
| `GET /productos/maestro` | `pages/productos/composicion/maestro.php` | Editor visual de composición (árbol + detalle) |
| `GET /productos/maestro/importar` | `pages/productos/composicion/import.php` | Cargar CSV, descargar plantilla, exportar |
| `GET /productos/copiar-componentes` | `pages/productos/herramientas/copiar-componentes.php` | Copiar componentes nivel 1 entre variantes |
| `GET /productos/reemplazar-partes` | `pages/productos/herramientas/reemplazar-partes.php` | Reemplazo masivo de partes en maestros |
| `POST /productos/maestro/materiales` | modales | Agregar/editar/reemplazar componentes |

### 5.4 Elementos de UI

- **Maestro de composición**:
  - Panel izquierdo: árbol jerárquico de BOM con indentación, iconos de folder/cube, selección de nodo.
  - Panel derecho: detalle del nodo seleccionado con tabla de componentes, cantidad, unidad, acciones.
  - Buscador de variante maestra con filtros por tipo de parte (chips de colores).
  - Botones: agregar componente, editar, reemplazar, destino de partes, importar/exportar.
  - Modales: `_modalAgregar.php`, `_modalEditar.php`, `_modalReemplazar.php`.
- **Importación BOM**:
  - Drag & drop / input file CSV.
  - Tabla de reporte: filas OK, omitidas, error con motivo.
  - Detección de ciclos y duplicados.
- **Copiar componentes**:
  - Selectores de variante origen y destino.
  - Tablas comparativas de componentes.
  - Resultado: copiadas / eliminadas / saltadas.
- **Reemplazar partes**:
  - Paso 1: parte origen y reemplazo.
  - Paso 2: previsualización de maestros afectados (where-used).
  - Paso 3: selección de maestros y ejecución.

---

## 6. Módulo: Configuración / Administración

### 6.1 Funcionalidad

- CRUDs de catálogos maestros: tipos de partes, grupos, unidades de medida, entidades, tipos de depósito.
- Validaciones de movimientos entre tipos de depósito.
- Configuración general: decimales, redondeo, separadores, formatos fecha/hora.
- Recálculo masivo de dimensiones geométricas.
- Gestión de empresa, usuarios, roles y permisos ACL sobre menú.

### 6.2 Pantallas de catálogos

| Ruta | Vista | Entidad |
|------|-------|---------|
| `GET /configuracion/tipos-partes` | `pages/admin/catalogo/tipos/index.php` | `tipos_partes` |
| `GET /configuracion/grupos-partes` | `pages/admin/grupos/index.php` | `grupos_partes` |
| `GET /configuracion/unidades` | `pages/admin/catalogo/unidades/index.php` | `unidades_medida` |
| `GET /configuracion/entidades` | `pages/admin/catalogo/entidades/index.php` | `entidades` |
| `GET /configuracion/tipos-depositos` | `pages/admin/catalogo/depositos/index.php` | `tipos_depositos` |
| `GET /configuracion/depositos-validaciones` | `pages/admin/catalogo/depositos/validaciones.php` | `tipos_depositos_movimientos` |
| `GET /produccion/centros` | `pages/admin/centros/index.php` | `centros_trabajo` (legacy) |
| `GET /configuracion/general` | `pages/admin/configuracion/general.php` | `configuracion_general` |

### 6.3 Pantallas de partes y variantes

| Ruta | Vista | Propósito |
|------|-------|-----------|
| `GET /productos/partes` | `pages/admin/partes/index.php` | Vista clásica tipo tarjetas colapsables por parte |
| `GET /productos/partes/manager` | `pages/admin/partes/manager.php` | Manager moderno de 3 paneles (Alpine.js) |
| `GET /productos/partes/manager/{id}` | - | Ver parte y variantes |
| `GET /productos/partes/importar` | `pages/admin/partes/import.php` | Importar partes/variantes desde CSV |

### 6.4 Pantallas de empresa-usuarios

| Ruta | Vista | Propósito |
|------|-------|-----------|
| `/empresa-usuarios/empresa` | `pages/admin/empresa-usuarios/empresa.php` | Datos de la empresa tenant |
| `/empresa-usuarios/usuarios` | `pages/admin/empresa-usuarios/usuarios.php` | Usuarios de la empresa |
| `/empresa-usuarios/roles` | `pages/admin/empresa-usuarios/roles.php` | Roles |
| `/empresa-usuarios/permisos` | `pages/admin/empresa-usuarios/permisos.php` | Árbol ACL de menú por rol/usuario |

### 6.5 Elementos de UI

- CRUDs de catálogo: tabla de listado + formulario en la misma página (crear/editar inline).
- Unidades de medida: selector de tipo, símbolo, equivalencia base, flag "es base".
- Validaciones de depósitos: matriz origen-destino con switches ON/OFF.
- Configuración general: formulario en dos columnas, preview de formato numérico/fecha.
- **Manager de partes**:
  - Barra de búsqueda + filtro por tipo.
  - Panel izquierdo: listado de variantes de la parte seleccionada.
  - Panel central: formulario de parte y formulario de variante en secciones.
  - Estados de variante: activa, desarrollo, obsoleta, descontinuada (badges).
  - Botones: guardar, agregar variante, editar variante, eliminar, ir a maestro, ir a destino.
- **Importación de partes**:
  - Upload CSV, plantilla descargable, mapeo de columnas, reporte de filas.
- **Permisos ACL**:
  - Árbol de menú a la izquierda con checkboxes y selectores de nivel (none/read/write).
  - Tabla de roles/usuarios a la derecha.
  - Guardado masivo por AJAX.
  - Badges allow/deny/inherit.

---

## 7. Módulo: Producción

### 7.1 Funcionalidad

- Dashboard de operaciones de producción.
- Centros de trabajo: recursos productivos con capacidad, eficiencia, costo/hora.
- Rutas de producción: secuencia de operaciones asociadas a un BOM.
- Órdenes de producción: ciclo de vida completo con estados y transiciones.
- Planificación de recursos: asignación de centros a operaciones/órdenes, vista Gantt.

### 7.2 Entidades principales

| Entidad | Tabla | Campos / estados |
|---------|-------|------------------|
| Centro de trabajo | `centros_trabajo` | código, nombre, tipo, capacidad_horas_dia, eficiencia, costo_hora, activo |
| Ruta de producción | `rutas_produccion` | bom_id, secuencia, centro_id, tiempos (setup, proceso, cola, movimiento), costos |
| Orden de producción | `ordenes_produccion` | número, variante_id, bom_id, cantidades, fechas, estado, prioridad |
| Planificación recurso | `planificacion_recursos` | orden_id, operación_id, centro_id, periodo tsrange, estado |

### 7.3 Estados de órdenes de producción

```
borrador → planificada → liberada → en_proceso → completada → cerrada
              ↓              ↓            ↓
           cancelada    cancelada    cancelada / pausada → en_proceso
```

Prioridades: `baja`, `normal`, `alta`, `urgente`.

### 7.4 Pantallas

| Ruta | Vista | Propósito |
|------|-------|-----------|
| `GET /produccion` | `pages/produccion/index.php` | Dashboard de operaciones |
| `GET /produccion/ejecucion` | `pages/produccion/ejecucion.php` | Placeholder de ejecución |
| `GET /produccion/centros-trabajo` | `pages/produccion/centros_trabajo/index.php` | Listado de centros |
| `GET /produccion/centros-trabajo/create` | `pages/produccion/centros_trabajo/create.php` | Crear centro |
| `GET /produccion/centros-trabajo/{id}/edit` | `pages/produccion/centros_trabajo/edit.php` | Editar centro |
| `GET /produccion/centros-trabajo/{id}` | `pages/produccion/centros_trabajo/show.php` | Detalle de centro |
| `GET /produccion/rutas` | `pages/produccion/rutas/index.php` | Listado de rutas por BOM |
| `GET /produccion/rutas/{id}/editor` | `pages/produccion/rutas/editor.php` | Editor visual de operaciones (Alpine.js) |
| `GET /produccion/ordenes` | `pages/produccion/ordenes/index.php` | Listado de órdenes con dashboard de estados |
| `GET /produccion/ordenes/create` | `pages/produccion/ordenes/create.php` | Crear orden |
| `GET /produccion/ordenes/{id}` | `pages/produccion/ordenes/show.php` | Detalle con timeline y acciones |
| `GET /produccion/ordenes/{id}/edit` | `pages/produccion/ordenes/edit.php` | Editar orden |
| `GET /produccion/planificacion` | `pages/produccion/planificacion/index.php` | Vista lista de planificación |
| `GET /produccion/planificacion/gantt` | `pages/produccion/planificacion/gantt.php` | Vista Gantt |

### 7.5 Elementos de UI

- **Dashboard de producción**:
  - Hero card con gradiente primary.
  - 4 KPIs: centros activos, rutas configuradas, órdenes en proceso, capacidad utilizada.
  - Acciones rápidas grandes.
  - Alertas y pendientes.
  - Tarjetas de acceso a submódulos.
- **Centros de trabajo**:
  - Tabla con filtros de búsqueda, tipo, activo.
  - Formulario con campos de código, nombre, tipo, capacidad, eficiencia, costo/hora.
  - Vista de detalle con panel lateral de disponibilidad y operaciones.
- **Editor de rutas**:
  - Header con resumen: operaciones, minutos totales, costo total, centros usados.
  - Lista reordenable de operaciones (Alpine.js).
  - Cada operación: secuencia badge, nombre, centro, setup, tiempo proceso, descripción.
  - Botones: agregar, eliminar, mover arriba/abajo, guardar.
- **Órdenes de producción**:
  - Listado con dashboard de estados (6 cards de colores).
  - Filtros: búsqueda, estado, prioridad, rango de fechas.
  - Tabla: número, producto, cantidad, fechas, prioridad badge, estado badge, avance bar.
  - Crear: número auto, buscador de variante, selector de BOM por AJAX, cantidad, fechas.
  - Detalle: header grande con estado, dropdown de acciones según estado, información en dos columnas, barra de avance, timeline vertical, panel lateral con planificación y materiales requeridos.
- **Planificación**:
  - Vista lista: filtros centro/fecha, alertas de conflictos, tabla de asignaciones, resumen de carga por centro.
  - Vista Gantt: controles de centro y rango, botón exportar PDF, barras horizontales por recurso/orden, leyenda de estados.

---

## 8. Módulo: Planeamiento MRP

### 8.1 Funcionalidad

- Analizar fabricabilidad de variantes con BOM activa según stock disponible.
- Clasificar en: fabricable, parcial, sin stock.
- Calcular unidades máximas fabricables y cobertura por componente.

### 8.2 Entidades

- `bom_cabecera`, `bom_detalle`, `variantes`, `partes`, `unidades_medida`, stock actual.

### 8.3 Pantallas

| Ruta | Vista | Propósito |
|------|-------|-----------|
| `GET /planeamiento/sugerencias` | `pages/planeamiento/sugerencias.php` | Listado colapsable de fabricabilidad |
| `GET /planeamiento/ordenes` | `pages/planeamiento/ordenes.php` | Placeholder de órdenes planificadas |

### 8.4 Elementos de UI

- 4 KPI cards: total con BOM, fabricables, parciales, sin stock.
- Filtros rápidos por estado (botones de colores).
- Accordion de variantes, expandiendo automáticamente las fabricables.
- Cada item: código parte + variante, descripción, unidades posibles, barra de cobertura, badge de estado.
- Dentro: tabla de componentes con necesario, stock disponible, cobertura con barra de progreso.
- Tooltips explicativos de condiciones.

---

## 9. Módulo: Transacciones / Compras / Movimientos

### 9.1 Funcionalidad

- Registrar compras como movimientos `PROVEEDOR → ALMACEN`.
- Registrar movimientos de stock entre depósitos.
- Validar flujos permitidos entre tipos de depósito.
- Actualizar costo de variante y stock actual.

### 9.2 Entidades principales

| Entidad | Tabla |
|---------|-------|
| Compra | `compras` |
| Movimiento de stock | `movimientos_stock` |
| Tipo de depósito | `tipos_depositos` |
| Entidad (proveedor) | `entidades` |

### 9.3 Pantallas

| Ruta | Vista | Propósito |
|------|-------|-----------|
| `GET /compras` | `pages/compras/index.php` | Listado de compras registradas |
| `GET /compras/create` | redirige a movimientos | Crear compra |
| `GET /transacciones/movimientos-partes` | `pages/transacciones/movimientos-partes.php` | Formulario unificado + historial |

### 9.4 Elementos de UI

- **Listado de compras**:
  - Tabla: fecha, item/variante, cantidad uso, cantidad compra, costo unitario, total, proveedor, observaciones.
  - Botón grande "Registrar nueva compra".
  - Badges/alertas de éxito.
- **Movimientos de partes**:
  - Layout de dos columnas: formulario (5/12) + historial (7/12).
  - Stats row: total, compras, internos, salidas.
  - Formulario en secciones:
    - Fecha/Hora y flujo origen-destino (selects dependientes).
    - Parte/Variante (búsqueda tipo-ahead).
    - Cantidad, unidad de medida, importe total.
    - Sección compra (proveedor, moneda, cotización, comprobante) cuando origen = PROVEEDOR.
    - Referencias y observaciones.
  - Historial con edición inline.

---

## 10. Módulo: Inventario

### 10.1 Funcionalidad

- Clasificar stock en crítico / advertencia / normal según punto de pedido.
- Detectar faltantes y lotes mínimos.

### 10.2 Pantalla

| Ruta | Vista | Propósito |
|------|-------|-----------|
| `GET /inventario/critico` | `pages/inventario/critico.php` | Análisis de stock crítico |

### 10.3 Elementos de UI

- 4 KPI cards: total, crítico, advertencia, normal.
- Filtros por estado.
- Tabla responsive: estado badge, código parte/variante, descripción, tipo, grupo, stock actual, punto pedido, faltante, lote mínimo.
- Leyenda de estados al final.

---

## 11. Módulo: Reportes

### 11.1 Funcionalidad

- Destino de partes: composición (rama 1 / plana / árbol) + dónde se utiliza.
- Listado de ingeniería: listado de materiales con opciones de salida y exportación Excel/PDF.
- Planificación de producción: cálculo de requerimientos consolidados para múltiples productos, con lotes mínimos y costos.
- Resumen por grupos: estadísticas y detalle por grupo de partes.

### 11.2 Pantallas

| Ruta | Vista | Propósito |
|------|-------|-----------|
| `GET /reportes/destino-partes` | `pages/reportes/destino-partes.php` | Composición y where-used de una variante |
| `GET /reportes/listado-ingenieria` | `pages/reportes/listado-ingenieria.php` | Listado de materiales exportable |
| `GET /reportes/planificacion-produccion` | `pages/reportes/planificacion-produccion.php` | Requerimientos para producción programada |
| `GET /reportes/resumen-grupos` | `pages/reportes/resumen-grupos.php` | Estadísticas por grupo de partes |

### 11.3 Elementos de UI

- **Destino de partes**:
  - Buscador de variante.
  - Alerta de variante seleccionada con datos.
  - Tabs: Rama 1 / Plana / Árbol.
  - Vista de árbol con iconos folder/cube.
  - Panel derecho: dónde se utiliza (tabla de BOMs padre).
- **Listado de ingeniería**:
  - Buscador de variante.
  - Opciones: cantidad a fabricar, tipo de salida (árbol/plana/rama1), incluir precios, agrupar por tipo, ordenar, filtrar tipos.
  - Tabla de resultados con niveles, códigos, cantidades, unidades, costos opcionales.
  - Botones de exportación Excel y PDF.
- **Planificación de producción**:
  - Formulario para agregar productos a programar (variante + cantidad).
  - Tabla de productos programados con botón quitar.
  - Tabla de requerimientos: componente, cantidad necesaria, stock, faltante, lote mínimo, a pedir, costo, total.
  - Totales al pie.
  - Exportación Excel/PDF.
- **Resumen por grupos**:
  - Selector de grupo.
  - Tabla resumen de grupos o acordeón con partes/variantes, stock y estado.

---

## 12. Módulo: Agente AI (Luchi)

### 12.1 Funcionalidad

- Asistente conversacional integrado con Ollama Cloud / OpenAI-compatible.
- Página completa de chat y botón flotante en todas las pantallas.
- Capaz de crear partes, BOMs, proveedores y otros registros mediante conversación guiada.
- Sistema de preview + confirmación antes de guardar cambios.

### 12.2 Pantallas y componentes

| Ruta/Archivo | Propósito |
|--------------|-----------|
| `GET /agent` | `views/agentAI/agent_chat.php` → `agenteAI/frontend/views/agent_chat.php`. Página completa de chat |
| `_agent_floating_button.php` | Botón flotante tipo WhatsApp en layout app |
| `agenteAI/frontend/views/components/_suggestions.php` | Sugerencias rápidas |
| `agenteAI/frontend/views/components/_preview.php` | Preview de datos a guardar |
| `agenteAI/frontend/views/components/_message.php` | Burbujas de mensaje |

### 12.3 Elementos de UI

- Chat full-page: header, historial de mensajes, sugerencias, área de preview con botones confirmar/cancelar, textarea de input.
- Botón flotante: icono de robot, estado online/offline, contenedor expandible con header, mensajes, sugerencias, input.
- Badges de intención, loading, offline.

---

## 13. API relevante para frontend

| Endpoint | Uso en UI |
|----------|-----------|
| `POST /api/v1/search/variantes` | Búsqueda tipo-ahead de variantes |
| `POST /api/v1/search/partes` | Búsqueda tipo-ahead de partes |
| `POST /api/v1/search/centros-trabajo` | Búsqueda de centros |
| `GET /api/v1/bom/variantes/{id}` | Obtener BOMs por variante |
| `GET /api/v1/bom/variantes/{id}/nivel1` | Componentes nivel 1 |
| `GET /api/v1/depositos-validaciones/{origenId}/destinos` | Destinos permitidos para select dependiente |
| `POST /api/v1/agent/message` | Enviar mensaje al agente AI |
| `POST /api/v1/agent/confirm` | Confirmar guardado del agente AI |
| `GET /api/v1/agent/suggestions` | Sugerencias del agente |

---

## 14. Guía para diseño HTML5

### 14.1 Pantallas obligatorias a diseñar

1. **Públicas**: Landing, login, register, menú unificado, navbar/footer.
2. **Dashboard**: Panel principal con KPIs y gráficos.
3. **Mapa del sistema**: Grid de módulos.
4. **Productos**:
   - Listado BOMs, maestro de composición (árbol + detalle), importación BOM.
   - Copiar componentes, reemplazar partes.
   - Partes (vista clásica), manager moderno de partes, importación partes.
5. **Configuración**:
   - CRUDs: tipos, grupos, unidades, entidades, tipos depósito, validaciones, centros.
   - Configuración general.
6. **Producción**:
   - Dashboard de operaciones.
   - Centros de trabajo (listado, crear, editar, detalle).
   - Rutas (listado, editor visual).
   - Órdenes (listado, crear, editar, detalle con timeline).
   - Planificación (vista lista y Gantt).
7. **Planeamiento**: Sugerencias de fabricación, órdenes planificadas.
8. **Transacciones**: Listado de compras, formulario unificado de movimientos.
9. **Inventario**: Stock crítico.
10. **Reportes**: Destino de partes, listado ingeniería, planificación producción, resumen grupos.
11. **Empresa y usuarios**: Empresa, usuarios, roles, permisos ACL.
12. **Agente AI**: Chat full-page y botón flotante.

### 14.2 Componentes reutilizables a documentar

- **Header principal** con tenant, usuario, logout, menú (Ctrl+K).
- **Sidebar** jerárquica con secciones, items, badges WIP, info de tenant.
- **Menú unificado** (modal overlay) con búsqueda, categorías, tarjetas.
- **Footer** público y de app.
- **KPI Card** con icono, valor, descripción.
- **Data Table** con filtros, paginación, acciones.
- **Form Card** con secciones y validaciones.
- **SearchClient** (búsqueda tipo-ahead de variantes/partes).
- **Variante Action Buttons** (gestionar, maestro, destino, editar, eliminar).
- **Timeline de estados** para órdenes.
- **Gantt simplificado** para planificación.
- **Botón flotante del agente AI**.
- **Modales de confirmación** (Bootstrap).
- **Alertas flash** success/error.

### 14.3 Convenciones de diseño a respetar

- Usar **Bootstrap 5.3.7** como base; no jQuery.
- Usar **Font Awesome 6.7.2** para iconos; **Bootstrap Icons** como secundario.
- Usar **Alpine.js 3.14.9** para interactividad reactiva.
- CSS custom en `public/assets/css/` siguiendo la estructura existente:
  - `global/main.css`
  - `components/cards.css`, `components/buttons.css`
  - `modules/*.css` por módulo
- Mantener temática clara por defecto; soportar `data-bs-theme="dark"`.
- Formularios con etiquetas, placeholders, validaciones inline, feedback.
- Tablas responsive con scroll horizontal en móvil.
- Botones de acción consistentes: primary = guardar/aceptar, outline = secundario, danger = eliminar.
- Badges de estado consistentes con la semántica definida.

### 14.4 Responsive breakpoints a considerar

- **Móvil (< 768px)**: sidebar oculta, menú unificado pantalla completa, tablas scroll, KPIs 2 columnas.
- **Tablet (768px - 991px)**: sidebar colapsable, formularios 1 columna.
- **Desktop (≥ 992px)**: sidebar visible, dos columnas en formularios y vistas de detalle.
- **Large (≥ 1200px)**: tres paneles en manager, árbol + detalle en maestro.

---

## 15. Relaciones entre módulos (resumen visual)

```
[Configuración]
   ├─ Partes / Variantes ─────┐
   ├─ Tipos, Grupos, UM ──────┤
   ├─ Entidades, Depósitos ───┤
   └─ Centros de trabajo ─────┤
                             ▼
[Productos/BOM] ──► [Producción: Rutas]
        │                    │
        │                    ▼
        │            [Producción: Órdenes]
        │                    │
        │                    ▼
        │            [Producción: Planificación/Gantt]
        │
        ├──────────► [Planeamiento MRP]
        │                │
        ▼                ▼
[Inventario] ◄── [Transacciones: Movimientos / Compras]
        │
        └──────────► [Reportes]
                       │
                       ▼
              [Excel / PDF export]

[Empresa y Usuarios] controla permisos ACL de todo.
[Agente AI] puede operar sobre Partes, BOMs, Proveedores, etc.
```

---

## 16. Notas para el diseñador HTML5

- El sistema **no usa build step**: todo HTML5 debe ser puro o integrado con CDN de Bootstrap/Alpine/FontAwesome.
- Las vistas actuales son **PHP nativo**; para un rediseño HTML5 se recomienda:
  - Prototipar en HTML estático primero.
  - Luego adaptar a las vistas `.php` usando los helpers de escape (`View::escape`), helpers de URL (`url()`) y helpers de formato (`app_format_number`).
- Reutilizar los **partials existentes** (`header.php`, `sidebar.php`, `footer.php`, `unified_menu_modal.php`) para mantener consistencia.
- Mantener el **menú unificado** como punto central de navegación; es el patrón más usado por usuarios avanzados.
- Considerar **accesibilidad básica**: labels asociados, roles ARIA en tablas, focus visible, tooltips con atributos correctos.
- Respetar la **jerarquía visual**: título H1 por página, secciones H2/H3, KPIs prominentes, tablas limpias.
- Para el **manager de partes** y el **editor de rutas**, Alpine.js es indispensable; diseñar los estados reactivos desde el principio.
- Los **reportes de planificación** y **Gantt** son los más complejos visualmente: priorizar claridad en cantidades, colores de estado y totales.

---

**Fin del resumen.** Este documento debe permitir diseñar wireframes, mockups y prototipos HTML5 completos de cada módulo del sistema MRP sin necesidad de volver a revisar el código fuente.
