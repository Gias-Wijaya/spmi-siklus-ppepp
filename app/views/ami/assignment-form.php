<?php
/**
 * M3 — Form edit penugasan AMI: auditor, ketua auditee, tanggal audit, nomor laporan.
 * @var array $assignment @var array $users @var array $periods
 * @var array $auditorUsers
 */
$locked = $assignment['schedule_status'] === 'closed';
?>

<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-person-check me-1"></i>
        <strong>Edit penugasan: <?= e((string)$assignment['unit_name']) ?></strong>, jadwal
        <?= e((string)$assignment['schedule_name']) ?> (<?= e((string)$assignment['schedule_status']) ?>)
        — atur auditor, ketua auditee, tanggal audit, dan nomor laporan.
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-outline-dark"
           href="<?= e(url('/ami/assignments?schedule_id=' . (int)$assignment['schedule_id'])) ?>">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar penugasan
        </a>
    </div>
</div>

<?php if ($locked): ?>
    <div class="alert alert-warning py-2">Jadwal sudah <strong>ditutup</strong> — penugasan terkunci.</div>
<?php endif; ?>

<div class="card" style="max-width:640px">
    <div class="card-header bg-white"><strong>Isian penugasan</strong></div>
    <div class="card-body">
        <form method="post" action="<?= e(url('/ami/assignments/' . (int)$assignment['id'])) ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Unit kerja (auditee — waket)</label>
                <input type="text" class="form-control" value="<?= e((string)$assignment['unit_name']) ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Auditor <span class="text-danger">*</span></label>
                <?php if (!$auditorUsers): ?>
                    <div class="text-muted small">Belum ada pengguna aktif pada unit LPM.</div>
                <?php else: ?>
                    <!-- Item 8 (foto-08): auditor hanya pengguna unit LPM. -->
                    <select name="auditor_user_id" class="form-select">
                        <?php foreach ($auditorUsers as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= (int)($assignment['auditor_user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                                <?= e($u['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Auditor AMI berasal dari unit LPM — ditugaskan oleh LPM, bukan role terpisah.</div>
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label">Ketua auditee (lead unit)</label>
                <select name="auditee_lead_user_id" class="form-select">
                    <option value="0">— belum ditetapkan —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= (int)($assignment['auditee_lead_user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                            <?= e($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label">Tanggal audit</label>
                    <input type="date" name="audit_date" class="form-control" value="<?= e((string)($assignment['audit_date'] ?? '')) ?>">
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label">Nomor laporan</label>
                    <input type="text" name="report_number" class="form-control" placeholder="LPM/AMI/2026/…" value="<?= e((string)($assignment['report_number'] ?? '')) ?>">
                </div>
            </div>
            <button class="btn btn-primary" type="submit" <?= $locked ? 'disabled' : '' ?>>Simpan</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('/ami/assignments?schedule_id=' . (int)$assignment['schedule_id'])) ?>">Batal</a>
        </form>
    </div>
</div>
