<?php
declare(strict_types=1);

/**
 * Router untuk server bawaan PHP (pengembangan lokal saja).
 *
 *   php -S 127.0.0.1:8080 -t public public/router.php
 *
 * Di produksi gunakan Apache XAMPP + public/.htaccess (tanpa file ini).
 */

$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = __DIR__ . $path;

// Biarkan server yang menyajikan berkas statis (css, js, unggahan).
if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
