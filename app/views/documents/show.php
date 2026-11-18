<?php
/** @var array $doc @var array $versions @var array $approvals @var bool $canEdit */

$isSuper = Auth::isSuperAdmin();
$days    = days_to_expiry($doc['expiry_date']);
$state   = (string)$doc['approval_status'];
$shown   = in_array($state, ['approved', 'archived'], true);
$editable = $canEdit && !in_array($state, ['archived'], true);
?>
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h2 class="h5 mb-1"><?= e($doc['title']) ?></h2>
        <div class="small text-muted">
            <?= $doc['doc_number'] ? e($doc['doc_number']) . ' · ' : '' ?>
            v<?= e($doc['version']) ?> ·
            <?= e($doc['category_name'] ?? 'Tanpa kategori') ?> ·
            <?= e($doc['standard_name'] ?? 'Tanpa standar') ?> ·
            <?= e($doc['unit_name'] ?? 'Tanpa unit') ?>
        </div>
    </div>
    <a href="<?= e(url('/documents')) ?>" class="btn btn-sm btn-outline-secondary">← Daftar dokumen</a>
</div>

<?php if ($errors ?? []): ?>
    <div class="alert alert-danger"><?= e($errors) ?></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <!-- ============================================ Metadata -->
        <div class="card table-card mb-3">
            <div class="card-header bg-white"><strong>Metadata dokumen</strong></div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-4 text-muted small">Status persetujuan</div>
                    <div class="col-8"><?= approval_badge($state) ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted small">Status masa berlaku</div>
                    <div class="col-8">
                        <?= $shown ? status_badge((string)$doc['status_color']) : '<span class="text-muted">— (belum berlaku)</span>' ?>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted small">Tanggal mulai berlaku</div>
                    <div class="col-8"><?= format_date($doc['effective_date']) ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted small">Masa berlaku s/d</div>
                    <div class="col-8">
                        <?= format_date($doc['expiry_date']) ?>
                        <?php if ($days !== null && $days >= 0): ?>
                            <span class="badge text-bg-light border">(<?= $days ?> hari lagi)</span>
                        <?php elseif ($days !== null): ?>
                            <span class="badge text-bg-danger">(kedaluwarsa <?= abs($days) ?> hari)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted small">Pemilik / pengunggah</div>
                    <div class="col-8"><?= e($doc['owner_name'] ?? '—') ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted small">Diajukan</div>
                    <div class="col-8"><?= format_date($doc['submitted_at'], 'd M Y H:i') ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-4 text-muted small">Disetujui</div>
                    <div class="col-8"><?= format_date($doc['approved_at'], 'd M Y H:i') ?></div>
                </div>
                <div class="row">
                    <div class="col-4 text-muted small">Keterangan</div>
                    <div class="col-8"><?= e($doc['description'] ?? '—') ?></div>
                </div>
            </div>
        </div>

        <!-- ============================================ Aksi -->
        <div class="card table-card mb-3">
            <div class="card-header bg-white"><strong>Tindakan</strong></div>
            <div class="card-body d-flex flex-wrap gap-2">

                <a class="btn btn-outline-primary" target="_blank"
                   href="<?= e(url('/documents/' . (int)$doc['id'] . '/download')) ?>"><i class="bi bi-download me-1"></i>Unduh berkas</a>

                <?php if ($canEdit && in_array($state, ['draft', 'rejected'], true)): ?>
                    <form method="post" action="<?= e(url('/documents/' . (int)$doc['id'] . '/submit')) ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <button class="btn btn-success" type="submit">Ajukan persetujuan</button>
                    </form>
                <?php endif; ?>

                <?php if ($canEdit): ?>
                    <a class="btn btn-outline-secondary"
                       href="<?= e(url('/documents/' . (int)$doc['id'] . '/edit')) ?>">Ubah metadata</a>
                <?php endif; ?>

                <?php if ($isSuper && $state === 'submitted'): ?>
                    <form method="post" action="<?= e(url('/documents/' . (int)$doc['id'] . '/approve')) ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <button class="btn btn-success" type="submit"
                                data-confirm="Setujui dokumen ini dan nyatakan berlaku?"><i class="bi bi-check2-circle me-1"></i>Setujui</button>
                    </form>
                <?php endif; ?>

                <?php if ($isSuper && $state === 'submitted'): ?>
                    <button class="btn btn-outline-danger" type="button" data-bs-toggle="collapse"
                            data-bs-target="#rejectBox"><i class="bi bi-x-circle me-1"></i>Kembalikan</button>
                <?php endif; ?>

                <?php if ($isSuper && $state !== 'archived'): ?>
                    <form method="post" action="<?= e(url('/documents/' . (int)$doc['id'] . '/archive')) ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-dark" type="submit"
                                data-confirm="Arsipkan dokumen? Status menjadi merah (tidak berlaku)."><i class="bi bi-archive me-1"></i>Arsipkan</button>
                    </form>
                <?php endif; ?>

                <?php if ($isSuper && $state === 'submitted'): ?>
                    <div class="collapse w-100 mt-2" id="rejectBox">
                        <form method="post" action="<?= e(url('/documents/' . (int)$doc['id'] . '/reject')) ?>">
                            <?= csrf_field() ?>
                            <label class="form-label small">Catatan pengembalian <span class="text-danger">*</span></label>
                            <textarea class="form-control mb-2" name="comment" rows="2" required
                                      placeholder="Jelaskan hal yang perlu diperbaiki…"></textarea>
                            <button class="btn btn-sm btn-danger" type="submit">Kirim pengembalian</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================ Riwayat versi -->
        <div class="card table-card">
            <div class="card-header bg-white"><strong>Riwayat versi</strong></div>
            <div class="card-body p-0">
                <?php if (!$versions): ?>
                    <p class="text-muted p-3 mb-0">Belum ada revisi — ini versi pertama (v<?= e($doc['version']) ?>).</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>Versi</th>
                                <th>Catatan perubahan</th>
                                <th>Pengunggah</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($versions as $v): ?>
                                <tr>
                                    <td>v<?= e($v['version']) ?></td>
                                    <td class="small"><?= e($v['change_note'] ?? '—') ?></td>
                                    <td class="small"><?= e($v['submitted_by_name'] ?? '—') ?></td>
                                    <td>
                                        <?php if ($v['status'] === 'approved'): ?>
                                            <span class="badge text-bg-success">Disetujui</span>
                                        <?php elseif ($v['status'] === 'rejected'): ?>
                                            <span class="badge text-bg-danger">Ditolak</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-info">Menunggu</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary py-0"
                                           href="<?= e(url('/documents/' . (int)$doc['id'] . '/download')) ?>">Unduh</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <!-- ============================================ Riwayat persetujuan -->
        <div class="card table-card mb-3">
            <div class="card-header bg-white"><strong>Riwayat persetujuan</strong></div>
            <ul class="list-group list-group-flush">
                <?php if (!$approvals): ?>
                    <li class="list-group-item text-muted">Belum pernah diajukan.</li>
                <?php endif; ?>
                <?php foreach ($approvals as $a): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong class="small">Tahap <?= (int)$a['step'] ?></strong>
                            <span class="small text-muted"><?= format_date($a['acted_at'], 'd M Y H:i') ?></span>
                        </div>
                        <div class="small">
                            <?php if ($a['status'] === 'approved'): ?>
                                <i class="bi bi-check-circle-fill text-success me-1"></i>Disetujui oleh <?= e($a['approver_name'] ?? 'LPM') ?>
                            <?php elseif ($a['status'] === 'rejected'): ?>
                                <i class="bi bi-x-circle-fill text-danger me-1"></i>Dikembalikan oleh <?= e($a['approver_name'] ?? 'LPM') ?>
                            <?php else: ?>
                                <i class="bi bi-hourglass-split text-warning me-1"></i>Menunggu persetujuan Super Admin (LPM)
                            <?php endif; ?>
                        </div>
                        <?php if ($a['comment']): ?>
                            <div class="small text-muted fst-italic">"<?= e($a['comment']) ?>"</div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- ============================================ Formulir revisi -->
        <?php if ($editable && in_array($state, ['approved', 'rejected', 'draft'], true)): ?>
            <div class="card table-card">
                <div class="card-header bg-white"><strong>Revisi — unggah versi baru</strong></div>
                <div class="card-body">
                    <form method="post"
                          action="<?= e(url('/documents/' . (int)$doc['id'] . '/revise')) ?>"
                          enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <?php $nextVersion = (string)round((float)$doc['version'] + 0.1, 1); ?>
                            <label class="form-label small">Nomor versi baru <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="version" required
                                   placeholder="Contoh: 2.0"
                                   value="<?= e($nextVersion) ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Berkas versi baru <span class="text-danger">*</span></label>
                            <input class="form-control" type="file" name="file" required
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Catatan perubahan</label>
                            <textarea class="form-control" name="change_note" rows="2"
                                      placeholder="Apa yang berubah dari versi sebelumnya?"></textarea>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Unggah &amp; ajukan versi baru</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
