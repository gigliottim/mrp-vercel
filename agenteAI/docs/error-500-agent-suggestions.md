# Plan de Corrección - Error 500 en /api/v1/agent/suggestions

## Resumen del Problema

El endpoint `/api/v1/agent/suggestions` está retornando un error 500 (Internal Server Error) en producción (`https://mimrp.com.ar`), aunque el código local funciona correctamente.

## Análisis Realizado

### Archivos Verificados (Producción vs Local)

1. **Configuración (`config/agent_ai.php`)**:
   - ✅ Existe en producción
   - ✅ Sin errores de sintaxis
   - ✅ Contiene la sección `suggestions` con 4 propuestas predefinidas

2. **Controlador (`app/controllers/agent_AgentController.php`)**:
   - ✅ Existe en producción
   - ✅ Sin errores de sintaxis
   - ✅ Ya tiene la corrección aplicada (usa `config('agent_ai', [])` con valor por defecto)

3. **Rutas (`routes/api.php`)**:
   - ✅ Existe en producción
   - ✅ Sin errores de sintaxis
   - ✅ Ruta correctamente definida: `GET /api/v1/agent/suggestions`

4. **Helpers (`bootstrap/helpers.php`)**:
   - ✅ Existe en producción
   - ✅ Sin errores de sintaxis
   - ✅ Función `config()` correctamente definida

5. **Config (`app/core/Config/Config.php`)**:
   - ✅ Existe en producción
   - ✅ Sin errores de sintaxis
   - ✅ Método `get()` correctamente implementado

6. **Bootstrap (`bootstrap/app.php`)**:
   - ✅ Existe en producción
   - ✅ Configuración cargada correctamente

7. **Entorno (`.env`)**:
   - ⚠️ No hay variables `AGENT_AI_*` definidas
   - ✅ La configuración usa valores por defecto

## Diagnóstico

El problema no es de sintaxis ni de código faltante. Los archivos en producción tienen la misma versión que el local (con la corrección aplicada). Posibles causas:

1. **Cache de OPcache**: PHP puede estar usando una versión en caché del código
2. **Error en tiempo de ejecución**: El error 500 puede ser causado por una excepción no controlada en la configuración o base de datos
3. **Problema con Valkey/Redis**: El sistema de caché puede estar fallando
4. **Error en la configuración de base de datos**: La conexión a la base de datos puede estar fallando al intentar cargar configuración

## Plan de Corrección

### Paso 1: Verificar Logs de Error en Producción

Ejecutar en producción:
```bash
docker compose -C /opt/mrp logs -f php-fpm
```

Buscar errores relacionados con:
- Configuración
- Base de datos
- Valkey/Redis
- Excepciones no controladas

### Paso 2: Verificar Estado de Valkey

Ejecutar en producción:
```bash
docker compose -C /opt/mrp exec valkey valkey-cli ping
```

Si no responde, reiniciar:
```bash
docker compose -C /opt/mrp up -d valkey
```

### Paso 3: Limpiar Cache de OPcache

Ejecutar en producción:
```bash
docker compose -C /opt/mrp exec php-fpm sh -c "php -r 'opcache_reset();'"
```

### Paso 4: Verificar Configuración de Base de Datos

Ejecutar en producción:
```bash
docker compose -C /opt/mrp exec php-fpm php -r "
require '/opt/mrp/bootstrap/autoload.php';
require '/opt/mrp/bootstrap/helpers.php';
\\App\\Core\\Config\\Config::load('/opt/mrp/config');
echo 'Config loaded successfully\n';
echo 'Agent AI config: ' . (\\App\\Core\\Config\\Config::has('agent_ai') ? 'exists' : 'not found') . '\n';
"
```

### Paso 5: Probar el Endpoint

Ejecutar en producción:
```bash
curl -v https://mimrp.com.ar/api/v1/agent/suggestions
```

## Plan de Implementación

### Opción A: Desplegar Nuevamente (Recomendada)

1. Verificar que el código local esté actualizado
2. Ejecutar el script de deploy:
   ```bash
   ./deploy.sh
   ```
3. Verificar que el despliegue sea exitoso
4. Probar el endpoint en producción

### Opción B: Corrección Manual Rápida

Si el deploy no resuelve el problema:

1. Acceder al servidor por SSH
2. Verificar logs de error
3. Limpiar cache de OPcache
4. Reiniciar contenedores si es necesario

## Verificación Post-Corrección

1. Acceder a `https://mimrp.com.ar/dashboard`
2. Verificar que el agente muestre "Fuera de Linea" (si no hay configuración)
3. Verificar que `/api/v1/agent/suggestions` retorne JSON válido
4. Verificar que `/api/v1/agent/config` retorne estado correcto

## Archivos Modificados

- `app/controllers/agent_AgentController.php` (corrección aplicada localmente)
  - Línea 96-105: Método `getSuggestions()` con valor por defecto

## Notas Adicionales

- El error 500 puede ser causado por una excepción no controlada en la configuración
- El sistema usa configuración por defecto si no hay variables de entorno
- El agente muestra "En Linea" en el frontend pero los endpoints fallan, lo que indica un problema de sincronización entre frontend y backend
