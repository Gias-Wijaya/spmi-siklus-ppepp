<?php
/** @var string $content */
$logo = logo_file();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Masuk') ?> — <?= e(config('app_name')) ?></title>
    <?php if ($logo): ?>
        <link rel="icon" href="<?= e(url('/assets/img/' . $logo)) ?>">
    <?php else: ?>
        <link rel="icon" href="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%3E%3Cpath%20d='M16%202%204%207v9c0%207.5%205.1%2012.6%2012%2014%206.9-1.4%2012-6.5%2012-14V7L16%202z'%20fill='%230d6efd'/%3E%3Cpath%20d='m14%2021-5-5%201.8-1.8L14%2017.4l7.2-7.2L23%2012l-9%209z'%20fill='%23fff'/%3E%3C/svg%3E">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= e(url('/assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="auth-shell">
    <div class="auth-card card p-4">
        <div class="text-center mb-4">
            <?php if ($logo): ?>
                <img src="<?= e(url('/assets/img/' . $logo)) ?>" alt="" class="auth-brand-logo">
            <?php else: ?>
                <div class="auth-brand-icon"><i class="bi bi-shield-check"></i></div>
            <?php endif; ?>
            <h1 class="h5 mt-2 mb-0"><?= e(config('app_name')) ?></h1>
            <small class="text-muted"><?= e(config('app_tagline')) ?></small>
        </div>

        <?php $flashList = flashes(); ?>
        <?php if ($flashList): ?>
            <!-- Hasil aksi — dirender sebagai toast SweetAlert2 oleh skrip di bawah. -->
            <div id="swalFlash" hidden
                 data-flashes="<?= e(json_encode($flashList, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
                <?php foreach ($flashList as $f): ?>
                    <div class="alert alert-<?= e($f['type']) ?> py-2"><?= e($f['message']) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?= $content ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
(function () {
    var box = document.getElementById('swalFlash');
    if (!box) return;
    var show = function () { box.hidden = false; };
    try {
        var list = JSON.parse(box.getAttribute('data-flashes') || '[]');
        var icons = { success: 'success', danger: 'error', error: 'error', warning: 'warning', info: 'info' };
        if (window.Swal && list.length) {
            list.forEach(function (f, i) {
                setTimeout(function () {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: icons[f.type] || 'info',
                        title: f.message,
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    });
                }, 300 * i);
            });
        } else {
            show();
        }
    } catch (e) {
        show();
    }
})();
</script>
</body>
</html>
