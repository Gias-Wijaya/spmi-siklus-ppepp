<?php
declare(strict_types=1);

/** Laporan capaian Monev satu unit pada satu periode (FR-04). */
final class MonevReport extends Model
{
    protected static string $table = 'monev_reports';

    public static function findPair(int $periodId, int $unitId): ?array
    {
        return Database::fetch(
            'SELECT * FROM monev_reports WHERE period_id = ? AND unit_id = ? LIMIT 1',
            [$periodId, $unitId]
        );
    }

    public static function detail(int $id): ?array
    {
        return Database::fetch(
            'SELECT r.*, p.name AS period_name, p.start_date, p.end_date, p.status AS period_status,
                    u.name AS unit_name, u.code AS unit_code, usr.name AS submitted_by_name
               FROM monev_reports r
               JOIN monev_periods p ON p.id = r.period_id
               JOIN units u        ON u.id = r.unit_id
               LEFT JOIN users usr ON usr.id = r.submitted_by
              WHERE r.id = ?
              LIMIT 1',
            [$id]
        );
    }

    /** Seluruh unit + laporannya pada satu periode (untuk tabel rekap). */
    public static function forPeriod(int $periodId): array
    {
        return Database::all(
            'SELECT u.id AS unit_id, u.name AS unit_name,
                    r.id AS report_id, r.status, r.total_score, r.submitted_at, r.submitted_by
               FROM units u
               LEFT JOIN monev_reports r ON r.unit_id = u.id AND r.period_id = ?
              WHERE u.is_active = 1
              ORDER BY u.name ASC',
            [$periodId]
        );
    }

    /** Rata-rata capaian per periode (grafik tren lintas periode — FR-05). */
    public static function trendPerPeriod(): array
    {
        return Database::all(
            "SELECT p.id, p.name, p.start_date, p.end_date, p.status,
                    ROUND(AVG(r.total_score), 2) AS avg_score,
                    COUNT(r.id) AS report_count
               FROM monev_periods p
               LEFT JOIN monev_reports r
                      ON r.period_id = p.id AND r.status IN ('submitted','approved')
              GROUP BY p.id
              ORDER BY p.start_date ASC, p.id ASC"
        );
    }

    /** Skor per unit pada satu periode (grafik batang — FR-05). */
    public static function unitScores(int $periodId): array
    {
        return Database::all(
            "SELECT u.id AS unit_id, u.name AS unit_name,
                    r.id AS report_id, r.status, r.total_score
               FROM units u
               LEFT JOIN monev_reports r ON r.unit_id = u.id AND r.period_id = ?
              WHERE u.is_active = 1 AND r.id IS NOT NULL
              ORDER BY r.total_score DESC",
            [$periodId]
        );
    }

    /**
     * Rata-rata capaian per indikator pada satu periode + penanda di bawah target (FR-05).
     * $unitId diisi → rerata diambil dari laporan satu unit DAN daftar indikator
     * disaring ke unit pengisi tersebut — indikator "semua unit" tetap tampil,
     * indikator unit lain tidak (filter tabel dasbor, review LPM 19 & 25 Nov).
     */
    public static function indicatorAverages(int $periodId, ?int $unitId = null): array
    {
        $unitSql = $unitId !== null ? ' AND r.unit_id = ?' : '';
        $listSql = $unitId !== null ? ' AND (i.unit_id IS NULL OR i.unit_id = ?)' : '';
        $params  = $unitId !== null ? [$periodId, $unitId, $unitId] : [$periodId];

        return Database::all(
            "SELECT i.id, i.code, i.name, i.unit_of_measure, i.target, i.weight,
                    ROUND(AVG(t.ap), 2) AS avg_ap,
                    COUNT(t.report_id) AS respondent_count
               FROM monev_indicators i
               LEFT JOIN (
                   SELECT v.indicator_id, v.achievement_percent AS ap, v.report_id
                     FROM monev_values v
                     JOIN monev_reports r ON r.id = v.report_id
                    WHERE r.period_id = ?{$unitSql}
               ) t ON t.indicator_id = i.id
              WHERE i.is_active = 1{$listSql}
              GROUP BY i.id
              ORDER BY i.code ASC",
            $params
        );
    }

    /**
     * Hitung ulang capaian tiap indikator dan skor total laporan.
     * achievement_percent = nilai / target × 100, dibatasi maksimum 100%
     * (review LPM foto-03 — capaian 120% tidak boleh tampil hijau).
     * total_score = rerata tertimbang seluruh indikator terisi (juga ≤ 100).
     */
    public static function recalculate(int $reportId): void
    {
        $values = Database::all(
            'SELECT v.id, v.value, i.target, i.weight
               FROM monev_values v
               JOIN monev_indicators i ON i.id = v.indicator_id
              WHERE v.report_id = ?',
            [$reportId]
        );

        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($values as $row) {
            $target = (float)$row['target'];
            $weight = (float)$row['weight'];
            $value  = (float)$row['value'];

            $achievement = $target > 0 ? round(($value / $target) * 100, 2) : 0.0;
            if ($achievement > 100) {
                $achievement = 100.0;
            }
            Database::update(
                'monev_values',
                ['achievement_percent' => $achievement],
                'id = ?',
                [(int)$row['id']]
            );

            $weightedSum += $achievement * $weight;
            $weightTotal += $weight;
        }

        $total = $weightTotal > 0 ? round($weightedSum / $weightTotal, 2) : null;
        if ($total !== null && $total > 100) {
            $total = 100.0;
        }

        Database::update(
            'monev_reports',
            ['total_score' => $total],
            'id = ?',
            [$reportId]
        );
    }

    public static function statusCounts(): array
    {
        $rows  = Database::all(
            'SELECT status, COUNT(*) AS total FROM monev_reports GROUP BY status'
        );
        $count = ['draft' => 0, 'submitted' => 0, 'approved' => 0];
        foreach ($rows as $row) {
            $count[$row['status']] = (int)$row['total'];
        }
        return $count;
    }
}
