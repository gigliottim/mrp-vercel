# Propuesta de Refactorización y Unificación de Movimientos y Compras

## Estado Actual

Actualmente el sistema maneja tres conceptos relacionados pero separados:

1.  **Compras (`compras`)**: Registra la operación comercial de compra.
    *   Campos: `id`, `fecha`, `proveedor` (string), `id_variante`, `cantidad`, `precio_untarlo`, `precio_total`, `observaciones`.
    *   Registra el *hecho económico* y el detalle del ítem comprado.

2.  **Movimientos de Stock (`movimientos_stock`)**: Registra el cambio físico de inventario.
    *   Campos: `id`, `fecha`, `id_variante`, `cantidad`, `id_tipo_deposito_origen`, `id_tipo_deposito_destino`, `referencia_tipo`, `referencia_id`.
    *   Es transaccional y "ciego" al motivo comercial, solo le importa de dónde sale y a dónde va el stock.
    *   Actualmente vinculado a compras mediante `referencia_tipo = 'compra'` y `referencia_id = id_compra`.

3.  **Movimientos de Partes (UI: `/transacciones/movimientos-partes`)**:
    *   Es una interfaz (actualmente en desarrollo/incompleta) diseñada para registrar movimientos manuales entre depósitos (ej. Almacén -> Producción).
    *   No tiene una tabla propia específica asignada en la lógica actual (usaría `movimientos_stock`).

## 1. Unificación de Tablas (Compras vs Movimientos)

### Análisis de Viabilidad

La propuesta es unificar la tabla `compras` con `movimientos_stock` (o la tabla que usará la UI de movimientos de partes), argumentando que una compra es simplemente un movimiento desde un depósito "PROVEEDOR" hacia "ALMACEN".

#### Opción A: Unificación Total (Eliminar tabla `compras`)
Mover todos los datos de la compra directamente a la tabla de movimientos.

*   **Cambios necesarios en `movimientos_stock`**:
    *   Agregar `id_proveedor` (o `proveedor` string por ahora).
    *   Agregar `precio_unitario` y `precio_total` (para valorizar el movimiento).
    *   Agregar `nro_factura` o `comprobante`.

*   **Ventajas**:
    *   Menos tablas.
    *   Consulta única para ver historial de movimientos y costos.

*   **Desventajas**:
    *   **Complejidad de Datos**: No todos los movimientos tienen precio (ej. mover de Almacén a Producción no cambia el costo de adquisición, aunque puede tener costo contable). Campos nulos frecuentes.
    *   **Estructura de Documentos**: Una compra real (`Factura`) suele tener *múltiples ítems*. Si eliminamos la tabla `compras` (o una tabla cabecera), y ponemos todo en movimientos (nivel línea), perdemos la agrupación natural de "Una Factura = Múltiples Líneas". Tendríamos que repetir el número de factura y proveedor en cada línea de movimiento.
    *   **Diferencia de Naturaleza**: La compra es un documento comercial/legal. El movimiento es un registro logístico. A veces la mercadería llega antes que la factura (Remito) o viceversa.

#### Opción B Refinada: Modelo Híbrido con Integración Directa (Elegida)

Se opta por un modelo donde **`movimientos_stock` es la tabla principal** que registra el hecho físico, y la tabla `compras` actúa como satélite para la información comercial/financiera.

**Lógica de Relación:**
*   Se genera un registro en `movimientos_stock` (ID X) con:
    *   Variante, Cantidad, Fecha.
    *   Depósito Origen: PROVEEDOR.
    *   Depósito Destino: ALMACEN.
*   Se genera un registro en `compras` que **referencia al movimiento stock** (FK `id_movimiento_stock`).
    *   Esta tabla `compras` **NO** tendrá campos redundantes como cantidad, variante o depósitos.
    *   Solo almacenará: Precio Unitario, Precio Total, Nro Factura, y Referencia a la Entidad (Proveedor).

**Estructura sugerida:**
1.  **`movimientos_stock`** (Master Físico):
    *   `id` (PK)
    *   `fecha`
    *   `id_variante`
    *   `cantidad`
    *   `id_tipo_deposito_origen`
    *   `id_tipo_deposito_destino`
