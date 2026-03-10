# Wizard y Migraciones Multiempresa (Version Simplificada)

## Flujo recomendado (simplificado)
1. Se crea una migracion nueva en `database/migrations`.
2. Se aplica en `mrp_tunna`.
3. Se regenera `database/wizard/00_mrp_wizard.sql` desde `mrp_tunna`.
4. Las empresas nuevas nacen al dia automaticamente.
5. Las empresas existentes muestran `N` pendientes en login/footer.
6. Un job diario aplica migraciones pendientes por tenant y actualiza estado en `mrp_auth`.

## Automatizacion diaria (5:00 AM Argentina)
- Ejecutar todos los dias a las `05:00` en zona horaria `America/Argentina/Buenos_Aires`.
- Recorrer tenants desde `mrp_auth.company_databases`.
- Calcular pendientes por tenant: migraciones en `database/migrations` que no esten en `tenant.schema_migrations`.
- Aplicar pendientes en orden.
- Registrar estado por empresa en `mrp_auth`: `pending_count`, `checked_at`, `last_error`.
- Usar lock global + lock por tenant para evitar ejecuciones concurrentes.

## UX minima
- Footer/Login muestra: `Actualizaciones pendientes: N`.
- Si `N = 0`: mostrar `Base actualizada`.
- Si `N > 0`: alerta visible y opcion `Actualizar ahora` para admin.
