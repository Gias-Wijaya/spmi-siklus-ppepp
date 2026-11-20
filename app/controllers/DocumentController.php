<?php
declare(strict_types=1);

/**
 * M1 — Dokumen SPMI: unggah, klasifikasi, masa berlaku (🟢🟡🔴),
 * versi/revisi. Pengesahan berkas (TTD basah) dilakukan di luar sistem —
 * sistem hanya menyimpan berkas + metadata + riwayat versi.
 */
final class DocumentController extends Controller
{
    /* ------------------------------------------------------------------ Index */
    public function index(): void
    {
        $filters = [
            'q'           => trim((string)($_GET['q'] ?? '')),
            'category_id' => (int)($_GET['category_id'] ?? 0),
            'status'      => (string)($_GET['status'] ?? ''),
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per  = (int)config('per_page', 10);

        $result = Document::search($filters, $page, $per);

        $this->view('documents/index', [
            'title'      => 'Dokumen SPMI',
            'rows'       => $result['rows'],
            'total'      => $result['total'],
            'page'       => $page,
            'per'        => $per,
            'filters'    => $filters,
            'categories' => DocumentCategory::flat(),
            'counts'     => Document::countsByColor(),
        ]);
    }

    /* ------------------------------------------------------------------ Export (FR-14) */

    /**
     * Ekspor daftar dokumen menjadi CSV siap Excel (FR-14).
     * Saringan ikut berlaku — memakai filter persis seperti halaman daftar.
     */
    public function export(): void
    {
        $filters = [
            'q'           => trim((string)($_GET['q'] ?? '')),
            'category_id' => (int)($_GET['category_id'] ?? 0),
            'status'      => (string)($_GET['status'] ?? ''),
        ];

        // Sama seperti daftar layar (tanpa paginasi) sehingga isinya identik.
        $rows = Document::search($filters, 1, 100000)['rows'];

        $colorLabel = [
            'green'  => 'Hijau (berlaku)',
            'yellow' => 'Kuning (mendekati masa berlaku)',
            'red'    => 'Merah (kedaluwarsa)',
        ];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['title'],
                $r['doc_number'],
                $r['version'],
                $r['category_name'] ?? '-',
                $r['standard_name'] ?? '-',
                $r['unit_name'] ?? '-',
                $r['owner_name'] ?? '-',
                format_date($r['effective_date']),
                format_date($r['expiry_date']),
                $colorLabel[$r['status_color']] ?? $r['status_color'],
                format_date($r['updated_at'], 'd M Y H:i'),
            ];
        }

