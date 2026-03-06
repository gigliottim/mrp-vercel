-- Fix menu routes for Empresa y Usuarios section on already-migrated environments.
-- Run with: php migrate_database.php --connection=mrp_auth

UPDATE menu_items
SET route = '/empresa-usuarios/empresa', updated_at = CURRENT_TIMESTAMP
WHERE code = 'admin.empresa';

UPDATE menu_items
SET route = '/empresa-usuarios/usuarios', updated_at = CURRENT_TIMESTAMP
WHERE code = 'admin.usuarios';

UPDATE menu_items
SET route = '/empresa-usuarios/roles', updated_at = CURRENT_TIMESTAMP
WHERE code = 'admin.roles';

UPDATE menu_items
SET route = '/empresa-usuarios/permisos', updated_at = CURRENT_TIMESTAMP
WHERE code = 'admin.permisos';
