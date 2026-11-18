<?php
/**
 * M3 — Rekap formulir kepuasan (FR-08): rerata tertimbang, rekap per pertanyaan,
 * kutipan masukan teks, dan daftar responden.
 * @var array $recap @var array $respondents
 */
$survey         = $recap['survey'];
$questions      = $recap['questions'];
$skalaQuestions = array_values(array_filter($questions, static fn ($q) => $q['type'] === 'skala' && $q['avg_score'] !== null));
$chartLabels    = array_map(static fn ($q) => mb_substr((string)$q['question'], 0, 40), $skalaQuestions);
$chartScores    = array_map(static fn ($q) => (float)$q['avg_score'], $skalaQuestions);
?>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <a class="small" href="<?= e(url('/ami/satisfaction')) ?>">← Kembali ke daftar formulir</a>
        <h1 class="h4 mt-1">Rekap: <?= e((string)$survey['name']) ?></h1>
        <div class="text-muted small">Dihitung otomatis dari jawaban responden (skala 1–5).</div>
    </div>
    <button class="btn btn-sm btn-dark" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak / PDF</button>
</div>

<style>
    @media print {
        .sidebar, header, .no-print, .topbar { display: none !important; }
        .content, main { margin: 0 !important; padding: 0 !important; }
        body { background: #fff !important; }
        .card { border: none !important; box-shadow: none !important; }
    }
</style>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Responden</div>
                <div class="fs-4 fw-semibold"><?= (int)$recap['response_count'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Rerata skor</div>
                <div class="fs-4 fw-semibold">
                    <?= $recap['overall_score'] !== null ? number_format((float)$recap['overall_score'], 2, ',', '.') . '<span class="fs-6 text-muted">/5</span>' : '—' ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Persentase kepuasan</div>
                <div class="fs-4 fw-semibold"><?= puas_badge($recap['overall_pct'] !== null ? (float)$recap['overall_pct'] : null) ?></div>
                <div class="small text-muted">≥80% hijau · ≥60% kuning</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Status formulir</div>
                <div class="fs-5 fw-semibold mt-1">
                    <?= match ($survey['status']) {
                        'open'   => '<span class="badge text-bg-success">terbuka</span>',
                        'closed' => '<span class="badge text-bg-dark">ditutup</span>',
                        default  => '<span class="badge text-bg-secondary">draf</span>',
                    } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white"><strong>Rerata per pertanyaan skala (1–5)</strong></div>
            <div class="card-body">
                <canvas id="chartPuas" height="240"></canvas>
                <?php if (!$skalaQuestions): ?>
                    <div class="text-muted small">Belum ada jawaban skala.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100 table-card">
            <div class="card-header bg-white"><strong>Rekap per pertanyaan</strong></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Pertanyaan</th>
                        <th style="width:80px">Jenis</th>
                        <th class="text-center" style="width:70px">Jawaban</th>
                        <th class="text-center" style="width:130px">Rerata</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$questions): ?>
                        <tr><td colspan="4" class="text-muted">Belum ada pertanyaan.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($questions as $q): ?>
                        <tr>
                            <td class="small"><?= e((string)$q['question']) ?></td>
                            <td class="small"><?= e($q['type']) ?></td>
                            <td class="text-center small"><?= (int)$q['answer_count'] ?></td>
                            <td class="text-center">
                                <?php if ($q['type'] === 'skala'): ?>
                                    <?= $q['avg_score'] !== null
                                        ? '<span class="small">' . number_format((float)$q['avg_score'], 2, ',', '.') . '/5</span>'
                                        : '<span class="text-muted small">—</span>' ?>
                                <?php else: ?>
                                    <span class="text-muted small">kualitatif</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white"><strong>Masukan teks responden (terbaru)</strong></div>
            <div class="list-group list-group-flush" style="max-height:320px; overflow:auto">
                <?php if (!$recap['texts']): ?>
                    <div class="list-group-item text-muted small">Belum ada masukan teks.</div>
                <?php endif; ?>
                <?php foreach ($recap['texts'] as $t): ?>
                    <div class="list-group-item">
                        <div class="small"><?= e((string)$t['answer_text']) ?></div>
                        <div class="text-muted small mt-1">
                            <?= e($t['respondent_name'] ?? 'Responden anonim') ?> ·
                            <?= e((string)$t['question']) ?> ·
                            <?= e(date('d/m/Y H:i', strtotime((string)$t['submitted_at']))) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100 table-card">
            <div class="card-header bg-white"><strong>Daftar responden</strong></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                    <tr><th>Responden</th><th>Waktu kirim</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$respondents): ?>
                        <tr><td colspan="2" class="text-muted">Belum ada responden.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($respondents as $r): ?>
                        <tr>
                            <td class="small"><?= e($r['respondent_name'] ?? '—') ?></td>
                            <td class="small text-muted"><?= e(date('d/m/Y H:i', strtotime((string)$r['submitted_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const puasLabels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
    const puasScores = <?= json_encode($chartScores) ?>;
    if (puasLabels.length) {
        new Chart(document.getElementById('chartPuas'), {
            type: 'bar',
            data: {
                labels: puasLabels,
                datasets: [{
                    label: 'Rerata (1–5)',
                    data: puasScores,
                    backgroundColor: puasScores.map(v => v >= 4 ? '#198754' : (v >= 3 ? '#ffc107' : '#dc3545'))
                }]
            },
            options: {
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, suggestedMax: 5 } }
            }
        });
    }
</script>
