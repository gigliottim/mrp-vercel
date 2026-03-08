<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class ConfiguracionGeneral extends BaseTenantModel
{
    private const DEFAULTS = [
        'decimal_places' => 4,
        'rounding_mode' => 'half_up',
        'thousand_separator' => '.',
        'decimal_separator' => ',',
        'date_format' => 'd/m/Y',
        'time_format' => 'H:i',
    ];

    private bool $tableEnsured = false;

    protected function getTable(): string
    {
        return 'configuracion_general';
    }

    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    public function getSettings(): array
    {
        $this->ensureTable();

        $stmt = $this->connection->query(
            'SELECT decimal_places, rounding_mode, thousand_separator, decimal_separator, date_format, time_format
             FROM configuracion_general
             WHERE id = 1
             LIMIT 1'
        );

        $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if ($row === false) {
            return self::DEFAULTS;
        }

        return [
            'decimal_places' => (int) ($row['decimal_places'] ?? self::DEFAULTS['decimal_places']),
            'rounding_mode' => (string) ($row['rounding_mode'] ?? self::DEFAULTS['rounding_mode']),
            'thousand_separator' => (string) ($row['thousand_separator'] ?? self::DEFAULTS['thousand_separator']),
            'decimal_separator' => (string) ($row['decimal_separator'] ?? self::DEFAULTS['decimal_separator']),
            'date_format' => (string) ($row['date_format'] ?? self::DEFAULTS['date_format']),
            'time_format' => (string) ($row['time_format'] ?? self::DEFAULTS['time_format']),
        ];
    }

    public function saveSettings(array $settings): void
    {
        $this->ensureTable();

        $sql = 'INSERT INTO configuracion_general
                    (id, decimal_places, rounding_mode, thousand_separator, decimal_separator, date_format, time_format, created_at, updated_at)
                VALUES
                    (1, :decimal_places, :rounding_mode, :thousand_separator, :decimal_separator, :date_format, :time_format, NOW(), NOW())
                ON CONFLICT (id)
                DO UPDATE SET
                    decimal_places = EXCLUDED.decimal_places,
                    rounding_mode = EXCLUDED.rounding_mode,
                    thousand_separator = EXCLUDED.thousand_separator,
                    decimal_separator = EXCLUDED.decimal_separator,
                    date_format = EXCLUDED.date_format,
                    time_format = EXCLUDED.time_format,
                    updated_at = NOW()';

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'decimal_places' => (int) $settings['decimal_places'],
            'rounding_mode' => (string) $settings['rounding_mode'],
            'thousand_separator' => (string) $settings['thousand_separator'],
            'decimal_separator' => (string) $settings['decimal_separator'],
            'date_format' => (string) $settings['date_format'],
            'time_format' => (string) $settings['time_format'],
        ]);
    }

    private function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }

        $this->connection->exec(
            "CREATE TABLE IF NOT EXISTS configuracion_general (
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
            )"
        );

        $stmt = $this->connection->prepare(
            "INSERT INTO configuracion_general (id, decimal_places, rounding_mode, thousand_separator, decimal_separator, date_format, time_format)
             VALUES (1, 4, 'half_up', '.', ',', 'd/m/Y', 'H:i')
             ON CONFLICT (id) DO NOTHING"
        );
        $stmt->execute();

        $this->tableEnsured = true;
    }
}
