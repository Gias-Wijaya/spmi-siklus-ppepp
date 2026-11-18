<?php
declare(strict_types=1);

/**
 * M4 — Pengendalian–Peningkatan (FR-09–FR-12):
 *  - FR-09 konversi temuan AMI menjadi rencana aksi (PIC, tenggat, prioritas);
 *  - FR-10 pelacakan status tindak lanjut: open → in_progress → done → verified;
 *  - FR-11 unggah bukti tindak lanjut + verifikasi (catatan) oleh Super Admin;
 *  - FR-12 umpan balik verifikasi berupa permintaan revisi dokumen (siklus berikutnya).
 *
 * Aturan bisnis (04-dfd.md): tidak ada aksi tanpa temuan; tuntas hanya setelah
 * bukti diverifikasi Super Admin; permintaan revisi memicu alur M1.
 */
final class ActionPlanController extends Controller
{
    /* ================================================== Dasbor M4 (FR-10) */

    public function index(): void
    {
        $periods = AmiSchedule::ordered();

        $scheduleId = (int)($_GET['schedule_id'] ?? 0);
        $schedule   = $scheduleId > 0 ? AmiSchedule::find($scheduleId) : null;
        if (!$schedule) {
            $schedule   = AmiSchedule::activeOrLatest();
            $scheduleId = $schedule ? (int)$schedule['id'] : 0;
        }

        $isSuper = Auth::isSuperAdmin();
        $status  = (string)($_GET['status'] ?? '');
        $prio    = (string)($_GET['priority'] ?? '');
        if (!in_array($status, ['open', 'in_progress', 'done', 'verified'], true)) {
            $status = '';
        }
        if (!in_array($prio, ['tinggi', 'sedang', 'rendah'], true)) {
            $prio = '';
        }

        $filters = [
            'schedule_id' => $scheduleId,
            'status'      => $status,
            'priority'    => $prio,
            'pic_user_id' => $isSuper ? 0 : (int)Auth::id(),
        ];

        $this->view('followup/index', [
            'title'      => 'Pengendalian–Peningkatan',
            'periods'    => $periods,
            'schedule'   => $schedule,
            'rows'       => ActionPlan::list($filters),
            'recap'      => ActionPlan::recap($scheduleId, $isSuper ? 0 : (int)Auth::id()),
            'isSuper'    => $isSuper,
            'findings'   => ($isSuper && $schedule) ? ActionPlan::findingsForConversion($scheduleId) : [],
            'picOptions' => $isSuper ? User::activeAll() : [],
            'errors'     => [],
            'filterStatus'  => $status,
            'filterPriority'=> $prio,
        ]);
    }

    /* ------------------------------------------------------------ Ekspor (FR-14) */

    /**
     * Ekspor daftar rencana aksi menjadi CSV siap Excel (FR-14).
     * Saringan jadwal/status/prioritas dan keterbatasan PIC anggota ikut berlaku
     * — memakai filter persis seperti halaman daftar.
     */
    public function export(): void
    {
        $scheduleId = (int)($_GET['schedule_id'] ?? 0);
        $schedule   = $scheduleId > 0 ? AmiSchedule::find($scheduleId) : null;
        if (!$schedule) {
            $schedule   = AmiSchedule::activeOrLatest();
            $scheduleId = $schedule ? (int)$schedule['id'] : 0;
        }

        $isSuper = Auth::isSuperAdmin();
        $status  = (string)($_GET['status'] ?? '');
        $prio    = (string)($_GET['priority'] ?? '');
        if (!in_array($status, ['open', 'in_progress', 'done', 'verified'], true)) {
            $status = '';
        }
        if (!in_array($prio, ['tinggi', 'sedang', 'rendah'], true)) {
            $prio = '';
        }

        $rows = ActionPlan::list([
            'schedule_id' => $scheduleId,
            'status'      => $status,
            'priority'    => $prio,
            'pic_user_id' => $isSuper ? 0 : (int)Auth::id(),
        ]);

        $statusLabel = [
            'open'        => 'Terbuka',
            'in_progress' => 'Berjalan',
            'done'        => 'Selesai',
            'verified'    => 'Terverifikasi',
        ];
        $priorityLabel = [
            'tinggi' => 'Tinggi',
            'sedang' => 'Sedang',
            'rendah' => 'Rendah',
        ];

        $lines = [[
            'Rencana Tindak Lanjut — ' . ($schedule['name'] ?? '-'),
            $status !== '' ? 'Saringan status: ' . ($statusLabel[$status] ?? $status) : 'Seluruh status',
            $prio !== '' ? 'Saringan prioritas: ' . ($priorityLabel[$prio] ?? $prio) : 'Seluruh prioritas',
        ]];
        $lines[] = [];
        $lines[] = [
            'Jadwal AMI', 'Temuan', 'Indikator', 'Unit temuan', 'Rencana aksi',
            'PIC', 'Unit PIC', 'Tenggat', 'Prioritas', 'Status',
            'Bukti (total/menunggu)', 'Diverifikasi', 'Catatan verifikasi', 'Dokumen revisi',
        ];

        foreach ($rows as $r) {
            $lines[] = [
                $r['schedule_name'],
                $r['finding_desc'],
                $r['indicator_code'] ?? '-',
                $r['unit_name'],
                $r['description'],
                $r['pic_name'] ?? '-',
                $r['pic_unit_name'] ?? '-',
                $r['due_date'] !== null ? format_date($r['due_date']) : '-',
                $priorityLabel[$r['priority']] ?? $r['priority'],
                $statusLabel[$r['status']] ?? $r['status'],
                (int)$r['evidence_count'] . ' / ' . (int)$r['pending_evidence'],
                $r['verifier_name'] !== null
                    ? $r['verifier_name'] . ' (' . format_date($r['verified_at'] ?? null) . ')'
                    : '-',
                $r['verification_note'] ?? '-',
                $r['revision_doc_title'] ?? '-',
            ];
        }

        export_csv('rencana-aksi-' . date('Y-m-d') . '.csv', $lines);
    }

