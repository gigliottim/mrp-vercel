# 🔍 Sistema de Búsqueda Modular - SearchClient

## 📋 Descripción

Sistema de búsqueda reutilizable y modular que sigue principios SOLID. Permite realizar búsquedas asíncronas en múltiples contextos de la aplicación con configuración flexible.

---

## 🏗️ Arquitectura

### Backend (PHP)

```
app/
├── Repositories/
│   └── SearchRepository.php      # Acceso a datos (queries SQL)
├── services/
│   └── SearchService.php          # Lógica de negocio
└── controllers/Api/
    └── SearchController.php       # API endpoints livianos
```

### Frontend (JavaScript)

```
public/assets/js/modules/
└── SearchClient.js                # Módulo reutilizable
```

### API Routes

```
POST /api/v1/search/variantes
GET  /api/v1/search/variantes/:id
POST /api/v1/search/tipos-partes
POST /api/v1/search/centros-trabajo
```

---

## 🚀 Uso Básico

### 1. Incluir el módulo

```html
<script src="<?= asset('js/modules/SearchClient.js') ?>"></script>
```

### 2. HTML requerido

```html
<!-- Input de búsqueda -->
<input type="text" 
       id="search-input" 
       placeholder="Buscar..." 
       autocomplete="off">

<!-- Input oculto para el valor seleccionado (opcional) -->
<input type="hidden" id="hidden-value" name="id_item">

<!-- Contenedor de resultados -->
<div id="search-results" class="list-group"></div>
```

### 3. Inicializar SearchClient

```javascript
document.addEventListener('DOMContentLoaded', function() {
    const searchClient = new SearchClient({
        endpoint: '/api/v1/search/variantes',
        inputElement: document.getElementById('search-input'),
        resultsContainer: document.getElementById('search-results'),
        hiddenInput: document.getElementById('hidden-value'), // Opcional
        minChars: 2,
        debounceDelay: 300,
        maxResults: 10
    });
});
```

---

## ⚙️ Configuración

### Opciones del Constructor

| Opción | Tipo | Requerido | Default | Descripción |
|--------|------|-----------|---------|-------------|
| `endpoint` | string | ✅ | - | URL del API endpoint |
| `inputElement` | HTMLElement | ✅ | - | Input de búsqueda |
| `resultsContainer` | HTMLElement | ✅ | - | Contenedor de resultados |
| `hiddenInput` | HTMLElement | ❌ | null | Input oculto para valor |
| `minChars` | number | ❌ | 2 | Caracteres mínimos |
| `debounceDelay` | number | ❌ | 300 | Delay en milisegundos |
| `maxResults` | number | ❌ | 10 | Máximo de resultados |
| `format` | string | ❌ | 'standard' | Formato de datos |
| `filters` | object | ❌ | {} | Filtros adicionales |
| `onSelect` | function | ❌ | null | Callback al seleccionar |
| `renderItem` | function | ❌ | null | Renderizado custom |

---

## 📝 Ejemplos Avanzados

### Con Filtros Dinámicos

```javascript
const searchClient = new SearchClient({
    endpoint: '/api/v1/search/variantes',
    inputElement: document.getElementById('material-search'),
    resultsContainer: document.getElementById('material-results'),
    hiddenInput: document.getElementById('id_material'),
    filters: {
        tipo_codigo: 'MP',        // Solo materia prima
        exclude_ids: [1, 5, 10]   // Excluir IDs específicos
    },
    onSelect: (item) => {
        console.log('Seleccionado:', item);
        // Lógica adicional
    }
});

// Actualizar filtros dinámicamente
function changeFilter(newType) {
    searchClient.updateFilters({
        tipo_codigo: newType
    });
}
```

### Renderizado Personalizado

```javascript
const searchClient = new SearchClient({
    endpoint: '/api/v1/search/variantes',
    inputElement: document.getElementById('search-input'),
    resultsContainer: document.getElementById('search-results'),
    renderItem: (item, index) => {
        const div = document.createElement('div');
        div.className = 'custom-result-item';
        div.innerHTML = `
            <span class="code">${item.codigo_variante}</span>
            <span class="detail">${item.detalle}</span>
            <span class="badge">${item.tipo_codigo}</span>
        `;
        return div;
    }
});
```

### Con Alpine.js

```html
<div x-data="{ searchClient: null }" x-init="initSearch()">
    <input type="text" id="alpine-search">
    <div id="alpine-results"></div>
</div>

<script>
function initSearch() {
    this.searchClient = new SearchClient({
        endpoint: '/api/v1/search/variantes',
        inputElement: document.getElementById('alpine-search'),
        resultsContainer: document.getElementById('alpine-results'),
        onSelect: (item) => {
            // Actualizar estado de Alpine
            this.$dispatch('item-selected', item);
        }
    });
}
</script>
```

---

## 🎯 Métodos Públicos

### `updateFilters(newFilters)`
Actualiza los filtros y re-ejecuta la búsqueda si existe query.

```javascript
searchClient.updateFilters({
    tipo_codigo: 'PT',
    exclude_ids: [20, 30]
});
```

### `clear()`
Limpia el input y oculta resultados.

```javascript
searchClient.clear();
```

### `destroy()`
Destruye el componente y limpia event listeners.

```javascript
searchClient.destroy();
```

---

## 🔌 API Backend

### Request Format

```json
POST /api/v1/search/variantes
{
    "query": "motor",
    "filters": {
        "tipo_codigo": "PT",
        "exclude_ids": [1, 5, 10]
    },
    "limit": 10,
    "format": "standard"
}
```

