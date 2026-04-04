Usar un modelo **Qwen de pocos parámetros** es una decisión muy acertada para tareas estructuradas como crear piezas y BOMs: bajo costo, rápida inferencia, excelente cumplimiento de JSON y fácil despliegue local o en API económica.

Aquí tienes la guía técnica actualizada para integrarlo en tu MRP con PHP 8.5:

---
## 🔹 1. Qué versión de Qwen usar
| Modelo | VRAM/RAM | Rendimiento JSON | Ideal para MRP |
|--------|----------|------------------|----------------|
| `Qwen2.5-1.5B-Instruct` | ~1.5 GB | Muy bueno en tareas simples | Piezas, UOM, proveedores, BOMs planos |
| `Qwen2.5-3B-Instruct` | ~2.5 GB | Excelente equilibrio | BOMs de 2 niveles, atributos técnicos, códigos |
| `Qwen2.5-Coder-1.5B/3B` | Similar | Mejor en sintaxis estricta | Si usas plantillas JSON complejas o validaciones técnicas |

> ✅ **Recomendación:** Empieza con **`qwen2.5:1.5b`** (Ollama) o `qwen2.5-1.5b-instruct` (API). Si notas fallos en BOMs >5 componentes, pasa a `3b`.

---
## 🔹 2. Dónde ejecutarlo
| Opción | Endpoint | Costo | Privacidad | Setup |
|--------|----------|-------|------------|-------|
| **Local con Ollama** | `http://localhost:11434/v1/chat/completions` | $0 (CPU/RAM) | Total | `ollama pull qwen2.5:1.5b` |
| **DashScope (Alibaba)** | `https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions` | ~$0.15/1M tokens | Alta | API Key en portal DashScope |
| **OpenRouter / Together** | Compatible OpenAI | ~$0.20/1M tokens | Media | Selección de modelo `qwen/qwen2.5-1.5b-instruct` |

> 💡 Todos usan **API compatible con OpenAI**, por lo que tu código PHP solo cambia `model` y `Authorization`.

---
## 🔹 3. Integración en PHP 8.5 (Ejemplo funcional)
```php
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;

$client = HttpClient::create([
    'headers' => [
        'Authorization' => 'Bearer ' . $_ENV['DASHSCOPE_API_KEY'], // o '' si usas Ollama local
        'Content-Type' => 'application/json'
    ]
]);

$prompt = 'Genera la pieza "Cilindro neumático CN-40" con: tubo de acero inoxidable 304, pistón de aluminio 6061, 2 sellos Viton, vástago cromado. Código: CN-XXXX.';

$response = $client->request('POST', 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions', [
    'json' => [
        'model' => 'qwen2.5-1.5b-instruct', // o 'qwen2.5:1.5b' en Ollama
        'messages' => [
            ['role' => 'system', 'content' => 'Eres un asistente MRP. Devuelve ÚNICAMENTE un objeto JSON válido. Sin markdown, sin explicaciones.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0.1,
        'max_tokens' => 800,
        'seed' => 42 // Reproducibilidad
    ]
]);

if ($response->getStatusCode() !== 200) {
    throw new \RuntimeException('Error IA: ' . $response->getContent(false));
}

$data = $response->toArray();
$rawJson = trim($data['choices'][0]['message']['content'] ?? '');
$aiData = json_decode($rawJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    throw new \RuntimeException('IA no devolvió JSON válido: ' . json_last_error_msg());
}
```

---
## 🔹 4. Esquema JSON optimizado para Qwen pequeños
Los modelos <3B pierden coherencia con anidamientos profundos. Usa **listas planas + ensamblaje en PHP**:

```json
{
  "part_number": "CN-4012",
  "description": "Cilindro neumático CN-40",
  "type": "Componente",
  "uom": "UNI",
  "materials": ["Acero Inoxidable 304", "Aluminio 6061", "Viton"],
  "bom_items": [
    {"child_part": "TUBO-304-40", "qty": 1, "uom": "UNI"},
    {"child_part": "PIST-AL6061-40", "qty": 1, "uom": "UNI"},
    {"child_part": "SELLO-VITON-12", "qty": 2, "uom": "UNI"},
    {"child_part": "VAST-CROM-40", "qty": 1, "uom": "UNI"}
  ]
}
```
✅ Ventaja: Qwen 1.5B genera esto con >90% de precisión. La lógica de árbol la resuelves en PHP con una tabla `bom_parent_child`.

