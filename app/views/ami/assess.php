<?php
/**
 * M3 — Penilaian AMI per indikator: skor 0–4 + bukti + catatan temuan (FR-07).
 * Skor ≤ 2 wajib bercatatan → temuan tercatat otomatis (aktivitas Gambar 4.5).
 * @var array $assignment @var array $instruments @var array $values
 * @var array $findings @var bool $canEdit @var array $errors
 */
$err = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$scoreLabels = [
    4 => '4 · Memenuhi',
    3 => '3 · Hampir memenuhi',
    2 => '2 · Sebagian memenuhi',
    1 => '1 · Belum memenuhi',
    0 => '0 · Tidak memenuhi',
];
$statusBadge = match ($assignment['status']) {
    'assigned'    => '<span class="badge text-bg-secondary">belum mulai</span>',
    'in_progress' => '<span class="badge text-bg-info">berjalan</span>',
    'completed'   => '<span class="badge text-bg-success">penilaian lengkap</span>',
    default       => '<span class="badge text-bg-secondary">' . e((string)$assignment['status']) . '</span>',
};
$assessed = 0;
foreach ($instruments as $i) {
    if (isset($values[(int)$i['id']])) {
        $assessed++;
    }
}
?>

<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-clipboard2-pulse me-1"></i>
        <strong>Penilaian AMI</strong>, <?= e((string)$assignment['schedule_name']) ?> ·
        auditor: <?= e($assignment['auditor_name'] ?? 'belum ditetapkan') ?> ·
        lead: <?= e($assignment['lead_name'] ?? '—') ?>
        <?php if ($assignment['audit_date']): ?> · tanggal audit <?= format_date($assignment['audit_date']) ?><?php endif; ?>
        — skor 0–4 per indikator, skor ≤ 2 wajib bercatatan (FR-07).
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <?= $statusBadge ?>
        <div class="small text-muted">Progres: <?= (int)$assessed ?>/<?= count($instruments) ?> indikator</div>
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('/ami')) ?>">
            <i class="bi bi-arrow-left me-1"></i>Dasbor AMI
        </a>
    </div>
</div>

<?php if (!$canEdit): ?>
    <div class="alert alert-info py-2">
        Mode <strong>baca saja</strong> —
        <?php if ((int)($assignment['auditor_user_id'] ?? 0) !== (int)Auth::id() && !Auth::isSuperAdmin()): ?>
            Anda bukan auditor penugasan ini.
        <?php else: ?>
            jadwal <strong><?= e((string)$assignment['schedule_status']) ?></strong> tidak sedang berjalan.
        <?php endif; ?>
        Penilaian hanya dapat diisi saat jadwal <strong>berjalan</strong> oleh auditor yang ditugaskan / LPM.
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger py-2 small">
        <?php foreach ($errors as $e1): ?><?= e($e1) ?><br><?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" class="assess-form" enctype="multipart/form-data"
      action="<?= e(url('/ami/assignments/' . (int)$assignment['id'] . '/assess')) ?>">
    <?= csrf_field() ?>

