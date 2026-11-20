<?php
/**
 * M3 — Pengisian formulir kepuasan oleh anggota (FR-07): skala 1–5 / pilihan / teks
 * (Item 11 — render bersama lewat satisfaction-fill-questions).
 * @var array $survey @var array $questions @var array $errors
 */
?>

<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-emoji-smile me-1"></i>
        <strong><?= e((string)$survey['name']) ?></strong>, periode
        <?= $survey['start_date'] ? format_date($survey['start_date']) : '—' ?> –
        <?= $survey['end_date'] ? format_date($survey['end_date']) : '—' ?> ·
        jawaban bersifat <strong>satu kali per responden</strong>.
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('/ami/satisfaction')) ?>">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar formulir
        </a>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger py-2 small">
        <?php foreach ($errors as $e1): ?><?= e($e1) ?><br><?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
$actionUrl = url('/ami/satisfaction/' . (int)$survey['id'] . '/fill');
$guest     = false;
include __DIR__ . '/satisfaction-fill-questions.php';
?>
