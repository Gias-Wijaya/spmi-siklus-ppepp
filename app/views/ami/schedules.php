<?php
/**
 * M3 — Kelola jadwal AMI tahunan (Super Admin): draft → berjalan → ditutup.
 * @var array $periods @var array $errors
 */
$err = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$statusBadge = static function (string $status): string {
    return match ($status) {
        'draft'   => '<span class="badge text-bg-secondary">draf</span>',
        'running' => '<span class="badge text-bg-success">berjalan</span>',
        'closed'  => '<span class="badge text-bg-dark">ditutup</span>',
        default   => '<span class="badge text-bg-secondary">' . e($status) . '</span>',
    };
};
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white"><strong>Buat jadwal AMI</strong></div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger py-2 small">
                        <?php foreach ($errors as $e1): ?><?= e($e1) ?><br><?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?= e(url('/ami/schedules')) ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Tahun AMI</label>
                        <input type="number" name="year" min="2000" max="2100" class="form-control<?= $err('year') ?>"
                               placeholder="2027" value="<?= e(old('year')) ?>">
                        <?php if (isset($errors['year'])): ?><div class="invalid-feedback"><?= e($errors['year']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama jadwal</label>
                        <input type="text" name="name" class="form-control<?= $err('name') ?>"
                               placeholder="AMI 2027" value="<?= e(old('name')) ?>">
                        <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Mulai</label>
                            <input type="date" name="start_date" class="form-control" value="<?= e(old('start_date')) ?>">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Selesai</label>
                            <input type="date" name="end_date" class="form-control<?= $err('end_date') ?>" value="<?= e(old('end_date')) ?>">
                        </div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Buat jadwal</button>
                </form>
                <div class="form-text">
                    AMI dilaksanakan <strong>setahun sekali</strong>. Setelah jadwal dibuat: susun
                    <a href="<?= e(url('/ami/instruments')) ?>">instrumen</a> &amp;
                    <a href="<?= e(url('/ami/assignments')) ?>">penugasan</a>, lalu jalankan.
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header bg-white"><strong>Daftar jadwal</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Tahun</th>
                        <th>Jadwal</th>
                        <th>Rentang</th>
                        <th>Status</th>
                        <th class="text-center">Penugasan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$periods): ?>
                        <tr><td colspan="6" class="text-muted">Belum ada jadwal.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($periods as $p): ?>
                        <tr>
                            <td><strong><?= e((string)$p['year']) ?></strong></td>
                            <td><?= e($p['name']) ?></td>
                            <td class="small">
                                <?= $p['start_date'] ? format_date($p['start_date']) : '—' ?> –
                                <?= $p['end_date'] ? format_date($p['end_date']) : '—' ?>
                            </td>
                            <td><?= $statusBadge((string)$p['status']) ?></td>
                            <td class="text-center small">
                                <?= (int)$p['assignment_count'] ?>
                                <span class="text-muted">(<?= (int)$p['completed_count'] ?> lengkap)</span>
                            </td>
                            <td class="text-end">
                                <form method="post" action="<?= e(url('/ami/schedules/' . (int)$p['id'] . '/toggle')) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm <?= $p['status'] === 'running' ? 'btn-outline-secondary' : 'btn-outline-success' ?>" type="submit">
                                        <?= match ($p['status']) {
                                            'draft'   => 'Jalankan',
                                            'running' => 'Tutup',
                                            default   => 'Buka kembali',
                                        } ?>
                                    </button>
                                </form>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/ami/instruments?schedule_id=' . (int)$p['id'])) ?>">Instrumen</a>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/ami/assignments?schedule_id=' . (int)$p['id'])) ?>">Penugasan</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="form-text mt-2">
            <strong>draft</strong> → penyusunan instrumen &amp; penugasan ·
            <strong>berjalan</strong> → penilaian auditor dibuka ·
            <strong>ditutup</strong> → seluruh isian terkunci (dapat dibuka kembali).
        </div>
    </div>
</div>
