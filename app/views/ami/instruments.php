<?php
/**
 * M3 — Instrumen AMI (indikator fleksibel, FR-06) + jejak perubahan (RM 3).
 * @var array $periods @var array|null $schedule @var array $instruments
 * @var array $standards @var array $logs @var array $errors
 */
$err = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$locked = $schedule && $schedule['status'] === 'closed';
$standardName = static fn (int $id): string => $id > 0 && isset($standards[array_search($id, array_column($standards, 'id'))])
    ? $standards[array_search($id, array_column($standards, 'id'))]['name']
    : '—';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <div class="text-muted small">
            Indikator <strong>dapat ditambah/diedit/dihapus</strong> — setiap perubahan tercatat otomatis (FR-06).
        </div>
    </div>
    <form method="get" action="<?= e(url('/ami/instruments')) ?>" class="d-flex gap-2">
        <select class="form-select form-select-sm" name="schedule_id">
            <?php foreach ($periods as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $schedule && (int)$p['id'] === (int)$schedule['id'] ? 'selected' : '' ?>>
                    <?= e($p['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-secondary" type="submit">Tampilkan</button>
    </form>
</div>

<?php if (!$schedule): ?>
    <div class="alert alert-info">Belum ada jadwal AMI — buat terlebih dahulu pada menu <a href="<?= e(url('/ami/schedules')) ?>">Jadwal AMI</a>.</div>
<?php else: ?>
    <?php if ($locked): ?>
        <div class="alert alert-warning py-2">
            Jadwal <strong><?= e($schedule['name']) ?></strong> sudah <strong>ditutup</strong> — instrumen terkunci.
            <a href="<?= e(url('/ami/schedules')) ?>">Buka kembali jadwal</a> untuk mengubah.
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger py-2 small">
            <?php foreach ($errors as $e1): ?><?= e($e1) ?><br><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <!-- Tambah indikator -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white"><strong>Tambah indikator</strong></div>
                <div class="card-body">
                    <?php if ($locked): ?>
                        <p class="text-muted small mb-0">Formulir terkunci karena jadwal ditutup.</p>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('/ami/instruments')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
                            <div class="mb-2">
                                <label class="form-label">Kode</label>
                                <input type="text" name="code" class="form-control<?= $err('code') ?>" placeholder="A.1" value="<?= e(old('code')) ?>">
                                <?php if (isset($errors['code'])): ?><div class="invalid-feedback"><?= e($errors['code']) ?></div><?php endif; ?>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Uraian indikator</label>
                                <textarea name="question" rows="3" class="form-control<?= $err('question') ?>" placeholder="Pertanyaan/indikator penilaian"><?= e(old('question')) ?></textarea>
                                <?php if (isset($errors['question'])): ?><div class="invalid-feedback"><?= e($errors['question']) ?></div><?php endif; ?>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Pedoman penilaian <span class="text-muted small">(opsional)</span></label>
                                <textarea name="assessment_guide" rows="2" class="form-control" placeholder="Cara menilai / bukti yang diterima"><?= e(old('assessment_guide')) ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <label class="form-label">Standar</label>
                                    <select name="standard_id" class="form-select">
                                        <option value="0">— tanpa standar —</option>
                                        <?php foreach ($standards as $s): ?>
                                            <option value="<?= (int)$s['id'] ?>" <?= (int)old('standard_id') === (int)$s['id'] ? 'selected' : '' ?>>
                                                <?= e($s['code']) ?> · <?= e($s['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-3 mb-2">
                                    <label class="form-label">Bobot</label>
                                    <input type="number" step="0.5" min="0.5" name="weight" class="form-control" value="<?= e(old('weight', '1')) ?>">
                                </div>
                                <div class="col-3 mb-2">
                                    <label class="form-label">Urutan</label>
                                    <input type="number" name="sort_order" class="form-control" value="<?= e(old('sort_order', '0')) ?>">
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="instActive" checked>
                                <label class="form-check-label small" for="instActive">Indikator aktif (dinilai)</label>
                            </div>
                            <button class="btn btn-primary w-100" type="submit">Tambah indikator</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Daftar indikator -->
        <div class="col-lg-8">
            <div class="card table-card">
                <div class="card-header bg-white"><strong>Indikator <?= e($schedule['name']) ?></strong></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Indikator</th>
                            <th>Standar</th>
                            <th class="text-center">Bobot</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$instruments): ?>
                            <tr><td colspan="6" class="text-muted">Belum ada indikator pada jadwal ini.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($instruments as $i): ?>
                            <tr class="<?= (int)$i['is_active'] === 0 ? 'table-secondary' : '' ?>">
                                <td class="fw-semibold"><?= e($i['code']) ?></td>
                                <td class="small"><?= e(mb_substr((string)$i['question'], 0, 110)) ?></td>
                                <td class="small text-muted"><?= e($i['standard_name'] ?? '—') ?></td>
                                <td class="text-center"><?= number_format((float)$i['weight'], 2, ',', '.') ?></td>
                                <td>
                                    <?= (int)$i['is_active'] === 1
                                        ? '<span class="badge text-bg-success">aktif</span>'
                                        : '<span class="badge text-bg-secondary">nonaktif</span>' ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/ami/instruments/' . (int)$i['id'] . '/edit')) ?>">Edit</a>
                                    <?php if (!$locked): ?>
                                        <form method="post" action="<?= e(url('/ami/instruments/' . (int)$i['id'] . '/toggle')) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                <?= (int)$i['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                                            </button>
                                        </form>
                                        <form method="post" action="<?= e(url('/ami/instruments/' . (int)$i['id'] . '/delete')) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger" type="submit"
                                                    data-confirm="Hapus indikator <?= e($i['code']) ?> beserta jejak perubahannya?">Hapus</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="form-text mt-2">
                Indikator yang sudah dinilai <strong>tidak dapat dihapus</strong> — nonaktifkan agar riwayat nilai tetap utuh.
            </div>
        </div>
    </div>

    <!-- Jejak perubahan -->
    <div class="card table-card">
        <div class="card-header bg-white"><strong>Jejak perubahan indikator (RM 3 — akuntabilitas edit instrumen)</strong></div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th style="width:150px">Waktu</th>
                    <th style="width:70px">Kode</th>
                    <th style="width:140px">Diubah oleh</th>
                    <th>Perubahan (lama → baru)</th>
                    <th style="width:200px">Alasan</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$logs): ?>
                    <tr><td colspan="5" class="text-muted">Belum ada perubahan indikator.</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td class="small text-muted"><?= e(date('d/m/Y H:i', strtotime((string)$l['created_at']))) ?></td>
                        <td class="small fw-semibold"><?= e($l['instrument_code']) ?></td>
                        <td class="small"><?= e($l['changed_by_name'] ?? '—') ?></td>
                        <td class="small">
                            <details>
                                <summary class="text-decoration-underline"><?= (int)$l['id'] ?> perubahan field</summary>
                                <div class="mt-1 p-2 bg-light rounded small">
                                    <div><strong>Lama:</strong><br><?= nl2br(e((string)$l['old_value'])) ?></div>
                                    <div class="mt-1"><strong>Baru:</strong><br><?= nl2br(e((string)$l['new_value'])) ?></div>
                                </div>
                            </details>
                        </td>
                        <td class="small text-muted"><?= e($l['reason'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
