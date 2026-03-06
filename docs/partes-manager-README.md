# Gestor Moderno de Partes y Variantes

## 📋 Descripción

Nueva interfaz moderna y eficiente para la gestión completa de Partes y sus Variantes en una sola pantalla.

## 🌐 URL de Acceso

```
http://localhost/mrp/productos/partes/manager
```

## ✨ Características Principales

### Diseño Moderno
- ✅ Interfaz tipo dashboard con diseño de 2 columnas
- ✅ Formularios interactivos con Alpine.js
- ✅ Sin recargas de página para operaciones CRUD de variantes
- ✅ Diseño responsive y accesible
- ✅ Iconos Font Awesome y badges visuales

### Funcionalidades

#### Gestión de Partes
- ✅ Crear/Editar parte en panel lateral izquierdo
- ✅ Campos principales: Código, Tipo, Grupo, UM, Detalle
- ✅ Dimensiones físicas: Largo/Alto, Ancho, Espesor/Profundidad
- ✅ Cálculo automático de Superficie y Volumen
- ✅ Selector de parte existente para edición rápida
- ✅ Estado activo/inactivo

#### Gestión de Variantes
- ✅ Agregar/Editar variantes en la misma página
- ✅ Formulario dinámico para variantes
- ✅ Tabla interactiva con edición inline
- ✅ Campos: Código, Detalle, Estado, Stock, Lote Mínimo, Punto Pedido, Peso
- ✅ Estados visuales con badges de colores
- ✅ Eliminación con confirmación

### Ventajas sobre la Vista Tradicional
- 🚀 Todo en una sola pantalla (no tabs)
- 🎯 Flujo de trabajo optimizado
- 💾 Guardado instantáneo sin recargas innecesarias
- 🎨 Interfaz más limpia y moderna
- 📱 Mejor experiencia en dispositivos móviles

## 📂 Estructura de Archivos

```
views/pages/admin/partes/
├── manager.php (100 líneas) - Vista principal
└── manager/
    ├── _parte_form.php (123 líneas) - Formulario de parte
    ├── _variante_form.php (113 líneas) - Formulario de variante
    └── _variantes_table.php (67 líneas) - Tabla de variantes

public/assets/js/
└── partes-manager.js (321 líneas) - Lógica Alpine.js

routes/
└── web.php - Rutas agregadas:
    - GET /productos/partes/manager
    - GET /productos/partes/manager/{id}

app/controllers/Admin/
└── PartesVariantesController.php - Métodos agregados:
    - manager()
    - managerEdit()
    - renderManager()
```

## 🛠️ Tecnologías Utilizadas

- **Alpine.js** - Reactividad y manejo de estado
- **Bootstrap 5** - Diseño y componentes UI
- **Font Awesome** - Iconografía
- **Fetch API** - Comunicación con backend
- **PHP 8+** - Backend

## 🎯 Flujo de Trabajo

### 1. Crear Nueva Parte + Variantes
1. Acceder a `/productos/partes/manager`
2. Completar formulario de Parte (izquierda)
3. Click en "Crear Parte"
4. Completar formulario de Variante (derecha)
5. Click en "Agregar" para cada variante
6. Las variantes aparecen en la tabla debajo

### 2. Editar Parte Existente
1. Seleccionar parte del dropdown superior
2. Se cargan datos de la parte y sus variantes
3. Editar campos necesarios
4. Click en "Actualizar Parte"

### 3. Gestionar Variantes
- **Agregar**: Completar formulario y click "Agregar"
- **Editar**: Click en botón editar (lápiz) en la tabla
- **Eliminar**: Click en botón eliminar (basura) con confirmación

## 🔄 Comparación con Vista Tradicional

| Aspecto | Vista Tradicional | Manager Moderno |
|---------|------------------|-----------------|
| **URL** | `/productos/partes` | `/productos/partes/manager` |
| **Diseño** | Tabs separados | Todo en una pantalla |
| **Recargas** | Sí (tras cada acción) | No (dinámico) |
| **UX** | Básica functional | Moderna e intuitiva |
| **Complejidad** | 542 líneas (refact. a 123) | 403 líneas total |
| **Mantenimiento** | Modular | Modular |

## 📝 Casos de Uso

### Caso 1: Alta Rápida de Producto Completo
```
Usuario necesita dar de alta "BANDOLERA CLOE" con 4 variantes de color
1. Crea la parte BANDOLERA CLOE
2. Agrega variantes: NEGRO PRAGA, NEGRO MATELASE, ROSA GRANEADO, ROSA MATELASE
3. Todo sin cambiar de página ni perder contexto
```

### Caso 2: Actualización de Stocks
```
Usuario necesita actualizar stocks de variantes
1. Selecciona la parte del dropdown
2. Ve todas las variantes en tabla
3. Edita cada variante directamente
4. Cambios se guardan individualmente
```

## 🎨 Características de UI/UX

### Código de Colores
- 🔵 **Azul**: Acciones principales (Crear)
- 🟢 **Verde**: Edición/Actualización
- 🔴 **Rojo**: Eliminación
- ⚫ **Gris**: Acciones secundarias

### Estados de Variante
- 🟢 **Activa**: bg-success
- 🟡 **En desarrollo**: bg-warning
- ⚫ **Obsoleta**: bg-secondary
- 🔴 **Descontinuada**: bg-danger

### Validaciones
- ✅ Campos requeridos marcados con asterisco (*)
- ✅ Validación HTML5 en formularios
- ✅ Confirmación en eliminaciones
- ✅ Mensajes de error descriptivos

## 🔧 Funcionalidades Técnicas

### Cálculo Automático de Dimensiones
```javascript
// Calcula superficie (m²) y volumen (cm³) desde dimensiones en mm
Superficie (m²) = (Largo × Ancho) / 1,000,000
Volumen (cm³) = (Largo × Ancho × Espesor) / 1,000
```

### Gestión de Estado con Alpine.js
- Estado centralizado en componente `parteManager()`
- Reactividad automática en formularios y tablas
- Sincronización con backend vía Fetch API

### Persistencia
- Formularios usan `POST` y `PUT` con `_method` override
- Respuestas redirect o JSON según contexto
- Recarga inteligente tras operaciones críticas

## 🚀 Mejoras Futuras Sugeridas

- [ ] Drag & drop para reordenar variantes
- [ ] Búsqueda/filtrado en tabla de variantes
- [ ] Carga de imágenes por variante
- [ ] Export a PDF/Excel
- [ ] Historial de cambios
- [ ] Clonación rápida de partes
- [ ] Campos de ubicación (Cuerpo, Pasillo, Estante)
- [ ] Campo anticipo de compra

## 📞 Soporte

Para dudas o sugerencias sobre esta funcionalidad, consultar:
- Archivo: `views/pages/admin/partes/manager.php`
- JavaScript: `public/assets/js/partes-manager.js`
- Controlador: `app/controllers/Admin/PartesVariantesController.php`

---

**Fecha de creación**: 14 de febrero de 2026
**Versión**: 1.0.0
**Estado**: ✅ Producción
