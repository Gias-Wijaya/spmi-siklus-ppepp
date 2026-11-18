<?php
/** @var array|null $doc @var array $categories @var array $standards @var array $units @var array $errors */
$isEdit    = $doc !== null;
$fill      = static fn (string $field, string $default = ''): string => old($field, (string)($doc[$field] ?? $default));
$isSuper   = Auth::isSuperAdmin();
$formTitle = $isEdit ? 'Ubah Metadata Dokumen' : 'Unggah Dokumen SPMI';
?>
<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'cloud-arrow-up' ?> me-1"></i>
        <strong><?= e($formTitle) ?></strong><?= $isEdit ? ', perbarui metadata, klasifikasi, dan masa berlaku dokumen.' : ', lengkapi metadata, klasifikasi, dan masa berlaku dokumen baru.' ?>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url($isEdit ? '/documents/' . (int)$doc['id'] : '/documents')) ?>">
            <i class="bi bi-arrow-left me-1"></i><?= $isEdit ? 'Kembali ke detail' : 'Kembali ke daftar' ?>
        </a>
    </div>
</div>

<div class="card table-card" style="max-width:900px">
    <div class="card-header bg-white"><strong><?= e($formTitle) ?></strong></div>
    <div class="card-body">

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <strong>Isian belum lengkap:</strong>
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post"
              action="<?= e($isEdit ? url('/documents/' . (int)$doc['id']) : url('/documents')) ?>"
              enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Judul dokumen <span class="text-danger">*</span></label>
                    <input class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                           type="text" name="title" required
                           value="<?= e($fill('title')) ?>"
                           placeholder="Contoh: Pedoman Penjaminan Mutu Internal">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Nomor dokumen</label>
                    <input class="form-control" type="text" name="doc_number"
                           value="<?= e($fill('doc_number')) ?>"
                           placeholder="Contoh: 001/SPMI/STIKOM-ER/2026">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Versi</label>
                    <input class="form-control" type="text" name="version"
                           value="<?= e($fill('version', '1.0')) ?>" placeholder="1.0">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Unit pemilik</label>
                    <?php if ($isSuper): ?>
                        <select class="form-select <?= isset($errors['unit_id']) ? 'is-invalid' : '' ?>" name="unit_id">
                            <option value="">— pilih unit —</option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"
                                    <?= (int)($doc['unit_id'] ?? 0) === (int)$u['id'] || old('unit_id') === (string)$u['id'] ? 'selected' : '' ?>>
                                    <?= e($u['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input class="form-control" type="text" readonly
                               value="<?= e(Auth::user()['unit_name'] ?? '—') ?>">
                        <input type="hidden" name="unit_id" value="<?= (int)(Auth::unitId() ?? 0) ?>">
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Kategori dokumen</label>
                    <select class="form-select" name="category_id">
                        <option value="">— tanpa kategori —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"
                                <?= (int)($doc['category_id'] ?? 0) === (int)$c['id'] || old('category_id') === (string)$c['id'] ? 'selected' : '' ?>>
                                <?= e($c['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Standar SPMI terkait</label>
                    <select class="form-select" name="standard_id">
                        <option value="">— pilih standar —</option>
                        <?php foreach ($standards as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"
                                <?= (int)($doc['standard_id'] ?? 0) === (int)$s['id'] || old('standard_id') === (string)$s['id'] ? 'selected' : '' ?>>
                                <?= e($s['code'] . ' — ' . $s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Tanggal mulai berlaku</label>
                    <input class="form-control <?= isset($errors['effective_date']) ? 'is-invalid' : '' ?>"
                           type="date" name="effective_date"
                           value="<?= e($fill('effective_date')) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Masa berlaku (sampai) <span class="text-danger">*</span>
                        <span class="text-muted small">≤ 30 hari → <span class="text-warning fw-semibold">kuning</span> · lewat → <span class="text-danger fw-semibold">merah</span></span>
                    </label>
                    <input class="form-control <?= isset($errors['expiry_date']) ? 'is-invalid' : '' ?>"
                           type="date" name="expiry_date"
                           value="<?= e($fill('expiry_date')) ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">Keterangan</label>
                    <textarea class="form-control" name="description" rows="3"
                              placeholder="Ringkasan isi/kelengkapan dokumen…"><?= e($fill('description')) ?></textarea>
                </div>

                <?php if ($isEdit): ?>
                    <div class="col-12">
                        <label class="form-label">Berkas saat ini</label>
                        <div class="small">
                            <a href="<?= e(url('/documents/' . (int)$doc['id'] . '/download')) ?>"><i class="bi bi-download me-1"></i>Unduh berkas</a>
                            — perubahan isi berkas dilakukan melalui tombol <em>Revisi</em> di halaman detail.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="col-12">
                        <label class="form-label">Berkas dokumen <span class="text-danger">*</span></label>
                        <input class="form-control <?= isset($errors['file']) ? 'is-invalid' : '' ?>"
                               type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg"
                               required>
                        <div class="form-text">
                            Format: PDF/Office/gambar · maksimal <?= (int)config('upload_max_kb') / 1024 ?> MB.
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <hr>
            <div class="d-flex gap-2">
                <?php if (!$isEdit): ?>
                    <button class="btn btn-success" type="submit" name="submit" value="1">
                        Simpan &amp; ajukan persetujuan
                    </button>
                    <button class="btn btn-outline-primary" type="submit">Simpan sebagai draft</button>
                <?php else: ?>
                    <button class="btn btn-primary" type="submit">Simpan perubahan</button>
                <?php endif; ?>
                <a class="btn btn-outline-secondary"
                   href="<?= e($isEdit ? url('/documents/' . (int)$doc['id']) : url('/documents')) ?>">Batal</a>
            </div>
        </form>
    </div>
</div>
