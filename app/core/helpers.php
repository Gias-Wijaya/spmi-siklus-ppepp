<?php
declare(strict_types=1);

/** Ambil seluruh konfigurasi atau satu kunci. */
function config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require BASE_PATH . '/app/config/config.php';
    }
    if ($key === null) {
        return $config;
    }
    return $config[$key] ?? $default;
}

/** Bangun URL absolut relatif terhadap document root aplikasi. */
function url(string $path = ''): string
{
    return config('base_url') . '/' . ltrim($path, '/');
}

/**
 * Cari berkas logo institusi di public/assets/img/ (urut prioritas).
 * Return nama berkas (mis. "logo.png") atau null bila belum ada.
 */
function logo_file(): ?string
{
    foreach (['logo.svg', 'logo.png', 'logo.webp', 'logo.jpg', 'logo.jpeg'] as $name) {
        if (is_file(dirname(__DIR__, 2) . '/public/assets/img/' . $name)) {
            return $name;
        }
    }
    return null;
}

/** Escape output (XSS). */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Alihkan ke URL internal lalu hentikan eksekusi. */
function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/* ------------------------------------------------------------------ CSRF */

function csrf_token(): string
{
    if (empty($_SESSION['_token'])) {
        $_SESSION['_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $token = (string)($_POST['_token'] ?? '');
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Token keamanan kedaluwarsa. Silakan muat ulang halaman dan coba lagi.');
    }
}

/* ------------------------------------------------------------------ Flash & old input */

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** Ambil (sekaligus kosongkan) daftar pesan flash. */
function flashes(): array
{
    $all = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $all;
}

function old(string $key, string $default = ''): string
{
    return (string)($_SESSION['_old'][$key] ?? $default);
}

function old_store(array $data): void
{
    $_SESSION['_old'] = $data;
}

function old_clear(): void
{
    unset($_SESSION['_old']);
}

/* ------------------------------------------------------------------ Audit & notifikasi */

function audit(string $action, ?string $table = null, ?int $rowId = null, array|string|null $detail = null): void
{
    try {
        Database::insert('audit_logs', [
            'user_id'    => Auth::id(),
            'action'     => $action,
            'table_name' => $table,
            'row_id'     => $rowId,
            'detail'     => is_array($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE) : $detail,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) {
        // kegagalan audit tidak boleh menghentikan alur utama
    }
}

/* ------------------------------------------------------------------ Format */

function format_date(?string $date, string $format = 'd M Y'): string
{
    if ($date === null || $date === '') {
        return '-';
    }
    $ts = strtotime($date);
    return $ts === false ? '-' : date($format, $ts);
}

/** Sisa hari sampai masa berlaku habis; null bila tanpa tanggal. */
function days_to_expiry(?string $expiry): ?int
{
    if ($expiry === null || $expiry === '') {
        return null;
    }
    return (int)floor((strtotime($expiry) - strtotime('today')) / 86400);
}

function status_badge(string $color): string
{
    return match ($color) {
        'green'  => '<span class="badge text-bg-success">Hijau · berlaku</span>',
        'yellow' => '<span class="badge text-bg-warning">Kuning · perlu revisi</span>',
        'red'    => '<span class="badge text-bg-danger">Merah · tidak berlaku</span>',
        default  => '<span class="badge text-bg-secondary">-</span>',
    };
}

/** Status laporan Monev. */
function monev_status_badge(string $status): string
{
    return match ($status) {
        'draft'     => '<span class="badge text-bg-secondary">Draf</span>',
        'submitted' => '<span class="badge text-bg-info">Menunggu review</span>',
        'approved'  => '<span class="badge text-bg-success">Disetujui</span>',
        default     => '<span class="badge text-bg-secondary">' . e($status) . '</span>',
    };
}

/**
 * Lampu capaian Monev: ≥100 hijau (target tercapai), ≥80 kuning, <80 merah.
 * Aturan yang sama dipakai pada tingkat indikator maupun skor laporan.
 */
function capaian_badge(?float $ap): string
{
    if ($ap === null) {
        return '<span class="badge text-bg-secondary">belum diisi</span>';
    }
    $text = number_format($ap, 1, ',', '.') . '%';
    if ($ap >= 100) {
        return '<span class="badge text-bg-success">' . $text . ' · hijau</span>';
    }
    if ($ap >= 80) {
        return '<span class="badge text-bg-warning">' . $text . ' · kuning</span>';
    }
    return '<span class="badge text-bg-danger">' . $text . ' · merah</span>';
}

/**
 * Skor AMI (rerata tertimbang 0–4): ≥3,0 hijau (setara "memenuhi"),
 * ≥2,0 kuning ("sebagian memenuhi"), <2,0 merah.
 */
function ami_score_badge(?float $score): string
{
    if ($score === null) {
        return '<span class="badge text-bg-secondary">belum dinilai</span>';
    }
    $text = number_format($score, 2, ',', '.') . '/4';
    if ($score >= 3) {
        return '<span class="badge text-bg-success">' . $text . ' · hijau</span>';
    }
    if ($score >= 2) {
        return '<span class="badge text-bg-warning">' . $text . ' · kuning</span>';
    }
    return '<span class="badge text-bg-danger">' . $text . ' · merah</span>';
}

/** Kepuasan (rerata skala 1–5 dalam persen): ≥80 hijau, ≥60 kuning, <60 merah. */
function puas_badge(?float $percent): string
{
    if ($percent === null) {
        return '<span class="badge text-bg-secondary">belum ada jawaban</span>';
    }
    $text = number_format($percent, 1, ',', '.') . '%';
    if ($percent >= 80) {
        return '<span class="badge text-bg-success">' . $text . '</span>';
    }
    if ($percent >= 60) {
        return '<span class="badge text-bg-warning">' . $text . '</span>';
    }
    return '<span class="badge text-bg-danger">' . $text . '</span>';
}

/** Status penugasan AMI. */
function ami_assignment_badge(string $status): string
{
    return match ($status) {
        'assigned'    => '<span class="badge text-bg-secondary">belum mulai</span>',
        'in_progress' => '<span class="badge text-bg-info">berjalan</span>',
        'completed'   => '<span class="badge text-bg-success">penilaian lengkap</span>',
        default       => '<span class="badge text-bg-secondary">' . e($status) . '</span>',
    };
}

/** Status temuan AMI. */
function finding_badge(string $status): string
{
    return match ($status) {
        'open'        => '<span class="badge text-bg-danger">Terbuka</span>',
        'in_progress' => '<span class="badge text-bg-warning">Ditindaklanjuti</span>',
        'verified'    => '<span class="badge text-bg-info">Terverifikasi</span>',
        'closed'      => '<span class="badge text-bg-success">Selesai</span>',
        default       => '<span class="badge text-bg-secondary">' . e($status) . '</span>',
    };
}

/** Tingkat keparahan temuan AMI. */
function severity_badge(string $severity): string
{
    return match ($severity) {
        'tinggi' => '<span class="badge text-bg-danger">tinggi</span>',
        'sedang' => '<span class="badge text-bg-warning">sedang</span>',
        'rendah' => '<span class="badge text-bg-success">rendah</span>',
        default  => '<span class="badge text-bg-secondary">' . e($severity) . '</span>',
    };
}

/** Tipe temuan AMI. */
function finding_type_label(string $type): string
{
    return match ($type) {
        'ketidaksesuaian' => 'Ketidaksesuaian',
        'observasi'       => 'Observasi',
        'rekomendasi'     => 'Rekomendasi',
        default           => $type,
    };
}

/** Status rencana tindak lanjut (FR-10): terbuka → berjalan → selesai → terverifikasi. */
function action_status_badge(string $status): string
{
    return match ($status) {
        'open'        => '<span class="badge text-bg-secondary">Terbuka</span>',
        'in_progress' => '<span class="badge text-bg-warning">Berjalan</span>',
        'done'        => '<span class="badge text-bg-info">Selesai · menunggu verifikasi</span>',
        'verified'    => '<span class="badge text-bg-success">Terverifikasi</span>',
        default       => '<span class="badge text-bg-light border">' . e($status) . '</span>',
    };
}

/* ------------------------------------------------------------------ Unggahan */

/**
 * Unggah satu berkas.
 * @return array{path?:string,name?:string,error?:string}
 */
function upload_file(string $field, string $subdir): array
{
    if (empty($_FILES[$field]) || (int)$_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['error' => 'Berkas wajib diunggah.'];
    }

    $file = $_FILES[$field];

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Gagal mengunggah berkas (kode ' . (int)$file['error'] . ').'];
    }
    if ((int)$file['size'] > (int)config('upload_max_kb') * 1024) {
        return ['error' => 'Ukuran berkas melebihi batas ' . (int)config('upload_max_kb') . ' KB.'];
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, config('upload_ext'), true)) {
        return ['error' => 'Ekstensi .' . $ext . ' tidak diizinkan (yang diizinkan: '
            . implode(', ', config('upload_ext')) . ').'];
    }

    $relative = trim($subdir, '/') . '/' . date('Y/m') . '/';
    $dir      = rtrim((string)config('upload_dir'), '/') . '/' . $relative;

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return ['error' => 'Gagal membuat folder unggahan.'];
    }

    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file((string)$file['tmp_name'], $dir . $name)) {
        return ['error' => 'Gagal menyimpan berkas ke server.'];
    }

    return ['path' => $relative . $name, 'name' => (string)$file['name']];
}