### Response Format

```json
{
    "success": true,
    "message": "Se encontraron 5 resultado(s)",
    "results": [
        {
            "id": 123,
            "codigo_variante": "MOT-001-A",
            "numero_parte": "PN-123",
            "tipo_codigo": "PT",
            "tipo_nombre": "Producto Terminado",
            "detalle": "Motor eléctrico 220V",
            "display_text": "[PT] MOT-001-A - Motor eléctrico 220V"
        }
    ],
    "count": 5
}
```

### Formatos Disponibles

- **`standard`**: Balance entre información y tamaño (default)
- **`compact`**: Solo datos esenciales (id, label, tipo)
- **`detailed`**: Información completa con descripciones

---

## 🎨 Estilos CSS

El módulo espera clases de Bootstrap 5 por defecto:

```css
.list-group { }
.list-group-item { }
.list-group-item-action { }
.badge { }
```

Puedes personalizar con CSS propio:

```css
#search-results {
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border-radius: 0.375rem;
}

#search-results .list-group-item:hover {
    background-color: #f8f9fa;
}
```

---

## ⌨️ Navegación por Teclado

- **Flecha Abajo**: Siguiente resultado
- **Flecha Arriba**: Resultado anterior
- **Enter**: Seleccionar resultado resaltado
- **Escape**: Cerrar resultados

---

## 🔒 Seguridad

✅ **XSS Prevention**: El módulo usa `escapeHtml()` para sanitizar outputs  
✅ **SQL Injection**: Los repositorios usan prepared statements  
✅ **Input Validation**: El servicio valida longitud mínima  
✅ **CORS**: El controlador maneja headers apropiados

---

## 🧪 Testing

### Test Manual

```javascript
// 1. Cargar página con SearchClient
// 2. Escribir "motor" en el input
// 3. Verificar que aparezcan resultados
// 4. Usar flechas para navegar
// 5. Presionar Enter para seleccionar
// 6. Verificar que el hidden input tenga el ID correcto
```

### Test con Filtros

```javascript
// 1. Aplicar filtro de tipo "MP"
// 2. Buscar "acero"
// 3. Verificar que solo aparezcan materias primas
// 4. Cambiar a filtro "PT"
// 5. Verificar que re-busque automáticamente
```

---

## 📦 Dependencias

- **Bootstrap 5**: Para estilos de list-group (opcional)
- **Font Awesome**: Para iconos (opcional)
- **Fetch API**: Para requests HTTP (incluido en navegadores modernos)

---

## 🐛 Troubleshooting

### Los resultados no aparecen

- ✅ Verificar que el endpoint esté correcto
- ✅ Revisar la consola del navegador para errores
- ✅ Confirmar que el API devuelve JSON válido
- ✅ Verificar que `minChars` no sea muy alto

### Los filtros no funcionan

- ✅ Llamar a `updateFilters()` después de cambiar
- ✅ Verificar que el backend procese los filtros
- ✅ Revisar que `tipo_codigo` coincida con valores en DB

### El input oculto no se actualiza

- ✅ Verificar que `hiddenInput` esté configurado
- ✅ Confirmar que el elemento exista en el DOM
- ✅ Usar `onSelect` callback para debugging

---

## 📚 Ejemplos de Uso en el Proyecto

### 1. Búsqueda de Materiales (index.php)

```javascript
searchClient = new SearchClient({
    endpoint: '/api/v1/search/variantes',
    inputElement: document.getElementById('search-material'),
    resultsContainer: document.getElementById('search-results'),
    hiddenInput: document.getElementById('id_material'),
    filters: {
        exclude_ids: [selectedVarianteId]
    }
});
```

### 2. Modal de Composición (maestro.php + Alpine.js)

```javascript
async performSearch(query) {
    const response = await fetch('/api/v1/search/variantes', {
        method: 'POST',
        body: JSON.stringify({
            query: query,
            filters: {
                tipo_codigo: this.activeFilter,
                exclude_ids: this.forbiddenIds
            }
        })
    });
    // ... procesar respuesta
}
```

---

## 🔄 Mantenimiento

### Agregar Nuevo Endpoint de Búsqueda

1. **Repository**: Agregar método en `SearchRepository.php`
2. **Service**: Agregar método en `SearchService.php`
3. **Controller**: Agregar método en `SearchController.php`
4. **Routes**: Registrar en `routes/api.php`

### Ejemplo: Búsqueda de Clientes

```php
// SearchRepository.php
public function searchClientes(string $query, array $filters = []): array {
    $sql = "SELECT id, razon_social, cuit FROM clientes WHERE razon_social ILIKE :query LIMIT 10";
    // ...
}

// SearchService.php
public function searchClientes(string $query): array {
    return $this->repository->searchClientes($query);
}

// SearchController.php
public function searchClientes(): void {
    $data = $this->getJsonInput();
    $result = $this->searchService->searchClientes($data['query']);
    $this->jsonResponse($result);
}

// routes/api.php
$router->post('/api/v1/search/clientes', [SearchController::class, 'searchClientes']);
```

---

## 📄 Licencia

Este módulo es parte del sistema MRP y sigue las mismas políticas de licencia del proyecto principal.

---

## 👥 Contribuciones

Para agregar funcionalidades o reportar bugs:

1. Documentar el caso de uso
2. Seguir principios SOLID
3. Mantener compatibilidad con implementaciones existentes
4. Agregar ejemplos de uso en este README
