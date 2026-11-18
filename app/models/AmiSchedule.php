<?php
declare(strict_types=1);

/** Jadwal AMI tahunan (unik per tahun) — siklus draft → running → closed. */
final class AmiSchedule extends Model
{
    protected static string $table = 'ami_schedules';

    public static function ordered(): array
    {
        return Database::all(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM ami_assignments a WHERE a.schedule_id = s.id) AS assignment_count,
                    (SELECT COUNT(*) FROM ami_assignments a WHERE a.schedule_id = s.id AND a.status = ?) AS completed_count
               FROM ami_schedules s
              ORDER BY s.year DESC, s.id DESC',
            ['completed']
        );
    }

    /** Jadwal berjalan; bila tidak ada, pakai yang terbaru. */
    public static function activeOrLatest(): ?array
    {
        $running = Database::fetch("SELECT * FROM ami_schedules WHERE status = 'running' ORDER BY year DESC LIMIT 1");
        if ($running) {
            return $running;
        }
        $rows = self::ordered();
        return $rows ? $rows[0] : null;
    }

    public static function nameExists(string $name, int $exceptId = 0): bool
    {
        $sql    = 'SELECT COUNT(*) AS n FROM ami_schedules WHERE name = ?';
        $params = [$name];
        if ($exceptId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $row = Database::fetch($sql, $params);
        return (int)($row['n'] ?? 0) > 0;
    }

    public static function yearExists(int $year, int $exceptId = 0): bool
    {
        $sql    = 'SELECT COUNT(*) AS n FROM ami_schedules WHERE year = ?';
        $params = [$year];
        if ($exceptId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $row = Database::fetch($sql, $params);
        return (int)($row['n'] ?? 0) > 0;
    }
}