/** Hapus berkas unggahan (diamankan terhadap path traversal). */
function delete_upload(?string $relative): void
{
    if (!$relative) {
        return;
    }
    $base = realpath(rtrim((string)config('upload_dir'), '/'));
    $full = realpath(rtrim((string)config('upload_dir'), '/') . '/' . ltrim($relative, '/'));
    if ($base && $full && str_starts_with($full, $base) && is_file($full)) {
        @unlink($full);
    }
}

/* ------------------------------------------------------------------ Paginasi */

function pagination(int $page, int $total, int $perPage, string $path, array $query = []): string
{
    $pages = (int)max(1, ceil($total / max(1, $perPage)));
    if ($pages <= 1) {
        return '';
    }

    $link = static function (int $p) use ($path, $query): string {
        $q = array_filter($query, static fn ($v) => $v !== '' && $v !== null);
        $q['page'] = $p;
        return url($path) . '?' . http_build_query($q);
    };

    $html  = '<nav><ul class="pagination pagination-sm mb-0">';
    $html .= '<li class="page-item' . ($page <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . e($link(max(1, $page - 1))) . '">&laquo;</a></li>';

    $start = max(1, $page - 2);
    $end   = min($pages, $page + 2);
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<li class="page-item' . ($i === $page ? ' active' : '') . '"><a class="page-link" href="' . e($link($i)) . '">' . $i . '</a></li>';
    }

    $html .= '<li class="page-item' . ($page >= $pages ? ' disabled' : '') . '"><a class="page-link" href="' . e($link(min($pages, $page + 1))) . '">&raquo;</a></li>';
    $html .= '</ul></nav>';

    return $html;
}

/* ------------------------------------------------------------------ Ekspor (FR-14) */

/**
 * Kirim satu berkas CSV siap unduh (FR-14) lalu hentikan eksekusi.
 *
 * Kompatibel Excel/LibreOffice berbahasa Indonesia:
 *  - BOM UTF-8 di awal agar karakter Indonesia terbaca benar;
 *  - pemisah titik koma (pemisah daftar Excel lokasi id-ID);
 *  - tanda kutip ganda (RFC 4180) untuk sel berisi pemisah, kutip, atau baris baru.
 *
 * @param array<int, array<int, scalar|null>> $lines seluruh baris berurutan
 *                                                   (judul kolom, data, blok kosong)
 */
function export_csv(string $filename, array $lines): never
{
    // Pastikan tidak ada buffer output yang mengotori berkas.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8

    foreach ($lines as $fields) {
        $cells = [];
        foreach ((array)$fields as $field) {
            $v = (string)($field ?? '');
            if (strpbrk($v, ";\"\r\n") !== false) {
                $v = '"' . str_replace('"', '""', $v) . '"';
            }
            $cells[] = $v;
        }
        fwrite($out, implode(';', $cells) . "\r\n");
    }

    fclose($out);
    exit;
}