2.  **`compras`** (Extension Comercial):
    *   `id` (PK)
    *   `id_movimiento_stock` (FK - Link al movimiento)
    *   `id_entidad` (FK - Link a tabla entidades)
    *   `precio_unitario`
    *   `nro_comprobante`
    *   `observaciones`

**Ventajas:**
*   Elimina redundancia de datos (cantidad y variante solo están en un lugar).
*   Mantiene separada la lógica física de la financiera, pero fuertemente vinculada.
*   Cualquier movimiento puede tener "datos extra" si se crean tablas satélite similares (ej. `ventas` vinculada a un movimiento de salida).

---

## 2. Integración de Movimientos de Partes (Internos)

Actualmente existe una interfaz en `/transacciones/movimientos-partes` para mover stock entre depósitos internos (ej. Almacén -> Producción).

bajos el nuevo esquema:

1.  **Registro Directo**: Estos movimientos, al ser puramente logísticos y no necesariamente comerciales, se registrarán **directamente** en `movimientos_stock`.
2.  **Sin Satélite**: A diferencia de las Compras, estos movimientos internos no requieren una tabla satélite obligatoria, a menos que se necesite trazar una Orden de Producción específica.
3.  **Campos Clave**:
    *   `id_tipo_deposito_origen`: (Ej. ID de 'ALMACEN')
    *   `id_tipo_deposito_destino`: (Ej. ID de 'PRODUCCION')
    *   `referencia_tipo`: 'interno' o 'produccion'
    *   `referencia_id`: (Opcional, ID de Orden de Producción)

**Flujo Unificado:**
*   El usuario ingresa a `/transacciones/movimientos-partes`.
*   Selecciona Origen y Destino (validando reglas de negocio).
*   El sistema inserta en `movimientos_stock`.
*   Si el movimiento implica una salida a Cliente (Venta), se podría crear una tabla satélite `ventas` similar a `compras` para los datos de facturación, siguiendo el mismo patrón.

---

## 3. Tabla de Proveedores

### Análisis
Actualmente el campo `proveedor` es un texto libre (`VARCHAR`).

**Problemas actuales:**
*   Inconsistencia: "Proveedor A", "proveedor A", "Prov. A" son tratados como distintos.
*   Falta de datos: No se puede guardar CUIT, Teléfono, Email del proveedor.
*   Dificultad de Reportes: No se puede agrupar fácilmente compras por proveedor real.

### Mejora Propuesta
Crear tabla `entidades` para unificar la gestión de Proveedores y Clientes.

**Estructura sugerida para `entidades`:**
*   `id` (PK)
*   `razon_social` (Nombre)
*   `tipo` (ENUM: 'PROVEEDOR', 'CLIENTE', 'AMBOS')
*   `identificacion_tributaria` (CUIT/RUT/TAX ID)
*   `contacto_email`
*   `contacto_telefono`
*   `direccion`

**Impacto:**
1.  **Normalización**: En tabla `compras` (o ventas) solo se guarda `id_entidad`.
2.  **Integridad**: Se evita duplicidad de nombres de proveedores ("Prov A", "Proveedor A").
3.  **Gestión**: Unificación de agenda de contactos.

### Plan de Acción Refinado

1.  **Crear tabla `entidades`**: Crear migración para la nueva tabla unificada.
2.  **Migrar Datos**: Script para extraer nombres únicos de la tabla `compras` actual y crearlos como registros en `entidades`.
3.  **Refactorizar `movimientos_stock`**: Dejarla como tabla master limpia.
4.  **Refactorizar tabla `compras`**:
    *   Vaciarla o re-crearla.
    *   Eliminar columnas `cantidad`, `id_variante` (ya que estarán en el movimiento vinculado).
    *   Agregar `id_movimiento_stock` (FK).
    *   Agregar `id_entidad` (FK).
5.  **Refactorizar `MovimientosPartesController`**:
    *   Adaptar para insertar directamente en `movimientos_stock` usando la nueva estructura simplificada.
    *   Asegurar que las validaciones de depósito origen/destino sigan funcionando.
6.  **Actualizar `ComprasController`**: Modificar para que al guardar:
    *   Primero inserte en `movimientos_stock`.
    *   Luego inserte los detalles financieros en `compras` usando el ID del movimiento generado.
