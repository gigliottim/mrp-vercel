# 🏗️ Arquitectura del Sistema de Búsqueda

## 📊 Diagrama de Flujo

```
┌─────────────────────────────────────────────────────────────────────┐
│                           FRONTEND (Browser)                         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  ┌───────────────────┐                                               │
│  │  SearchClient.js  │  ← Módulo Reutilizable (SOLID)               │
│  │                   │                                               │
│  │  - handleInput()  │     Responsabilidades:                        │
│  │  - search()       │     • Debouncing                              │
│  │  - render()       │     • HTTP Requests                           │
│  │  - selectItem()   │     • Rendering                               │
│  │  - updateFilters()│     • Event Handling                          │
│  └─────┬─────────────┘     • Navigation (keyboard)                   │
│        │                                                              │
│        │  fetch()                                                     │
│        ▼                                                              │
└────────┼──────────────────────────────────────────────────────────────┘
         │
         │  POST /api/v1/search/variantes
         │  { query, filters, limit, format }
         │
┌────────▼──────────────────────────────────────────────────────────────┐
│                          BACKEND (PHP/MVC)                            │
├───────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  ┌─────────────────────────────────────────────────────────────┐     │
│  │              routes/api.php (Routing Layer)                  │     │
│  │  POST /api/v1/search/variantes → SearchController           │     │
│  │  POST /api/v1/search/tipos-partes → SearchController        │     │
│  └──────────────────────┬──────────────────────────────────────┘     │
│                         │                                             │
│                         ▼                                             │
│  ┌─────────────────────────────────────────────────────────────┐     │
│  │      app/controllers/Api/SearchController.php               │     │
│  │      (Controlador Liviano - Solo Orquestación)              │     │
│  │                                                              │     │
│  │  public function searchVariantes(): void {                  │     │
│  │    $data = $this->getJsonInput();                           │     │
│  │    $result = $this->searchService->searchVariantes(...);    │     │
│  │    $this->jsonResponse($result);                            │     │
│  │  }                                                           │     │
│  └──────────────────────┬──────────────────────────────────────┘     │
│                         │                                             │
│                         ▼                                             │
│  ┌─────────────────────────────────────────────────────────────┐     │
│  │          app/services/SearchService.php                      │     │
│  │          (Lógica de Negocio)                                 │     │
│  │                                                              │     │
│  │  - Validación de entrada (min 2 chars)                       │     │
│  │  - Formateo de resultados                                    │     │
│  │  - Aplicación de reglas de negocio                          │     │
│  │  - Transformaciones de datos                                │     │
│  │                                                              │     │
│  │  Formatos disponibles:                                       │     │
│  │    • standard   → balance info/tamaño                        │     │
│  │    • compact    → mínimo esencial                            │     │
│  │    • detailed   → información completa                       │     │
│  └──────────────────────┬──────────────────────────────────────┘     │
│                         │                                             │
│                         ▼                                             │
│  ┌─────────────────────────────────────────────────────────────┐     │
│  │       app/Repositories/SearchRepository.php                  │     │
│  │       (Acceso a Datos - Solo SQL)                            │     │
│  │                                                              │     │
│  │  public function searchVariantes($query, $filters): array {  │     │
│  │    // Prepared statements                                    │     │
│  │    // Filtrado por tipo                                      │     │
│  │    // Exclusión de IDs (ciclos)                              │     │
│  │    // Joins optimizados                                      │     │
│  │    return $results;                                          │     │
│  │  }                                                           │     │
│  └──────────────────────┬──────────────────────────────────────┘     │
│                         │                                             │
│                         ▼                                             │
└────────────────────────┼─────────────────────────────────────────────┘
                         │
                         │  SQL Query
                         ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        DATABASE (PostgreSQL)                         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  SELECT v.id, v.codigo_variante, v.detalle,                         │
│         p.numero_parte, p.detalle AS parte_detalle,                 │
│         tp.codigo AS tipo_codigo, tp.nombre AS tipo_nombre          │
│  FROM variantes v                                                    │
│  INNER JOIN partes p ON v.id_parte = p.id                           │
│  LEFT JOIN tipos_partes tp ON p.id_tipo = tp.id                     │
│  WHERE (v.codigo_variante ILIKE :query                              │
│         OR p.numero_parte ILIKE :query                              │
│         OR v.detalle ILIKE :query)                                  │
│    AND tp.codigo = :tipo_codigo  -- Filtro opcional                 │
│    AND v.id NOT IN (:exclude_ids) -- Prevenir ciclos                │
│  ORDER BY v.codigo_variante                                         │
│  LIMIT :limit                                                       │
│                                                                       │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 🔄 Flujo de Datos Completo

### 1. Usuario Escribe en el Input

```
Usuario → Input → SearchClient.handleInput()
  ↓
  Debounce (300ms)
  ↓
  SearchClient.search()
