<?php
/**
 * M2 — Formulir edit indikator Monev (Super Admin).
 * @var array $indicator @var array $standards @var array $errors
 */
$err = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
?>

<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-sliders me-1"></i>
        <strong>Edit indikator <code><?= e($indicator['code']) ?></code></strong>, perbarui definisi,
        satuan, dan target indikator Monev.
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('/monev/indicators')) ?>">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white">
                <strong>Edit indikator <code><?= e($indicator['code']) ?></code></strong>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger py-2 small">
                        <?php foreach ($errors as $e1): ?><?= e($e1) ?><br><?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/monev/indicators/' . (int)$indicator['id'])) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Kode</label>
                        <input type="text" name="code" class="form-control<?= $err('code') ?>" value="<?= e($indicator['code']) ?>">
                        <?php if (isset($errors['code'])): ?><div class="invalid-feedback"><?= e($errors['code']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama indikator</label>
                        <textarea name="name" rows="3" class="form-control<?= $err('name') ?>"><?= e($indicator['name']) ?></textarea>
                        <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Standar terkait</label>
                        <select name="standard_id" class="form-select">
                            <option value="0">— tanpa standar —</option>
                            <?php foreach ($standards as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= (int)($indicator['standard_id'] ?? 0) === (int)$s['id'] ? 'selected' : '' ?>>
                                    <?= e($s['code'] ?? '') ?> <?= e($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-4 mb-3">
                            <label class="form-label">Satuan</label>
                            <input type="text" name="unit_of_measure" class="form-control" value="<?= e($indicator['unit_of_measure'] ?? '') ?>">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Target</label>
                            <input type="number" step="any" min="0" name="target" class="form-control<?= $err('target') ?>" value="<?= e($indicator['target']) ?>">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Bobot</label>
                            <input type="number" step="any" min="0" name="weight" class="form-control<?= $err('weight') ?>" value="<?= e($indicator['weight']) ?>">
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= (int)$indicator['is_active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Aktif (tampil pada isian Monev)</label>
                    </div>
                    <button class="btn btn-primary" type="submit">Simpan perubahan</button>
                </form>
                <div class="form-text">Perubahan indikator tidak menghapus nilai laporan lama; nilai lama tetap tersimpan pada periode sebelumnya.</div>
            </div>
        </div>
    </div>
</div>
