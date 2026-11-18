<?php
declare(strict_types=1);

/** Manajemen unit kerja — hanya Super Admin (LPM). */
final class UnitController extends Controller
{
    public function index(): void
    {
        $this->requireSuperAdmin();

        $rows = Database::all(
            'SELECT u.*,
                    (SELECT COUNT(*) FROM users usr WHERE usr.unit_id = u.id)              AS user_count,
                    (SELECT COUNT(*) FROM documents d  WHERE d.unit_id  = u.id)            AS doc_count
               FROM units u
              ORDER BY u.name ASC'
        );

        $this->view('units/index', [
            'title' => 'Unit Kerja',
            'rows'  => $rows,
            'errors'=> [],
        ]);
    }

    public function store(): void
    {
        $this->requireSuperAdmin();
        $errors = $this->required(['code', 'name'], ['code' => 'Kode unit', 'name' => 'Nama unit']);

        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        if ($code !== '' && Unit::codeExists($code)) {
            $errors['code'] = 'Kode unit sudah dipakai.';
        }

        if ($errors) {
            flash('danger', 'Periksa kembali isian formulir.');
            $this->redirect('/units');
        }

        $id = Unit::create([
            'code'        => $code,
            'name'        => trim((string)$_POST['name']),
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'is_active'   => 1,
        ]);

        audit('create', 'units', $id, ['code' => $code]);
        flash('success', 'Unit kerja ditambahkan.');
        $this->redirect('/units');
    }

    public function edit(string $id): void
    {
        $this->requireSuperAdmin();
        $unit = Unit::find((int)$id);
        if (!$unit) {
            $this->notFound('Unit tidak ditemukan.');
        }

        $this->view('units/form', [
            'title'  => 'Ubah Unit Kerja',
            'unit'   => $unit,
            'errors' => [],
        ]);
    }

    public function update(string $id): void
    {
        $this->requireSuperAdmin();
        $unit = Unit::find((int)$id);
        if (!$unit) {
            $this->notFound('Unit tidak ditemukan.');
        }

        $errors = $this->required(['code', 'name'], ['code' => 'Kode unit', 'name' => 'Nama unit']);
        $code   = strtoupper(trim((string)($_POST['code'] ?? '')));
        if ($code !== '' && Unit::codeExists($code, (int)$id)) {
            $errors['code'] = 'Kode unit sudah dipakai unit lain.';
        }

        if ($errors) {
            flash('danger', 'Periksa kembali isian formulir.');
            $this->redirect('/units/' . (int)$id . '/edit');
        }

        Unit::updateById((int)$id, [
            'code'        => $code,
            'name'        => trim((string)$_POST['name']),
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
        ]);

        audit('update', 'units', (int)$id, ['code' => $code]);
        flash('success', 'Unit kerja diperbarui.');
        $this->redirect('/units');
    }

    public function destroy(string $id): void
    {
        $this->requireSuperAdmin();
        $unit = Unit::find((int)$id);
        if (!$unit) {
            $this->notFound('Unit tidak ditemukan.');
        }

        $users = (int)Database::scalar('SELECT COUNT(*) FROM users WHERE unit_id = ?', [(int)$id]);
        $docs  = (int)Database::scalar('SELECT COUNT(*) FROM documents WHERE unit_id = ?', [(int)$id]);

        if ($users > 0 || $docs > 0) {
            flash('danger', 'Unit masih digunakan oleh ' . $users . ' pengguna dan ' . $docs . ' dokumen — tidak dapat dihapus.');
            $this->redirect('/units');
        }

        Unit::deleteById((int)$id);
        audit('delete', 'units', (int)$id, ['code' => $unit['code']]);
        flash('success', 'Unit kerja dihapus.');
        $this->redirect('/units');
    }
}
