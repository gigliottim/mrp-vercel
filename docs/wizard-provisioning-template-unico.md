# Wizard: Base Unica para Provisionar Empresas

## Objetivo
Evitar mantener dos fuentes de estructura de BD (por ejemplo `mrp_tunna` y `database/wizard/00_mrp_wizard.sql`) y reducir el riesgo de olvidos.

## Problema Actual
Hoy el alta por wizard se apoya en scripts de `database/wizard/` (ver `app/services/TenantProvisioningService.php`), mientras que el equipo tambien usa `mrp_tunna` como referencia operativa.

Cuando existen dos "fuentes de verdad", aparece drift de esquema:
- Una BD tiene tablas/campos nuevos y la otra no.
- Se aplican migraciones en una y se olvida la otra.
- El wizard puede crear tenants con estructura incompleta o desfasada.

## Decision Propuesta
Definir una sola fuente de verdad para estructura inicial del tenant.

## Opcion A (Inmediata): Usar `mrp_tunna` como base unica
Esta opcion cumple exactamente tu necesidad de "copiar la estructura de `mrp_tunna`".

### Regla
- El wizard SIEMPRE crea la nueva BD desde un snapshot `schema-only` de `mrp_tunna`.
- Los datos minimos iniciales (depositos, validaciones, UM) se siguen aplicando con scripts idempotentes.

### Implementacion Tecnica
1. Crear script de export automatica de esquema:
- Origen: `mrp_tunna`
- Salida: `database/wizard/00_mrp_wizard.sql`
- Comando sugerido:

```powershell
$env:PGPASSWORD='ojp9Q6aYT3KHDE8sMS2u'
pg_dump -h localhost -p 5432 -U mrp -d mrp_tunna --schema-only --no-owner --no-privileges --encoding=UTF8 > database/wizard/00_mrp_wizard.sql
```

2. Mantener `01_mrp_depositos.sql`, `02_mrp_validaciones.sql`, `03_mrp_um.sql` como seeds iniciales idempotentes.

3. Incorporar una verificacion previa en deploy/CI:
- Si hay migraciones nuevas, regenerar `00_mrp_wizard.sql`.
- Fallar pipeline si el snapshot no fue actualizado.

4. Crear task de VS Code para estandarizar proceso:
- `"🔄 Regenerar wizard desde mrp_tunna"`

5. Auditoria minima:
- Registrar en log/tabla que snapshot se uso (fecha/hash).

### Ventajas
- Unica referencia real: `mrp_tunna`.
- Cero doble mantenimiento manual.
- Menor probabilidad de errores en altas nuevas.

### Riesgos y mitigaciones
- Riesgo: `mrp_tunna` contiene datos no deseados.
- Mitigacion: exportar SIEMPRE con `--schema-only`.
- Riesgo: alguien cambia `mrp_tunna` sin migracion formal.
- Mitigacion: politica de cambios solo via migraciones + control en PR.

## Opcion B (Como lo hacen empresas): Template DB + Migrations como fuente real
Esta es la practica mas robusta en SaaS multi-tenant.

### Patron comun
1. La fuente de verdad del esquema es el directorio de migraciones (`database/migrations`).
2. Se construye una "template database" (ej: `mrp_template`) en CI/CD aplicando todas las migraciones.
3. El wizard crea nuevas DB clonando `mrp_template` o aplicando un dump schema-only generado de ese template.
4. Seeds minimos idempotentes se aplican al final.

### Ventajas
- Reproducible y auditable.
- Menor dependencia de una BD de uso diario (`mrp_tunna`).
- Facil versionar y validar en ambientes (dev/staging/prod).

## Recomendacion para este proyecto
- Corto plazo: aplicar Opcion A para resolver ya el problema operativo.
- Mediano plazo: migrar a Opcion B para estandar enterprise.

## Cambios concretos sugeridos en el repo
1. Nuevo script: `scripts/regenerate-wizard-schema-from-tunna.ps1`
- Exporta schema-only desde `mrp_tunna` a `database/wizard/00_mrp_wizard.sql`.
- Normaliza encoding UTF-8 sin BOM.

2. Nuevo check en CI/deploy:
- Validar que `00_mrp_wizard.sql` fue regenerado cuando cambia `database/migrations/*`.

3. Documentar runbook de provisionamiento:
- "Despues de migrar `mrp_tunna`, regenerar snapshot wizard".

4. Opcional (recomendado):
- Crear `scripts/build-template-db.ps1` para transicionar a `mrp_template`.

## Checklist Operativo
- [ ] Aplicar migraciones en `mrp_tunna`.
- [ ] Regenerar `database/wizard/00_mrp_wizard.sql` desde `mrp_tunna`.
- [ ] Ejecutar smoke test de registro (`scripts/smoke-test-register.php`).
- [ ] Confirmar que el tenant nuevo nace con estructura esperada.
- [ ] Versionar snapshot + migraciones en el mismo commit.

## Nota importante
Si se decide copiar estructura desde `mrp_tunna`, NO se recomienda crear tenant con `CREATE DATABASE ... TEMPLATE mrp_tunna` directamente en produccion, porque puede arrastrar estado no intencional (objetos temporales, permisos, o datos si la base no esta 100% limpia). Para seguridad operativa, preferir `pg_dump --schema-only` + seeds controlados.
