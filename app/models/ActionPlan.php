<?php
declare(strict_types=1);

/**
 * Rencana tindak lanjut (M4 — Pengendalian & Peningkatan; FR-09–FR-12).
 *
 * Aturan bisnis (sistem/desain/04-dfd.md):
 *  - temuan AMI menjadi sumber tunggal rencana aksi — tidak ada aksi tanpa temuan;
 *  - status: open → in_progress → done → verified;
 *  - tuntas hanya setelah bukti diverifikasi Super Admin;
 *  - verifikasi yang berujung pada perubahan dokumen memicu permintaan revisi (FR-12).
 */
final class ActionPlan extends Model
{
    protected static string $table = 'action_plans';

    /** Daftar rencana aksi lengkap dengan temuan, unit, PIC, dan ringkas bukti. */
    public static function list(array $filters = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['schedule_id'])) {
            $where[]  = 's.id = ?';
            $params[] = (int)$filters['schedule_id'];
        }
        if (!empty($filters['status'])) {
            $where[]  = 'ap.status = ?';
            $params[] = (string)$filters['status'];
        }
        if (!empty($filters['priority'])) {
            $where[]  = 'ap.priority = ?';
            $params[] = (string)$filters['priority'];
        }
        if (!empty($filters['pic_user_id'])) {
            $where[]  = 'ap.pic_user_id = ?';
            $params[] = (int)$filters['pic_user_id'];
        }

        $sql = 'SELECT ap.*,
                       f.description AS finding_desc, f.severity, f.finding_type,
                       f.status AS finding_status,
                       u.name AS unit_name,
                       s.id AS schedule_id, s.name AS schedule_name,
                       i.code AS indicator_code,
                       pu.name AS pic_name, pun.name AS pic_unit_name,
                       v.name AS verifier_name,
                       rd.title AS revision_doc_title,
                       (SELECT COUNT(*) FROM action_evidence ae WHERE ae.action_plan_id = ap.id) AS evidence_count,
                       (SELECT COUNT(*) FROM action_evidence ae
                         WHERE ae.action_plan_id = ap.id AND ae.status = ' . "'pending'" . ') AS pending_evidence
                FROM action_plans ap
                JOIN ami_findings f ON f.id = ap.finding_id
                JOIN ami_assignments aa ON aa.id = f.assignment_id
                JOIN ami_schedules s ON s.id = aa.schedule_id
                JOIN units u ON u.id = aa.unit_id
                LEFT JOIN ami_assessments asm ON asm.id = f.assessment_id
                LEFT JOIN ami_instruments i ON i.id = asm.instrument_id
                LEFT JOIN users pu ON pu.id = ap.pic_user_id
                LEFT JOIN units pun ON pun.id = ap.pic_unit_id
                LEFT JOIN users v ON v.id = ap.verified_by
                LEFT JOIN documents rd ON rd.id = ap.revision_document_id'
                . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
                . " ORDER BY FIELD(ap.status, 'open', 'in_progress', 'done', 'verified'),
                       ap.due_date IS NULL, ap.due_date ASC, ap.id DESC";

        return Database::all($sql, $params);
    }

    /** Jumlah rencana per status; difilter jadwal dan/atau PIC (0 = semua). */
    public static function recap(int $scheduleId = 0, int $picUserId = 0): array
    {
        $counts = ['open' => 0, 'in_progress' => 0, 'done' => 0, 'verified' => 0];

        $where  = ['1=1'];
        $params = [];
        if ($scheduleId > 0) {
            $where[]  = 's.id = ?';
            $params[] = $scheduleId;
        }
        if ($picUserId > 0) {
            $where[]  = 'ap.pic_user_id = ?';
            $params[] = $picUserId;
        }

        $rows = Database::all(
            'SELECT ap.status, COUNT(*) AS n
               FROM action_plans ap
               JOIN ami_findings f ON f.id = ap.finding_id
               JOIN ami_assignments aa ON aa.id = f.assignment_id
               JOIN ami_schedules s ON s.id = aa.schedule_id
              WHERE ' . implode(' AND ', $where) . '
              GROUP BY ap.status',
            $params
        );
        foreach ($rows as $r) {
            $counts[(string)$r['status']] = (int)$r['n'];
        }
        return $counts;
    }

    /** Detail satu rencana + temuan, jadwal, unit, PIC, verifier, dan dokumen revisi. */
    public static function withDetails(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return Database::fetch(
            'SELECT ap.*,
                    f.description AS finding_desc, f.severity, f.finding_type,
                    f.status AS finding_status, f.identified_by,
                    aa.id AS assignment_id, aa.auditor_user_id,
                    u.name AS unit_name,
                    s.id AS schedule_id, s.name AS schedule_name, s.year AS schedule_year,
                    i.code AS indicator_code, i.question AS indicator_question,
                    pu.name AS pic_name, pun.name AS pic_unit_name,
                    v.name AS verifier_name,
                    rd.title AS revision_doc_title, rd.version AS revision_doc_version
               FROM action_plans ap
               JOIN ami_findings f ON f.id = ap.finding_id
               JOIN ami_assignments aa ON aa.id = f.assignment_id
               JOIN ami_schedules s ON s.id = aa.schedule_id
               JOIN units u ON u.id = aa.unit_id
               LEFT JOIN ami_assessments asm ON asm.id = f.assessment_id
               LEFT JOIN ami_instruments i ON i.id = asm.instrument_id
               LEFT JOIN users pu ON pu.id = ap.pic_user_id
               LEFT JOIN units pun ON pun.id = ap.pic_unit_id
               LEFT JOIN users v ON v.id = ap.verified_by
               LEFT JOIN documents rd ON rd.id = ap.revision_document_id
              WHERE ap.id = ?
              LIMIT 1',
            [$id]
        );
    }

    /**
     * Temuan pada suatu jadwal untuk konversi menjadi rencana aksi (FR-09),
     * lengkap dengan jumlah rencana yang sudah terbentuk.
     */
    public static function findingsForConversion(int $scheduleId): array
    {
        return Database::all(
            "SELECT f.*, u.name AS unit_name, i.code AS indicator_code,
                    (SELECT COUNT(*) FROM action_plans ap WHERE ap.finding_id = f.id) AS plan_count
               FROM ami_findings f
               JOIN ami_assignments aa ON aa.id = f.assignment_id
               JOIN units u ON u.id = aa.unit_id
               LEFT JOIN ami_assessments asm ON asm.id = f.assessment_id
               LEFT JOIN ami_instruments i ON i.id = asm.instrument_id
              WHERE aa.schedule_id = ?
              ORDER BY plan_count ASC,
                       FIELD(f.severity, 'tinggi', 'sedang', 'rendah'),
                       f.id DESC",
            [$scheduleId]
        );
    }
}