---
## 🔹 5. Validación + Reintento automático (Crítico para MRP)
```php
$validator = Validation::createValidator();
$violations = $validator->validate($aiData, new Assert\Collection([
    'part_number' => [new Assert\NotBlank(), new Assert\Regex('/^[A-Z]{2,4}-\d{3,6}$/')],
    'type' => new Assert\Choice(['MateriaPrima', 'Componente', 'ProductoFinal']),
    'bom_items' => new Assert\All([
        new Assert\Collection([
            'child_part' => new Assert\NotBlank(),
            'qty' => [new Assert\Type('numeric'), new Assert\GreaterThanOrEqual(0)]
        ])
    ])
]));

if (count($violations) > 0) {
    // Reintento con prompt corregido (máx 2 intentos)
    $errorHints = [];
    foreach ($violations as $v) $errorHints[] = $v->getMessage();
    
    $prompt .= "\n\nCORRIGE estos errores y devuelve SOLO JSON válido: " . implode('; ', $errorHints);
    // ... repetir llamada con $prompt actualizado
}
```

---
## 🔹 6. Límites de Qwen pequeño y cómo mitigarlos
| Problema | Solución en PHP + Prompt |
|----------|---------------------------|
| BOMs de >3 niveles | Genera solo nivel 1. Usa un loop PHP que llame a la IA por cada subcomponente si es necesario. |
| Inventar códigos | Define regex estricto en prompt + validación PHP. La IA solo sugiere, PHP genera si falta. |
| Unidades inconsistentes | Mantén tabla `uom_master`. La IA solo devuelve claves (`UNI`, `KG`, `M`). Valida con `Assert\Choice`. |
| Alucinación de materiales | Pide solo materiales de una lista permitida o devuelve `null` si no está seguro. |
| JSON roto | Usa `temperature: 0.1`, `seed`, y fallback a `json_decode` con retry. Si falla, devuelve error humano. |

---
## 🔹 7. Flujo seguro recomendado para tu MRP
1. Usuario ingresa descripción técnica o sube ficha PDF/Excel.
2. PHP extrae texto → envía a Qwen 1.5B/3B con schema plano.
3. PHP valida JSON + reglas de negocio (códigos, UOM, stock mínimo, lead time).
4. Si pasa validación → muestra **vista previa** al usuario.
5. Usuario aprueba → PHP inserta en `parts` y `bom_lines` dentro de transacción.
6. Loguea: `ai_model`, `prompt_hash`, `validation_log`, `user_id`, `approved_at`.

---
## 📦 ¿Qué necesitas para empezar hoy?
Dime cuál de estos prefieres y te lo genero listo para copiar/pegar:
1. `docker-compose.yml` + Ollama con Qwen2.5:1.5b expuesto a PHP
2. Clase PHP `QwenMRPAssistant.php` con retry, validación y logging
3. Prompt templates optimizados para: piezas, BOMs, materias primas, proveedores
4. Algoritmo PHP para detectar referencias circulares en BOMs antes de insertar

Los modelos pequeños como Qwen 1.5B/3B son perfectamente viables para MRP si **desacoplas la generación de la estructura jerárquica** y dejas que PHP maneje el árbol, las validaciones y la integridad referencial.


Aquí tienes una guía completa, lista para producción, para desplegar **Ollama + Qwen 2.5 (1.5B/3B)** localmente con Docker y conectarlo de forma segura a tu MRP en PHP 8.5.

---
## 📦 1. Requisitos mínimos
| Componente | Recomendación |
|------------|---------------|
| **RAM** | 4 GB mínimo (1.5B) / 6 GB (3B) |
| **CPU** | 2+ núcleos con soporte AVX2 |
| **SO** | Linux / macOS / Windows (WSL2 recomendado) |
| **Docker** | v24+ con Docker Compose v2 |
| **Espacio** | ~1.2 GB por modelo (formato GGUF Q4_K_M) |

> 💡 No necesitas GPU para estos tamaños. Qwen 1.5B/3B corre fluido en CPU moderna (~150-400ms por respuesta con `num_ctx=4096`).

---
## 🐳 2. `docker-compose.yml` optimizado
```yaml
version: '3.8'

services:
  ollama:
    image: ollama/ollama:latest
    container_name: ollama-mrp
    ports:
      - "127.0.0.1:11434:11434"  # Solo localhost por seguridad
    volumes:
      - ./ollama_/root/.ollama
    environment:
      - OLLAMA_HOST=0.0.0.0
      - OLLAMA_NUM_PARALLEL=2        # Máx 2 requests concurrentes
      - OLLAMA_KEEP_ALIVE=24h        # Mantiene modelo en RAM tras uso
    restart: unless-stopped
    # Descomenta si usas NVIDIA y quieres aceleración por GPU:
    # deploy:
    #   resources:
    #     reservations:
    #       devices:
    #         - driver: nvidia
    #           count: all
    #           capabilities: [gpu]
```

---
## 🚀 3. Inicialización rápida
```bash
# 1. Levanta el servicio
docker compose up -d

# 2. Descarga el modelo (1.5B es ideal para BOMs planos y piezas)
docker exec ollama-mrp ollama pull qwen2.5:1.5b

# O si prefieres más precisión en estructuras complejas:
# docker exec ollama-mrp ollama pull qwen2.5:3b

# 3. Verifica que está corriendo
curl http://localhost:11434/api/tags
```
> ✅ La primera descarga tardará ~30-60s. Quedará cacheada en `./ollama_data`.