    /* ====================================== Konversi temuan (FR-09, Super) */

    public function store(): void
    {
        $this->requireSuperAdmin();

        $finding = Finding::find((int)($_POST['finding_id'] ?? 0));
        if (!$finding) {
            flash('danger', 'Temuan tidak ditemukan — rencana aksi wajib bersumber dari temuan AMI.');
            $this->redirect('/followup');
        }

        $description = trim((string)($_POST['description'] ?? ''));
        $picId       = (int)($_POST['pic_user_id'] ?? 0);
        $dueDate     = (string)($_POST['due_date'] ?? '');
        $priority    = (string)($_POST['priority'] ?? 'sedang');

        $errors = [];
        if ($description === '') {
            $errors[] = 'Uraian tindak lanjut wajib diisi.';
        }
        $pic = $picId > 0 ? User::find($picId) : null;
        if (!$pic || (int)$pic['is_active'] !== 1) {
            $errors[] = 'Penanggung jawab harus pengguna aktif.';
        }
        $due = $dueDate !== '' ? DateTime::createFromFormat('Y-m-d', $dueDate) : false;
        if ($due === false || $due->format('Y-m-d') !== $dueDate) {
            $errors[] = 'Tenggat waktu wajib diisi dengan format tanggal yang benar.';
        }
        if (!in_array($priority, ['tinggi', 'sedang', 'rendah'], true)) {
            $priority = 'sedang';
        }
        if ($errors) {
            flash('danger', implode(' ', $errors));
            $this->redirect('/followup');
        }

        $planId = ActionPlan::create([
            'finding_id'  => (int)$finding['id'],
            'pic_user_id' => $picId,
            'pic_unit_id' => $pic['unit_id'] !== null ? (int)$pic['unit_id'] : null,
            'description' => $description,
            'due_date'    => $dueDate,
            'status'      => 'open',
            'priority'    => $priority,
        ]);

        audit('create', 'action_plans', $planId, [
            'finding_id' => (int)$finding['id'],
            'pic_user_id'=> $picId,
            'due_date'   => $dueDate,
            'priority'   => $priority,
        ]);

        Notification::send(
            $picId,
            'Rencana aksi ditugaskan',
            'Anda ditugaskan menindaklanjuti temuan: ' . mb_substr($description, 0, 90)
                . ' (tenggat ' . format_date($dueDate) . ').',
            'temuan',
            'action_plans',
            $planId
        );

        flash('success', 'Rencana aksi berhasil dibuat dan ditugaskan kepada ' . $pic['name'] . '.');
        $assignment = AmiAssignment::find((int)$finding['assignment_id']);
        $this->redirect('/followup' . ($assignment ? '?schedule_id=' . (int)$assignment['schedule_id'] : ''));
    }

    /* ============================================ Detail rencana aksi */

