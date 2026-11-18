<?php
/**
 * M2 — Dasbor Monev: ringkasan periode, grafik tren, rekap unit & indikator (FR-05).
 * @var array $periods @var array|null $period @var array $reports @var array $indicators
 * @var array $trend @var float|null $avg @var int $below
 * @var array $chartTrend @var array $chartUnits @var array $counts @var array $errors
 */
$isSuper = Auth::isSuperAdmin();
$ownUnit = (int)Auth::unitId();
$unitCount = count($reports);
$filledCount = count(array_filter($reports, static fn ($r) => $r['report_id'] !== null));
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="text-muted small">
        Tren capaian 3-bulanan · menampilkan periode
        <strong><?= $period ? e($period['name']) : '-' ?></strong>
    </div>
    <form method="get" action="<?= e(url('/monev')) ?>" class="d-flex gap-2">
        <select class="form-select form-select-sm" name="period_id">
            <?php foreach ($periods as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $period && (int)$p['id'] === (int)$period['id'] ? 'selected' : '' ?>>
                    <?= e($p['name']) ?> (<?= format_date($p['start_date']) ?> – <?= format_date($p['end_date']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-secondary" type="submit">Tampilkan</button>
    </form>
    <?php if ($period): ?>
        <a class="btn btn-sm btn-outline-success"
           href="<?= e(url('/monev/export?period_id=' . (int)$period['id'])) ?>"
           title="Unduh rekap periode ini (per unit + per indikator)">
            <i class="bi bi-file-earlier me-1"></i>Ekspor Excel
        </a>
    <?php endif; ?>
</div>

<?php if (!$periods): ?>
    <div class="alert alert-info">Belum ada periode Monev. <?= $isSuper ? 'Buat periode terlebih dahulu pada menu <a href="' . e(url('/monev/periods')) . '">Kelola Periode</a>.' : 'Hubungi LPM.' ?></div>
<?php endif; ?>

<!-- Ringkasan -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card stat-card p-3 h-100">
            <div class="stat-top">
                <span class="stat-label">Laporan masuk</span>
                <span class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-inbox"></i></span>
            </div>
            <div class="stat-value"><?= $filledCount ?>/<?= $unitCount ?></div>
            <div class="small text-muted mt-1">unit pada periode ini</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card p-3 h-100">
            <div class="stat-top">
                <span class="stat-label">Rata-rata capaian</span>
                <span class="stat-icon bg-success-subtle text-success"><i class="bi bi-speedometer2"></i></span>
            </div>
            <div class="stat-value fs-5"><?= $avg !== null ? capaian_badge($avg) : '<span class="text-muted">—</span>' ?></div>
            <div class="small text-muted mt-1">tertimbang, seluruh unit</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card p-3 h-100">
            <div class="stat-top">
                <span class="stat-label">Di bawah target</span>
                <span class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-exclamation-triangle"></i></span>
            </div>
            <div class="stat-value"><?= $below ?> <span class="fs-6 text-muted">indikator</span></div>
            <div class="small text-muted mt-1">capaian &lt; 100%</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card p-3 h-100">
            <div class="stat-top">
                <span class="stat-label">Status laporan</span>
                <span class="stat-icon bg-secondary-subtle text-secondary"><i class="bi bi-clipboard-data"></i></span>
            </div>
            <div class="mt-2 small fw-semibold">
                <?= (int)$counts['draft'] ?> draf ·
                <?= (int)$counts['submitted'] ?> menunggu ·
                <?= (int)$counts['approved'] ?> disetujui
            </div>
            <div class="small text-muted mt-1">seluruh periode</div>
        </div>
    </div>
</div>

<!-- Grafik -->
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white"><strong>Tren rata-rata capaian antarperiode</strong></div>
            <div class="card-body">
                <canvas id="chartTrend" height="220"></canvas>
                <?php if (count($chartTrend['labels']) === 0): ?>
                    <div class="text-muted small">Belum ada data laporan.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white"><strong>Capaian per unit — <?= $period ? e($period['name']) : '-' ?></strong></div>
            <div class="card-body">
                <canvas id="chartUnits" height="220"></canvas>
                <?php if (count($chartUnits['labels']) === 0): ?>
                    <div class="text-muted small">Belum ada laporan pada periode ini.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Rekap unit -->
<div class="card table-card mb-3">
    <div class="card-header bg-white"><strong>Rekap capaian per unit — <?= $period ? e($period['name']) : '-' ?></strong></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Unit</th>
                <th>Status laporan</th>
                <th class="text-center">Skor total</th>
                <th class="text-end">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$reports): ?>
                <tr><td colspan="4" class="text-muted">Tidak ada unit aktif.</td></tr>
            <?php endif; ?>
            <?php foreach ($reports as $r):
                $allowed = $isSuper || (int)$r['unit_id'] === $ownUnit;
                $isOpen  = $period && $period['status'] === 'open';
                ?>
                <tr>
                    <td><?= e($r['unit_name']) ?></td>
                    <td>
                        <?= $r['report_id'] !== null ? monev_status_badge((string)$r['status']) : '<span class="badge text-bg-light border">belum diisi</span>' ?>
                        <?php if ($r['report_id'] !== null && $r['submitted_at']): ?>
                            <span class="small text-muted ms-1"><?= format_date($r['submitted_at'], 'd M Y') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?= $r['report_id'] !== null && $r['total_score'] !== null
                            ? capaian_badge((float)$r['total_score'])
                            : '<span class="text-muted">—</span>' ?>
                    </td>
                    <td class="text-end">
                        <?php if ($r['report_id'] !== null): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/monev/reports/' . (int)$r['report_id'])) ?>">Lihat</a>
                        <?php elseif ($allowed && $isOpen): ?>
                            <a class="btn btn-sm btn-primary" href="<?= e(url('/monev/report?period_id=' . (int)$period['id'] . '&unit_id=' . (int)$r['unit_id'])) ?>">Isi capaian</a>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Capaian per indikator -->
<div class="card table-card">
    <div class="card-header bg-white"><strong>Capaian per indikator — <?= $period ? e($period['name']) : '-' ?></strong></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Kode</th>
                <th>Indikator</th>
                <th class="text-end">Target</th>
                <th class="text-center">Rata-rata</th>
                <th class="text-center">Pengisi</th>
                <th>Capaian</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$indicators): ?>
                <tr><td colspan="6" class="text-muted">Belum ada indikator. <?= $isSuper ? 'Tambahkan pada menu <a href="' . e(url('/monev/indicators')) . '">Kelola Indikator</a>.' : '' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($indicators as $i): ?>
                <tr>
                    <td><code><?= e($i['code']) ?></code></td>
                    <td>
                        <?= e($i['name']) ?>
                        <?php if ($i['avg_ap'] !== null && (float)$i['avg_ap'] < 100): ?>
                            <span class="badge text-bg-warning">di bawah target</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= e($i['target']) ?> <?= e($i['unit_of_measure'] ?? '') ?></td>
                    <td class="text-center"><?= $i['avg_ap'] !== null ? capaian_badge((float)$i['avg_ap']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="text-center"><?= (int)$i['respondent_count'] ?></td>
                    <td>
                        <?php if ($i['avg_ap'] !== null && (float)$i['avg_ap'] < 100): ?>
                            <span class="small text-danger">kurang <?= number_format(100 - (float)$i['avg_ap'], 1, ',', '.') ?> poin dari target</span>
                        <?php else: ?>
                            <span class="small text-success">target tercapai</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const trendData = <?= json_encode($chartTrend, JSON_UNESCAPED_UNICODE) ?>;
    const unitData  = <?= json_encode($chartUnits, JSON_UNESCAPED_UNICODE) ?>;
    const lampu = v => v >= 100 ? '#198754' : (v >= 80 ? '#ffc107' : '#dc3545');

    if (trendData.labels.length) {
        new Chart(document.getElementById('chartTrend'), {
            type: 'line',
            data: {
                labels: trendData.labels,
                datasets: [{
                    label: 'Rata-rata capaian (%)',
                    data: trendData.scores,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,.15)',
                    fill: true,
                    tension: .25
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, suggestedMax: 100 } }
            }
        });
    }

    if (unitData.labels.length) {
        new Chart(document.getElementById('chartUnits'), {
            type: 'bar',
            data: {
                labels: unitData.labels,
                datasets: [{
                    label: 'Skor capaian (%)',
                    data: unitData.scores,
                    backgroundColor: unitData.scores.map(lampu)
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }
</script>
