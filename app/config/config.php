<?php
declare(strict_types=1);

/**
 * Konfigurasi aplikasi — sesuaikan kredensial basis data dengan XAMPP Anda.
 */

$root = dirname(__DIR__, 2); // app/config -> app -> root proyek

return [
    'app_name'    => 'Sistem Informasi Penjaminan Mutu Internal',
    'app_tagline' => 'STIKOM El Rahma',

    // URL dasar otomatis: '/lpm/public' saat diakses via localhost/lpm/public
    'base_url' => rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/'),

    // --- Basis data (impor sistem/desain/03-database.sql terlebih dahulu) ---
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'spmi_stikom',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // --- Unggahan ---
    'upload_dir'    => $root . '/public/uploads',
    'upload_url'    => '/uploads',
    'upload_max_kb' => 5120, // 5 MB
    'upload_ext'    => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg'],

    // --- Aturan bisnis M1 ---
    'validity_warning_days' => 30, // ≤ 30 hari sebelum kedaluwarsa → status kuning
    'per_page'              => 10,
];
