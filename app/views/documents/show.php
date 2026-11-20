<?php
/** @var array $doc @var array $versions @var bool $canEdit */

$days     = days_to_expiry($doc['expiry_date']);
$editable = $canEdit;
?>
<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-file-earmark-text me-1"></i>
        <strong><?= e($doc['title']) ?></strong>,
        <?= $doc['doc_number'] ? e($doc['doc_number']) . ' · ' : '' ?>
        v<?= e($doc['version']) ?> ·
        <?= e($doc['category_name'] ?? 'Tanpa kategori') ?> ·
        <?= e($doc['standard_name'] ?? 'Tanpa standar') ?> ·
        <?= e($doc['unit_name'] ?? 'Tanpa unit') ?>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($editable): ?>
            <a href="<?= e(url('/documents/' . (int)$doc['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil-square me-1"></i>Ubah
            </a>
        <?php endif; ?>
        <a href="<?= e(url('/documents')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Daftar dokumen
        </a>
    </div>
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
                    <div class="col-4 text-muted small">Status masa berlaku</div>
                    <div class="col-8"><?= status_badge((string)$doc['status_color']) ?></div>
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
                    <div class="col-4 text-muted small">Terakhir diperbarui</div>
                    <div class="col-8"><?= format_date($doc['updated_at'], 'd M Y H:i') ?></div>
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

                <?php if ($canEdit): ?>
                    <a class="btn btn-outline-secondary"
                       href="<?= e(url('/documents/' . (int)$doc['id'] . '/edit')) ?>">Ubah metadata</a>
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
                                <th>Tanggal</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($versions as $v): ?>
                                <tr>
                                    <td>v<?= e($v['version']) ?></td>
                                    <td class="small"><?= e($v['change_note'] ?? '—') ?></td>
                                    <td class="small"><?= e($v['submitted_by_name'] ?? '—') ?></td>
                                    <td class="small"><?= format_date($v['created_at'], 'd M Y H:i') ?></td>
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
        <!-- ============================================ Pengesahan -->
        <div class="card table-card mb-3">
            <div class="card-header bg-white"><strong>Pengesahan dokumen</strong></div>
            <div class="card-body small text-muted">
                Pengesahan (tanda tangan basah) dilakukan di luar sistem. Sistem menyimpan
                berkas beserta metadata &amp; riwayat versinya.
            </div>
        </div>

        <!-- ============================================ Formulir revisi -->
        <?php if ($editable): ?>
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
                        <button class="btn btn-primary w-100" type="submit">Unggah versi baru</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
