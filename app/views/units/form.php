<?php /** @var array $unit @var array $errors */ ?>
<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-buildings me-1"></i>
        <strong>Ubah unit kerja</strong>, perbarui kode dan nama unit pemilik dokumen serta auditee AMI.
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('/units')) ?>">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar
        </a>
    </div>
</div>

<div class="card table-card" style="max-width:640px">
    <div class="card-header bg-white"><strong>Ubah Unit Kerja</strong></div>
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/units/' . (int)$unit['id'])) ?>">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Kode <span class="text-danger">*</span></label>
                    <input class="form-control" type="text" name="code" required
                           value="<?= e($unit['code']) ?>">
                </div>
                <div class="col-md-9">
                    <label class="form-label">Nama unit <span class="text-danger">*</span></label>
                    <input class="form-control" type="text" name="name" required
                           value="<?= e($unit['name']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Keterangan</label>
                    <input class="form-control" type="text" name="description"
                           value="<?= e($unit['description'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                               <?= (int)$unit['is_active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Unit aktif</label>
                    </div>
                </div>
            </div>

            <hr>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit">Simpan perubahan</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/units')) ?>">Batal</a>
            </div>
        </form>
    </div>
</div>
