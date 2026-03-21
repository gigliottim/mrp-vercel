-- Setup fixed roles: Administrador, Usuario, Super Administrador
DO $$
BEGIN
    -- Ensure "Super Administrador" exists
    IF NOT EXISTS (SELECT 1 FROM roles WHERE name = 'Super Administrador') THEN
        INSERT INTO roles (name, guard_name, created_at, updated_at) VALUES ('Super Administrador', 'web', NOW(), NOW());
    END IF;

    -- Ensure "Administrador" exists
    IF NOT EXISTS (SELECT 1 FROM roles WHERE name = 'Administrador') THEN
        INSERT INTO roles (name, guard_name, created_at, updated_at) VALUES ('Administrador', 'web', NOW(), NOW());
    END IF;

    -- Ensure "Usuario" exists
    IF NOT EXISTS (SELECT 1 FROM roles WHERE name = 'Usuario') THEN
        INSERT INTO roles (name, guard_name, created_at, updated_at) VALUES ('Usuario', 'web', NOW(), NOW());
    END IF;
END $$;
