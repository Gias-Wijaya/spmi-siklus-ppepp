<?php
/**
 * M2 — Detail laporan Monev: nilai, capaian, bukti, review LPM.
 * @var array $report @var array $values @var bool $canEdit
 */
$isSuper  = Auth::isSuperAdmin();
$isOpen   = $report['period_status'] === 'open';
$editable = $canEdit && in_array($report['status'], ['draft'], true) && $isOpen;
$score    = $report['total_score'] !== null ? (float)$report['total_score'] : null;
?>

<style>
    @media print {
        .sidebar, header, .no-print, .topbar { display: none !important; }
        .content, main { margin: 0 !important; padding: 0 !important; }
        body { background: #fff !important; }
        .card { border: none !important; box-shadow: none !important; }
    }
</style>

<div class="page-head no-print">
    <div class="page-desc">
        <i class="bi bi-journal-text me-1"></i>
        <strong>Laporan Monev <?= e($report['period_name']) ?></strong>, unit
        <strong><?= e($report['unit_name']) ?></strong> — nilai, capaian, bukti, dan review LPM.
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-sm btn-dark" type="button" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Cetak / PDF
        </button>
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('/monev')) ?>">
            <i class="bi bi-arrow-left me-1"></i>Dasbor Monev
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <div class="small text-muted">Status</div>
                        <?= monev_status_badge((string)$report['status']) ?>
                        <?php if ($report['submitted_at']): ?>
                            <span class="small text-muted ms-1">diajukan <?= format_date($report['submitted_at'], 'd M Y H:i') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted">Skor total (tertimbang)</div>
                        <div class="fs-4 fw-semibold"><?= $score !== null ? capaian_badge($score) : '<span class="text-muted">—</span>' ?></div>
                    </div>
                </div>
                <hr>
                <div class="small text-muted">
                    Periode <?= format_date($report['start_date']) ?> – <?= format_date($report['end_date']) ?> ·
                    Pengisi: <?= e($report['submitted_by_name'] ?? '-') ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-muted mb-1">Catatan / riwayat pengembalian</div>
                <?php if (!empty($report['notes'])): ?>
                    <div class="small" style="white-space: pre-line"><?= e($report['notes']) ?></div>
                <?php else: ?>
                    <div class="small text-muted">Belum ada catatan.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card table-card mb-3">
    <div class="card-header bg-white"><strong>Rincian capaian indikator</strong></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Kode</th>
                <th>Indikator</th>
                <th class="text-end">Target</th>
                <th class="text-end">Nilai</th>
                <th class="text-center">Capaian</th>
                <th>Bukti</th>
                <th>Catatan</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$values): ?>
                <tr><td colspan="7" class="text-muted">Belum ada nilai tersimpan.</td></tr>
            <?php endif; ?>
            <?php foreach ($values as $v): ?>
                <tr>
                    <td><code class="small"><?= e($v['code']) ?></code></td>
                    <td class="small">
                        <?= e($v['indicator_name']) ?>
                        <?php if (!(int)$v['is_active']): ?>
                            <span class="badge text-bg-secondary">indikator dinonaktifkan</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end small"><?= e($v['target']) ?> <?= e($v['unit_of_measure'] ?? '') ?></td>
                    <td class="text-end"><strong><?= e($v['value']) ?></strong></td>
                    <td class="text-center"><?= capaian_badge($v['achievement_percent'] !== null ? (float)$v['achievement_percent'] : null) ?></td>
                    <td>
                        <?php if (!empty($v['evidence_file'])): ?>
                            <a class="btn btn-sm btn-outline-secondary py-0" href="<?= e(url('/monev/evidence/' . (int)$v['id'])) ?>" target="_blank">Unduh</a>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= e($v['notes'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex gap-2 flex-wrap mb-4">
    <?php if ($editable): ?>
        <a class="btn btn-primary" href="<?= e(url('/monev/report?period_id=' . (int)$report['period_id'] . '&unit_id=' . (int)$report['unit_id'])) ?>">
            Edit / lengkapi isian
        </a>
    <?php endif; ?>
    <?php if (!$isOpen): ?>
        <span class="text-muted small align-self-center">Periode sudah ditutup — isian terkunci.</span>
    <?php endif; ?>
</div>

<?php if ($isSuper && $report['status'] === 'submitted'): ?>
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card border-success">
                <div class="card-body">
                    <strong class="d-block mb-2">Setujui laporan</strong>
                    <div class="small text-muted mb-2">Menandai capaian unit sebagai resmi pada periode ini.</div>
                    <form method="post" action="<?= e(url('/monev/reports/' . (int)$report['id'] . '/approve')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-success" type="submit">Setujui</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-warning">
                <div class="card-body">
                    <strong class="d-block mb-2">Kembalikan untuk perbaikan</strong>
                    <form method="post" action="<?= e(url('/monev/reports/' . (int)$report['id'] . '/return')) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <textarea name="comment" rows="2" class="form-control" placeholder="Catatan wajib — jelaskan yang perlu diperbaiki" required></textarea>
                        </div>
                        <button class="btn btn-warning" type="submit">Kembalikan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
