<?php
/** @var string $q @var array $docs @var array $findings @var array $plans @var int $total */

/** Sorot kata kunci pada teks yang sudah diamankan (escape dulu, lalu highlight). */
$hl = static function (string $text, string $q): string {
    $safe = e($text);
    if ($q === '') {
        return $safe;
    }
    $out = preg_replace(
        '/(' . preg_quote($q, '/') . ')/iu',
        '<mark class="search-hit">$1</mark>',
        $safe
    );
    return $out ?? $safe;
};
?>
<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-search me-1"></i>
        <strong>Pencarian global</strong>, telusuri dokumen SPMI, temuan AMI, dan rencana aksi
        dari satu kotak (pintasan: tekan <kbd class="border rounded px-1">/</kbd>).
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('/search')) ?>" role="search" class="row g-2">
            <div class="col">
                <input class="form-control" type="search" name="q" value="<?= e($q) ?>"
                       placeholder="Judul, nomor dokumen, atau uraian temuan…" autofocus
                       aria-label="Kata kunci pencarian">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-search me-1"></i>Cari
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($q === ''): ?>
    <div class="card">
        <div class="empty-state">
            <i class="bi bi-search"></i>
            <h6>Mulai mengetik untuk mencari</h6>
            <p>Contoh: “SOP”, “pedoman”, “evaluasi dosen”.</p>
        </div>
    </div>
<?php elseif ($total === 0): ?>
    <div class="card">
        <div class="empty-state">
            <i class="bi bi-emoji-neutral"></i>
            <h6>Tidak ada hasil untuk “<?= e($q) ?>”</h6>
            <p>Periksa ejaan atau gunakan kata kunci yang lebih umum.</p>
        </div>
    </div>
<?php else: ?>
    <?php if ($docs): ?>
        <div class="card table-card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-file-earmark-text me-1"></i>Dokumen SPMI</span>
                <span class="badge text-bg-primary"><?= count($docs) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Dokumen</th>
                        <th class="d-none d-md-table-cell">Kategori</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($docs as $d): ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/documents/' . (int)$d['id'])) ?>" class="fw-semibold">
                                    <?= $hl((string)$d['title'], $q) ?>
                                </a>
                                <div class="small text-muted">
                                    <?= $hl((string)($d['doc_number'] ?: 'Tanpa nomor'), $q) ?>
                                    · v<?= e((string)$d['version']) ?>
                                    <?php if ($d['expiry_date']): ?>
                                        · s.d. <?= e(format_date((string)$d['expiry_date'])) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <?= e($d['category_name'] ?? '—') ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <?= status_badge((string)$d['status_color']) ?>
                                <?= approval_badge((string)$d['approval_status']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($findings): ?>
        <div class="card table-card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-exclamation-diamond me-1"></i>Temuan AMI</span>
                <span class="badge text-bg-warning"><?= count($findings) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Temuan</th>
                        <th class="d-none d-md-table-cell">Unit / Jadwal</th>
                        <th class="text-end">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($findings as $f): ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/ami/findings?schedule_id=' . (int)$f['schedule_id'])) ?>"
                                   class="fw-semibold"><?= $hl(mb_strimwidth((string)$f['description'], 0, 120, '…'), $q) ?></a>
                                <div class="small text-muted">Temuan #<?= (int)$f['id'] ?></div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <?= e($f['unit_name'] ?? '—') ?>
                                <div class="small text-muted"><?= e($f['schedule_name'] ?? '') ?></div>
                            </td>
                            <td class="text-end text-nowrap">
                                <?= severity_badge((string)$f['severity']) ?>
                                <?= finding_badge((string)$f['status']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($plans): ?>
        <div class="card table-card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-gear-wide-connected me-1"></i>Rencana Aksi</span>
                <span class="badge text-bg-primary"><?= count($plans) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Rencana aksi</th>
                        <th class="d-none d-md-table-cell">Unit / PIC</th>
                        <th class="text-end">Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($plans as $p): ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/followup/plans/' . (int)$p['id'])) ?>" class="fw-semibold">
                                    <?= $hl(mb_strimwidth((string)$p['description'], 0, 120, '…'), $q) ?>
                                </a>
                                <div class="small text-muted">Rencana Aksi #<?= (int)$p['id'] ?></div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <?= e($p['unit_name'] ?? '—') ?>
                                <div class="small text-muted">PIC: <?= e($p['pic_name'] ?? '—') ?></div>
                            </td>
                            <td class="text-end text-nowrap">
                                <?= action_status_badge((string)$p['status']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
