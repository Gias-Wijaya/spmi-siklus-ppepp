<?php
declare(strict_types=1);

/**
 * Pencarian global (FR lintas modul): dokumen SPMI (M1), temuan AMI (M3),
 * dan rencana aksi (M4). Hak akses mengikuti modul sumber — anggota hanya
 * melihat rencana aksi miliknya sendiri.
 *
 * Medan pencarian ≥ daftar modulnya: selain judul/nomor/uraian, versi &
 * kategori dokumen serta nama unit/jadwal/PIC ikut dicari — metadata yang
 * tampil di daftar modul selalu dapat ditemukan dari kotak global.
 */
final class SearchController extends Controller
{
    private const PER_GROUP = 8;

    public function index(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $docs = $findings = $plans = [];
        $total = 0;

        if ($q !== '') {
            $like = '%' . $q . '%';

            $docs = Database::all(
                "SELECT d.id, d.title, d.doc_number, d.version,
                        d.status_color, d.expiry_date, c.name AS category_name
                   FROM documents d
                   LEFT JOIN document_categories c ON c.id = d.category_id
                  WHERE d.title LIKE ? OR d.doc_number LIKE ? OR d.description LIKE ?
                     OR d.version LIKE ? OR c.name LIKE ?
                  ORDER BY d.updated_at DESC
                  LIMIT " . self::PER_GROUP,
                [$like, $like, $like, $like, $like]
            );

            $findings = Database::all(
                "SELECT f.id, f.description, f.severity, f.status, f.finding_type,
                        u.name AS unit_name, s.id AS schedule_id, s.name AS schedule_name
                   FROM ami_findings f
                   JOIN ami_assignments a ON a.id = f.assignment_id
                   JOIN units u ON u.id = a.unit_id
                   JOIN ami_schedules s ON s.id = a.schedule_id
                  WHERE f.description LIKE ? OR u.name LIKE ? OR s.name LIKE ?
                  ORDER BY f.id DESC
                  LIMIT " . self::PER_GROUP,
                [$like, $like, $like]
            );

            // Rencana aksi: anggota hanya melihat miliknya (sama dengan M4).
            $isSuper = Auth::isSuperAdmin();
            $plans = Database::all(
                "SELECT ap.id, ap.description, ap.status, ap.priority, ap.due_date,
                        u.name AS unit_name, pu.name AS pic_name, f.id AS finding_id
                   FROM action_plans ap
                   JOIN ami_findings f ON f.id = ap.finding_id
                   JOIN ami_assignments a ON a.id = f.assignment_id
                   JOIN units u ON u.id = a.unit_id
                   LEFT JOIN users pu ON pu.id = ap.pic_user_id
                  WHERE (ap.description LIKE ? OR f.description LIKE ?
                         OR pu.name LIKE ? OR u.name LIKE ?)"
                  . ($isSuper ? '' : ' AND ap.pic_user_id = ?') . "
                  ORDER BY FIELD(ap.status, 'open', 'in_progress', 'done', 'verified'), ap.id DESC
                  LIMIT " . self::PER_GROUP,
                $isSuper ? [$like, $like, $like, $like] : [$like, $like, $like, $like, (int)Auth::id()]
            );

            $total = count($docs) + count($findings) + count($plans);
        }

        $this->view('search/index', [
            'title'    => 'Pencarian',
            'q'        => $q,
            'docs'     => $docs,
            'findings' => $findings,
            'plans'    => $plans,
            'total'    => $total,
        ]);
    }
}
