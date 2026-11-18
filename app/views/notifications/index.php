<?php /** @var array $rows @var int $total @var int $page @var int $per */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="text-muted small"><?= (int)$total ?> notifikasi</div>
    <form method="post" action="<?= e(url('/notifications/read-all')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-sm btn-outline-secondary" type="submit">Tandai semua sudah dibaca</button>
    </form>
</div>

<div class="card table-card">
    <ul class="list-group list-group-flush">
        <?php if (!$rows): ?>
            <li class="list-group-item">
                <div class="empty-state py-3">
                    <i class="bi bi-bell"></i>
                    Belum ada notifikasi.
                </div>
            </li>
        <?php endif; ?>
        <?php foreach ($rows as $n): ?>
            <li class="list-group-item notif-item d-flex justify-content-between align-items-start<?= (int)$n['is_read'] === 0 ? ' unread' : '' ?>">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if ((int)$n['is_read'] === 0): ?>
                            <span class="badge rounded-pill bg-danger">&nbsp;</span>
                        <?php endif; ?>
                        <strong class="small"><?= e($n['title']) ?></strong>
                        <span class="badge text-bg-light border"><?= e($n['type']) ?></span>
                    </div>
                    <div class="small"><?= e($n['message']) ?></div>
                    <?php if ($n['ref_table'] === 'documents' && $n['ref_id']): ?>
                        <a class="small" href="<?= e(url('/documents/' . (int)$n['ref_id'])) ?>">Buka dokumen →</a>
                    <?php elseif ($n['ref_table'] === 'monev_reports' && $n['ref_id']): ?>
                        <a class="small" href="<?= e(url('/monev/reports/' . (int)$n['ref_id'])) ?>">Buka laporan Monev →</a>
                    <?php elseif ($n['ref_table'] === 'monev_periods' && $n['ref_id']): ?>
                        <a class="small" href="<?= e(url('/monev?period_id=' . (int)$n['ref_id'])) ?>">Buka periode Monev →</a>
                    <?php elseif ($n['ref_table'] === 'ami_schedules' && $n['ref_id']): ?>
                        <a class="small" href="<?= e(url('/ami/report?schedule_id=' . (int)$n['ref_id'])) ?>">Buka jadwal AMI →</a>
                    <?php elseif ($n['ref_table'] === 'ami_assignments' && $n['ref_id']): ?>
                        <a class="small" href="<?= e(url('/ami/assignments/' . (int)$n['ref_id'] . '/assess')) ?>">Buka penilaian AMI →</a>
                    <?php elseif ($n['ref_table'] === 'ami_findings'): ?>
                        <a class="small" href="<?= e(url('/ami/findings')) ?>">Buka daftar temuan →</a>
                    <?php elseif ($n['ref_table'] === 'action_plans' && $n['ref_id']): ?>
                        <a class="small" href="<?= e(url('/followup/plans/' . (int)$n['ref_id'])) ?>">Buka rencana aksi →</a>
                    <?php elseif ($n['ref_table'] === 'satisfaction_surveys'): ?>
                        <a class="small" href="<?= e(url('/ami/satisfaction')) ?>">Buka formulir kepuasan →</a>
                    <?php endif; ?>
                </div>
                <span class="small text-muted text-nowrap ms-3"><?= format_date($n['created_at'], 'd M Y H:i') ?></span>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($total > $per): ?>
        <div class="card-footer bg-white d-flex justify-content-end">
            <?= pagination($page, $total, $per, '/notifications') ?>
        </div>
    <?php endif; ?>
</div>
