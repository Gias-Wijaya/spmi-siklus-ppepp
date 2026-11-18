<?php
declare(strict_types=1);

/** Kontroler dasar: render tampilan, alihkan URL, penjaga hak akses. */
class Controller
{
    /**
     * Render tampilan ke dalam layout.
     * @param array<string,mixed> $data
     */
    protected function view(string $template, array $data = [], string $layout = 'layouts/app'): void
    {
        $file = BASE_PATH . '/app/views/' . $template . '.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('Tampilan tidak ditemukan: ' . e($template));
        }

        // Layout dapat ditentukan lewat data ('layout' => 'layouts/auth').
        $layout = (string)($data['layout'] ?? $layout);

        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        $content = ob_get_clean();

        $layoutFile = BASE_PATH . '/app/views/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            http_response_code(500);
            exit('Layout tidak ditemukan: ' . e($layout));
        }
        include $layoutFile;

        // Render selalu menjadi langkah terakhir sebuah aksi.
        exit;
    }

    protected function redirect(string $path): never
    {
        redirect($path);
    }

    /** Hanya Super Admin (LPM). */
    protected function requireSuperAdmin(): void
    {
        if (!Auth::isSuperAdmin()) {
            $this->forbidden();
        }
    }

    protected function forbidden(): never
    {
        http_response_code(403);
        $this->view('errors/403', ['title' => '403 — Akses ditolak']);
        exit;
    }

    protected function notFound(string $message = 'Data tidak ditemukan.'): never
    {
        http_response_code(404);
        $this->view('errors/404', ['title' => '404 — Tidak ditemukan', 'message' => $message]);
        exit;
    }

    /** Validasi minimal: semua kolom wajib terisi. @return array<string,string> */
    protected function required(array $fields, array $labels): array
    {
        $errors = [];
        foreach ($fields as $field) {
            $value = trim((string)($_POST[$field] ?? ''));
            if ($value === '') {
                $errors[$field] = ($labels[$field] ?? $field) . ' wajib diisi.';
            }
        }
        return $errors;
    }

    /** Simpan old input untuk mengisi ulang form. */
    protected function keepOld(array $fields): void
    {
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = (string)($_POST[$field] ?? '');
        }
        old_store($data);
    }
}
