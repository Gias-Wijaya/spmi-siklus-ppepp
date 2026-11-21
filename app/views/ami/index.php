<?php
/**
 * M3 — Dasbor AMI: progres penilaian, skor per unit/standar, temuan, kepuasan.
 * @var array $periods @var array|null $schedule @var array|null $progress
 * @var array $unitScores @var array $standardRows @var array $assignments
 * @var int $openFindings @var array $recentFinding @var array $surveys
 * @var array $chartUnits @var array $chartStd
 */
$isSuper = Auth::isSuperAdmin();
$myId    = (int)Auth::id();

$scoreMap = [];
foreach ($unitScores as $u) {
    $scoreMap[(int)$u['assignment_id']] = $u;
}
$indicators = $progress ? (int)$progress['indicators'] : 0;

$scored = array_filter($unitScores, static fn ($u) => $u['avg_score'] !== null);
$avgAll = $scored
    ? round(array_sum(array_map(static fn ($u) => (float)$u['avg_score'], $scored)) / count($scored), 2)
    : null;

$openSurveys  = array_values(array_filter($surveys, static fn ($s) => $s['status'] === 'open'));
$totalAnswers = array_sum(array_map(static fn ($s) => (int)$s['response_count'], $surveys));
?>

<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-clipboard2-check me-1"></i>
        <strong>Dasbor AMI</strong>, tahun AMI <strong><?= $schedule ? e($schedule['name']) : '-' ?></strong>
        <?= $schedule ? '(' . e($schedule['status']) . ')' : '' ?> — progres penilaian, skor per unit
        dan standar, temuan, serta kepuasan pengguna (FR-07).
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="get" action="<?= e(url('/ami')) ?>" class="d-flex gap-2">
            <select class="form-select form-select-sm" name="schedule_id">
                <?php foreach ($periods as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $schedule && (int)$p['id'] === (int)$schedule['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-secondary" type="submit">Tampilkan</button>
        </form>
        <?php if ($isSuper): ?>
            <!-- Masukan LPM foto-3 — pintasan kelola instrumen (indikator) AMI, seperti Kelola Indikator di Monev. -->
            <a class="btn btn-sm btn-outline-primary"
               href="<?= e(url('/ami/instruments')) ?>"
               title="Tambah, ubah, dan nonaktifkan instrumen (indikator) AMI per jadwal">
                <i class="bi bi-sliders me-1"></i>Kelola Instrumen
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$periods): ?>
    <div class="alert alert-info">
        Belum ada jadwal AMI.
        <?= $isSuper ? 'Buat pada menu <a href="' . e(url('/ami/schedules')) . '">Jadwal AMI</a>.' : 'Hubungi LPM.' ?>
    </div>
<?php endif; ?>

<!-- Ringkasan -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card stat-card p-3 h-100">
            <div class="stat-top">
                <span class="stat-label">Progres penilaian</span>
                <span class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-graph-up-arrow"></i></span>
            </div>
            <div class="stat-value"><?= $progress ? e($progress['percent']) . '%' : '—' ?></div>
            <div class="small text-muted mt-1">
                <?= $progress ? (int)$progress['assessed'] . ' dari '
                    . ((int)$progress['assignments'] * (int)$progress['indicators']) . ' isian' : '—' ?>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card p-3 h-100">
            <div class="stat-top">
                <span class="stat-label">Rata-rata skor unit</span>
                <span class="stat-icon bg-success-subtle text-success"><i class="bi bi-clipboard2-check"></i></span>
            </div>
            <div class="stat-value fs-5"><?= ami_score_badge($avgAll) ?></div>
            <div class="small text-muted mt-1">skala 0–4, tertimbang</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card p-3 h-100">
            <div class="stat-top">
                <span class="stat-label">Temuan terbuka</span>
                <span class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-exclamation-diamond"></i></span>
            </div>
            <div class="stat-value"><?= (int)$openFindings ?></div>
            <div class="small text-muted mt-1">
                <a href="<?= e(url('/ami/findings' . ($schedule ? '?schedule_id=' . (int)$schedule['id'] : ''))) ?>">lihat daftar →</a>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card p-3 h-100">
            <div class="stat-top">
                <span class="stat-label">Formulir kepuasan</span>
                <span class="stat-icon bg-info-subtle text-info"><i class="bi bi-hand-thumbs-up"></i></span>
            </div>
            <div class="stat-value"><?= count($openSurveys) ?> <span class="fs-6 text-muted">terbuka</span></div>
            <div class="small text-muted mt-1"><?= (int)$totalAnswers ?> responden (semua periode)</div>
        </div>
    </div>
</div>

<!-- Grafik -->
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white"><strong>Skor per unit — <?= $schedule ? e($schedule['name']) : '-' ?></strong></div>
            <div class="card-body">
                <?php $unitHasScore = array_filter($chartUnits['scores'], static fn ($v) => $v !== null); ?>
                <?php if ($chartUnits['labels'] && $unitHasScore): ?>
                    <canvas id="chartUnits" height="220"></canvas>
                <?php elseif (!$chartUnits['labels']): ?>
                    <div class="text-muted small">Belum ada penugasan unit.</div>
                <?php else: ?>
                    <div class="text-muted small">Belum ada penilaian.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white"><strong>Skor per standar — rata-rata (skala 0–4)</strong></div>
            <div class="card-body">
                <?php $stdHasScore = array_filter($chartStd['scores'], static fn ($v) => $v !== null); ?>
                <?php if ($stdHasScore): ?>
                    <canvas id="chartStd" height="220"></canvas>
                <?php else: ?>
                    <div class="text-muted small">Belum ada penilaian.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Penugasan -->
<div class="card table-card mb-3">
    <div class="card-header bg-white d-flex justify-content-between">
        <strong>Progres penilaian per unit</strong>
        <?php if ($isSuper && $schedule): ?>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/ami/assignments?schedule_id=' . (int)$schedule['id'])) ?>">Kelola penugasan</a>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Unit</th>
                <th>Auditor</th>
                <th>Status</th>
                <th class="text-center">Dinilai</th>
                <th class="text-center">Skor</th>
                <th class="text-end">Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$assignments): ?>
                <tr><td colspan="6" class="text-muted">Belum ada penugasan pada jadwal ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($assignments as $a):
                $s = $scoreMap[(int)$a['id']] ?? null;
                $canEdit = $isSuper || (int)($a['auditor_user_id'] ?? 0) === $myId;
                ?>
                <tr>
                    <td>
                        <strong><?= e($a['unit_name']) ?></strong>
                        <?php if ($a['audit_date']): ?>
                            <br><span class="small text-muted">audit: <?= format_date($a['audit_date']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= e($a['auditor_name'] ?? 'belum ditetapkan') ?></td>
                    <td><?= ami_assignment_badge((string)$a['status']) ?></td>
                    <td class="text-center small"><?= (int)$a['assessed_count'] ?>/<?= $indicators ?></td>
                    <td class="text-center">
                        <?= ami_score_badge($s && $s['avg_score'] !== null ? (float)$s['avg_score'] : null) ?>
                    </td>
                    <td class="text-end">
                        <?php if ($canEdit): ?>
                            <a class="btn btn-sm btn-primary" href="<?= e(url('/ami/assignments/' . (int)$a['id'] . '/assess')) ?>">
                                <?= (int)$a['assessed_count'] > 0 ? 'Lanjut penilaian' : 'Isi penilaian' ?>
                            </a>
                        <?php else: ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/ami/assignments/' . (int)$a['id'] . '/assess')) ?>">Lihat</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3 mb-3">
    <!-- Temuan terbaru -->
    <div class="col-lg-7">
        <div class="card h-100 table-card">
            <div class="card-header bg-white"><strong>Temuan terbaru</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr><th>Unit</th><th>Temuan</th><th>Keparahan</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$recentFinding): ?>
                        <tr><td colspan="4" class="text-muted">Belum ada temuan.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recentFinding as $f): ?>
                        <tr>
                            <td class="small">
                                <?= e($f['unit_name']) ?>
                                <br><span class="text-muted"><?= e($f['schedule_name']) ?></span>
                            </td>
                            <td class="small"><?= e(mb_substr((string)$f['description'], 0, 90)) ?>…</td>
                            <td><?= severity_badge((string)$f['severity']) ?></td>
                            <td><?= finding_badge((string)$f['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Kepuasan -->
    <div class="col-lg-5">
        <div class="card h-100 table-card">
            <div class="card-header bg-white d-flex justify-content-between">
                <strong>Formulir kepuasan</strong>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/ami/satisfaction')) ?>">Buka</a>
            </div>
            <div class="list-group list-group-flush">
                <?php if (!$surveys): ?>
                    <div class="list-group-item text-muted">Belum ada formulir.</div>
                <?php endif; ?>
                <?php foreach (array_slice($surveys, 0, 4) as $sv): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small fw-semibold"><?= e($sv['name']) ?></div>
                            <div class="small text-muted"><?= (int)$sv['response_count'] ?> responden · <?= (int)$sv['question_count'] ?> pertanyaan</div>
                        </div>
                        <?php if ($sv['status'] === 'open'): ?>
                            <a class="btn btn-sm btn-outline-success" href="<?= e(url('/ami/satisfaction/' . (int)$sv['id'] . '/fill')) ?>">Isi</a>
                        <?php else: ?>
                            <span class="badge text-bg-secondary"><?= e($sv['status']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const unitData = <?= json_encode($chartUnits, JSON_UNESCAPED_UNICODE) ?>;
    const stdData  = <?= json_encode($chartStd, JSON_UNESCAPED_UNICODE) ?>;
    const lampu = v => v >= 3 ? '#198754' : (v >= 2 ? '#ffc107' : '#dc3545');

    const elUnits = document.getElementById('chartUnits');
    if (elUnits) {
        new Chart(elUnits, {
            type: 'bar',
            data: {
                labels: unitData.labels,
                datasets: [{
                    label: 'Skor tertimbang (0–4)',
                    data: unitData.scores,
                    backgroundColor: unitData.scores.map(v => v === null ? '#adb5bd' : lampu(v))
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, suggestedMax: 4 } }
            }
        });
    }

    const elStd = document.getElementById('chartStd');
    if (elStd) {
        new Chart(elStd, {
            type: 'bar',
            data: {
                labels: stdData.labels,
                datasets: [{
                    label: 'Rerata skor (0–4)',
                    data: stdData.scores,
                    backgroundColor: stdData.scores.map(v => v === null ? '#adb5bd' : lampu(v))
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, suggestedMax: 4 } }
            }
        });
    }
</script>
