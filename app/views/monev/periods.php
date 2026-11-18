<?php
/**
 * M2 — Kelola periode Monev (Super Admin): buka/tutup periode 3-bulanan.
 * @var array $periods @var array $errors
 */
$err = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white"><strong>Buat periode baru</strong></div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger py-2 small">
                        <?php foreach ($errors as $e1): ?><?= e($e1) ?><br><?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/monev/periods')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Nama periode</label>
                        <input type="text" name="name" class="form-control<?= $err('name') ?>"
                               placeholder="contoh: 2026-2 (Juli–Desember)" value="<?= e(old('name')) ?>">
                        <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Mulai</label>
                            <input type="date" name="start_date" class="form-control<?= $err('start_date') ?>" value="<?= e(old('start_date')) ?>">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Selesai</label>
                            <input type="date" name="end_date" class="form-control<?= $err('end_date') ?>" value="<?= e(old('end_date')) ?>">
                        </div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Buat periode</button>
                </form>
                <div class="form-text">Monev diisi <strong>3 bulan sekali</strong> oleh Wakil Ketua I–IV. Periode <em>terbuka</em> menerima isian; <em>tertutup</em> mengunci seluruh isian.</div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header bg-white"><strong>Daftar periode</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Periode</th>
                        <th>Rentang</th>
                        <th>Status</th>
                        <th class="text-center">Laporan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$periods): ?>
                        <tr><td colspan="5" class="text-muted">Belum ada periode.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($periods as $p): ?>
                        <tr>
                            <td><strong><?= e($p['name']) ?></strong></td>
                            <td class="small"><?= format_date($p['start_date']) ?> – <?= format_date($p['end_date']) ?></td>
                            <td>
                                <?= $p['status'] === 'open'
                                    ? '<span class="badge text-bg-success">Terbuka</span>'
                                    : '<span class="badge text-bg-secondary">Tertutup</span>' ?>
                            </td>
                            <td class="text-center"><?= (int)$p['report_count'] ?></td>
                            <td class="text-end">
                                <form method="post" action="<?= e(url('/monev/periods/' . (int)$p['id'] . '/toggle')) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm <?= $p['status'] === 'open' ? 'btn-outline-secondary' : 'btn-outline-success' ?>" type="submit">
                                        <?= $p['status'] === 'open' ? 'Tutup periode' : 'Buka kembali' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
