<?php /** @var string $message */ ?>
<div class="text-center py-5">
    <div class="error-icon bg-warning-subtle text-warning"><i class="bi bi-search"></i></div>
    <h2 class="h4 mt-2">404 — Tidak ditemukan</h2>
    <p class="text-muted"><?= e($message ?? 'Data yang Anda cari tidak ditemukan.') ?></p>
    <a href="<?= e(url('/')) ?>" class="btn btn-primary"><i class="bi bi-house me-1"></i>Kembali ke dasbor</a>
</div>
