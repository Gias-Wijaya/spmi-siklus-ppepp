<?php
declare(strict_types=1);

/**
 * Bukti tindak lanjut rencana aksi (M4, FR-11).
 * Setiap rencana paling banyak satu bukti berstatus pending pada satu waktu;
 * keputusan verifikasi (approved/rejected) diberikan Super Admin per berkas.
 */
final class ActionEvidence extends Model
{
    protected static string $table = 'action_evidence';

    /** Seluruh bukti milik satu rencana (terbaru di atas). */
    public static function forPlan(int $planId): array
    {
        return Database::all(
            'SELECT ae.*, u.name AS uploader_name
               FROM action_evidence ae
               LEFT JOIN users u ON u.id = ae.uploaded_by
              WHERE ae.action_plan_id = ?
              ORDER BY ae.id DESC',
            [$planId]
        );
    }

    /** Bukti yang masih menunggu verifikasi (bila ada). */
    public static function pendingFor(int $planId): ?array
    {
        if ($planId <= 0) {
            return null;
        }
        return Database::fetch(
            "SELECT * FROM action_evidence
              WHERE action_plan_id = ? AND status = 'pending'
              ORDER BY id DESC LIMIT 1",
            [$planId]
        );
    }
}
