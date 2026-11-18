<?php
/**
 * M3 — Laporan temuan & skor AMI (FR-08, UC-24): rekap per unit/standar/indikator + gap, siap cetak.
 * @var array $periods @var array $schedule @var array $progress
 * @var array $unitScores @var array $standardRows @var array $instrumentRows
 * @var array $findings @var int $openCount
 */
$scored = array_filter($unitScores, static fn ($u) => $u['avg_score'] !== null);
$avgAll = $scored
    ? round(array_sum(array_map(static fn ($u) => (float)$u['avg_score'], $scored)) / count($scored), 2)
    : null;
?>

<style>
    @media print {
        .sidebar, header, .no-print, .topbar { display: none !important; }
        .content, main { margin: 0 !important; padding: 0 !important; }
        body { background: #fff !important; }
        .card { border: none !important; box-shadow: none !important; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 no-print flex-wrap gap-2">
    <div>
        <a class="small" href="<?= e(url('/ami')) ?>">← Kembali ke dasbor AMI</a>
        <h1 class="h4 mt-1">Laporan Temuan AMI — <?= e((string)$schedule['name']) ?></h1>
    </div>
    <div class="d-flex gap-2">
        <form method="get" action="<?= e(url('/ami/report')) ?>" class="d-flex gap-2">
            <select class="form-select form-select-sm" name="schedule_id">
                <?php foreach ($periods as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$schedule['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-secondary" type="submit">Tampilkan</button>
        </form>
        <button class="btn btn-sm btn-dark" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak / PDF</button>
        <a class="btn btn-sm btn-outline-success" href="<?= e(url('/ami/export?schedule_id=' . (int)$schedule['id'])) ?>"
           title="Unduh laporan AMI (filter jadwal ikut berlaku)">
            <i class="bi bi-file-earlier me-1"></i>Ekspor Excel
        </a>
    </div>
</div>

<!-- Ringkasan -->
<div class="card mb-3">
    <div class="card-header bg-white"><strong>A. Ringkasan pelaksanaan</strong></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="text-muted small">Status jadwal</div>
                <div class="fw-semibold"><?= e((string)$schedule['status']) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">Progres penilaian</div>
                <div class="fw-semibold"><?= e((string)$progress['percent']) ?>%
                    <span class="text-muted fw-normal small">
                        (<?= (int)$progress['assessed'] ?>/<?= ((int)$progress['assignments'] * (int)$progress['indicators']) ?> isian)
                    </span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">Rerata skor unit</div>
                <div class="fw-semibold"><?= ami_score_badge($avgAll) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted small">Temuan terbuka</div>
                <div class="fw-semibold"><?= (int)$openCount ?> <span class="text-muted fw-normal small">dari <?= count($findings) ?> temuan</span></div>
            </div>
        </div>
    </div>
</div>

<!-- Rekap per unit -->
<div class="card mb-3">
    <div class="card-header bg-white"><strong>B. Rekap skor per unit</strong></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th>Unit</th>
                <th>Auditor</th>
                <th class="text-center">Dinilai</th>
                <th class="text-center">Skor tertimbang</th>
                <th class="text-center">Gap dari 4</th>
                <th class="text-center">Temuan</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$unitScores): ?>
                <tr><td colspan="7" class="text-muted">Belum ada penugasan.</td></tr>
            <?php endif; ?>
            <?php
            $findingPerUnit = [];
            foreach ($findings as $f) {
                $k = (string)$f['unit_name'];
                $findingPerUnit[$k] = ($findingPerUnit[$k] ?? 0) + 1;
            }
            foreach ($unitScores as $u):
                $score = $u['avg_score'] !== null ? (float)$u['avg_score'] : null;
                $gap   = $score !== null ? round(4 - $score, 2) : null;
                ?>
                <tr>
                    <td><strong><?= e($u['unit_name']) ?></strong></td>
                    <td class="small"><?= e($u['auditor_name'] ?? '—') ?></td>
                    <td class="text-center"><?= (int)$u['assessed_count'] ?></td>
                    <td class="text-center"><?= ami_score_badge($score) ?></td>
                    <td class="text-center small"><?= $gap !== null ? number_format($gap, 2, ',', '.') : '—' ?></td>
                    <td class="text-center small"><?= (int)($findingPerUnit[$u['unit_name']] ?? 0) ?></td>
                    <td class="small"><?= ami_assignment_badge((string)$u['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Rekap per standar + gap -->
<div class="card mb-3">
    <div class="card-header bg-white"><strong>C. Rekap per standar &amp; analisis gap</strong></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th>Standar</th>
                <th class="text-center">Indikator dinilai</th>
                <th class="text-center">Rerata skor</th>
                <th class="text-center">Gap (4 − skor)</th>
                <th>Gap terbesar?</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $maxGap = null;
            foreach ($standardRows as $s) {
                if ($s['gap'] !== null && ($maxGap === null || (float)$s['gap'] > $maxGap)) {
                    $maxGap = (float)$s['gap'];
                }
            }
            if (!$standardRows): ?>
                <tr><td colspan="5" class="text-muted">Belum ada data penilaian.</td></tr>
            <?php endif; ?>
            <?php foreach ($standardRows as $s): ?>
                <tr>
                    <td>
                        <?= e($s['standard_code'] ?? '—') ?> · <?= e($s['standard_name'] ?? 'Tanpa standar') ?>
                    </td>
                    <td class="text-center"><?= (int)$s['assessed_count'] ?></td>
                    <td class="text-center"><?= ami_score_badge($s['avg_score'] !== null ? (float)$s['avg_score'] : null) ?></td>
                    <td class="text-center small">
                        <?= $s['gap'] !== null ? number_format((float)$s['gap'], 2, ',', '.') : '—' ?>
                    </td>
                    <td class="small">
                        <?php if ($s['gap'] !== null && $maxGap !== null && (float)$s['gap'] === $maxGap && $maxGap > 0): ?>
                            <span class="badge text-bg-danger">gap terbesar — prioritas peningkatan</span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-body border-top small text-muted">
        Gap = selisih rerata skor tertimbang dari skor penuh (4,00). Semakin besar gap, semakin jauh
        capaian standar dari kondisi “memenuhi sepenuhnya” — digunakan sebagai dasar prioritas
        perbaikan pada siklus Pengendalian–Peningkatan.
    </div>
</div>

<!-- Rekap per indikator -->
<div class="card mb-3">
    <div class="card-header bg-white"><strong>D. Rekap per indikator</strong></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th style="width:70px">Kode</th>
                <th>Indikator</th>
                <th class="text-center" style="width:70px">Bobot</th>
                <th class="text-center" style="width:90px">Dinilai</th>
                <th class="text-center" style="width:140px">Rerata skor</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$instrumentRows): ?>
                <tr><td colspan="5" class="text-muted">Belum ada indikator.</td></tr>
            <?php endif; ?>
            <?php foreach ($instrumentRows as $i): ?>
                <tr>
                    <td class="fw-semibold"><?= e($i['code']) ?></td>
                    <td class="small">
                        <?= e((string)$i['question']) ?>
                        <?php if (!empty($i['standard_name'])): ?>
                            <br><span class="text-muted"><?= e((string)$i['standard_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= number_format((float)$i['weight'], 2, ',', '.') ?></td>
                    <td class="text-center"><?= (int)$i['assessed_count'] ?></td>
                    <td class="text-center"><?= ami_score_badge($i['avg_score'] !== null ? (float)$i['avg_score'] : null) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Daftar temuan -->
<div class="card mb-3">
    <div class="card-header bg-white"><strong>E. Daftar temuan (<?php echo count($findings); ?>)</strong></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
            <tr>
                <th style="width:140px">Unit</th>
                <th style="width:80px">Indikator</th>
                <th style="width:120px">Tipe</th>
                <th style="width:80px">Keparahan</th>
                <th>Uraian</th>
                <th style="width:110px">Status</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$findings): ?>
                <tr><td colspan="6" class="text-muted">Tidak ada temuan pada jadwal ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($findings as $f): ?>
                <tr>
                    <td class="small"><?= e($f['unit_name']) ?></td>
                    <td class="small"><?= e($f['indicator_code'] ?? '—') ?></td>
                    <td class="small"><?= e(finding_type_label((string)$f['finding_type'])) ?></td>
                    <td><?= severity_badge((string)$f['severity']) ?></td>
                    <td class="small"><?= e((string)$f['description']) ?></td>
                    <td><?= finding_badge((string)$f['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="text-muted small no-print">
    Laporan dihasilkan otomatis oleh Sistem Informasi Penjaminan Mutu Internal — dicetak
    <?= e(date('d/m/Y H:i')) ?> oleh <?= e((string)(Auth::user()['name'] ?? '-')) ?>.
</div>
