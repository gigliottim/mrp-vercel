CREATE TABLE IF NOT EXISTS configuracion_general (
    id SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    decimal_places SMALLINT NOT NULL DEFAULT 4 CHECK (decimal_places BETWEEN 1 AND 6),
    rounding_mode VARCHAR(20) NOT NULL DEFAULT 'half_up',
    thousand_separator VARCHAR(1) NOT NULL DEFAULT '.',
    decimal_separator VARCHAR(1) NOT NULL DEFAULT ',',
    date_format VARCHAR(20) NOT NULL DEFAULT 'd/m/Y',
    time_format VARCHAR(20) NOT NULL DEFAULT 'H:i',
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_configuracion_general_rounding_mode
        CHECK (rounding_mode IN ('half_up', 'half_down', 'half_even', 'truncate')),
    CONSTRAINT chk_configuracion_general_separators
        CHECK (thousand_separator <> decimal_separator)
);

INSERT INTO configuracion_general (id, decimal_places, rounding_mode, thousand_separator, decimal_separator, date_format, time_format)
VALUES (1, 4, 'half_up', '.', ',', 'd/m/Y', 'H:i')
ON CONFLICT (id) DO NOTHING;
