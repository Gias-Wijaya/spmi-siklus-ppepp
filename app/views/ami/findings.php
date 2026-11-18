<?php
/**
 * M3 — Temuan AMI: daftar + filter (FR-08), pencatatan manual, transisi status (LPM).
 * @var array $periods @var array|null $schedule @var array $rows
 * @var bool $isSuper @var array $formAssignments @var array $errors
 */
$filterStatus = (string)($_GET['status'] ?? '');
$filterSev    = (string)($_GET['severity'] ?? '');
$locked = $schedule && $schedule['status'] === 'closed';
?>

<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-exclamation-diamond me-1"></i>
        <strong>Temuan AMI</strong>, otomatis dari penilaian skor ≤ 2 atau dicatat manual;
        diteruskan ke modul Pengendalian–Peningkatan (M4) (FR-08).
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('/ami/report' . ($schedule ? '?schedule_id=' . (int)$schedule['id'] : ''))) ?>"><i class="bi bi-file-earmark-bar-graph me-1"></i>Laporan AMI</a>
        <form method="get" action="<?= e(url('/ami/findings')) ?>" class="d-flex gap-2">
            <select class="form-select form-select-sm" name="schedule_id">
                <?php foreach ($periods as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $schedule && (int)$p['id'] === (int)$schedule['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select class="form-select form-select-sm" name="status">
                <option value="">semua status</option>
                <?php foreach (['open' => 'Terbuka', 'in_progress' => 'Ditindaklanjuti', 'verified' => 'Terverifikasi', 'closed' => 'Selesai'] as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select form-select-sm" name="severity">
                <option value="">semua keparahan</option>
                <?php foreach (['tinggi' => 'tinggi', 'sedang' => 'sedang', 'rendah' => 'rendah'] as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= $filterSev === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-secondary" type="submit">Saring</button>
        </form>
    </div>
</div>

<?php if (!$schedule): ?>
    <div class="alert alert-info">Belum ada jadwal AMI.</div>
<?php else: ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger py-2 small">
            <?php foreach ($errors as $e1): ?><?= e($e1) ?><br><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card table-card">
                <div class="card-header bg-white d-flex justify-content-between">
                    <strong>Daftar temuan — <?= e($schedule['name']) ?></strong>
                    <span class="small text-muted"><?= count($rows) ?> temuan</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Temuan</th>
                            <th style="width:90px">Keparahan</th>
                            <th style="width:150px">Status</th>
                            <th style="width:220px" class="text-end">Aksi status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="5" class="text-muted">Tidak ada temuan pada saringan ini.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $f): ?>
                            <tr>
                                <td class="small">
                                    <strong><?= e($f['unit_name']) ?></strong>
                                    <br><span class="text-muted"><?= e($f['auditor_name'] ?? '—') ?></span>
                                </td>
                                <td class="small">
                                    <?php if (!empty($f['indicator_code'])): ?>
                                        <span class="badge text-bg-light border"><?= e($f['indicator_code']) ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-light border">manual</span>
                                    <?php endif; ?>
                                    <?= e(finding_type_label((string)$f['finding_type'])) ?><br>
                                    <?= e((string)$f['description']) ?>
                                    <br><span class="text-muted">dicatat: <?= e($f['finder_name'] ?? '—') ?> · <?= format_date((string)$f['created_at'], 'd M Y') ?></span>
                                </td>
                                <td><?= severity_badge((string)$f['severity']) ?></td>
                                <td><?= finding_badge((string)$f['status']) ?></td>
                                <td class="text-end">
                                    <?php if ($isSuper): ?>
                                        <form method="post" action="<?= e(url('/ami/findings/' . (int)$f['id'])) ?>" class="d-flex gap-1 justify-content-end">
                                            <?= csrf_field() ?>
                                            <select name="status" class="form-select form-select-sm" style="width:auto">
                                                <?php foreach (['open' => 'Terbuka', 'in_progress' => 'Ditindaklanjuti', 'verified' => 'Terverifikasi', 'closed' => 'Selesai'] as $k => $lbl): ?>
                                                    <option value="<?= $k ?>" <?= $f['status'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Simpan</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="small text-muted">hanya LPM</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Temuan manual -->
            <div class="card mb-3">
                <div class="card-header bg-white"><strong>Catat temuan manual</strong></div>
                <div class="card-body">
                    <?php if ($locked): ?>
                        <p class="text-muted small mb-0">Jadwal ditutup — penambahan temuan baru dilakukan pada siklus berikutnya.</p>
                    <?php elseif (!$formAssignments): ?>
                        <p class="text-muted small mb-0">
                            <?= $isSuper ? 'Belum ada penugasan pada jadwal ini.' : 'Anda tidak memiliki penugasan auditor pada jadwal ini.' ?>
                        </p>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('/ami/findings')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
                            <div class="mb-2">
                                <label class="form-label">Penugasan (unit)</label>
                                <select name="assignment_id" class="form-select" required>
                                    <option value="">— pilih unit —</option>
                                    <?php foreach ($formAssignments as $a): ?>
                                        <option value="<?= (int)$a['id'] ?>"><?= e($a['unit_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <label class="form-label">Tipe</label>
                                    <select name="finding_type" class="form-select">
                                        <option value="ketidaksesuaian">Ketidaksesuaian</option>
                                        <option value="observasi">Observasi</option>
                                        <option value="rekomendasi">Rekomendasi</option>
                                    </select>
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="form-label">Keparahan</label>
                                    <select name="severity" class="form-select">
                                        <option value="tinggi">tinggi</option>
                                        <option value="sedang" selected>sedang</option>
                                        <option value="rendah">rendah</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Uraian temuan</label>
                                <textarea name="description" rows="3" class="form-control" required
                                          placeholder="Ketidaksesuaian/observasi yang ditemukan"></textarea>
                            </div>
                            <button class="btn btn-primary w-100" type="submit">Catat temuan</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Keterangan -->
            <div class="card">
                <div class="card-header bg-white"><strong>Alur status temuan</strong></div>
                <div class="card-body small">
                    <p class="mb-2">
                        <span class="badge text-bg-danger">Terbuka</span> →
                        <span class="badge text-bg-warning">Ditindaklanjuti</span> →
                        <span class="badge text-bg-info">Terverifikasi</span> →
                        <span class="badge text-bg-success">Selesai</span>
                    </p>
                    <p class="text-muted mb-0">
                        Perubahan status dilakukan LPM. Temuan berstatus terbuka menjadi bahan konversi
                        rencana aksi pada modul Pengendalian–Peningkatan (FR-09, iterasi berikutnya).
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
