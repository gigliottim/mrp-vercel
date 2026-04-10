# 🔴 URGENTE — Fix Deploy: Agente AI no funciona en producción

> **Creado:** 4 de abril de 2026
> **Causa:** El `deploy.sh` NO incluye `agenteAI/` y las migraciones están como `.bkp`

---

## Problemas Identificados

| # | Problema | Impacto | Gravedad |
|---|----------|---------|----------|
| P1 | `agenteAI/` **NO** está en `RELEASE_ITEMS` del deploy.sh | El VPS nunca recibe los archivos del agente | 🔴 CRÍTICO |
| P2 | Migraciones del agente renombradas a `.bkp` | Las tablas `agent_conversations`, `agent_messages`, `agent_ai_logs` no se crean | 🔴 CRÍTICO |
| P3 | `routes/api.php` y `routes/web.php` referencian `App\AgenteAI\Backend\Controllers\AgentController` que no existe en el VPS | Error 500 inmediato | 🔴 CRÍTICO |
| P4 | `bootstrap/autoload.php` mapea `App\AgenteAI\*` → `agenteAI/backend/` que no existe en el VPS | Class not found | 🔴 CRÍTICO |
| P5 | `public/agenteAI/frontend/` con JS/CSS no se sube al VPS | Assets 404 | 🟠 ALTA |

### Cadena de fallo

```
Browser → GET /api/v1/agent/config
         → routes/api.php usa AgentController::class
         → autoload intenta cargar agenteAI/backend/controllers/AgentController.php
         → archivo NO EXISTE en VPS (no se incluyó en deploy)
         → Fatal Error: Class not found
         → 500 Internal Server Error
```

---

## Plan de Fix

### Fix 1: Agregar `agenteAI` a `RELEASE_ITEMS` en deploy.sh

```bash
# Cambiar esta línea en deploy.sh:
RELEASE_ITEMS=(app bootstrap config database/migrations migrate_database.php public routes views vendor composer.json composer.lock .env)

# A:
RELEASE_ITEMS=(app bootstrap config database/migrations migrate_database.php public routes views vendor composer.json composer.lock .env agenteAI)
```

### Fix 2: Restaurar migraciones `.sql` (quitar `.bkp`)

```bash
# En local:
cd database/migrations
for f in 2026_04_03_*.bkp; do
  mv "$f" "${f%.bkp}"
done
```

Esto restaura:
- `2026_04_03_001_create_agent_conversations.sql`
- `2026_04_03_002_create_agent_messages.sql`
- `2026_04_03_003_create_agent_ai_logs.sql`
- `2026_04_03_004_add_agent_ai_menu_item.sql`

### Fix 3: Verificar que `public/agenteAI/` se incluye

`public/` ya está en `RELEASE_ITEMS`, así que `public/agenteAI/frontend/` se subirá automáticamente.

### Fix 4: En el VPS (post-deploy)

```bash
# Verificar que llegaron los archivos
ls -la /opt/mrp/agenteAI/backend/controllers/AgentController.php
ls -la /opt/mrp/agenteAI/backend/services/
ls -la /opt/mrp/public/agenteAI/frontend/

# Ejecutar migraciones manualmente
docker compose exec -T php-fpm php /app/migrate_database.php --path=/app/database/migrations --skip-existing

# Verificar tablas
docker compose exec -T postgres psql -U mrp -d mrp_tunna -c "\dt agent_*"

# Limpiar OPcache
docker compose exec -T php-fpm php -r "opcache_reset();"

# Reiniciar php-fpm
docker compose restart php-fpm
```

---

## Orden de Ejecución

| # | Paso | Comando |
|---|------|---------|
| 1 | Agregar `agenteAI` a RELEASE_ITEMS en deploy.sh | Editar deploy.sh |
| 2 | Restaurar migraciones `.sql` | `mv *.bkp *.sql` en database/migrations/ |
| 3 | Commit + deploy | `./deploy.sh` |
| 4 | SSH al VPS + ejecutar migraciones | Ver Fix 4 |
| 5 | Probar endpoints | `curl https://mimrp.com.ar/api/v1/agent/config` |

---

## Rollback

Si el fix no funciona:
1. Quitar `agenteAI` de RELEASE_ITEMS
2. Restaurar routes y autoload a versiones anteriores
3. Re-deploy

---

**Estado:** ⏳ Pendiente