    public function show(string $id): void
    {
        $plan = ActionPlan::withDetails((int)$id);
        if (!$plan) {
            $this->notFound('Rencana aksi tidak ditemukan.');
        }
        $this->authorizePlan($plan);

        $isSuper   = Auth::isSuperAdmin();
        $evidence  = ActionEvidence::forPlan((int)$id);
        $documents = [];
        if ($isSuper && $plan['status'] === 'verified' && $plan['revision_document_id'] === null) {
            $documents = Database::all(
                "SELECT id, title, version, approval_status
                   FROM documents
                  WHERE approval_status <> 'archived'
                  ORDER BY title ASC"
            );
        }

        $this->view('followup/show', [
            'title'     => 'Rencana Aksi #' . (int)$id,
            'plan'      => $plan,
            'evidence'  => $evidence,
            'documents' => $documents,
            'isSuper'   => $isSuper,
            'errors'    => [],
        ]);
    }

    /* =============================================== Penyuntingan (Super) */

    public function update(string $id): void
    {
        $this->requireSuperAdmin();

        $plan = ActionPlan::find((int)$id);
        if (!$plan) {
            $this->notFound('Rencana aksi tidak ditemukan.');
        }
        if ($plan['status'] === 'verified') {
            flash('warning', 'Rencana aksi sudah terverifikasi dan tidak dapat disunting.');
            $this->redirect('/followup/plans/' . (int)$id);
        }

        $description = trim((string)($_POST['description'] ?? ''));
        $picId       = (int)($_POST['pic_user_id'] ?? 0);
        $dueDate     = (string)($_POST['due_date'] ?? '');
        $priority    = (string)($_POST['priority'] ?? 'sedang');

        $pic = $picId > 0 ? User::find($picId) : null;
        $due = $dueDate !== '' ? DateTime::createFromFormat('Y-m-d', $dueDate) : false;
        if ($description === '' || !$pic || (int)$pic['is_active'] !== 1
            || $due === false || $due->format('Y-m-d') !== $dueDate) {
            flash('danger', 'Periksa kembali isian: uraian, penanggung jawab aktif, dan tenggat wajib benar.');
            $this->redirect('/followup/plans/' . (int)$id);
        }
        if (!in_array($priority, ['tinggi', 'sedang', 'rendah'], true)) {
            $priority = 'sedang';
        }

        $oldPic = (int)$plan['pic_user_id'];
        ActionPlan::updateById((int)$id, [
            'description' => $description,
            'pic_user_id' => $picId,
            'pic_unit_id' => $pic['unit_id'] !== null ? (int)$pic['unit_id'] : null,
            'due_date'    => $dueDate,
            'priority'    => $priority,
        ]);

        audit('update', 'action_plans', (int)$id, [
            'description' => $description,
            'pic_user_id' => $picId,
            'due_date'    => $dueDate,
            'priority'    => $priority,
        ]);

        if ($oldPic !== $picId) {
            Notification::send(
                $picId,
                'Penanggung jawab rencana aksi berubah',
                'Anda kini menjadi penanggung jawab: ' . mb_substr($description, 0, 90),
                'temuan',
                'action_plans',
                (int)$id
            );
        }

        flash('success', 'Rencana aksi diperbarui.');
        $this->redirect('/followup/plans/' . (int)$id);
    }

    /* ==================================== Status tindak lanjut (FR-10) */

    public function updateStatus(string $id): void
    {
        $plan = ActionPlan::find((int)$id);
        if (!$plan) {
            $this->notFound('Rencana aksi tidak ditemukan.');
        }
        $this->authorizePlan($plan);

        $target = (string)($_POST['status'] ?? '');
        if ($target !== 'in_progress') {
            flash('danger', 'Status tujuan tidak valid — hanya "mulai dikerjakan" yang diizinkan di sini.');
            $this->redirect('/followup/plans/' . (int)$id);
        }
        if ($plan['status'] !== 'open') {
            flash('warning', 'Hanya rencana berstatus terbuka yang dapat dimulai.');
            $this->redirect('/followup/plans/' . (int)$id);
        }

        ActionPlan::updateById((int)$id, ['status' => 'in_progress']);
        audit('update', 'action_plans', (int)$id, ['status' => 'in_progress']);

        if (!Auth::isSuperAdmin()) {
            Notification::send(
                User::superAdminIds(),
                'Tindak lanjut dimulai',
                'Penanggung jawab mulai mengerjakan rencana aksi #' . (int)$id . '.',
                'temuan',
                'action_plans',
                (int)$id
            );
        }

        flash('success', 'Status rencana aksi: berjalan (in_progress).');
        $this->redirect('/followup/plans/' . (int)$id);
    }

    /* ================================================== Bukti (FR-11) */

