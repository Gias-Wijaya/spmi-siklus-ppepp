<?php /** @var array $rows @var array $units @var array $filter */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <form class="row g-2" method="get" action="<?= e(url('/users')) ?>">
        <div class="col-auto">
            <input class="form-control" type="search" name="q" value="<?= e($filter['q']) ?>"
                   placeholder="Cari nama / email / NIP…">
        </div>
        <div class="col-auto">
            <select class="form-select" name="role">
                <option value="">Semua peran</option>
                <option value="super_admin" <?= $filter['role'] === 'super_admin' ? 'selected' : '' ?>>Super Admin (LPM)</option>
                <option value="anggota" <?= $filter['role'] === 'anggota' ? 'selected' : '' ?>>Anggota (Waket)</option>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-outline-secondary" type="submit">Filter</button>
        </div>
    </form>
    <a href="<?= e(url('/users/create')) ?>" class="btn btn-success">＋ Tambah pengguna</a>
</div>

<div class="card table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Unit</th>
                    <th>Peran</th>
                    <th>Status</th>
                    <th>Login terakhir</th>
                    <th class="text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="text-muted p-3">Tidak ada pengguna yang cocok.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $u): ?>
                    <?php $self = (int)$u['id'] === (int)Auth::id(); ?>
                    <tr>
                        <td>
                            <strong><?= e($u['name']) ?></strong>
                            <?php if ($self): ?><span class="badge text-bg-light border">Anda</span><?php endif; ?>
                            <div class="small text-muted"><?= e($u['nip'] ?? '') ?></div>
                        </td>
                        <td class="small"><?= e($u['email']) ?></td>
                        <td class="small"><?= e($u['unit_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($u['role'] === 'super_admin'): ?>
                                <span class="badge text-bg-primary">Super Admin (LPM)</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Anggota</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$u['is_active'] === 1): ?>
                                <span class="badge text-bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge text-bg-danger">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= format_date($u['last_login_at'], 'd M Y H:i') ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary"
                               href="<?= e(url('/users/' . (int)$u['id'] . '/edit')) ?>">Ubah</a>
                            <?php if (!$self): ?>
                                <form method="post" class="d-inline"
                                      action="<?= e(url('/users/' . (int)$u['id'] . '/delete')) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit"
                                            data-confirm="Hapus pengguna <?= e($u['name']) ?>?">Hapus</button>
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
