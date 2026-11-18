<?php
declare(strict_types=1);

/** Periode Monev — diisi enam bulan sekali (T3). */
final class MonevPeriod extends Model
{
    protected static string $table = 'monev_periods';

    /** Terbaru lebih dulu. */
    public static function ordered(): array
    {
        return Database::all('SELECT * FROM monev_periods ORDER BY start_date DESC, id DESC');
    }

    public static function open(): array
    {
        return Database::all(
            "SELECT * FROM monev_periods WHERE status = 'open' ORDER BY start_date DESC"
        );
    }

    public static function isOpen(int $id): bool
    {
        $period = self::find($id);
        return $period !== null && $period['status'] === 'open';
    }

    public static function nameExists(string $name, int $exceptId = 0): bool
    {
        return (bool)Database::scalar(
            'SELECT COUNT(*) FROM monev_periods WHERE name = ? AND id <> ?',
            [$name, $exceptId]
        );
    }

    /** Jumlah laporan pada satu periode (untuk ditampilkan di daftar). */
    public static function reportCount(int $periodId): int
    {
        return (int)Database::scalar(
            'SELECT COUNT(*) FROM monev_reports WHERE period_id = ?',
            [$periodId]
        );
    }
}