    public function uploadEvidence(string $id): void
    {
        $plan = ActionPlan::withDetails((int)$id);
        if (!$plan) {
            $this->notFound('Rencana aksi tidak ditemukan.');
        }
        $this->authorizePlan($plan);

        if ($plan['status'] === 'verified') {
            flash('warning', 'Rencana aksi sudah terverifikasi — bukti tidak dapat ditambahkan.');
            $this->redirect('/followup/plans/' . (int)$id);
        }
        if (ActionEvidence::pendingFor((int)$id)) {
            flash('warning', 'Bukti sebelumnya masih menunggu verifikasi LPM.');
            $this->redirect('/followup/plans/' . (int)$id);
        }

        $file = upload_file('file', 'bukti-tindak-lanjut');
        if (isset($file['error'])) {
            flash('danger', $file['error']);
            $this->redirect('/followup/plans/' . (int)$id);
        }

        $evidenceId = ActionEvidence::create([
            'action_plan_id' => (int)$id,
            'file_path'      => (string)$file['path'],
            'note'           => trim((string)($_POST['note'] ?? '')) ?: null,
            'uploaded_by'    => Auth::id(),
            'status'         => 'pending',
        ]);

        // Pelaksanaan dinyatakan selesai → menunggu verifikasi LPM.
        ActionPlan::updateById((int)$id, ['status' => 'done']);

        audit('create', 'action_evidence', $evidenceId, ['action_plan_id' => (int)$id, 'file' => $file['name']]);
        audit('update', 'action_plans', (int)$id, ['status' => 'done']);

        if (!Auth::isSuperAdmin()) {
            Notification::send(
                User::superAdminIds(),
                'Bukti tindak lanjut menunggu verifikasi',
                'Bukti untuk rencana aksi #' . (int)$id . ' telah diunggah dan menunggu verifikasi LPM.',
                'temuan',
                'action_plans',
                (int)$id
            );
        }

        flash('success', 'Bukti terunggah — status rencana aksi: selesai, menunggu verifikasi.');
        $this->redirect('/followup/plans/' . (int)$id);
    }

    /** Verifikasi bukti oleh Super Admin: diterima → verified; dikembalikan → in_progress. */
    public function verify(string $id): void
    {
        $this->requireSuperAdmin();

        $evidence = ActionEvidence::find((int)$id);
        if (!$evidence) {
            $this->notFound('Bukti tidak ditemukan.');
        }
        $plan = ActionPlan::withDetails((int)$evidence['action_plan_id']);
        if (!$plan) {
            $this->notFound('Rencana aksi tidak ditemukan.');
        }
        if ($evidence['status'] !== 'pending') {
            flash('warning', 'Bukti ini sudah pernah diputuskan.');
            $this->redirect('/followup/plans/' . (int)$plan['id']);
        }

        $decision = (string)($_POST['decision'] ?? '');
        $note     = trim((string)($_POST['note'] ?? ''));
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            flash('danger', 'Keputusan verifikasi tidak valid.');
            $this->redirect('/followup/plans/' . (int)$plan['id']);
        }
        if ($decision === 'rejected' && $note === '') {
            flash('danger', 'Catatan wajib diisi saat bukti dikembalikan.');
            $this->redirect('/followup/plans/' . (int)$plan['id']);
        }

        ActionEvidence::updateById((int)$id, ['status' => $decision]);

        $picId = (int)$plan['pic_user_id'];
        if ($decision === 'approved') {
            ActionPlan::updateById((int)$plan['id'], [
                'status'            => 'verified',
                'verified_by'       => Auth::id(),
                'verified_at'       => date('Y-m-d H:i:s'),
                'verification_note' => $note !== '' ? $note : null,
            ]);
            audit('update', 'action_plans', (int)$plan['id'], ['verification' => 'approved']);
            if ($picId > 0 && $picId !== (int)Auth::id()) {
                Notification::send(
                    $picId,
                    'Tindak lanjut terverifikasi',
                    'Bukti rencana aksi #' . (int)$plan['id'] . ' diterima LPM'
                        . ($note !== '' ? ' — catatan: ' . mb_substr($note, 0, 90) : '.'),
                    'temuan',
                    'action_plans',
                    (int)$plan['id']
                );
            }
            flash('success', 'Bukti diterima — rencana aksi diverifikasi.');
        } else {
            ActionPlan::updateById((int)$plan['id'], [
                'status'            => 'in_progress',
                'verification_note' => $note,
            ]);
            audit('update', 'action_evidence', (int)$id, ['decision' => 'rejected']);
            if ($picId > 0 && $picId !== (int)Auth::id()) {
                Notification::send(
                    $picId,
                    'Bukti tindak lanjut dikembalikan',
                    'Perbaiki bukti rencana aksi #' . (int)$plan['id'] . ' — catatan: ' . mb_substr($note, 0, 90),
                    'temuan',
                    'action_plans',
                    (int)$plan['id']
                );
            }
            flash('warning', 'Bukti dikembalikan — status rencana aksi kembali berjalan.');
        }