<div class="card table-card mb-3">
    <div class="card-header bg-white d-flex justify-content-between">
        <strong>Daftar indikator (skala 0–4, bobot menentukan skor tertimbang)</strong>
        <span class="small text-muted">skor ≤ 2 → wajib catatan temuan</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th style="width:64px">Kode</th>
                <th>Indikator</th>
                <th style="width:70px" class="text-center">Bobot</th>
                <th style="width:180px">Skor</th>
                <th style="width:240px">Catatan temuan</th>
                <th style="width:150px">Bukti</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$instruments): ?>
                <tr><td colspan="6" class="text-muted">Belum ada indikator aktif pada jadwal ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($instruments as $inst):
                $iid = (int)$inst['id'];
                $v   = $values[$iid] ?? null;
                $cur = $v !== null ? (int)$v['score'] : null;
                ?>
                <tr>
                    <td class="fw-semibold"><?= e($inst['code']) ?></td>
                    <td>
                        <div class="small"><?= e((string)$inst['question']) ?></div>
                        <?php if (!empty($inst['assessment_guide'])): ?>
                            <details class="small text-muted">
                                <summary class="text-decoration-underline">pedoman penilaian</summary>
                                <div class="mt-1 p-2 bg-light rounded"><?= nl2br(e((string)$inst['assessment_guide'])) ?></div>
                            </details>
                        <?php endif; ?>
                        <?php if (!empty($inst['standard_name'])): ?>
                            <span class="badge text-bg-light border mt-1"><?= e((string)$inst['standard_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= number_format((float)$inst['weight'], 2, ',', '.') ?></td>
                    <td>
                        <?php if ($canEdit): ?>
                            <select name="scores[<?= $iid ?>]" class="form-select form-select-sm score-select">
                                <option value="">— belum dinilai —</option>
                                <?php foreach ($scoreLabels as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $cur === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <?= ami_score_badge($cur !== null ? (float)$cur : null) ?>
                            <?php if ($cur !== null): ?>
                                <div class="small text-muted"><?= e($scoreLabels[$cur] ?? '') ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($canEdit): ?>
                            <input type="text" name="notes[<?= $iid ?>]" class="form-control form-control-sm note-input"
                                   placeholder="wajib bila skor ≤ 2"
                                   value="<?= e((string)($v['finding_note'] ?? '')) ?>">
                        <?php else: ?>
                            <span class="small"><?= e((string)($v['finding_note'] ?? '—')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="small">
                        <?php if ($canEdit): ?>
                            <?php if (!empty($v['evidence_file'])): ?>
                                <div class="mb-1">
                                    <a href="<?= e(url('/ami/evidence/' . (int)$v['id'])) ?>" target="_blank">unduh bukti lama</a>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="evidence[<?= $iid ?>]" class="form-control form-control-sm"
                                   accept="<?= e(implode(',', (array)config('upload_ext'))) ?>">
                        <?php elseif (!empty($v['evidence_file'])): ?>
                            <a href="<?= e(url('/ami/evidence/' . (int)$v['id'])) ?>" target="_blank">lihat bukti</a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($canEdit): ?>
        <div class="card-body border-top">
            <button class="btn btn-primary" type="submit">Simpan penilaian</button>
            <span class="small text-muted ms-2">
                Penilaian yang sudah disimpan dapat diperbarui selama jadwal berjalan; status penugasan diperbarui otomatis.
            </span>
        </div>
    <?php endif; ?>
</div>
</form>

<!-- Temuan yang tercatat -->
<div class="card table-card">
    <div class="card-header bg-white d-flex justify-content-between">
        <strong>Temuan pada penugasan ini</strong>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/ami/findings?schedule_id=' . (int)$assignment['schedule_id'])) ?>">Kelola temuan</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th style="width:80px">Kode</th>
                <th>Tipe</th>
                <th style="width:90px">Keparahan</th>
                <th>Uraian</th>
                <th style="width:130px">Status</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$findings): ?>
                <tr><td colspan="5" class="text-muted">Belum ada temuan — seluruh indikator memenuhi skor minimal 3.</td></tr>
            <?php endif; ?>
            <?php foreach ($findings as $f): ?>
                <tr>
                    <td class="small fw-semibold"><?= e($f['indicator_code'] ?? '— manual —') ?></td>
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

<?php if ($canEdit): ?>
<script>
    // Skor ≤ 2 → catatan temuan wajib diisi (ditegaskan sebelum kirim).
    document.querySelector('form.assess-form')?.addEventListener('submit', function (ev) {
        const rows = this.querySelectorAll('tr');
        for (const row of rows) {
            const sel = row.querySelector('.score-select');
            const note = row.querySelector('.note-input');
            if (!sel || !note) continue;
            const val = sel.value;
            if (val !== '' && Number(val) <= 2 && note.value.trim() === '') {
                ev.preventDefault();
                note.classList.add('is-invalid');
                note.focus();
                var msg = 'Skor ≤ 2 pada indikator ' + ((row.querySelector('td')?.textContent || '').trim()) + ' wajib disertai catatan temuan.';
                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Catatan temuan wajib',
                        text: msg,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#2563eb'
                    });
                } else {
                    alert(msg); /* cadangan bila SweetAlert2 tak termuat */
                }
                return false;
            }
            note.classList.remove('is-invalid');
        }
    });
</script>
<?php endif; ?>