```

### 2. Request HTTP al Backend

```
fetch('/api/v1/search/variantes', {
  method: 'POST',
  body: JSON.stringify({
    query: 'motor',
    filters: { tipo_codigo: 'PT', exclude_ids: [1, 5] },
    limit: 10,
    format: 'standard'
  })
})
```

### 3. Procesamiento en el Backend

```
API Route → SearchController.searchVariantes()
  ↓
  getJsonInput() - Parsea JSON
  ↓
  SearchService.searchVariantes()
    ↓
    Valida query (min 2 chars)
    ↓
    SearchRepository.searchVariantes()
      ↓
      Construye SQL con filtros
      ↓
      Ejecuta Prepared Statement
      ↓
      Retorna resultados crudos
    ↓
    Formatea según 'format' (standard/compact/detailed)
    ↓
    Retorna array estructurado
  ↓
  jsonResponse() - Envía JSON al cliente
```

### 4. Render en el Frontend

```
SearchClient recibe respuesta JSON
  ↓
  this.currentResults = data.results
  ↓
  this.render()
    ↓
    Itera results
    ↓
    Llama renderItem() por cada uno
    ↓
    Agrega event listeners
    ↓
    Muestra contenedor
```

### 5. Usuario Selecciona Item

```
Click en item → selectItem(item)
  ↓
  Actualiza inputElement.value
  ↓
  Actualiza hiddenInput.value (si existe)
  ↓
  Ejecuta onSelect callback (si existe)
  ↓
  Oculta resultados
```

---

## 🎯 Principios SOLID Aplicados

### **S** - Single Responsibility

- **SearchClient**: Solo manejo de UI y eventos
- **SearchController**: Solo orquestación HTTP
- **SearchService**: Solo lógica de negocio
- **SearchRepository**: Solo queries SQL

### **O** - Open/Closed

- Extensible: Se pueden agregar nuevos formatos sin modificar código existente
- Cerrado: La lógica core está encapsulada

### **L** - Liskov Substitution

- SearchRepository puede ser sustituido por MockSearchRepository para testing
- SearchService puede usar diferentes repositorios

### **I** - Interface Segregation

- Interfaces específicas por responsabilidad
- Clientes solo dependen de lo que necesitan

### **D** - Dependency Inversion

```php
// ✅ Correcto: Inyección de dependencias
class SearchService {
    public function __construct(SearchRepository $repository) {
        $this->repository = $repository;
    }
}

// ❌ Incorrecto: Dependencia directa
class SearchService {
    public function __construct() {
        $this->repository = new SearchRepository(); // Acoplamiento fuerte
    }
}
```

---

## 📦 Estructura de Archivos

```
mrp/
├── app/
│   ├── Repositories/
│   │   └── SearchRepository.php       # Capa de datos
│   ├── services/
│   │   └── SearchService.php           # Lógica de negocio
│   └── controllers/
│       └── Api/
│           └── SearchController.php    # Capa HTTP
├── public/
│   └── assets/
│       └── js/
│           └── modules/
│               └── SearchClient.js     # Módulo frontend
├── routes/
│   └── api.php                         # Definición de rutas
├── docs/
│   ├── SearchClient-README.md          # Documentación
│   ├── SearchClient-examples.html      # Ejemplos interactivos
│   └── SearchClient-architecture.md    # Este archivo
└── views/
    └── pages/
        └── productos/
            └── composicion/
                ├── index.php           # Implementación 1
                └── maestro.php         # Implementación 2