        $this->redirect('/followup/plans/' . (int)$plan['id']);
    }

    public function downloadEvidence(string $id): void
    {
        $evidence = ActionEvidence::find((int)$id);
        if (!$evidence || empty($evidence['file_path'])) {
            $this->notFound('Bukti tidak ditemukan.');
        }
        $plan = ActionPlan::find((int)$evidence['action_plan_id']);
        if (!$plan) {
            $this->notFound('Rencana aksi tidak ditemukan.');
        }
        $this->authorizePlan($plan);

        $base = realpath(rtrim((string)config('upload_dir'), '/'));
        $full = realpath(rtrim((string)config('upload_dir'), '/') . '/' . ltrim((string)$evidence['file_path'], '/'));

        if ($base === false || $full === false || !str_starts_with((string)$full, (string)$base) || !is_file($full)) {
            $this->notFound('Berkas tidak ditemukan di server.');
        }

        audit('download', 'action_evidence', (int)$id, ['file' => basename((string)$full)]);

        $ext  = strtolower(pathinfo((string)$full, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'pdf'  => 'application/pdf',
            'doc', 'docx' => 'application/msword',
            'xls', 'xlsx' => 'application/vnd.ms-excel',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . rawurlencode(basename((string)$full)) . '"');
        header('Content-Length: ' . filesize($full));
        header('X-Content-Type-Options: nosniff');
        readfile($full);
        exit;
    }

    /* ================================== Umpan balik revisi dokumen (FR-12) */

    public function requestRevision(string $id): void
    {
        $this->requireSuperAdmin();

        $plan = ActionPlan::withDetails((int)$id);
        if (!$plan) {
            $this->notFound('Rencana aksi tidak ditemukan.');
        }
        if ($plan['status'] !== 'verified') {
            flash('warning', 'Permintaan revisi dokumen hanya dapat diberikan setelah verifikasi.');
            $this->redirect('/followup/plans/' . (int)$id);
        }
        if ($plan['revision_document_id'] !== null) {
            flash('warning', 'Permintaan revisi untuk rencana ini sudah pernah dibuat.');
            $this->redirect('/followup/plans/' . (int)$id);
        }

        $documentId = (int)($_POST['document_id'] ?? 0);
        $note       = trim((string)($_POST['note'] ?? ''));
        $document   = $documentId > 0 ? Document::find($documentId) : null;

        if (!$document || $document['approval_status'] === 'archived') {
            flash('danger', 'Dokumen tujuan tidak ditemukan atau sudah diarsipkan.');
            $this->redirect('/followup/plans/' . (int)$id);
        }
        if ($note === '') {
            flash('danger', 'Catatan umpan balik revisi wajib diisi.');
            $this->redirect('/followup/plans/' . (int)$id);
        }

        ActionPlan::updateById((int)$id, [
            'revision_document_id'   => $documentId,
            'revision_note'          => $note,
            'revision_requested_at'  => date('Y-m-d H:i:s'),
        ]);

        audit('update', 'action_plans', (int)$id, [
            'revision_document_id' => $documentId,
            'revision_note'        => $note,
        ]);

        // Pemilik dokumen + LPM menerima tautan langsung ke halaman dokumen (F15 → F1).
        $recipients = User::superAdminIds();
        if ((int)($document['owner_user_id'] ?? 0) > 0) {
            $recipients[] = (int)$document['owner_user_id'];
        }
        $recipients = array_values(array_unique(array_diff(array_map('intval', $recipients), [Auth::id()])));
        if ($recipients) {
            Notification::send(
                $recipients,
                'Permintaan revisi dokumen',
                '"' . $document['title'] . '" diminta direvisi dari hasil tindak lanjut: '
                    . mb_substr($note, 0, 90),
                'dokumen',
                'documents',
                $documentId
            );
        }

        flash('success', 'Permintaan revisi dokumen terkirim — pemilik dokumen menerima notifikasi.');
        $this->redirect('/followup/plans/' . (int)$id);
    }

    /* ======================================================== Penjagaan */

    /** Anggota hanya untuk rencana miliknya; Super Admin untuk semua. */
    private function authorizePlan(array $plan): void
    {
        if (Auth::isSuperAdmin()) {
            return;
        }
        if ((int)($plan['pic_user_id'] ?? 0) === (int)Auth::id()) {
            return;
        }
        $this->forbidden();
    }
}
