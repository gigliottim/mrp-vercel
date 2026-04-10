# Validaciones de Movimientos entre Depósitos

## 📋 Descripción

Sistema de configuración para controlar qué movimientos están permitidos entre diferentes tipos de depósitos. Esto permite que el sistema sea completamente ajustable por el cliente/empresa, evitando errores en los movimientos de inventario.

## 🎯 Funcionalidad

- **Configuración flexible**: Defina para cada tipo de depósito origen, a qué tipos de destino se pueden realizar movimientos
- **Validación automática**: Los formularios de movimientos solo mostrarán depósitos destino válidos según la configuración
- **Interfaz intuitiva**: Selección visual mediante checkboxes de los destinos permitidos
- **API REST**: EndpointsAPI para integración con formularios de movimientos

## 📁 Archivos Creados

### Base de Datos
- `database/migrations/2026-02-15_create_tipos_depositos_movimientos.sql` - Migración de la tabla

### Modelos
- `app/models/TipoDepositoMovimiento.php` - Modelo con métodos para gestionar las validaciones

### Controladores
- `app/controllers/Admin/DepositosValidacionesController.php` - Controlador principal

### Vistas
- `views/pages/admin/catalogo/depositos/validaciones.php` - Listado de configuraciones
- `views/pages/admin/catalogo/depositos/validaciones-form.php` - Formulario de configuración

### Scripts
- `ejecutar_migracion_validaciones_depositos.php` - Script para ejecutar la migración

## 🚀 Instalación

### 1. Ejecutar la migración

```bash
php ejecutar_migracion_validaciones_depositos.php
```

Este script:
- Crea la tabla `tipos_depositos_movimientos`
- Inserta configuraciones por defecto (ejemplos comunes)
- Muestra estadísticas de configuración

### 2. Configuración por Defecto

La migración incluye ejemplos de movimientos comunes:
- **ALMACEN** → PRODUCCION, PRE-PRODUCCION, CLIENTE
- **PRODUCCION** → ALMACEN
- **PROVEEDOR** → ALMACEN
- **AJUSTE** ↔ Todos los tipos (para ajustes de inventario)

## 🔧 Uso

### Interfaz Web

1. Acceder a: `/configuracion/depositos-validaciones`
2. Seleccionar el tipo de depósito origen que desea configurar
3. Hacer clic en "Configurar"
4. Marcar los tipos de depósito destino permitidos
5. Agregar observaciones (opcional)
6. Guardar configuración

### API REST

#### Obtener destinos permitidos para un origen

```http
GET /api/v1/depositos-validaciones/{origenId}/destinos
```

**Respuesta:**
```json
{
  "success": true,
  "destinos": [
    {
      "id": 2,
      "codigo": "PRODUCCION",
      "nombre": "Producción",
      "descripcion": "...",
      "activo": true
    }
  ]
}
```

#### Validar si un movimiento está permitido

```http
POST /api/v1/depositos-validaciones/validar
Content-Type: application/json

{
  "origen_id": 1,
  "destino_id": 2
}
```

**Respuesta:**
```json
{
  "success": true,
  "permitido": true,
  "mensaje": "Movimiento permitido"
}
```

## 💻 Integración en Formularios

### Ejemplo JavaScript para filtrar depósitos destino

```javascript
// Cuando cambia el depósito origen
document.getElementById('deposito-origen').addEventListener('change', async function() {
    const origenId = this.value;
    const selectDestino = document.getElementById('deposito-destino');
    
    if (!origenId) {
        selectDestino.disabled = true;
        return;
    }
    
    try {
        const response = await fetch(`/api/v1/depositos-validaciones/${origenId}/destinos`);
        const data = await response.json();
        
        if (data.success) {
            // Limpiar opciones actuales
            selectDestino.innerHTML = '<option value="">Seleccione destino...</option>';
            
            // Agregar solo destinos permitidos
            data.destinos.forEach(destino => {
                const option = document.createElement('option');
                option.value = destino.id;
                option.textContent = `${destino.nombre} (${destino.codigo})`;
                selectDestino.appendChild(option);
            });
            
            selectDestino.disabled = false;
        }
    } catch (error) {
        console.error('Error al cargar destinos permitidos:', error);
        alert('Error al cargar los depósitos destino disponibles');
    }
});
```

### Ejemplo PHP en formularios

