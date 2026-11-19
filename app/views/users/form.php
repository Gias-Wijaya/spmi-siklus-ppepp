<?php
/** @var array|null $user @var array $units @var array $errors */
$isEdit = $user !== null;
$fill   = static fn (string $field, string $default = ''): string => old($field, (string)($user[$field] ?? $default));
?>
<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-<?= $isEdit ? 'person-square' : 'person-plus' ?> me-1"></i>
        <strong><?= $isEdit ? 'Ubah pengguna' : 'Tambah pengguna' ?></strong>,
        <?= $isEdit ? 'perbarui identitas, unit kerja, peran, dan status akun.' : 'daftarkan akun Super Admin (LPM) atau anggota (Waket I–III).' ?>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('/users')) ?>">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar
        </a>
    </div>
</div>

<div class="card table-card" style="max-width:760px">
    <div class="card-header bg-white">
        <strong><?= $isEdit ? 'Ubah Pengguna' : 'Tambah Pengguna' ?></strong>
    </div>
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post"
              action="<?= e($isEdit ? url('/users/' . (int)$user['id']) : url('/users')) ?>">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Nama lengkap <span class="text-danger">*</span></label>
                    <input class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                           type="text" name="name" required value="<?= e($fill('name')) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                           type="email" name="email" required value="<?= e($fill('email')) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">
                        Kata sandi <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?>
                        <span class="text-muted small"><?= $isEdit ? '(kosongkan bila tidak diubah)' : 'min. 6 karakter' ?></span>
                    </label>
                    <input class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                           type="password" name="password" <?= $isEdit ? '' : 'required' ?>
                           autocomplete="new-password">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Peran <span class="text-danger">*</span></label>
                    <select class="form-select <?= isset($errors['role']) ? 'is-invalid' : '' ?>" name="role" required>
                        <option value="anggota" <?= $fill('role', 'anggota') === 'anggota' ? 'selected' : '' ?>>
                            Anggota — Waket I–III
                        </option>
                        <option value="super_admin" <?= $fill('role') === 'super_admin' ? 'selected' : '' ?>>
                            Super Admin — LPM
                        </option>
                    </select>
                    <?php if (isset($errors['role'])): ?>
                        <div class="invalid-feedback"><?= e($errors['role']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Unit kerja</label>
                    <select class="form-select" name="unit_id">
                        <option value="">— tanpa unit —</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"
                                <?= (int)($user['unit_id'] ?? 0) === (int)$u['id'] || old('unit_id') === (string)$u['id'] ? 'selected' : '' ?>>
                                <?= e($u['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">NIP / NIDN</label>
                    <input class="form-control" type="text" name="nip" value="<?= e($fill('nip')) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Telepon</label>
                    <input class="form-control" type="text" name="phone" value="<?= e($fill('phone')) ?>">
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                               <?= (int)($user['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Akun aktif (dapat masuk)</label>
                    </div>
                    <?php if (isset($errors['is_active'])): ?>
                        <div class="text-danger small"><?= e($errors['is_active']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <hr>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Simpan perubahan' : 'Tambah pengguna' ?></button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/users')) ?>">Batal</a>
            </div>
        </form>
    </div>
</div>
