<?php
declare(strict_types=1);

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        $this->view('auth/login', [
            'title'  => 'Masuk',
            'layout' => 'layouts/auth',
        ]);
    }

    public function login(): void
    {
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            flash('danger', 'Email dan kata sandi wajib diisi.');
            $this->view('auth/login', [
                'title'  => 'Masuk',
                'layout' => 'layouts/auth',
                'email'  => $email,
            ]);
        }

        if (Auth::attempt($email, $password)) {
            audit('login', 'users', Auth::id());
            old_clear();
            $this->redirect('/');
        }

        flash('danger', 'Email atau kata sandi salah, atau akun tidak aktif.');
        $this->view('auth/login', [
            'title'  => 'Masuk',
            'layout' => 'layouts/auth',
            'email'  => $email,
        ]);
    }

    public function logout(): void
    {
        audit('logout', 'users', Auth::id());
        Auth::logout();
        $this->redirect('/login');
    }
}
