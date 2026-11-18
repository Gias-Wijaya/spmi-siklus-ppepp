<?php
declare(strict_types=1);

/** Nilai capaian per indikator pada satu laporan Monev. */
final class MonevValue extends Model
{
    protected static string $table = 'monev_values';

    /** Nilai laporan lengkap dengan data indikatornya. */
    public static function forReport(int $reportId): array
    {
        return Database::all(
            'SELECT v.*, i.code, i.name AS indicator_name, i.unit_of_measure,
                    i.target, i.weight, i.is_active
               FROM monev_values v
               JOIN monev_indicators i ON i.id = v.indicator_id
              WHERE v.report_id = ?
              ORDER BY i.code ASC',
            [$reportId]
        );
    }

    public static function findPair(int $reportId, int $indicatorId): ?array
    {
        return Database::fetch(
            'SELECT * FROM monev_values WHERE report_id = ? AND indicator_id = ? LIMIT 1',
            [$reportId, $indicatorId]
        );
    }
}
