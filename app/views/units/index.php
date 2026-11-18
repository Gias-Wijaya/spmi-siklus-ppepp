<?php /** @var array $rows @var array $errors */ ?>
<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-buildings me-1"></i>
        <strong>Unit kerja</strong>, daftar unit pemilik dokumen dan auditee — <?= count($rows) ?> unit terdaftar.
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#unitForm">
            <i class="bi bi-plus-lg me-1"></i>Tambah unit
        </button>
    </div>
</div>

<div class="collapse mb-3" id="unitForm">
    <div class="card table-card">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="post" action="<?= e(url('/units')) ?>">
                <?= csrf_field() ?>
                <div class="col-md-2">
                    <label class="form-label small">Kode <span class="text-danger">*</span></label>
                    <input class="form-control" type="text" name="code" required placeholder="W1">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Nama unit <span class="text-danger">*</span></label>
                    <input class="form-control" type="text" name="name" required placeholder="Bidang Akademik">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Keterangan</label>
                    <input class="form-control" type="text" name="description" placeholder="Wakil Ketua I">
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary" type="submit">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Keterangan</th>
                    <th>Pengguna</th>
                    <th>Dokumen</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="text-muted p-3">Belum ada unit kerja.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $u): ?>
                    <tr>
                        <td><span class="badge text-bg-light border"><?= e($u['code']) ?></span></td>
                        <td><strong><?= e($u['name']) ?></strong></td>
                        <td class="small text-muted"><?= e($u['description'] ?? '—') ?></td>
                        <td><?= (int)$u['user_count'] ?></td>
                        <td><?= (int)$u['doc_count'] ?></td>
                        <td>
                            <?php if ((int)$u['is_active'] === 1): ?>
                                <span class="badge text-bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary"
                               href="<?= e(url('/units/' . (int)$u['id'] . '/edit')) ?>">Ubah</a>
                            <form method="post" class="d-inline"
                                  action="<?= e(url('/units/' . (int)$u['id'] . '/delete')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit"
                                        data-confirm="Hapus unit <?= e($u['name']) ?>?">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
