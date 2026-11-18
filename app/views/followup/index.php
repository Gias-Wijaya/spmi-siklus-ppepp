<?php
/**
 * M4 — Pengendalian–Peningkatan: dasbor rencana aksi + konversi temuan (FR-09/FR-10).
 * @var array $periods @var array|null $schedule @var array $rows @var array $recap
 * @var bool $isSuper @var array $findings @var array $picOptions @var array $errors
 * @var string $filterStatus @var string $filterPriority
 */
$filterStatus   = $filterStatus ?? '';
$filterPriority = $filterPriority ?? '';
$today          = date('Y-m-d');

$stat = static function (string $key, string $label, string $cls, string $icon, string $chip) use ($recap): string {
    return '<div class="col-6 col-lg-3"><div class="card stat-card p-3 h-100">'
        . '<div class="stat-top"><span class="stat-label">' . $label . '</span>'
        . '<span class="stat-icon ' . $chip . '"><i class="bi ' . $icon . '"></i></span></div>'
        . '<div class="stat-value ' . $cls . '">' . (int)($recap[$key] ?? 0) . '</div>'
        . '</div></div>';
};
?>

<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-gear-wide-connected me-1"></i>
        <strong>Konversi temuan AMI menjadi rencana aksi (FR-09)</strong>, pelacakan status tindak lanjut
        (FR-10), bukti &amp; verifikasi (FR-11), umpan balik revisi dokumen (FR-12).
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-outline-success"
           href="<?= e(url('/followup/export?' . http_build_query(array_filter([
               'schedule_id' => $schedule ? (int)$schedule['id'] : '',
               'status'      => $filterStatus,
               'priority'    => $filterPriority,
           ], static fn ($v) => $v !== '')))) ?>"
           title="Unduh daftar rencana aksi (saringan ikut berlaku)">
            <i class="bi bi-file-earlier me-1"></i>Ekspor Excel
        </a>
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('/ami/findings')) ?>"><i class="bi bi-search me-1"></i>Daftar temuan AMI</a>
        <form method="get" action="<?= e(url('/followup')) ?>" class="d-flex gap-2 flex-wrap">
            <select class="form-select form-select-sm" name="schedule_id">
                <?php foreach ($periods as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $schedule && (int)$p['id'] === (int)$schedule['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select class="form-select form-select-sm" name="status">
                <option value="">semua status</option>
                <?php foreach (['open' => 'Terbuka', 'in_progress' => 'Berjalan', 'done' => 'Selesai', 'verified' => 'Terverifikasi'] as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select form-select-sm" name="priority">
                <option value="">semua prioritas</option>
                <?php foreach (['tinggi' => 'tinggi', 'sedang' => 'sedang', 'rendah' => 'rendah'] as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= $filterPriority === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-secondary" type="submit">Saring</button>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <?= $stat('open', 'Terbuka', 'text-danger', 'bi-circle', 'bg-danger-subtle text-danger') ?>
    <?= $stat('in_progress', 'Berjalan', 'text-warning', 'bi-play-circle', 'bg-warning-subtle text-warning') ?>
    <?= $stat('done', 'Selesai (menunggu verifikasi)', 'text-info', 'bi-hourglass-split', 'bg-info-subtle text-info') ?>
    <?= $stat('verified', 'Terverifikasi', 'text-success', 'bi-patch-check', 'bg-success-subtle text-success') ?>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger py-2 small">
        <?php foreach ($errors as $e1): ?><?= e($e1) ?><br><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header bg-white d-flex justify-content-between">
                <strong>Rencana aksi — <?= $schedule ? e($schedule['name']) : 'semua jadwal' ?></strong>
                <span class="small text-muted"><?= count($rows) ?> rencana</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Temuan (sumber)</th>
                        <th>Rencana aksi</th>
                        <th style="width:90px">Bukti</th>
                        <th style="width:140px">Status</th>
                        <th style="width:70px" class="text-end">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="5" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-clipboard2-x"></i>
                                Belum ada rencana aksi<?= $schedule ? ' pada jadwal ini' : '' ?>.
                                <?php if ($isSuper): ?>Buat dari temuan melalui formulir di samping kanan.<?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $overdue = $r['due_date'] !== null && $r['due_date'] < $today && $r['status'] !== 'verified';
                        ?>
                        <tr>
                            <td class="small">
                                <strong><?= e($r['unit_name']) ?></strong>
                                <?php if (!empty($r['indicator_code'])): ?>
                                    <span class="badge text-bg-light border"><?= e($r['indicator_code']) ?></span>
                                <?php endif; ?>
                                <br><?= e(mb_substr((string)$r['finding_desc'], 0, 90)) ?>
                                <br><span class="text-muted"><?= severity_badge((string)$r['severity']) ?>
                                    · temuan <?= e(finding_badge((string)$r['finding_status'])) ?></span>
                            </td>
                            <td class="small">
                                <?= e(mb_substr((string)$r['description'], 0, 110)) ?>
                                <br><span class="text-muted">PIC: <strong><?= e($r['pic_name'] ?? '—') ?></strong>
                                    (<?= e($r['pic_unit_name'] ?? '—') ?>)<br>
                                    Tenggat: <?= format_date((string)$r['due_date']) ?>
                                    <?php if ($overdue): ?><span class="badge text-bg-danger">terlambat</span><?php endif; ?>
                                    · prioritas <?= e($r['priority']) ?></span>
                                <?php if ($r['revision_document_id'] !== null): ?>
                                    <br><span class="badge text-bg-dark">revisi dokumen: <?= e($r['revision_doc_title']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-center">
                                <?= (int)$r['evidence_count'] ?>
                                <?php if ((int)$r['pending_evidence'] > 0): ?>
                                    <br><span class="badge text-bg-info">1 menunggu</span>
                                <?php endif; ?>
                            </td>
                            <td><?= action_status_badge((string)$r['status']) ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/followup/plans/' . (int)$r['id'])) ?>">Buka</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Konversi temuan → rencana aksi (FR-09, Super Admin) -->
        <div class="card mb-3">
            <div class="card-header bg-white"><strong>Baru: konversi temuan → rencana aksi</strong></div>
            <div class="card-body">
                <?php if (!$isSuper): ?>
                    <p class="text-muted small mb-0">
                        Pembuatan rencana aksi dilakukan LPM (Super Admin) dari daftar temuan AMI.
                        Anda menerima penugasan sebagai penanggung jawab.
                    </p>
                <?php elseif (!$schedule): ?>
                    <p class="text-muted small mb-0">Belum ada jadwal AMI — temuan menjadi sumber rencana aksi.</p>
                <?php elseif (!$findings): ?>
                    <p class="text-muted small mb-0">
                        Belum ada temuan pada jadwal ini. Temuan terbentuk dari penilaian skor ≤ 2 (M3).
                        <a href="<?= e(url('/ami/findings')) ?>">Buka daftar temuan →</a>
                    </p>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/followup/plans')) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label">Temuan (sumber wajib)</label>
                            <select name="finding_id" class="form-select" required>
                                <option value="">— pilih temuan —</option>
                                <?php foreach ($findings as $f): ?>
                                    <option value="<?= (int)$f['id'] ?>">
                                        #<?= (int)$f['id'] ?> · <?= e($f['unit_name']) ?> ·
                                        <?= e($f['severity']) ?> ·
                                        <?= e(mb_substr((string)$f['description'], 0, 55)) ?>
                                        <?= (int)$f['plan_count'] > 0 ? ' — sudah ' . (int)$f['plan_count'] . ' aksi' : ' — belum ada aksi' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Uraian tindak lanjut</label>
                            <textarea name="description" rows="2" class="form-control" required
                                      placeholder="Perbaikan yang akan dilakukan"></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Penanggung jawab (PIC)</label>
                            <select name="pic_user_id" class="form-select" required>
                                <option value="">— pilih pengguna —</option>
                                <?php foreach ($picOptions as $u): ?>
                                    <option value="<?= (int)$u['id'] ?>">
                                        <?= e($u['name']) ?> — <?= e($u['unit_name'] ?? 'tanpa unit') ?>
                                        (<?= $u['role'] === 'super_admin' ? 'LPM' : 'Waket' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="form-label">Tenggat</label>
                                <input type="date" name="due_date" class="form-control" required
                                       min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="form-label">Prioritas</label>
                                <select name="priority" class="form-select">
                                    <option value="tinggi">tinggi</option>
                                    <option value="sedang" selected>sedang</option>
                                    <option value="rendah">rendah</option>
                                </select>
                            </div>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Buat rencana aksi &amp; tugaskan</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Keterangan alur -->
        <div class="card">
            <div class="card-header bg-white"><strong>Alur tindak lanjut (FR-10–FR-12)</strong></div>
            <div class="card-body small">
                <p class="mb-2">
                    <span class="badge text-bg-secondary">Terbuka</span> →
                    <span class="badge text-bg-warning">Berjalan</span> →
                    <span class="badge text-bg-info">Selesai</span> →
                    <span class="badge text-bg-success">Terverifikasi</span>
                </p>
                <p class="text-muted mb-0">
                    PIC menandai mulai, mengunggah bukti (→ selesai), lalu LPM memverifikasi:
                    diterima → terverifikasi; dikembalikan → berjalan kembali dengan catatan.
                    Setelah terverifikasi, LPM dapat meminta <strong>revisi dokumen</strong>
                    sebagai umpan balik ke tahap Penetapan (menutup siklus PPEPP).
                </p>
            </div>
        </div>
    </div>
</div>
