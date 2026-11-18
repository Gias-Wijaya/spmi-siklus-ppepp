<?php
declare(strict_types=1);

/**
 * Penilaian AMI per indikator (skor 0–4) + perhitungan rekap otomatis (FR-08).
 */
final class AmiAssessment extends Model
{
    protected static string $table = 'ami_assessments';

    /** Nilai tersimpan untuk satu penugasan (termasuk instrumen nonaktif). */
    public static function forAssignment(int $assignmentId): array
    {
        return Database::all(
            'SELECT aa.*, i.code, i.question, i.weight, i.sort_order, i.is_active, i.standard_id
               FROM ami_assessments aa
               JOIN ami_instruments i ON i.id = aa.instrument_id
              WHERE aa.assignment_id = ?
              ORDER BY i.sort_order, i.code',
            [$assignmentId]
        );
    }

    public static function findPair(int $assignmentId, int $instrumentId): ?array
    {
        return Database::fetch(
            'SELECT * FROM ami_assessments WHERE assignment_id = ? AND instrument_id = ? LIMIT 1',
            [$assignmentId, $instrumentId]
        );
    }

    /** Skor tertimbang per unit: Σ(skor×bobot) ÷ Σ(bobot) atas indikator yang sudah dinilai. */
    public static function unitScores(int $scheduleId): array
    {
        return Database::all(
            'SELECT a.id AS assignment_id, a.status, a.audit_date,
                    u.name AS unit_name, au.name AS auditor_name,
                    COUNT(aa.id) AS assessed_count,
                    ROUND(SUM(aa.score * i.weight) / NULLIF(SUM(i.weight), 0), 2) AS avg_score
               FROM ami_assignments a
               JOIN units u ON u.id = a.unit_id
               LEFT JOIN users au ON au.id = a.auditor_user_id
               LEFT JOIN ami_assessments aa ON aa.assignment_id = a.id
               LEFT JOIN ami_instruments i ON i.id = aa.instrument_id AND i.is_active = 1
              WHERE a.schedule_id = ?
              GROUP BY a.id
              ORDER BY u.name',
            [$scheduleId]
        );
    }

    /** Rerata skor per standar + selisih (gap) dari skor penuh 4. */
    public static function standardScores(int $scheduleId): array
    {
        $rows = Database::all(
            'SELECT s.id AS standard_id, s.code AS standard_code, s.name AS standard_name,
                    COUNT(aa.id) AS assessed_count,
                    ROUND(SUM(aa.score * i.weight) / NULLIF(SUM(i.weight), 0), 2) AS avg_score
               FROM ami_instruments i
               LEFT JOIN standards s ON s.id = i.standard_id
               LEFT JOIN ami_assessments aa ON aa.instrument_id = i.id
              WHERE i.schedule_id = ? AND i.is_active = 1
              GROUP BY s.id
              ORDER BY s.code IS NULL, s.code',
            [$scheduleId]
        );
        foreach ($rows as &$r) {
            $r['gap'] = $r['avg_score'] !== null
                ? round(4 - (float)$r['avg_score'], 2)
                : null;
        }
        return $rows;
    }

    /** Rerata skor per indikator (untuk laporan & grafik). */
    public static function instrumentAverages(int $scheduleId): array
    {
        return Database::all(
            'SELECT i.id, i.code, i.question, i.weight, i.standard_id, s.name AS standard_name,
                    COUNT(aa.id) AS assessed_count, ROUND(AVG(aa.score), 2) AS avg_score
               FROM ami_instruments i
               LEFT JOIN standards s ON s.id = i.standard_id
               LEFT JOIN ami_assessments aa ON aa.instrument_id = i.id
              WHERE i.schedule_id = ? AND i.is_active = 1
              GROUP BY i.id
              ORDER BY i.sort_order, i.code',
            [$scheduleId]
        );
    }

    public static function progress(int $scheduleId): array
    {
        $row = Database::fetch(
            'SELECT (SELECT COUNT(*) FROM ami_assignments a WHERE a.schedule_id = ?) AS assignments,
                    (SELECT COUNT(*) FROM ami_instruments i WHERE i.schedule_id = ? AND i.is_active = 1) AS indicators,
                    (SELECT COUNT(*) FROM ami_assessments aa
                       JOIN ami_assignments a ON a.id = aa.assignment_id
                       JOIN ami_instruments i ON i.id = aa.instrument_id AND i.is_active = 1
                      WHERE a.schedule_id = ?) AS assessed',
            [$scheduleId, $scheduleId, $scheduleId]
        );
        $total = (int)$row['assignments'] * (int)$row['indicators'];
        $row['percent'] = $total > 0
            ? round((int)$row['assessed'] / $total * 100, 1)
            : 0.0;
        return $row;
    }
}
