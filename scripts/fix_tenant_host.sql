UPDATE company_databases
SET host = 'postgresql',
    port = '5432',
    updated_at = NOW()
WHERE host = 'lemp-postgresql';

SELECT company_id, host, port, database_name, username
FROM company_databases
ORDER BY company_id;
