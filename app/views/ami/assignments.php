<?php
/**
 * M3 — Penugasan auditor–auditee per unit (FR-07): pasangan auditor/unit tahunan.
 * @var array $periods @var array|null $schedule @var array $assignments
 * @var array $findingCounts @var array $freeUnits @var array $users @var array $errors
 * @var array $auditorUsers
 */
$err = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$locked = $schedule && $schedule['status'] === 'closed';
$firstAuditor = (int)($auditorUsers[0]['id'] ?? 0);

$userName = static fn (?int $id): string => $id && isset($users[array_search($id, array_column($users, 'id'))])
    ? $users[array_search($id, array_column($users, 'id'))]['name']
    : '—';
?>

<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-person-check me-1"></i>
        Pasangan <strong>auditor–auditee</strong> ditetapkan per unit — auditor adalah penugasan,
        bukan hak akses terpisah (FR-07).
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
    <form method="get" action="<?= e(url('/ami/assignments')) ?>" class="d-flex gap-2">
        <select class="form-select form-select-sm" name="schedule_id">
            <?php foreach ($periods as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= $schedule && (int)$p['id'] === (int)$schedule['id'] ? 'selected' : '' ?>>
                    <?= e($p['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-secondary" type="submit">Tampilkan</button>
    </form>
    </div>
</div>

<?php if (!$schedule): ?>
    <div class="alert alert-info">Belum ada jadwal AMI — buat terlebih dahulu pada menu <a href="<?= e(url('/ami/schedules')) ?>">Jadwal AMI</a>.</div>
<?php else: ?>
    <?php if ($locked): ?>
        <div class="alert alert-warning py-2">
            Jadwal <strong><?= e($schedule['name']) ?></strong> sudah <strong>ditutup</strong> — penugasan &amp; penilaian terkunci.
        </div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger py-2 small">
            <?php foreach ($errors as $e1): ?><?= e($e1) ?><br><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <!-- Tambah penugasan -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white"><strong>Tambah penugasan</strong></div>
                <div class="card-body">
                    <?php if ($locked): ?>
                        <p class="text-muted small mb-0">Formulir terkunci karena jadwal ditutup.</p>
                    <?php elseif (!$freeUnits): ?>
                        <p class="text-muted small mb-0">Semua unit kerja sudah ditugaskan pada jadwal ini.</p>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('/ami/assignments')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="schedule_id" value="<?= (int)$schedule['id'] ?>">
                            <div class="mb-2">
                                <label class="form-label">Unit kerja (auditee — waket)</label>
                                <select name="unit_id" class="form-select<?= $err('unit_id') ?>">
                                    <option value="0">— pilih unit —</option>
                                    <?php foreach ($freeUnits as $u): ?>
                                        <option value="<?= (int)$u['id'] ?>"><?= e($u['code']) ?> · <?= e($u['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['unit_id'])): ?><div class="invalid-feedback"><?= e($errors['unit_id']) ?></div><?php endif; ?>
                                <div class="form-text">Auditee hanya unit waket (W1–W3) — LPM tidak menjadi auditee.</div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Auditor <span class="text-danger">*</span></label>
                                <?php if (!$auditorUsers): ?>
                                    <div class="text-muted small">Belum ada pengguna aktif pada unit LPM.</div>
                                <?php else: ?>
                                    <!-- Item 8 (foto-08): auditor hanya pengguna unit LPM. -->
                                    <select name="auditor_user_id" class="form-select<?= $err('auditor_user_id') ?>">
                                        <?php foreach ($auditorUsers as $u): ?>
                                            <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === $firstAuditor ? 'selected' : '' ?>>
                                                <?= e($u['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['auditor_user_id'])): ?><div class="invalid-feedback"><?= e($errors['auditor_user_id']) ?></div><?php endif; ?>
                                    <div class="form-text">Auditor AMI berasal dari unit LPM (penugasan, bukan role).</div>
                                <?php endif; ?>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Ketua auditee (lead unit)</label>
                                <select name="auditee_lead_user_id" class="form-select">
                                    <option value="0">— belum ditetapkan —</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tanggal audit</label>
                                <input type="date" name="audit_date" class="form-control" value="<?= e(old('audit_date')) ?>">
                            </div>
                            <button class="btn btn-primary w-100" type="submit">Buat penugasan</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Daftar penugasan -->
        <div class="col-lg-8">
            <div class="card table-card">
                <div class="card-header bg-white"><strong>Daftar penugasan — <?= e($schedule['name']) ?></strong></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Unit (auditee)</th>
                            <th>Auditor</th>
                            <th>Status</th>
                            <th class="text-center">Dinilai</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$assignments): ?>
                            <tr><td colspan="5" class="text-muted">Belum ada penugasan.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($assignments as $a): ?>
                            <tr>
                                <td>
                                    <strong><?= e($a['unit_name']) ?></strong>
                                    <br><span class="small text-muted">
                                        lead: <?= e($a['lead_name'] ?? '—') ?>
                                        <?php if ($a['audit_date']): ?> · audit <?= format_date($a['audit_date']) ?><?php endif; ?>
                                    </span>
                                </td>
                                <td class="small"><?= e($a['auditor_name'] ?? '—') ?></td>
                                <td><?= ami_assignment_badge((string)$a['status']) ?></td>
                                <td class="text-center small"><?= (int)$a['assessed_count'] ?></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/ami/assignments/' . (int)$a['id'] . '/edit')) ?>">Edit</a>
                                    <a class="btn btn-sm btn-primary" href="<?= e(url('/ami/assignments/' . (int)$a['id'] . '/assess')) ?>">Nilai</a>
                                    <?php if (!$locked): ?>
                                        <form method="post" action="<?= e(url('/ami/assignments/' . (int)$a['id'] . '/delete')) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button class="btn btn-sm btn-outline-danger" type="submit"
                                                    data-confirm="Hapus penugasan <?= e($a['unit_name']) ?> beserta nilai &amp; temuannya?">Hapus</button>
                                        </form>
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

    <div class="card table-card">
        <div class="card-header bg-white"><strong>Progres penilaian per unit</strong></div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Unit</th>
                    <th>Status</th>
                    <th class="text-center">Indikator dinilai</th>
                    <th class="text-center">Temuan</th>
                    <th class="text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$assignments): ?>
                    <tr><td colspan="5" class="text-muted">—</td></tr>
                <?php endif; ?>
                <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td class="small"><?= e($a['unit_name']) ?></td>
                        <td><?= ami_assignment_badge((string)$a['status']) ?></td>
                        <td class="text-center small"><?= (int)$a['assessed_count'] ?></td>
                        <td class="text-center small"><?= (int)($findingCounts[(int)$a['id']] ?? 0) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/ami/assignments/' . (int)$a['id'] . '/assess')) ?>">Detail penilaian</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
