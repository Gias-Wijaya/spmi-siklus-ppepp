<?php
/**
 * M3 — Form edit instrumen AMI (FR-06): simpan + alasan perubahan, riwayat di bawah.
 * @var array $instrument @var array|null $schedule @var array $standards
 * @var array $logs @var array $errors
 */
$err = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$locked = $schedule && $schedule['status'] === 'closed';
$stdId = (int)($instrument['standard_id'] ?? 0);
?>

<div class="mb-3">
    <a class="small" href="<?= e(url('/ami/instruments?schedule_id=' . (int)($schedule['id'] ?? 0))) ?>">← Kembali ke daftar instrumen</a>
    <h1 class="h4 mt-1">Edit instrumen: <?= e((string)$instrument['code']) ?></h1>
    <div class="text-muted small">
        Jadwal: <?= e($schedule['name'] ?? '—') ?> · Nilai penilaian yang sudah ada <strong>tidak berubah</strong> saat indikator diedit.
    </div>
</div>

<?php if ($locked): ?>
    <div class="alert alert-warning py-2">Jadwal sudah <strong>ditutup</strong> — instrumen terkunci. <a href="<?= e(url('/ami/schedules')) ?>">Buka kembali jadwal</a> untuk mengubah.</div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger py-2 small">
        <?php foreach ($errors as $e1): ?><?= e($e1) ?><br><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white"><strong>Isian indikator</strong></div>
            <div class="card-body">
                <form method="post" action="<?= e(url('/ami/instruments/' . (int)$instrument['id'])) ?>">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-4 mb-3">
                            <label class="form-label">Kode</label>
                            <input type="text" name="code" class="form-control<?= $err('code') ?>" value="<?= e((string)$instrument['code']) ?>">
                            <?php if (isset($errors['code'])): ?><div class="invalid-feedback"><?= e($errors['code']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Bobot</label>
                            <input type="number" step="0.5" min="0.5" name="weight" class="form-control" value="<?= e((string)$instrument['weight']) ?>">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label">Urutan</label>
                            <input type="number" name="sort_order" class="form-control" value="<?= e((string)$instrument['sort_order']) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Uraian indikator</label>
                        <textarea name="question" rows="4" class="form-control<?= $err('question') ?>"><?= e((string)$instrument['question']) ?></textarea>
                        <?php if (isset($errors['question'])): ?><div class="invalid-feedback"><?= e($errors['question']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pedoman penilaian <span class="text-muted small">(opsional)</span></label>
                        <textarea name="assessment_guide" rows="3" class="form-control"><?= e((string)($instrument['assessment_guide'] ?? '')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Standar terkait</label>
                        <select name="standard_id" class="form-select">
                            <option value="0">— tanpa standar —</option>
                            <?php foreach ($standards as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= $stdId === (int)$s['id'] ? 'selected' : '' ?>>
                                    <?= e($s['code']) ?> · <?= e($s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editActive"
                               <?= (int)$instrument['is_active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="editActive">Indikator aktif (dinilai)</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alasan perubahan <span class="text-muted small">(opsional — dicatat di jejak)</span></label>
                        <input type="text" name="reason" class="form-control" placeholder="contoh: penyelarasan dengan Permendiktisaintek 39/2025">
                    </div>
                    <button class="btn btn-primary" type="submit" <?= $locked ? 'disabled' : '' ?>>Simpan perubahan</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white"><strong>Riwayat perubahan indikator ini</strong></div>
            <div class="list-group list-group-flush">
                <?php if (!$logs): ?>
                    <div class="list-group-item text-muted small">Belum pernah diubah.</div>
                <?php endif; ?>
                <?php foreach ($logs as $l): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between small">
                            <strong><?= e(date('d/m/Y H:i', strtotime((string)$l['created_at']))) ?></strong>
                            <span class="text-muted"><?= e($l['changed_by_name'] ?? '—') ?></span>
                        </div>
                        <details class="small mt-1">
                            <summary class="text-decoration-underline">Lihat perubahan</summary>
                            <div class="mt-1 p-2 bg-light rounded">
                                <div><strong>Lama:</strong><br><?= nl2br(e((string)$l['old_value'])) ?></div>
                                <div class="mt-1"><strong>Baru:</strong><br><?= nl2br(e((string)$l['new_value'])) ?></div>
                                <?php if (!empty($l['reason'])): ?>
                                    <div class="mt-1 text-muted">Alasan: <?= e((string)$l['reason']) ?></div>
                                <?php endif; ?>
                            </div>
                        </details>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