```

---

## 🔐 Seguridad

### XSS Prevention

```javascript
// SearchClient.js
escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || ''; // textContent escapa automáticamente
    return div.innerHTML;
}
```

### SQL Injection Prevention

```php
// SearchRepository.php
$stmt = $this->connection->prepare($sql);
$stmt->bindValue(':query', "%{$query}%");  // Prepared statements
$stmt->execute();
```

### Input Validation

```php
// SearchService.php
if (strlen($query) < 2) {
    return ['success' => false, 'message' => 'Query muy corta'];
}
```

### CORS Headers

```php
// SearchController.php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
```

---

## 🚀 Optimizaciones

### Frontend

- **Debouncing**: Evita requests excesivos (300ms)
- **Caching**: Resultados en `currentResults` para re-render
- **Lazy Loading**: Solo carga cuando se necesita
- **Event Delegation**: Un listener para múltiples items

### Backend

- **Prepared Statements**: Reutilización de queries
- **Índices DB**: En columnas de búsqueda
- **Limit**: Máximo de resultados por query
- **ILIKE**: Case-insensitive optimizado

### Database

```sql
-- Índices recomendados
CREATE INDEX idx_variantes_codigo ON variantes (codigo_variante);
CREATE INDEX idx_partes_numero ON partes (numero_parte);
CREATE INDEX idx_variantes_detalle_gin ON variantes USING gin(to_tsvector('spanish', detalle));
```

---

## 📊 Métricas de Performance

| Métrica | Valor Esperado | Crítico Si |
|---------|---------------|------------|
| Tiempo de respuesta API | < 100ms | > 500ms |
| Size del response JSON | < 10KB | > 50KB |
| Tiempo de render | < 50ms | > 200ms |
| Debounce delay | 300ms | N/A |

---

## 🧪 Testing

### Unit Tests (Backend)

```php
// tests/Unit/SearchServiceTest.php
public function test_valida_query_minima() {
    $service = new SearchService($this->mockRepository);
    $result = $service->searchVariantes('a'); // Solo 1 char
    
    $this->assertFalse($result['success']);
    $this->assertStringContainsString('al menos 2', $result['message']);
}
```

### Integration Tests

```php
// tests/Integration/SearchApiTest.php
public function test_search_variantes_endpoint() {
    $response = $this->post('/api/v1/search/variantes', [
        'query' => 'motor',
        'limit' => 5
    ]);
    
    $this->assertEquals(200, $response->getStatusCode());
    $data = json_decode($response->getBody(), true);
    $this->assertTrue($data['success']);
}
```

### E2E Tests (Frontend)

```javascript
// tests/e2e/search.spec.js
describe('SearchClient', () => {
    it('debería buscar y seleccionar un item', async () => {
        await page.type('#search-input', 'motor');
        await page.waitForSelector('.list-group-item');
        await page.click('.list-group-item:first-child');
        
        const hiddenValue = await page.$eval('#hidden-input', el => el.value);
        expect(hiddenValue).toBeTruthy();
    });
});
```

---

## 🔮 Extensiones Futuras

### 1. Búsqueda con Autocompletado Inteligente

```javascript
// Sugerencias basadas en historial
const searchClient = new SearchClient({
    suggestions: true,
    suggestionsProvider: async () => {
        return await fetchRecentSearches();
    }
});
```

### 2. Búsqueda Fuzzy

```php
// SearchRepository.php
// Usar similarity() de PostgreSQL
$sql = "SELECT *, similarity(codigo_variante, :query) as sim
        FROM variantes
        WHERE similarity(codigo_variante, :query) > 0.3
        ORDER BY sim DESC";
```

### 3. Búsqueda Multi-entidad

```javascript
const multiSearch = new SearchClient({
    endpoints: {
        variantes: '/api/v1/search/variantes',
        clientes: '/api/v1/search/clientes',
        proveedores: '/api/v1/search/proveedores'
    },
    searchIn: 'variantes' // Default
});
```

---

## 📚 Referencias

- [Principios SOLID](https://en.wikipedia.org/wiki/SOLID)
- [Fetch API MDN](https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API)
- [PostgreSQL Full-Text Search](https://www.postgresql.org/docs/current/textsearch.html)
- [REST API Best Practices](https://restfulapi.net/)

---

**Versión**: 1.0.0  
**Fecha**: Febrero 2026  
**Mantenedor**: MRP Development Team
