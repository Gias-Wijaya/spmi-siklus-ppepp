<?php
/** @var array $stats @var array $pending @var array $myDocuments @var array $latestDocs @var array $notifications */
$isSuper = Auth::isSuperAdmin();

$statCard = static function (string $label, int $value, string $valueCls, string $icon, string $chipCls,
                             ?string $href = null): string {
    $inner = '<div class="stat-top">'
        . '<span class="stat-label">' . e($label) . '</span>'
        . '<span class="stat-icon ' . e($chipCls) . '"><i class="bi ' . e($icon) . '"></i></span>'
        . '</div>'
        . '<div class="stat-value ' . e($valueCls) . '">' . $value . '</div>';
    if ($href !== null) {
        return '<div class="col-6 col-lg-3"><a class="card stat-card p-3 d-block text-decoration-none" href="'
            . e($href) . '">' . $inner . '</a></div>';
    }
    return '<div class="col-6 col-lg-3"><div class="card stat-card p-3 h-100">' . $inner . '</div></div>';
};
?>
<div class="row g-3 mb-3">
    <?= $statCard('Dokumen berlaku', (int)$stats['green'], 'text-success', 'bi-check2-circle', 'bg-success-subtle text-success') ?>
    <?= $statCard('Perlu revisi', (int)$stats['yellow'], 'text-warning', 'bi-pencil-square', 'bg-warning-subtle text-warning') ?>
    <?= $statCard('Tidak berlaku', (int)$stats['red'], 'text-danger', 'bi-x-octagon', 'bg-danger-subtle text-danger') ?>
    <?= $statCard('Menunggu persetujuan', (int)$stats['pending'], 'text-info', 'bi-hourglass-split', 'bg-info-subtle text-info') ?>
</div>

<div class="row g-3 mb-3">
    <?= $statCard('Total dokumen', (int)$stats['total'], '', 'bi-files', 'bg-primary-subtle text-primary') ?>
    <?= $statCard('Temuan terbuka (AMI)', (int)$stats['findings'], '', 'bi-exclamation-diamond', 'bg-warning-subtle text-warning') ?>
    <?= $statCard('Tindak lanjut berjalan', (int)$stats['actions'], '', 'bi-arrow-repeat', 'bg-primary-subtle text-primary', url('/followup')) ?>
    <?= $statCard($isSuper ? 'Pengguna aktif' : 'Laporan Monev',
        $isSuper ? (int)$stats['users'] : (int)$stats['monev'], '',
        $isSuper ? 'bi-people' : 'bi-journal-text', 'bg-secondary-subtle text-secondary') ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <?php if ($isSuper): ?>
            <div class="card table-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-hourglass-split me-1 text-info"></i>Menunggu persetujuan</strong>
                    <a href="<?= e(url('/documents?approval=submitted')) ?>">Lihat semua</a>
                </div>
                <div class="card-body p-0">
                    <?php if (!$pending): ?>
                        <div class="empty-state">
                            <i class="bi bi-clipboard-check"></i>
                            Tidak ada dokumen yang menunggu persetujuan.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Dokumen</th>
                                    <th>Pengunggah</th>
                                    <th>Diajukan</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach (array_slice($pending, 0, 6) as $d): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= e(url('/documents/' . $d['id'])) ?>"><?= e($d['title']) ?></a>
                                            <div class="small text-muted">v<?= e($d['version']) ?> · <?= e($d['unit_name'] ?? '-') ?></div>
                                        </td>
                                        <td><?= e($d['owner_name'] ?? '-') ?></td>
                                        <td class="small"><?= format_date($d['submitted_at'], 'd M Y H:i') ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary"
                                               href="<?= e(url('/documents/' . $d['id'])) ?>">Proses</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-file-earmark-text me-1 text-primary"></i><?= $isSuper ? 'Dokumen terbaru' : 'Dokumen saya' ?></strong>
                <a href="<?= e(url('/documents')) ?>">Semua dokumen</a>
            </div>
            <div class="card-body p-0">
                <?php $list = $isSuper ? $latestDocs : $myDocuments; ?>
                <?php if (!$list): ?>
                    <div class="empty-state">
                        <i class="bi bi-folder2-open"></i>
                        Belum ada dokumen.
                        <a href="<?= e(url('/documents/create')) ?>">Unggah dokumen pertama</a>.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Dokumen</th>
                                <th>Masa berlaku</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($list as $d): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(url('/documents/' . $d['id'])) ?>"><?= e($d['title']) ?></a>
                                        <div class="small text-muted"><?= e($d['unit_name'] ?? '-') ?> ·
                                            <?= e($d['category_name'] ?? 'Tanpa kategori') ?></div>
                                    </td>
                                    <td class="small"><?= format_date($d['expiry_date']) ?></td>
                                    <td>
                                        <?= approval_badge((string)$d['approval_status']) ?>
                                        <?php if (in_array($d['approval_status'], ['approved', 'archived'], true)): ?>
                                            <?= status_badge((string)$d['status_color']) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-bell me-1 text-primary"></i>Notifikasi terbaru</strong>
                <a href="<?= e(url('/notifications')) ?>">Semua</a>
            </div>
            <ul class="list-group list-group-flush">
                <?php if (!$notifications): ?>
                    <li class="list-group-item">
                        <div class="empty-state py-3">
                            <i class="bi bi-bell"></i>
                            Belum ada notifikasi.
                        </div>
                    </li>
                <?php endif; ?>
                <?php foreach ($notifications as $n): ?>
                    <li class="list-group-item notif-item<?= (int)$n['is_read'] === 0 ? ' unread' : '' ?>">
                        <div class="d-flex justify-content-between gap-2">
                            <strong class="small"><?= e($n['title']) ?></strong>
                            <span class="small text-muted text-nowrap"><?= format_date($n['created_at'], 'd M H:i') ?></span>
                        </div>
                        <div class="small"><?= e($n['message']) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card table-card mt-3">
            <div class="card-header"><strong><i class="bi bi-arrow-repeat me-1 text-primary"></i>Siklus PPEPP</strong></div>
            <div class="card-body">
                <div class="ppepp-step"><span class="step-num">1</span><span><strong>Penetapan</strong> — dokumen SPMI (M1)</span></div>
                <div class="ppepp-step"><span class="step-num">2</span><span><strong>Pelaksanaan</strong> — Monev 3-bulanan (M2)</span></div>
                <div class="ppepp-step"><span class="step-num">3</span><span><strong>Evaluasi</strong> — AMI tahunan &amp; kepuasan (M3)</span></div>
                <div class="ppepp-step"><span class="step-num">4</span><span><strong>Pengendalian</strong> — rencana aksi &amp; verifikasi (M4)</span></div>
                <div class="ppepp-step"><span class="step-num">5</span><span><strong>Peningkatan</strong> — revisi dokumen → siklus berikutnya</span></div>
            </div>
        </div>
    </div>
</div>
