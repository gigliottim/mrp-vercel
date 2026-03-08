-- Expand decimal places setting range from 1..6 to 1..10.
ALTER TABLE configuracion_general
    DROP CONSTRAINT IF EXISTS configuracion_general_decimal_places_check;

ALTER TABLE configuracion_general
    ADD CONSTRAINT configuracion_general_decimal_places_check
    CHECK (decimal_places BETWEEN 1 AND 10);
