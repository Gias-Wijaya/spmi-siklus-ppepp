<?php /** @var string $message */ ?>
<div class="text-center py-5">
    <div class="error-icon bg-primary-subtle text-primary"><i class="bi bi-shield-lock"></i></div>
    <h2 class="h4 mt-2">403 — Akses ditolak</h2>
    <p class="text-muted"><?= e($message ?? 'Halaman ini hanya tersedia bagi Super Admin (LPM).') ?></p>
    <a href="<?= e(url('/')) ?>" class="btn btn-primary"><i class="bi bi-house me-1"></i>Kembali ke dasbor</a>
</div>
