<?php
/**
 * M2 — Kelola indikator Monev (Super Admin): indikator dinamis turunan standar.
 * @var array $indicators @var array $standards @var array $errors
 */
$err = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
?>

<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-sliders me-1"></i>
        <strong>Kelola indikator Monev</strong>, indikator dinamis turunan standar yang menjadi
        kolom penilaian setiap laporan unit (FR-05).
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('/monev')) ?>">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke dasbor
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white"><strong>Tambah indikator</strong></div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger py-2 small">
                        <?php foreach ($errors as $e1): ?><?= e($e1) ?><br><?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/monev/indicators')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Kode</label>
                        <input type="text" name="code" class="form-control<?= $err('code') ?>" placeholder="contoh: IND-05" value="<?= e(old('code')) ?>">
                        <?php if (isset($errors['code'])): ?><div class="invalid-feedback"><?= e($errors['code']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama indikator</label>
                        <textarea name="name" rows="2" class="form-control<?= $err('name') ?>" placeholder="contoh: Keterlaluan penyelesaian dokumen kurikulum"><?= e(old('name')) ?></textarea>
                        <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Standar terkait</label>
                        <select name="standard_id" class="form-select">
                            <option value="0">— tanpa standar —</option>
                            <?php foreach ($standards as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= old('standard_id') === (string)$s['id'] ? 'selected' : '' ?>>
                                    <?= e($s['code'] ?? '') ?> <?= e($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unit pengisi</label>
                        <select name="unit_id" class="form-select<?= $err('unit_id') ?>">
                            <option value="0">— Semua unit —</option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= old('unit_id') === (string)$u['id'] ? 'selected' : '' ?>>
                                    <?= e($u['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['unit_id'])): ?><div class="invalid-feedback"><?= e($errors['unit_id']) ?></div><?php endif; ?>
                        <div class="form-text">Semua unit mengisi indikator ini; pilih unit bila hanya unit tertentu yang mengisi.</div>
                    </div>
                    <div class="row">
                        <div class="col-4 mb-3">
                            <label class="form-label">Satuan</label>
                            <input type="text" name="unit_of_measure" class="form-control" placeholder="%" value="<?= e(old('unit_of_measure')) ?>">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Target</label>
                            <input type="number" step="any" min="0" max="100" name="target" class="form-control<?= $err('target') ?>" value="<?= e(old('target', '80')) ?>">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Bobot</label>
                            <input type="number" step="any" min="0" name="weight" class="form-control<?= $err('weight') ?>" value="<?= e(old('weight', '1')) ?>">
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked>
                        <label class="form-check-label" for="isActive">Aktif (tampil pada isian Monev)</label>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Tambah indikator</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header bg-white"><strong>Daftar indikator</strong> <span class="text-muted small">(<?= count($indicators) ?>)</span></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Indikator</th>
                        <th>Standar</th>
                        <th>Unit pengisi</th>
                        <th class="text-end">Target</th>
                        <th class="text-end">Bobot</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$indicators): ?>
                        <tr><td colspan="8" class="text-muted">Belum ada indikator.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($indicators as $i): ?>
                        <tr>
                            <td><code><?= e($i['code']) ?></code></td>
                            <td class="small"><?= e($i['name']) ?></td>
                            <td class="small"><?= e($i['standard_name'] ?? '—') ?></td>
                            <td class="small"><?= e($i['unit_name'] ?? 'Semua unit') ?></td>
                            <td class="text-end"><?= e($i['target']) ?> <?= e($i['unit_of_measure'] ?? '') ?></td>
                            <td class="text-end"><?= e($i['weight']) ?></td>
                            <td>
                                <?= (int)$i['is_active'] === 1
                                    ? '<span class="badge text-bg-success">aktif</span>'
                                    : '<span class="badge text-bg-secondary">nonaktif</span>' ?>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/monev/indicators/' . (int)$i['id'] . '/edit')) ?>">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
