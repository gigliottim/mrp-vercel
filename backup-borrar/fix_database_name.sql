-- Actualizar database_name en company_databases
-- Ejecutar en BD: mrp_auth

-- Ver estado actual
SELECT c.slug, cd.database_name, cd.host
FROM company_databases cd
JOIN companies c ON cd.company_id = c.id;

-- Actualizar de 'mrp' a 'mrp_tenant_demo' (o el nombre correcto de tu BD)
UPDATE company_databases
SET database_name = 'mrp_tenant_demo'
WHERE database_name = 'mrp';

-- Verificar
SELECT c.slug, cd.database_name, cd.host
FROM company_databases cd
JOIN companies c ON cd.company_id = c.id;
