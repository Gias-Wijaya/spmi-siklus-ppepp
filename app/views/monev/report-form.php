<?php
/**
 * M2 — Formulir isian capaian Monev per unit per periode (FR-04).
 * @var array $period @var array $unit @var array $indicators @var array $values @var array $errors
 */
$valuesMap = [];
foreach ($values as $v) {
    $valuesMap[(int)$v['indicator_id']] = $v;
}
$isSuper = Auth::isSuperAdmin();
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <div class="text-muted small">
            Periode <strong><?= e($period['name']) ?></strong> ·
            <?= format_date($period['start_date']) ?> – <?= format_date($period['end_date']) ?> ·
            Unit <strong><?= e($unit['name']) ?></strong>
        </div>
        <div class="small text-muted">Monev diisi <strong>enam bulan sekali</strong>; nilai = capaian aktual, target mengikuti indikator.</div>
    </div>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/monev')) ?>">← Dasbor Monev</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger py-2 small">
        <?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/monev/report')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="period_id" value="<?= (int)$period['id'] ?>">
    <input type="hidden" name="unit_id" value="<?= (int)$unit['id'] ?>">

    <div class="card table-card mb-3">
        <div class="card-header bg-white"><strong>Isian capaian indikator</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    <th style="width:26%">Indikator</th>
                    <th class="text-end" style="width:10%">Target</th>
                    <th style="width:14%">Nilai aktual</th>
                    <th style="width:22%">Bukti pendukung (PDF/ gambar)</th>
                    <th>Catatan</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$indicators): ?>
                    <tr><td colspan="5" class="text-muted">Belum ada indikator aktif — hubungi LPM.</td></tr>
                <?php endif; ?>
                <?php foreach ($indicators as $i):
                    $indId = (int)$i['id'];
                    $current = $valuesMap[$indId] ?? null;
                    ?>
                    <tr>
                        <td>
                            <code class="small"><?= e($i['code']) ?></code><br>
                            <span class="small"><?= e($i['name']) ?></span>
                            <?php if ($i['standard_name'] ?? null): ?>
                                <br><span class="text-muted small">→ <?= e($i['standard_name']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end small">
                            <?= e($i['target']) ?> <?= e($i['unit_of_measure'] ?? '') ?>
                            <?php if ($current && $current['achievement_percent'] !== null): ?>
                                <br><?= capaian_badge((float)$current['achievement_percent']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="number" step="any" min="0" name="values[<?= $indId ?>]"
                                   class="form-control form-control-sm"
                                   value="<?= $current ? e(rtrim(rtrim((string)$current['value'], '0'), '.')) : '' ?>"
                                   placeholder="0">
                        </td>
                        <td>
                            <input type="file" name="evidence[<?= $indId ?>]" class="form-control form-control-sm">
                            <?php if ($current && !empty($current['evidence_file'])): ?>
                                <div class="small mt-1">Terkirim — <a href="<?= e(url('/monev/evidence/' . (int)$current['id'])) ?>" target="_blank">lihat</a></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <input type="text" name="notes[<?= $indId ?>]" class="form-control form-control-sm"
                                   placeholder="opsional" value="<?= $current ? e($current['notes'] ?? '') : '' ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button class="btn btn-outline-secondary" type="submit" name="save" value="draft">Simpan sebagai draf</button>
        <button class="btn btn-primary" type="submit" name="submit" value="1">Ajukan untuk review</button>
        <a class="btn btn-link text-muted" href="<?= e(url('/monev')) ?>">Batal</a>
    </div>
    <div class="text-muted small mb-4">
        Draf dapat disunting kapan saja selama periode terbuka; setelah diajukan, LPM akan mereview
        <?= $isSuper ? '(Anda dapat menyetujui/mengembalikan sebagai LPM).' : 'dan memberi kabar melalui notifikasi.' ?>
    </div>
</form>
