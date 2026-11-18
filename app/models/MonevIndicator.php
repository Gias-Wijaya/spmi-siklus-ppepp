<?php
declare(strict_types=1);

/** Indikator capaian Monev (turunan standar SPMI). */
final class MonevIndicator extends Model
{
    protected static string $table = 'monev_indicators';

    /** Seluruh indikator lengkap dengan nama standar. */
    public static function withStandard(): array
    {
        return Database::all(
            'SELECT i.*, s.name AS standard_name, s.code AS standard_code
               FROM monev_indicators i
               LEFT JOIN standards s ON s.id = i.standard_id
              ORDER BY i.code ASC'
        );
    }

    public static function active(): array
    {
        return Database::all(
            "SELECT i.*, s.name AS standard_name
               FROM monev_indicators i
               LEFT JOIN standards s ON s.id = i.standard_id
              WHERE i.is_active = 1
              ORDER BY i.code ASC"
        );
    }

    public static function codeExists(string $code, int $exceptId = 0): bool
    {
        return (bool)Database::scalar(
            'SELECT COUNT(*) FROM monev_indicators WHERE code = ? AND id <> ?',
            [$code, $exceptId]
        );
    }
}
