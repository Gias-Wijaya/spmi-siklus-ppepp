<?php
declare(strict_types=1);

/**
 * Instrumen AMI — indikator FLEKSIBEL (FR-06): dapat ditambah/diedit/dihapus
 * oleh Super Admin; setiap perubahan tercatat di ami_instrument_logs.
 */
final class AmiInstrument extends Model
{
    protected static string $table = 'ami_instruments';

    public static function forSchedule(int $scheduleId, bool $activeOnly = false): array
    {
        $sql = 'SELECT i.*, s.name AS standard_name, s.code AS standard_code
                  FROM ami_instruments i
                  LEFT JOIN standards s ON s.id = i.standard_id
                 WHERE i.schedule_id = ?';
        if ($activeOnly) {
            $sql .= ' AND i.is_active = 1';
        }
        $sql .= ' ORDER BY i.sort_order, i.code, i.id';
        return Database::all($sql, [$scheduleId]);
    }

    public static function codeExists(int $scheduleId, string $code, int $exceptId = 0): bool
    {
        $sql    = 'SELECT COUNT(*) AS n FROM ami_instruments WHERE schedule_id = ? AND code = ?';
        $params = [$scheduleId, $code];
        if ($exceptId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        $row = Database::fetch($sql, $params);
        return (int)($row['n'] ?? 0) > 0;
    }

    public static function assessedCount(int $instrumentId): int
    {
        $row = Database::fetch('SELECT COUNT(*) AS n FROM ami_assessments WHERE instrument_id = ?', [$instrumentId]);
        return (int)($row['n'] ?? 0);
    }

    /** Riwayat perubahan indikator (jejak RM 3) — 30 perubahan terakhir. */
    public static function recentLogs(int $limit = 30): array
    {
        return Database::all(
            'SELECT l.*, i.code AS instrument_code, i.question AS current_question, u.name AS changed_by_name
               FROM ami_instrument_logs l
               JOIN ami_instruments i ON i.id = l.instrument_id
               LEFT JOIN users u ON u.id = l.changed_by
              ORDER BY l.id DESC
              LIMIT ' . max(1, $limit)
        );
    }

    public static function logsFor(int $instrumentId): array
    {
        return Database::all(
            'SELECT l.*, u.name AS changed_by_name
               FROM ami_instrument_logs l
               LEFT JOIN users u ON u.id = l.changed_by
              WHERE l.instrument_id = ?
              ORDER BY l.id DESC',
            [$instrumentId]
        );
    }

    public static function logChange(int $instrumentId, ?int $userId, string $old, string $new, string $reason): void
    {
        Database::insert('ami_instrument_logs', [
            'instrument_id' => $instrumentId,
            'changed_by'    => $userId,
            'old_value'     => $old,
            'new_value'     => $new,
            'reason'        => $reason !== '' ? $reason : null,
        ]);
    }
}
