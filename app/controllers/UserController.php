<?php
declare(strict_types=1);

/** Manajemen pengguna & hak akses — hanya Super Admin (LPM). */
final class UserController extends Controller
{
    public function index(): void
    {
        $this->requireSuperAdmin();

        $q    = trim((string)($_GET['q'] ?? ''));
        $role = trim((string)($_GET['role'] ?? ''));
        $rows = User::search($q, $role);

        $this->view('users/index', [
            'title'  => 'Pengguna',
            'rows'   => $rows,
            'units'  => Unit::active(),
            'filter' => ['q' => $q, 'role' => $role],
        ]);
    }

    public function create(): void
    {
        $this->requireSuperAdmin();
        $this->view('users/form', [
            'title' => 'Tambah Pengguna',
            'user'  => null,
            'units' => Unit::active(),
            'errors'=> [],
        ]);
    }

    public function store(): void
    {
        $this->requireSuperAdmin();

        $errors = $this->required(
            ['name', 'email', 'password', 'role'],
            ['name' => 'Nama', 'email' => 'Email', 'password' => 'Kata sandi', 'role' => 'Peran']
        );

        $email = trim((string)($_POST['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid.';
        }
        if ($email !== '' && User::emailExists($email)) {
            $errors['email'] = 'Email sudah terdaftar.';
        }
        $password = (string)($_POST['password'] ?? '');
        if ($password !== '' && strlen($password) < 6) {
            $errors['password'] = 'Kata sandi minimal 6 karakter.';
        }
        $role = (string)($_POST['role'] ?? '');
        if ($role !== '' && !in_array($role, ['super_admin', 'anggota'], true)) {
            $errors['role'] = 'Peran tidak dikenal.';
        }

        if ($errors) {
            $this->keepOld(['name', 'email', 'role', 'nip', 'phone', 'unit_id']);
            flash('danger', 'Periksa kembali isian formulir.');
            $this->view('users/form', [
                'title'  => 'Tambah Pengguna',
                'user'   => null,
                'units'  => Unit::active(),
                'errors' => $errors,
            ]);
        }

        $id = User::create([
            'unit_id'   => ((int)($_POST['unit_id'] ?? 0)) ?: null,
            'name'      => trim((string)$_POST['name']),
            'email'     => $email,
            'password'  => password_hash($password, PASSWORD_DEFAULT),
            'role'      => $role,
            'nip'       => trim((string)($_POST['nip'] ?? '')) ?: null,
            'phone'     => trim((string)($_POST['phone'] ?? '')) ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ]);

        audit('create', 'users', $id, ['name' => $_POST['name'], 'role' => $role]);
        old_clear();
        flash('success', 'Pengguna berhasil ditambahkan.');
        $this->redirect('/users');
    }

    public function edit(string $id): void
    {
        $this->requireSuperAdmin();
        $user = User::find((int)$id);
        if (!$user) {
            $this->notFound('Pengguna tidak ditemukan.');
        }

        $this->view('users/form', [
            'title'  => 'Ubah Pengguna',
            'user'   => $user,
            'units'  => Unit::active(),
            'errors' => [],
        ]);
    }

    public function update(string $id): void
    {
        $this->requireSuperAdmin();
        $user = User::find((int)$id);
        if (!$user) {
            $this->notFound('Pengguna tidak ditemukan.');
        }

        $errors = $this->required(
            ['name', 'email', 'role'],
            ['name' => 'Nama', 'email' => 'Email', 'role' => 'Peran']
        );

        $email = trim((string)($_POST['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid.';
        }
        if ($email !== '' && User::emailExists($email, (int)$id)) {
            $errors['email'] = 'Email sudah dipakai pengguna lain.';
        }

        $password = (string)($_POST['password'] ?? '');
        if ($password !== '' && strlen($password) < 6) {
            $errors['password'] = 'Kata sandi minimal 6 karakter.';
        }

        // Tidak boleh menonaktifkan / menurunkan peran akunnya sendiri.
        if ((int)$id === (int)Auth::id()) {
            if (!isset($_POST['is_active'])) {
                $errors['is_active'] = 'Anda tidak dapat menonaktifkan akun sendiri.';
            }
            if (($_POST['role'] ?? '') !== 'super_admin') {
                $errors['role'] = 'Anda tidak dapat mengubah peran akun sendiri.';
            }
        }

        if ($errors) {
            $this->keepOld(['name', 'email', 'role', 'nip', 'phone', 'unit_id']);
            flash('danger', 'Periksa kembali isian formulir.');
            $this->view('users/form', [
                'title'  => 'Ubah Pengguna',
                'user'   => $user,
                'units'  => Unit::active(),
                'errors' => $errors,
            ]);
        }

        $data = [
            'unit_id'   => ((int)($_POST['unit_id'] ?? 0)) ?: null,
            'name'      => trim((string)$_POST['name']),
            'email'     => $email,
            'role'      => (string)$_POST['role'],
            'nip'       => trim((string)($_POST['nip'] ?? '')) ?: null,
            'phone'     => trim((string)($_POST['phone'] ?? '')) ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        User::updateById((int)$id, $data);
        audit('update', 'users', (int)$id, ['email' => $email, 'role' => $data['role']]);
        old_clear();
        flash('success', 'Data pengguna diperbarui.');
        $this->redirect('/users');
    }

    public function destroy(string $id): void
    {
        $this->requireSuperAdmin();
        $user = User::find((int)$id);
        if (!$user) {
            $this->notFound('Pengguna tidak ditemukan.');
        }
        if ((int)$id === (int)Auth::id()) {
            flash('danger', 'Anda tidak dapat menghapus akun sendiri.');
            $this->redirect('/users');
        }

        User::deleteById((int)$id);
        audit('delete', 'users', (int)$id, ['email' => $user['email']]);
        flash('success', 'Pengguna dihapus.');
        $this->redirect('/users');
    }
}
