<?php
declare(strict_types=1);

/**
 * Temuan hasil AMI (M3) — sumber rencana tindak lanjut (M4, FR-09).
 * Temuan otomatis terbentuk dari penilaian skor ≤ 2, atau dicatat manual.
 */
final class Finding extends Model
{
    protected static string $table = 'ami_findings';

    public static function forSchedule(int $scheduleId): array
    {
        return Database::all(
            "SELECT f.*, a.schedule_id, u.name AS unit_name, au.name AS auditor_name,
                    i.code AS indicator_code, i.question AS indicator_question,
                    us.name AS finder_name
               FROM ami_findings f
               JOIN ami_assignments a ON a.id = f.assignment_id
               JOIN units u ON u.id = a.unit_id
               LEFT JOIN users au ON au.id = a.auditor_user_id
               LEFT JOIN ami_assessments aa ON aa.id = f.assessment_id
               LEFT JOIN ami_instruments i ON i.id = aa.instrument_id
               LEFT JOIN users us ON us.id = f.identified_by
              WHERE a.schedule_id = ?
              ORDER BY FIELD(f.status, 'open', 'in_progress', 'verified', 'closed'),
                       FIELD(f.severity, 'tinggi', 'sedang', 'rendah'),
                       f.id DESC",
            [$scheduleId]
        );
    }

    public static function openCount(int $scheduleId): int
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS n FROM ami_findings f
               JOIN ami_assignments a ON a.id = f.assignment_id
              WHERE a.schedule_id = ? AND f.status IN ('open','in_progress')",
            [$scheduleId]
        );
        return (int)($row['n'] ?? 0);
    }

    public static function countFor(int $assignmentId): int
    {
        $row = Database::fetch('SELECT COUNT(*) AS n FROM ami_findings WHERE assignment_id = ?', [$assignmentId]);
        return (int)($row['n'] ?? 0);
    }

    public static function forAssignment(int $assignmentId): array
    {
        return Database::all(
            'SELECT f.*, i.code AS indicator_code, us.name AS finder_name
               FROM ami_findings f
               LEFT JOIN ami_assessments aa ON aa.id = f.assessment_id
               LEFT JOIN ami_instruments i ON i.id = aa.instrument_id
               LEFT JOIN users us ON us.id = f.identified_by
              WHERE f.assignment_id = ?
              ORDER BY f.id DESC',
            [$assignmentId]
        );
    }

    public static function byAssessment(int $assessmentId): ?array
    {
        return Database::fetch(
            'SELECT * FROM ami_findings WHERE assessment_id = ? LIMIT 1',
            [$assessmentId]
        );
    }

    public static function recent(int $limit = 6): array
    {
        return Database::all(
            'SELECT f.*, u.name AS unit_name, s.name AS schedule_name
               FROM ami_findings f
               JOIN ami_assignments a ON a.id = f.assignment_id
               JOIN ami_schedules s ON s.id = a.schedule_id
               JOIN units u ON u.id = a.unit_id
              ORDER BY f.id DESC
              LIMIT ' . max(1, $limit)
        );
    }
}
