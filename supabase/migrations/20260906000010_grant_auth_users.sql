-- Grant SELECT sobre auth.users a service_role
-- Necesario para que el trigger fn_super_admin_auto_link (SECURITY INVOKER)
-- pueda resolver el uuid del super admin al insertar una empresa vía API.

GRANT SELECT ON auth.users TO service_role;
