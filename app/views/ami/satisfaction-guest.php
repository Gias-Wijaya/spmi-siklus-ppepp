<?php
/**
 * Item 12 — Mode tamu: isi formulir kepuasan TANPA akun sistem
 * melalui tautan token /puas/{token} (mis. mahasiswa/pengunjung).
 * @var array $survey @var array $questions @var bool $done
 */
$enabled   = (int)$survey['guest_enabled'] === 1;
$open      = $survey['status'] === 'open';
$actionUrl = url('/puas/' . (string)$survey['guest_token']);
?>
<style>.auth-card { max-width: 680px; width: 100%; }</style>

<div class="mb-3">
    <h1 class="h5 mb-1"><i class="bi bi-person-plus me-1"></i>Isi Formulir Kepuasan — Mode Tamu</h1>
    <div class="text-muted small">
        <strong><?= e((string)$survey['name']) ?></strong> · responden <strong>Tamu</strong> (tanpa akun sistem) ·
        periode <?= $survey['start_date'] ? format_date($survey['start_date']) : '—' ?> –
        <?= $survey['end_date'] ? format_date($survey['end_date']) : '—' ?>
    </div>
</div>

<?php if (!$enabled): ?>
    <div class="alert alert-warning py-2">
        Tautan tamu sudah <strong>dinonaktifkan</strong> oleh LPM — formulir tidak dapat diisi.
    </div>
<?php elseif (!$open): ?>
    <div class="alert alert-warning py-2">
        Formulir <strong><?= e((string)$survey['name']) ?></strong> tidak sedang terbuka untuk pengisian.
    </div>
<?php elseif ($done): ?>
    <div class="alert alert-success py-2">
        Terima kasih — jawaban Anda sudah kami terima; formulir ini hanya dapat diisi sekali per perangkat.
    </div>
<?php else: ?>
    <?php
    $guest = true;
    include __DIR__ . '/satisfaction-fill-questions.php';
    ?>
    <div class="form-text">
        Jawaban dikirim sebagai responden <strong>Tamu</strong> — tercatat tanpa akun sistem.
    </div>
<?php endif; ?>
