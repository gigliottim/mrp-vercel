-- Función y trigger para auto-vincular al super admin con cualquier empresa nueva
CREATE OR REPLACE FUNCTION fn_super_admin_auto_link()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $fn$
DECLARE
    v_super_email  TEXT    := 'martin@unik.ar';
    v_role_name    TEXT    := 'super_admin';
    v_user_id      INTEGER;
    v_role_id      INTEGER;
BEGIN
    SELECT id INTO v_user_id FROM users WHERE lower(email) = v_super_email LIMIT 1;
    SELECT id INTO v_role_id FROM roles  WHERE name = v_role_name            LIMIT 1;
    IF v_user_id IS NOT NULL AND v_role_id IS NOT NULL THEN
        INSERT INTO user_company (user_id, company_id, role_id)
        VALUES (v_user_id, NEW.id, v_role_id)
        ON CONFLICT DO NOTHING;
    END IF;
    RETURN NEW;
END;
$fn$;

DROP TRIGGER IF EXISTS trg_super_admin_auto_link ON companies;

CREATE TRIGGER trg_super_admin_auto_link
AFTER INSERT ON companies
FOR EACH ROW
EXECUTE FUNCTION fn_super_admin_auto_link();
