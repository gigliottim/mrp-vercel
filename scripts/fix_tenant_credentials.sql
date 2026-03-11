UPDATE company_databases
SET username = 'mrp_unik_2026',
    password_encrypted = 'jqAe@Sy96^&z3vu2wK@@',
    updated_at = NOW();

SELECT company_id, host, port, database_name, username
FROM company_databases
ORDER BY company_id;
