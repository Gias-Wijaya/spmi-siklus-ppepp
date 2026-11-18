<?php /** @var string $title */ /** @var string $message */ ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Kesalahan') ?></title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2032%2032'%3E%3Cpath%20d='M16%202%204%207v9c0%207.5%205.1%2012.6%2012%2014%206.9-1.4%2012-6.5%2012-14V7L16%202z'%20fill='%230d6efd'/%3E%3Cpath%20d='m14%2021-5-5%201.8-1.8L14%2017.4l7.2-7.2L23%2012l-9%209z'%20fill='%23fff'/%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('/assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<div class="auth-shell">
    <div class="auth-card card text-center" style="max-width:430px">
        <div class="error-icon bg-primary-subtle text-primary"><i class="bi bi-exclamation-triangle"></i></div>
        <h1 class="h5"><?= e($title ?? 'Kesalahan') ?></h1>
        <p class="text-muted small mb-4"><?= e($message ?? '') ?></p>
        <a href="<?= e($home ?? url('/')) ?>" class="btn btn-primary w-100">
            <i class="bi bi-house me-1"></i>Kembali ke dasbor
        </a>
    </div>
</div>
</body>
</html>
