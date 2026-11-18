<?php
/**
 * M3 — Formulir kepuasan (FR-07): daftar survei; Super Admin membuat & membuka/menutup,
 * anggota mengisi saat status open.
 * @var array $surveys @var bool $isSuper @var array $errors
 */
$err = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$statusBadge = static function (string $status): string {
    return match ($status) {
        'draft'  => '<span class="badge text-bg-secondary">draf</span>',
        'open'   => '<span class="badge text-bg-success">terbuka</span>',
        'closed' => '<span class="badge text-bg-dark">ditutup</span>',
        default  => '<span class="badge text-bg-secondary">' . e($status) . '</span>',
    };
};
?>

<div class="mb-3">
    <h1 class="h4 mb-1">Formulir Kepuasan Pengguna</h1>
    <div class="text-muted small">
        Kuisioner layanan mutu — satu responden mengisi satu kali; rekap skor dihitung otomatis (FR-07/FR-08).
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger py-2 small">
        <?php foreach ($errors as $e1): ?><?= e($e1) ?><br><?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($isSuper): ?>
    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white"><strong>Buat formulir</strong></div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('/ami/satisfaction')) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label">Nama formulir</label>
                            <input type="text" name="name" class="form-control<?= $err('name') ?>"
                                   placeholder="Kepuasan Pengguna Layanan Mutu 2027" value="<?= e(old('name')) ?>">
                            <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="form-label">Mulai</label>
                                <input type="date" name="start_date" class="form-control" value="<?= e(old('start_date')) ?>">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label">Selesai</label>
                                <input type="date" name="end_date" class="form-control<?= $err('end_date') ?>" value="<?= e(old('end_date')) ?>">
                            </div>
                        </div>
                        <button class="btn btn-primary w-100 mt-1" type="submit">Buat formulir (draf)</button>
                    </form>
                    <div class="form-text">Setelah dibuat: tambahkan <strong>pertanyaan</strong>, lalu <strong>buka</strong> untuk pengisian.</div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card table-card">
                <div class="card-header bg-white"><strong>Daftar formulir</strong></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Formulir</th>
                            <th>Rentang</th>
                            <th>Status</th>
                            <th class="text-center">Pertanyaan</th>
                            <th class="text-center">Responden</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$surveys): ?>
                            <tr><td colspan="6" class="text-muted">Belum ada formulir.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($surveys as $s): ?>
                            <tr>
                                <td class="small"><strong><?= e($s['name']) ?></strong></td>
                                <td class="small text-muted">
                                    <?= $s['start_date'] ? format_date($s['start_date']) : '—' ?> –
                                    <?= $s['end_date'] ? format_date($s['end_date']) : '—' ?>
                                </td>
                                <td><?= $statusBadge((string)$s['status']) ?></td>
                                <td class="text-center"><?= (int)$s['question_count'] ?></td>
                                <td class="text-center"><?= (int)$s['response_count'] ?></td>
                                <td class="text-end text-nowrap">
                                    <form method="post" action="<?= e(url('/ami/satisfaction/' . (int)$s['id'] . '/toggle')) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm <?= $s['status'] === 'open' ? 'btn-outline-secondary' : 'btn-outline-success' ?>" type="submit">
                                            <?= match ($s['status']) {
                                                'draft'  => 'Buka',
                                                'open'   => 'Tutup',
                                                default  => 'Buka lagi',
                                            } ?>
                                        </button>
                                    </form>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/ami/satisfaction/' . (int)$s['id'] . '/questions')) ?>">Pertanyaan</a>
                                    <a class="btn btn-sm btn-outline-dark" href="<?= e(url('/ami/satisfaction/' . (int)$s['id'] . '/recap')) ?>">Rekap</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Daftar untuk semua pengguna -->
<div class="card table-card">
    <div class="card-header bg-white"><strong>Formulir tersedia</strong></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Formulir</th>
                <th>Periode</th>
                <th>Status</th>
                <th class="text-center">Responden</th>
                <th class="text-end">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$surveys): ?>
                <tr><td colspan="5" class="text-muted">Belum ada formulir kepuasan.</td></tr>
            <?php endif; ?>
            <?php foreach ($surveys as $s): ?>
                <tr>
                    <td class="small"><strong><?= e($s['name']) ?></strong></td>
                    <td class="small text-muted">
                        <?= $s['start_date'] ? format_date($s['start_date']) : '—' ?> –
                        <?= $s['end_date'] ? format_date($s['end_date']) : '—' ?>
                    </td>
                    <td><?= $statusBadge((string)$s['status']) ?></td>
                    <td class="text-center"><?= (int)$s['response_count'] ?></td>
                    <td class="text-end">
                        <?php if ($s['status'] === 'open'): ?>
                            <a class="btn btn-sm btn-success" href="<?= e(url('/ami/satisfaction/' . (int)$s['id'] . '/fill')) ?>">Isi sekarang</a>
                        <?php elseif ($s['status'] === 'closed'): ?>
                            <span class="small text-muted">pengisian ditutup</span>
                        <?php else: ?>
                            <span class="small text-muted">belum dibuka</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
