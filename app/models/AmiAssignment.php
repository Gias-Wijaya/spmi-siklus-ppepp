<?php
declare(strict_types=1);

/** Penugasan auditor–auditee per unit (penugasan, bukan role — sesuai wawancara LPM). */
final class AmiAssignment extends Model
{
    protected static string $table = 'ami_assignments';

    public static function forSchedule(int $scheduleId): array
    {
        return Database::all(
            'SELECT a.*, u.name AS unit_name, u.code AS unit_code,
                    au.name AS auditor_name, al.name AS lead_name,
                    (SELECT COUNT(*) FROM ami_assessments aa
                       WHERE aa.assignment_id = a.id AND aa.score IS NOT NULL) AS assessed_count
               FROM ami_assignments a
               JOIN units u ON u.id = a.unit_id
               LEFT JOIN users au ON au.id = a.auditor_user_id
               LEFT JOIN users al ON al.id = a.auditee_lead_user_id
              WHERE a.schedule_id = ?
              ORDER BY u.name',
            [$scheduleId]
        );
    }

    public static function withDetails(int $id): ?array
    {
        return Database::fetch(
            'SELECT a.*, u.name AS unit_name, u.code AS unit_code,
                    au.name AS auditor_name, al.name AS lead_name,
                    s.name AS schedule_name, s.year AS schedule_year, s.status AS schedule_status
               FROM ami_assignments a
               JOIN units u ON u.id = a.unit_id
               JOIN ami_schedules s ON s.id = a.schedule_id
               LEFT JOIN users au ON au.id = a.auditor_user_id
               LEFT JOIN users al ON al.id = a.auditee_lead_user_id
              WHERE a.id = ?',
            [$id]
        );
    }

    public static function unitExists(int $scheduleId, int $unitId): bool
    {
        $row = Database::fetch(
            'SELECT COUNT(*) AS n FROM ami_assignments WHERE schedule_id = ? AND unit_id = ?',
            [$scheduleId, $unitId]
        );
        return (int)($row['n'] ?? 0) > 0;
    }

    public static function unitExistsExcept(int $scheduleId, int $unitId, int $exceptId): bool
    {
        $row = Database::fetch(
            'SELECT COUNT(*) AS n FROM ami_assignments WHERE schedule_id = ? AND unit_id = ? AND id <> ?',
            [$scheduleId, $unitId, $exceptId]
        );
        return (int)($row['n'] ?? 0) > 0;
    }
}
