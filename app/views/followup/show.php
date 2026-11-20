<?php
/**
 * M4 — Detail rencana aksi: temuan sumber, status, bukti + verifikasi (FR-11),
 * penyuntingan PIC/tenggat (Super), dan permintaan revisi dokumen (FR-12).
 * @var array $plan @var array $evidence @var array $documents @var bool $isSuper @var array $errors
 */
$isPic    = (int)($plan['pic_user_id'] ?? 0) === (int)Auth::id();
$canAct   = $isSuper || $isPic;
$today    = date('Y-m-d');
$overdue  = $plan['due_date'] !== null && $plan['due_date'] < $today && $plan['status'] !== 'verified';
$pending  = null;
foreach ($evidence as $ev) {
    if ($ev['status'] === 'pending') {
        $pending = $ev;
        break;
    }
}
$evidenceStatus = static function (string $s): string {
    return match ($s) {
        'pending'  => '<span class="badge text-bg-info">menunggu verifikasi</span>',
        'approved' => '<span class="badge text-bg-success">diterima</span>',
        'rejected' => '<span class="badge text-bg-danger">dikembalikan</span>',
        default    => '<span class="badge text-bg-secondary">' . e($s) . '</span>',
    };
};
?>

<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-arrow-repeat me-1"></i>
        <strong>Rencana aksi tindak lanjut</strong>, sumber: temuan AMI —
        <?= e($plan['schedule_name']) ?> · <?= e($plan['unit_name']) ?> — bukti perbaikan diverifikasi
        oleh LPM sebelum ditutup (FR-10–FR-12).
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <?= action_status_badge((string)$plan['status']) ?>
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('/followup')) ?>">
            <i class="bi bi-arrow-left me-1"></i>Daftar rencana aksi
        </a>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger py-2 small">
        <?php foreach ($errors as $e1): ?><?= e($e1) ?><br><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <!-- Rencana aksi -->
        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Rencana aksi</strong></div>
            <div class="card-body">
                <p class="mb-2"><?= nl2br(e($plan['description'])) ?></p>
                <div class="row small">
                    <div class="col-sm-4 mb-2">
                        <span class="text-muted">Penanggung jawab</span><br>
                        <strong><?= e($plan['pic_name'] ?? '—') ?></strong> · <?= e($plan['pic_unit_name'] ?? '—') ?>
                    </div>
                    <div class="col-sm-4 mb-2">
                        <span class="text-muted">Tenggat</span><br>
                        <?= format_date((string)$plan['due_date']) ?>
                        <?php if ($overdue): ?><span class="badge text-bg-danger">terlambat</span><?php endif; ?>
                    </div>
                    <div class="col-sm-4 mb-2">
                        <span class="text-muted">Prioritas</span><br>
                        <?= e($plan['priority']) ?>
                    </div>
                </div>
                <?php if ($plan['status'] === 'verified'): ?>
                    <hr class="my-2">
                    <div class="small">
                        <span class="text-muted">Diverifikasi:</span>
                        <strong><?= e($plan['verified_by'] !== null ? ($plan['verifier_name'] ?? 'LPM') : '—') ?></strong>
                        · <?= format_date((string)$plan['verified_at'], 'd M Y H:i') ?>
                        <?php if ($plan['verification_note'] !== null && $plan['verification_note'] !== ''): ?>
                            <br><span class="text-muted">Catatan verifikasi:</span> <?= e($plan['verification_note']) ?>
                        <?php endif; ?>
                    </div>
                <?php elseif ($plan['verification_note'] !== null && $plan['verification_note'] !== '' && $plan['status'] === 'in_progress'): ?>
                    <hr class="my-2">
                    <div class="small text-muted">
                        Catatan pengembalian terakhir: <?= e($plan['verification_note']) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bukti & verifikasi (FR-11) -->
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between">
                <strong>Bukti tindak lanjut</strong>
                <span class="small text-muted"><?= count($evidence) ?> berkas</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Berkas</th>
                        <th>Catatan</th>
                        <th style="width:130px">Diunggah</th>
                        <th style="width:130px">Status</th>
                        <th style="width:230px" class="text-end">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$evidence): ?>
                        <tr><td colspan="5" class="text-muted">Belum ada bukti diunggah.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($evidence as $ev): ?>
                        <tr>
                            <td class="small">
                                <a href="<?= e(url('/followup/evidence/' . (int)$ev['id'] . '/download')) ?>">
                                    <i class="bi bi-paperclip me-1 text-muted"></i><?= e(basename((string)$ev['file_path'])) ?>
                                </a>
                            </td>
                            <td class="small"><?= e((string)($ev['note'] ?? '—')) ?></td>
                            <td class="small">
                                <?= e($ev['uploader_name'] ?? '—') ?><br>
                                <span class="text-muted"><?= format_date((string)$ev['created_at'], 'd M Y H:i') ?></span>
                            </td>
                            <td><?= $evidenceStatus((string)$ev['status']) ?></td>
                            <td class="text-end">
                                <?php if ($isSuper && $ev['status'] === 'pending'): ?>
                                    <form method="post" action="<?= e(url('/followup/evidence/' . (int)$ev['id'] . '/verify')) ?>"
                                          class="d-flex gap-1 justify-content-end">
                                        <?= csrf_field() ?>
                                        <select name="decision" class="form-select form-select-sm" style="width:auto" required>
                                            <option value="approved">Terima</option>
                                            <option value="rejected">Kembalikan</option>
                                        </select>
                                        <input type="text" name="note" class="form-control form-control-sm"
                                               style="width:120px" placeholder="catatan (wajib bila kembalikan)">
                                        <button class="btn btn-sm btn-primary" type="submit">Putuskan</button>
                                    </form>
                                <?php else: ?>
                                    <span class="small text-muted"><?= $isSuper ? '—' : 'menunggu LPM' ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Unggah bukti (FR-11) -->
        <?php if ($canAct && $plan['status'] !== 'verified' && !$pending): ?>
            <div class="card mb-3">
                <div class="card-header bg-white"><strong>Unggah bukti penyelesaian</strong></div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('/followup/plans/' . (int)$plan['id'] . '/evidence')) ?>"
                          enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label">Berkas bukti (PDF/dokumen/gambar, maks. <?= (int)config('upload_max_kb') ?> KB)</label>
                            <input type="file" name="file" class="form-control" required
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan penyelesaian (opsional)</label>
                            <input type="text" name="note" class="form-control"
                                   placeholder="Mis. bukti sosialisasi / hasil perbaikan">
                        </div>
                        <button class="btn btn-primary" type="submit">Unggah &amp; tandai selesai</button>
                        <span class="small text-muted ms-2">Status menjadi "selesai" lalu menunggu verifikasi LPM.</span>
                    </form>
                </div>
            </div>
        <?php elseif ($canAct && $pending): ?>
            <div class="alert alert-info small py-2">
                Bukti sedang menunggu verifikasi LPM — pengunggahan berikutnya dibuka setelah keputusan diberikan.
            </div>
        <?php endif; ?>

        <!-- Permintaan revisi dokumen (FR-12) — anggota hanya melihat statusnya -->
        <?php if ($isSuper || $plan['revision_document_id'] !== null): ?>
            <div class="card">
                <div class="card-header bg-white"><strong>Umpan balik ke tahap Penetapan — permintaan revisi dokumen (FR-12)</strong></div>
                <div class="card-body">
                    <?php if ($plan['revision_document_id'] !== null): ?>
                        <div class="small">
                            <span class="badge text-bg-dark">revisi diminta</span>
                            <strong><?= e($plan['revision_doc_title']) ?></strong>
                            (v<?= e((string)$plan['revision_doc_version']) ?>) ·
                            <?= format_date((string)$plan['revision_requested_at'], 'd M Y H:i') ?>
                            <br>Catatan: <?= e((string)$plan['revision_note']) ?>
                            <br><a href="<?= e(url('/documents/' . (int)$plan['revision_document_id'])) ?>">Buka halaman dokumen →</a>
                            <span class="text-muted">(pemilik mengunggah versi baru → siklus berikutnya)</span>
                        </div>
                    <?php elseif ($isSuper && $plan['status'] !== 'verified'): ?>
                        <p class="text-muted small mb-0">
                            Tersedia setelah rencana aksi <strong>terverifikasi</strong> —
                            verifikasi yang berujung pada perubahan dokumen menjadi permintaan revisi.
                        </p>
                    <?php elseif ($isSuper): ?>
                        <form method="post" action="<?= e(url('/followup/plans/' . (int)$plan['id'] . '/revision')) ?>">
                            <?= csrf_field() ?>
                            <div class="mb-2">
                                <label class="form-label">Dokumen yang diminta direvisi</label>
                                <select name="document_id" class="form-select" required>
                                    <option value="">— pilih dokumen —</option>
                                    <?php foreach ($documents as $d): ?>
                                        <option value="<?= (int)$d['id'] ?>">
                                            <?= e($d['title']) ?> (v<?= e((string)$d['version']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Catatan umpan balik (wajib)</label>
                                <textarea name="note" rows="2" class="form-control" required
                                          placeholder="Perubahan standar/dokumen yang diharapkan dari hasil tindak lanjut ini"></textarea>
                            </div>
                            <button class="btn btn-dark" type="submit">Kirim permintaan revisi dokumen</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <!-- Temuan sumber -->
        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Temuan sumber (M3)</strong></div>
            <div class="card-body small">
                <div class="mb-2">
                    <?= severity_badge((string)$plan['severity']) ?>
                    <?= e(finding_type_label((string)$plan['finding_type'])) ?>
                    <?= finding_badge((string)$plan['finding_status']) ?>
                </div>
                <?php if (!empty($plan['indicator_code'])): ?>
                    <div class="mb-2">
                        <span class="badge text-bg-light border"><?= e($plan['indicator_code']) ?></span>
                        <?= e((string)$plan['indicator_question']) ?>
                    </div>
                <?php endif; ?>
                <p class="mb-2"><?= e((string)$plan['finding_desc']) ?></p>
                <a class="small" href="<?= e(url('/ami/findings?schedule_id=' . (int)$plan['schedule_id'])) ?>">
                    Buka daftar temuan →
                </a>
            </div>
        </div>

        <!-- Status & aksi -->
        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Status tindak lanjut</strong></div>
            <div class="card-body">
                <div class="mb-2"><?= action_status_badge((string)$plan['status']) ?></div>
                <?php if ($canAct && $plan['status'] === 'open'): ?>
                    <form method="post" action="<?= e(url('/followup/plans/' . (int)$plan['id'] . '/status')) ?>" class="mb-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="in_progress">
                        <button class="btn btn-sm btn-warning w-100" type="submit">▶ Mulai kerjakan</button>
                    </form>
                <?php endif; ?>
                <div class="small text-muted">
                    Terbuka → Berjalan → Selesai (bukti) → Terverifikasi (LPM).
                </div>
            </div>
        </div>

        <!-- Penyuntingan (Super, belum terverifikasi) -->
        <?php if ($isSuper && $plan['status'] !== 'verified'): ?>
            <div class="card">
                <div class="card-header bg-white"><strong>Sunting rencana aksi</strong></div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('/followup/plans/' . (int)$plan['id'])) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label">Uraian</label>
                            <textarea name="description" rows="2" class="form-control" required><?= e($plan['description']) ?></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Penanggung jawab</label>
                            <select name="pic_user_id" class="form-select" required>
                                <?php foreach (User::activeAll() as $u): ?>
                                    <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === (int)$plan['pic_user_id'] ? 'selected' : '' ?>>
                                        <?= e($u['name']) ?> — <?= e($u['unit_name'] ?? 'tanpa unit') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="form-label">Tenggat</label>
                                <input type="date" name="due_date" class="form-control" required
                                       value="<?= e((string)$plan['due_date']) ?>">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label">Prioritas</label>
                                <select name="priority" class="form-select">
                                    <?php foreach (['tinggi', 'sedang', 'rendah'] as $p): ?>
                                        <option value="<?= $p ?>" <?= $plan['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-primary w-100" type="submit">Simpan perubahan</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