        export_csv('dokumen-spmi-' . date('Y-m-d') . '.csv', array_merge(
            [[
                'Judul', 'Nomor dokumen', 'Versi', 'Kategori', 'Standar', 'Unit', 'Pemilik',
                'Berlaku sejak', 'Berlaku s/d', 'Status masa berlaku', 'Diperbarui',
            ]],
            $data
        ));
    }

    /* ------------------------------------------------------------------ Create */
    public function create(): void
    {
        $this->view('documents/form', [
            'title'      => 'Unggah Dokumen',
            'doc'        => null,
            'categories' => DocumentCategory::flat(),
            'standards'  => Standard::active(),
            'units'      => Unit::active(),
            'errors'     => [],
        ]);
    }

    public function store(): void
    {
        $errors = $this->required(
            ['title', 'expiry_date'],
            ['title' => 'Judul dokumen', 'expiry_date' => 'Masa berlaku']
        );
        $this->validateDates($errors);

        // Unit: Anggota terkunci ke unitnya sendiri.
        $unitId = Auth::isSuperAdmin()
            ? ((int)($_POST['unit_id'] ?? 0) ?: null)
            : Auth::unitId();

        if ($unitId === null) {
            $errors['unit_id'] = 'Akun Anda belum terhubung ke unit kerja. Hubungi LPM.';
        }

        if ($errors) {
            $this->keepOld(['title', 'doc_number', 'version', 'category_id', 'standard_id', 'unit_id', 'effective_date', 'expiry_date', 'description']);
            flash('danger', 'Periksa kembali isian formulir.');
            $this->renderForm(null, $errors);
        }

        // Semua validasi lolos → baru unggah berkas.
        $upload = upload_file('file', 'dokumen');
        if (isset($upload['error'])) {
            flash('danger', $upload['error']);
            $this->keepOld(['title', 'doc_number', 'version', 'category_id', 'standard_id', 'unit_id', 'effective_date', 'expiry_date', 'description']);
            $this->renderForm(null, ['file' => $upload['error']]);
        }

        $expiry = trim((string)($_POST['expiry_date'] ?? '')) ?: null;

        // Unggah = langsung aktif sesuai masa berlaku (tanpa alur persetujuan).
        $id = Document::create([
            'category_id'     => ((int)($_POST['category_id'] ?? 0)) ?: null,
            'standard_id'     => ((int)($_POST['standard_id'] ?? 0)) ?: null,
            'unit_id'         => $unitId,
            'owner_user_id'   => Auth::id(),
            'title'           => trim((string)$_POST['title']),
            'doc_number'      => trim((string)($_POST['doc_number'] ?? '')) ?: null,
            'version'         => trim((string)($_POST['version'] ?? '')) ?: '1.0',
            'effective_date'  => trim((string)($_POST['effective_date'] ?? '')) ?: null,
            'expiry_date'     => $expiry,
            'file_path'       => $upload['path'],
            'status_color'    => 'green',
            'description'     => trim((string)($_POST['description'] ?? '')) ?: null,
        ]);

        Document::refreshStatusColors();

        audit('create', 'documents', $id, ['title' => $_POST['title']]);
        old_clear();
        flash('success', 'Dokumen berhasil diunggah dan langsung berlaku sesuai masa berlaku yang ditetapkan.');
        $this->redirect('/documents/' . $id);
    }

    /* ------------------------------------------------------------------ Show */
    public function show(string $id): void
    {
        $doc = Document::detail((int)$id);
        if (!$doc) {
            $this->notFound('Dokumen tidak ditemukan.');
        }

        $this->view('documents/show', [
            'title'     => $doc['title'],
            'doc'       => $doc,
            'versions'  => DocumentVersion::forDocument((int)$id),
            'canEdit'   => Document::canEdit($doc),
        ]);
    }

    /* ------------------------------------------------------------------ Edit */
    public function edit(string $id): void
    {
        $doc = Document::find((int)$id);
        if (!$doc) {
            $this->notFound('Dokumen tidak ditemukan.');
        }
        if (!Document::canEdit($doc)) {
            $this->forbidden();
        }

        $this->renderForm($doc, []);
    }

    public function update(string $id): void
    {
        $doc = Document::find((int)$id);
        if (!$doc) {
            $this->notFound('Dokumen tidak ditemukan.');
        }
        if (!Document::canEdit($doc)) {
            $this->forbidden();
        }

        $errors = $this->required(['title'], ['title' => 'Judul dokumen']);
        $this->validateDates($errors);

        $unitId = Auth::isSuperAdmin()
            ? ((int)($_POST['unit_id'] ?? 0) ?: null)
            : Auth::unitId();

        if ($errors) {
            $this->keepOld(['title', 'doc_number', 'version', 'category_id', 'standard_id', 'unit_id', 'effective_date', 'expiry_date', 'description']);
            flash('danger', 'Periksa kembali isian formulir.');
            $this->renderForm($doc, $errors);
        }

        $old = ['title' => $doc['title'], 'expiry_date' => $doc['expiry_date'], 'version' => $doc['version']];

        Document::updateById((int)$id, [
            'category_id'    => ((int)($_POST['category_id'] ?? 0)) ?: null,
            'standard_id'    => ((int)($_POST['standard_id'] ?? 0)) ?: null,
            'unit_id'        => $unitId,
            'title'          => trim((string)$_POST['title']),
            'doc_number'     => trim((string)($_POST['doc_number'] ?? '')) ?: null,
            'version'        => trim((string)($_POST['version'] ?? '')) ?: $doc['version'],
            'effective_date' => trim((string)($_POST['effective_date'] ?? '')) ?: null,
            'expiry_date'    => trim((string)($_POST['expiry_date'] ?? '')) ?: null,
            'description'    => trim((string)($_POST['description'] ?? '')) ?: null,
        ]);

        Document::refreshStatusColors();
        audit('update', 'documents', (int)$id, ['old' => $old, 'new' => ['title' => $_POST['title'], 'expiry_date' => $_POST['expiry_date'] ?? null]]);
        old_clear();
        flash('success', 'Metadata dokumen diperbarui.');
        $this->redirect('/documents/' . (int)$id);
    }

    /* ------------------------------------------------------------------ Revisi (versi baru) */
    public function revise(string $id): void
    {
        $doc = Document::find((int)$id);
        if (!$doc) {
            $this->notFound('Dokumen tidak ditemukan.');
        }
        if (!Document::canEdit($doc)) {
            $this->forbidden();
        }

        $version = trim((string)($_POST['version'] ?? ''));
        if ($version === '') {
            flash('danger', 'Nomor versi baru wajib diisi.');
            $this->redirect('/documents/' . (int)$id . '/edit');
        }

        $upload = upload_file('file', 'dokumen');
        if (isset($upload['error'])) {
            flash('danger', 'Revisi gagal — ' . $upload['error']);
            $this->redirect('/documents/' . (int)$id . '/edit');
        }

        $now = date('Y-m-d H:i:s');

        DocumentVersion::create([
            'document_id'  => (int)$id,
            'version'      => $version,
            'file_path'    => $upload['path'],
            'change_note'  => trim((string)($_POST['change_note'] ?? '')) ?: null,
            'submitted_by' => Auth::id(),
        ]);

        Document::updateById((int)$id, [
            'version'   => $version,
            'file_path' => $upload['path'],
        ]);

        Document::refreshStatusColors();

        audit('revise', 'documents', (int)$id, ['old_version' => $doc['version'], 'new_version' => $version]);
        flash('success', 'Versi baru diunggah — versi sebelumnya tetap terlacak pada riwayat versi.');
        $this->redirect('/documents/' . (int)$id);
    }

    /* ------------------------------------------------------------------ Unduh */
    public function download(string $id): void
    {
        $doc = Document::find((int)$id);
        if (!$doc) {
            $this->notFound('Dokumen tidak ditemukan.');
        }

        $base = realpath(rtrim((string)config('upload_dir'), '/'));
        $full = realpath(rtrim((string)config('upload_dir'), '/') . '/' . ltrim((string)$doc['file_path'], '/'));

        if ($base === false || $full === false || !str_starts_with((string)$full, (string)$base) || !is_file($full)) {
            $this->notFound('Berkas tidak ditemukan di server.');
        }

        audit('download', 'documents', (int)$id, ['title' => $doc['title']]);

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

    /* ------------------------------------------------------------------ Bantuan */
    private function renderForm(?array $doc, array $errors): void
    {
        $this->view('documents/form', [
            'title'      => $doc ? 'Ubah Dokumen' : 'Unggah Dokumen',
            'doc'        => $doc,
            'categories' => DocumentCategory::flat(),
            'standards'  => Standard::active(),
            'units'      => Unit::active(),
            'errors'     => $errors,
        ]);
    }

    private function validateDates(array &$errors): void
    {
        foreach (['effective_date', 'expiry_date'] as $field) {
            $value = trim((string)($_POST[$field] ?? ''));
            if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $errors[$field] = 'Format tanggal tidak valid.';
            }
        }
        $effective = trim((string)($_POST['effective_date'] ?? ''));
        $expiry    = trim((string)($_POST['expiry_date'] ?? ''));
        if ($effective !== '' && $expiry !== '' && $expiry <= $effective) {
            $errors['expiry_date'] = 'Masa berlaku harus setelah tanggal mulai berlaku.';
        }
    }

}
