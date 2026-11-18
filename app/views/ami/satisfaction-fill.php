<?php
/**
 * M3 — Pengisian formulir kepuasan oleh anggota (FR-07): skala 1–5 / teks bebas.
 * @var array $survey @var array $questions @var array $errors
 */
?>

<div class="mb-3">
    <a class="small" href="<?= e(url('/ami/satisfaction')) ?>">← Kembali ke daftar formulir</a>
    <h1 class="h4 mt-1"><?= e((string)$survey['name']) ?></h1>
    <div class="text-muted small">
        Periode: <?= $survey['start_date'] ? format_date($survey['start_date']) : '—' ?> –
        <?= $survey['end_date'] ? format_date($survey['end_date']) : '—' ?> ·
        jawaban bersifat <strong>satu kali per responden</strong>.
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger py-2 small">
        <?php foreach ($errors as $e1): ?><?= e($e1) ?><br><?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('/ami/satisfaction/' . (int)$survey['id'] . '/fill')) ?>">
    <?= csrf_field() ?>

    <?php if (!$questions): ?>
        <div class="alert alert-info">Formulir belum memiliki pertanyaan.</div>
    <?php endif; ?>

    <?php foreach ($questions as $i => $q): ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="fw-semibold mb-2">
                    <?= $i + 1 ?>. <?= e((string)$q['question']) ?>
                    <?php if ($q['type'] === 'skala'): ?>
                        <span class="badge text-bg-light border ms-1">skala 1–5</span>
                    <?php endif; ?>
                </div>

                <?php if ($q['type'] === 'skala'): ?>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php for ($v = 1; $v <= 5; $v++): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="answers[<?= (int)$q['id'] ?>]"
                                       value="<?= $v ?>" id="q<?= (int)$q['id'] ?>_<?= $v ?>" required>
                                <label class="form-check-label small" for="q<?= (int)$q['id'] ?>_<?= $v ?>"><?= $v ?></label>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <div class="form-text">
                        1 = sangat kurang · 3 = cukup · 5 = sangat baik
                    </div>
                <?php else: ?>
                    <textarea name="answers[<?= (int)$q['id'] ?>]" rows="3" class="form-control"
                              placeholder="Tuliskan masukan Anda…"></textarea>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($questions): ?>
        <button class="btn btn-success" type="submit">Kirim jawaban</button>
        <a class="btn btn-outline-secondary" href="<?= e(url('/ami/satisfaction')) ?>">Batal</a>
    <?php endif; ?>
</form>
