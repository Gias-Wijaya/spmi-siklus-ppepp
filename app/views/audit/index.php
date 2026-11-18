<?php
/** @var array $rows @var int $total @var int $page @var int $per @var array $users @var array $actions @var array $filter */
$query = ['user' => $filter['user'], 'action' => $filter['action']];
?>
<div class="card table-card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get" action="<?= e(url('/audit')) ?>">
            <div class="col-md-4">
                <label class="form-label small">Pengguna</label>
                <select class="form-select" name="user">
                    <option value="">Semua pengguna</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= (string)$filter['user'] === (string)$u['id'] ? 'selected' : '' ?>>
                            <?= e($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Aksi</label>
                <select class="form-select" name="action">
                    <option value="">Semua aksi</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?= e($a) ?>" <?= $filter['action'] === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary" type="submit">Filter</button>
            </div>
            <div class="col-md-2 text-end small text-muted"><?= (int)$total ?> catatan</div>
        </form>
    </div>
</div>

<div class="card table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Waktu</th>
                    <th>Pengguna</th>
                    <th>Aksi</th>
                    <th>Objek</th>
                    <th>Detail</th>
                    <th>IP</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="text-muted p-3">Tidak ada catatan.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $log): ?>
                    <tr>
                        <td class="small text-nowrap"><?= format_date($log['created_at'], 'd M Y H:i:s') ?></td>
                        <td class="small"><?= e($log['user_name'] ?? 'sistem') ?></td>
                        <td><span class="badge text-bg-light border"><?= e($log['action']) ?></span></td>
                        <td class="small">
                            <?= e($log['table_name'] ?? '—') ?>
                            <?= $log['row_id'] ? '#' . (int)$log['row_id'] : '' ?>
                        </td>
                        <td class="small text-muted" style="max-width:340px">
                            <?php
                            $detail = (string)($log['detail'] ?? '');
                            echo e(mb_strimwidth($detail, 0, 160, '…'));
                            ?>
                        </td>
                        <td class="small text-muted"><?= e($log['ip_address'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($total > $per): ?>
        <div class="card-footer bg-white d-flex justify-content-end">
            <?= pagination($page, $total, $per, '/audit', $query) ?>
        </div>
    <?php endif; ?>
</div>