---
## 🔌 4. Conectividad desde PHP 8.5
Ollama expone un **endpoint compatible con OpenAI** en `/v1/chat/completions`. No requiere API Key.

```php
use Symfony\Component\HttpClient\HttpClient;

$client = HttpClient::create([
    'timeout' => 30,
    'max_duration' => 45,
]);

$endpoint = 'http://127.0.0.1:11434/v1/chat/completions';

$response = $client->request('POST', $endpoint, [
    'json' => [
        'model' => 'qwen2.5:1.5b',
        'messages' => [
            ['role' => 'system', 'content' => 'Eres un asistente MRP. Devuelve ÚNICAMENTE un objeto JSON válido. Sin markdown, sin explicaciones.'],
            ['role' => 'user',   'content' => 'Crea la pieza "Cilindro neumático CN-40" con: tubo acero inox 304, pistón aluminio 6061, 2 sellos Viton. Código: CN-XXXX.']
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0.1,
        'seed' => 42,
        'num_ctx' => 4096
    ]
]);

if ($response->getStatusCode() !== 200) {
    throw new \RuntimeException('Ollama error: ' . $response->getContent(false));
}

$data = $response->toArray();
$rawJson = trim($data['choices'][0]['message']['content'] ?? '');
$part = json_decode($rawJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    throw new \RuntimeException('JSON inválido de IA: ' . json_last_error_msg());
}

// ✅ Ahora valida con Symfony Validator (como en el ejemplo anterior)
```

> 🔁 Si tu PHP corre **dentro de Docker**, cambia `http://127.0.0.1:11434` por `http://ollama:11434` y añade la red compartida en `docker-compose.yml`.

---
## ⚙️ 5. Optimizaciones para Qwen pequeño en MRP
| Parámetro | Valor recomendado | Por qué |
|-----------|-------------------|---------|
| `num_ctx` | `4096` | Suficiente para prompt + BOM plano. Menos memoria RAM. |
| `temperature` | `0.1` | Minimiza variabilidad y JSON roto. |
| `response_format` | `json_object` | Qwen 2.5 lo soporta nativamente. Evita `json_schema` (experimental en <7B). |
| `OLLAMA_NUM_PARALLEL` | `2` | Evita saturar CPU si varios usuarios generan piezas simultáneamente. |
| `OLLAMA_KEEP_ALIVE` | `24h` | Mantiene el modelo en RAM tras la primera carga. Respuestas en <200ms. |

### 📐 Estrategia BOM con PHP (ya que el árbol lo manejas tú)
1. La IA genera **solo nivel 1** (`bom_items` plano).
2. PHP guarda `part` + `bom_lines` en tablas relacionales.
3. Si un componente tiene sub-BOM, PHP lanza otra llamada recursiva (con límite de profundidad `MAX_BOM_DEPTH = 5`).
4. Algoritmo DFS en PHP detecta ciclos antes del `COMMIT`.

---
## ✅ 6. Verificación rápida
```bash
# Test de JSON estructurado
curl http://127.0.0.1:11434/v1/chat/completions \
  -H "Content-Type: application/json" \
  -d '{
    "model": "qwen2.5:1.5b",
    "messages": [{"role": "user", "content": "Devuelve solo JSON: {\"part\":\"MOTOR-01\",\"qty\":2}"}],
    "response_format": {"type": "json_object"},
    "temperature": 0.1
  }'
```
Debe devolver:
```json
{"choices":[{"message":{"content":"{\"part\":\"MOTOR-01\",\"qty\":2}"}}]}
```

---
## 🛡️ 7. Notas de producción
| Aspecto | Recomendación |
|---------|---------------|
| **No exponer `0.0.0.0`** | Usa `127.0.0.1` o reverse proxy con auth. |
| **Timeouts** | PHP `timeout: 30` + `max_duration: 45`. Reintenta 1 vez si falla. |
| **Logging** | Guarda `prompt_hash`, `model_version`, `response_time`, `validation_result`. |
| **Fallback** | Si `json_decode` falla o validación rechaza, muestra formulario pre-llenado para corrección manual. |
| **Actualizaciones** | `docker compose pull && docker compose up -d` semanal. Los modelos no se borran al actualizar. |

---
## 📥 ¿Qué sigue?
Si quieres, te genero:
1. `MrpAiClient.php` clase completa con retry, validación, logging y detección de ciclos BOM.
2. Prompt templates optimizados para: `Crear Pieza`, `Extender BOM Nivel N`, `Extraer Materia Prima de Ficha`.
3. Script de migración para tabla `bom_lines` con índices y constraints para integridad referencial.

Dime cuál prefieres y lo entrego listo para tu stack PHP 8.5 + Symfony HttpClient.
