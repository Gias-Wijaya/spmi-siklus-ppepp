<?php
declare(strict_types=1);

/** Indikator capaian Monev (turunan standar SPMI). */
final class MonevIndicator extends Model
{
    protected static string $table = 'monev_indicators';

    /** Seluruh indikator lengkap dengan nama standar & unit pengisi. */
    public static function withStandard(): array
    {
        return Database::all(
            'SELECT i.*, s.name AS standard_name, s.code AS standard_code,
                    u.name AS unit_name
               FROM monev_indicators i
               LEFT JOIN standards s ON s.id = i.standard_id
               LEFT JOIN units u ON u.id = i.unit_id
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

    /**
     * Masukan LPM foto-1 — indikator aktif untuk pengisian SATU unit:
     * indikator milik unit itu sendiri ATAU indikator "semua unit" (unit_id NULL).
     * Indikator unit lain tidak ikut tampil ("bukan bagian dia untuk mengisi").
     */
    public static function activeForUnit(int $unitId): array
    {
        return Database::all(
            "SELECT i.*, s.name AS standard_name
               FROM monev_indicators i
               LEFT JOIN standards s ON s.id = i.standard_id
              WHERE i.is_active = 1 AND (i.unit_id IS NULL OR i.unit_id = ?)
              ORDER BY i.code ASC",
            [$unitId]
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