```php
<?php
use App\Models\TipoDepositoMovimiento;

$movimientos = new TipoDepositoMovimiento();

// En el formulario, cuando se selecciona un origen:
$tipoOrigenId = (int) $_POST['tipo_deposito_origen_id'];

// Obtener solo los destinos permitidos
$destinosPermitidos = $movimientos->getDestinosPermitidos($tipoOrigenId);
?>

<select name="tipo_deposito_destino_id" required>
    <option value="">Seleccione destino...</option>
    <?php foreach ($destinosPermitidos as $destino) : ?>
        <option value="<?= $destino['id'] ?>">
            <?= htmlspecialchars($destino['nombre']) ?> (<?= htmlspecialchars($destino['codigo']) ?>)
        </option>
    <?php endforeach; ?>
</select>
```

## 📊 Estructura de la Tabla

```sql
tipos_depositos_movimientos (
    id                          SERIAL PRIMARY KEY,
    tipo_deposito_origen_id     INTEGER NOT NULL,
    tipo_deposito_destino_id    INTEGER NOT NULL,
    activo                      BOOLEAN DEFAULT TRUE,
    observaciones               TEXT,
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (tipo_deposito_origen_id) REFERENCES tipos_depositos(id),
    FOREIGN KEY (tipo_deposito_destino_id) REFERENCES tipos_depositos(id),
    UNIQUE (tipo_deposito_origen_id, tipo_deposito_destino_id)
)
```

## 🔐 Validaciones

### A nivel de base de datos
- Constraint `UNIQUE` evita duplicados (mismo origen-destino)
- Foreign keys aseguran integridad referencial
- Cascade delete: si se elimina un tipo de depósito, se eliminan sus configuraciones

### A nivel de aplicación
- Validación que origen ≠ destino
- Validación de IDs válidos y numéricos
- Transacciones para operaciones múltiples

## 🎨 Características de la Interfaz

- ✅ Vista de tabla con resumen de configuraciones
- ✅ Badges visuales para destinos configurados
- ✅ Contador de destinos por origen
- ✅ Formulario con checkboxes para fácil selección
- ✅ Actualización dinámica del contador
- ✅ Efectos visuales al seleccionar destinos
- ✅ Panel informativo con instrucciones
- ✅ Responsive design (Bootstrap 5)

## 🛠️ Métodos del Modelo

### `TipoDepositoMovimiento`

- `getAll()` - Obtiene todos los movimientos configurados con información completa
- `getDestinosPermitidos($origenId)` - Obtiene tipos de depósito destino permitidos para un origen
- `isMovimientoPermitido($origenId, $destinoId)` - Verifica si un movimiento está permitido
- `getByOrigen($origenId)` - Obtiene movimientos configurados para un origen específico
- `createOrUpdate($origenId, $destinoId, $activo, $observaciones)` - Crea o actualiza un movimiento
- `deleteMovimiento($origenId, $destinoId)` - Elimina un movimiento específico
- `deleteByOrigen($origenId)` - Elimina todos los movimientos de un origen
- `setDestinosPermitidos($origenId, $destinosIds, $observaciones)` - Establece destinos (reemplaza configuración existente)

## 📌 Rutas

### Web
- `GET /configuracion/depositos-validaciones` - Listado de configuraciones
- `GET /configuracion/depositos-validaciones/{id}/editar` - Formulario de edición
- `PUT /configuracion/depositos-validaciones/{id}` - Actualizar configuración
- `DELETE /configuracion/depositos-validaciones/{id}` - Eliminar configuración

### API
- `GET /api/v1/depositos-validaciones/{origenId}/destinos` - Obtener destinos permitidos
- `POST /api/v1/depositos-validaciones/validar` - Validar movimiento

## 🔄 Casos de Uso

1. **Restricción de flujo de producción**: Evitar que materiales vayan directamente de PROVEEDOR a PRODUCCION sin pasar por ALMACEN

2. **Control de salidas**: Restringir que solo desde ALMACEN se pueda enviar a CLIENTE

3. **Ajustes de inventario**: Permitir que AJUSTE pueda intercambiar con todos los tipos para correcciones

4. **Proceso de pre-producción**: Definir flujo ALMACEN → PRE-PRODUCCION → PRODUCCION → ALMACEN

## 📝 Notas

- La configuración se realiza a nivel de **tipos de depósitos**, no de depósitos individuales
- Esto permite mayor flexibilidad: cualquier depósito concreto de tipo A puede moverse a cualquier depósito concreto de tipo B si está permitido
- Las configuraciones pueden activarse/desactivarse sin eliminarlas
- Se mantiene historial mediante timestamps (created_at, updated_at)

## 🤝 Contribuciones

Este módulo está diseñado siguiendo los estándares de codificación del proyecto:
- ✅ Máximo 400 líneas por archivo
- ✅ Separación de responsabilidades (MVC)
- ✅ Nomenclatura consistente
- ✅ Código documentado
- ✅ PSR-12 coding standards

---

**Versión**: 1.0.0  
**Fecha**: 15 de febrero de 2026  
**Autor**: Sistema MRP
