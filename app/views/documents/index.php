<?php
/** @var array $rows @var int $total @var int $page @var int $per @var array $filters @var array $categories @var array $counts */

$query = array_filter([
    'q'           => $filters['q'],
    'category_id' => $filters['category_id'] ?: '',
    'status'      => $filters['status'],
    'approval'    => $filters['approval'],
], static fn ($v) => $v !== '' && $v !== null);
?>
<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-file-earmark-text me-1"></i>
        <strong>Dokumen SPMI</strong>, unggah &amp; klasifikasi kebijakan, pedoman, SOP, dan instrumen
        dengan masa berlaku serta alur persetujuan (FR-01–FR-04).
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= e(url('/documents/export' . ($query ? '?' . http_build_query($query) : ''))) ?>"
           class="btn btn-sm btn-outline-success" title="Unduh daftar dokumen (filter ikut berlaku)">
            <i class="bi bi-file-earlier me-1"></i>Ekspor Excel
        </a>
        <a href="<?= e(url('/documents/create')) ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Unggah dokumen
        </a>
    </div>
</div>

<div class="card table-card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get" action="<?= e(url('/documents')) ?>">
            <div class="col-md-4">
                <label class="form-label small">Cari</label>
                <input class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>"
                       placeholder="Judul / nomor dokumen…">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Kategori</label>
                <select class="form-select" name="category_id">
                    <option value="">Semua kategori</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$filters['category_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Status warna</label>
                <select class="form-select" name="status">
                    <option value="">Semua</option>
                    <option value="green" <?= $filters['status'] === 'green' ? 'selected' : '' ?>>Hijau</option>
                    <option value="yellow" <?= $filters['status'] === 'yellow' ? 'selected' : '' ?>>Kuning</option>
                    <option value="red" <?= $filters['status'] === 'red' ? 'selected' : '' ?>>Merah</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Status persetujuan</label>
                <select class="form-select" name="approval">
                    <option value="">Semua</option>
                    <?php foreach (['draft', 'submitted', 'approved', 'rejected', 'archived'] as $a): ?>
                        <option value="<?= $a ?>" <?= $filters['approval'] === $a ? 'selected' : '' ?>>
                            <?= ['draft' => 'Draft', 'submitted' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Dikembalikan', 'archived' => 'Diarsipkan'][$a] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <button class="btn btn-primary" type="submit">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="small text-muted">
        <?= (int)$total ?> dokumen ·
        <span class="text-success fw-semibold">● <?= (int)$counts['green'] ?></span> ·
        <span class="text-warning fw-semibold">● <?= (int)$counts['yellow'] ?></span> ·
        <span class="text-danger fw-semibold">● <?= (int)$counts['red'] ?></span>
    </div>
</div>

<div class="card table-card">
    <div class="card-body p-0">
        <?php if (!$rows): ?>
            <div class="empty-state">
                <i class="bi bi-search"></i>
                Belum ada dokumen yang cocok dengan filter.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="width:36%">Dokumen</th>
                        <th>Kategori / Unit</th>
                        <th>Versi</th>
                        <th>Masa berlaku</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $d): ?>
                        <?php
                        $days  = days_to_expiry($d['expiry_date']);
                        $shown = in_array($d['approval_status'], ['approved', 'archived'], true);
                        ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/documents/' . $d['id'])) ?>"><strong><?= e($d['title']) ?></strong></a>
                                <div class="small text-muted">
                                    <?= $d['doc_number'] ? e($d['doc_number']) . ' · ' : '' ?>
                                    <?= e($d['standard_name'] ?? '') ?>
                                </div>
                            </td>
                            <td class="small">
                                <?= e($d['category_name'] ?? '—') ?><br>
                                <span class="text-muted"><?= e($d['unit_name'] ?? '—') ?></span>
                            </td>
                            <td>v<?= e($d['version']) ?></td>
                            <td class="small">
                                <?= format_date($d['expiry_date']) ?>
                                <?php if ($days !== null && $days >= 0 && $shown): ?>
                                    <br><span class="text-muted">(<?= $days ?> hari lagi)</span>
                                <?php elseif ($days !== null && $days < 0 && $shown): ?>
                                    <br><span class="text-danger">(kedaluwarsa)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= approval_badge((string)$d['approval_status']) ?>
                                <?php if ($shown): ?><br><?= status_badge((string)$d['status_color']) ?><?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary"
                                   href="<?= e(url('/documents/' . $d['id'])) ?>">Lihat</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($total > $per): ?>
        <div class="card-footer bg-white d-flex justify-content-end">
            <?= pagination($page, $total, $per, '/documents', $query) ?>
        </div>
    <?php endif; ?>
</div>
