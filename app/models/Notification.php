<?php
declare(strict_types=1);

final class Notification extends Model
{
    protected static string $table = 'notifications';

    /** Kirim notifikasi ke satu atau banyak pengguna. */
    public static function send(int|array $userIds, string $title, string $message, string $type = 'umum', ?string $refTable = null, ?int $refId = null): void
    {
        foreach ((array)$userIds as $userId) {
            $userId = (int)$userId;
            if ($userId <= 0) {
                continue;
            }
            Database::insert('notifications', [
                'user_id'   => $userId,
                'title'     => $title,
                'message'   => $message,
                'type'      => $type,
                'ref_table' => $refTable,
                'ref_id'    => $refId,
            ]);
        }
    }

    public static function unreadCount(int $userId): int
    {
        return self::count('user_id = ? AND is_read = 0', [$userId]);
    }

    public static function latest(int $userId, int $limit = 8): array
    {
        $limit = max(1, $limit);
        return Database::all(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit,
            [$userId]
        );
    }

    public static function allFor(int $userId, int $page, int $perPage): array
    {
        $total   = self::count('user_id = ?', [$userId]);
        $perPage = max(1, $perPage);
        $offset  = (max(1, $page) - 1) * $perPage;
        $rows    = Database::all(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT '
            . $perPage . ' OFFSET ' . $offset,
            [$userId]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    public static function markAllRead(int $userId): void
    {
        Database::update('notifications', ['is_read' => 1], 'user_id = ? AND is_read = 0', [$userId]);
    }

    public static function markRead(int $id, int $userId): void
    {
        Database::update('notifications', ['is_read' => 1], 'id = ? AND user_id = ?', [$id, $userId]);
    }

    /* ------------------------------------------------------ Penjaga tenggat (FR-15) */

    /**
     * Periksa tenggat yang mendekat lalu kirim notifikasi 'tenggat' bila perlu.
     *
     * Berjalan pada setiap permintaan (padanan "timer pengingat" aktor A3):
     *  - masa berlaku dokumen disetujui  ≤ validity_warning_days hari (atau sudah lewat);
     *  - periode Monev terbuka           dalam rentang −7 … +7 hari terhadap akhir periode;
     *  - jadwal AMI berjalan             dalam rentang −7 … +7 hari terhadap akhir jadwal;
     *  - rencana aksi terbuka/berjalan   dalam rentang −7 … +7 hari terhadap tenggat PIC.
     *
     * Deduplikasi permanen per (penerima, jenis, rujukan): satu peristiwa tenggat
     * hanya menghasilkan satu notifikasi sehingga pemuatan halaman berulang tidak
     * menggandakan notifikasi.
     */
    public static function checkDeadlines(): void
    {
        $warn  = (int)config('validity_warning_days', 30);
        $today = date('Y-m-d');
        $lo    = date('Y-m-d', strtotime('-7 days'));
        $hi7   = date('Y-m-d', strtotime('+7 days'));
        $hiDoc = date('Y-m-d', strtotime('+' . $warn . ' days'));
        $super = User::superAdminIds();

        // 1) Masa berlaku dokumen (FR-02/FR-15) — pemberitahuan ke pemilik + LPM.
        $docs = Database::all(
            "SELECT id, title, owner_user_id, expiry_date
               FROM documents
              WHERE expiry_date IS NOT NULL
                AND expiry_date <= ?",
            [$hiDoc]
        );
        foreach ($docs as $d) {
            $days  = days_to_expiry((string)$d['expiry_date']);
            $until = format_date($d['expiry_date']);
            $msg   = ($days !== null && $days >= 0)
                ? 'Dokumen "' . $d['title'] . '" berakhir masa berlaku pada ' . $until . ' (' . $days . ' hari lagi).'
                : 'Dokumen "' . $d['title'] . '" sudah kedaluwarsa sejak ' . $until . '.';

            self::notifyDeadline(
                array_unique(array_merge([(int)$d['owner_user_id']], $super)),
                'Masa berlaku dokumen',
                $msg,
                'documents',
                (int)$d['id']
            );
        }

        // 2) Akhir periode Monev (FR-04/FR-15) — ke LPM sebagai pengelola periode.
        foreach (self::deadlineRows(
            "SELECT id, name, end_date FROM monev_periods
              WHERE status = 'open' AND end_date BETWEEN ? AND ?",
            [$lo, $hi7]
        ) as $p) {
            self::notifyDeadline($super, 'Tenggat periode Monev', self::periodMessage('Monev', (string)$p['name'], (string)$p['end_date'], $today), 'monev_periods', (int)$p['id']);
        }

        // 3) Akhir jadwal AMI (FR-08/FR-15) — ke LPM sebagai penanggung jawab jadwal.
        foreach (self::deadlineRows(
            "SELECT id, name, end_date FROM ami_schedules
              WHERE status = 'running' AND end_date BETWEEN ? AND ?",
            [$lo, $hi7]
        ) as $s) {
            self::notifyDeadline($super, 'Tenggat jadwal AMI', self::periodMessage('AMI', (string)$s['name'], (string)$s['end_date'], $today), 'ami_schedules', (int)$s['id']);
        }

        // 4) Tenggat rencana aksi (FR-10/FR-15) — ke PIC + LPM.
        foreach (self::deadlineRows(
            "SELECT id, description, due_date, pic_user_id FROM action_plans
              WHERE status IN ('open','in_progress')
                AND due_date IS NOT NULL AND due_date BETWEEN ? AND ?",
            [$lo, $hi7]
        ) as $plan) {
            self::notifyDeadline(
                array_unique(array_merge([(int)$plan['pic_user_id']], $super)),
                'Tenggat tindak lanjut',
                self::periodMessage('Rencana aksi', (string)$plan['description'], (string)$plan['due_date'], $today),
                'action_plans',
                (int)$plan['id']
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    private static function deadlineRows(string $sql, array $params): array
    {
        return Database::all($sql, $params);
    }

    /** Susun pesan tenggat yang sama pola untuk periode, jadwal, dan rencana aksi. */
    private static function periodMessage(string $kind, string $name, string $endDate, string $today): string
    {
        $days = (int)floor((strtotime($endDate) - strtotime($today)) / 86400);
        $until = format_date($endDate);
        if ($days >= 0) {
            return 'Tenggat ' . $kind . ' "' . $name . '" pada ' . $until . ' (' . $days . ' hari lagi).';
        }
        return 'Tenggat ' . $kind . ' "' . $name . '" terlewat sejak ' . $until . '.';
    }

    /**
     * Kirim notifikasi tenggat dengan deduplikasi permanen per penerima + rujukan.
     * @param array<int, int> $userIds
     */
    private static function notifyDeadline(array $userIds, string $title, string $message, string $refTable, int $refId): void
    {
        foreach ($userIds as $userId) {
            $userId = (int)$userId;
            if ($userId <= 0) {
                continue;
            }
            $exists = (int)Database::scalar(
                'SELECT COUNT(*) FROM notifications
                  WHERE user_id = ? AND type = ? AND ref_table = ? AND ref_id = ?',
                [$userId, 'tenggat', $refTable, $refId]
            );
            if ($exists > 0) {
                continue;
            }
            self::send($userId, $title, $message, 'tenggat', $refTable, $refId);
        }
    }
}
