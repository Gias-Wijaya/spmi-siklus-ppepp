<?php
/**
 * Item 11 — blok pertanyaan formulir kepuasan: render semua jenis
 * (Skala 1–5 / Pilihan / Teks — isian singkat/feedback/saran).
 * Dipakai pengisian anggota (satisfaction-fill) dan mode tamu (satisfaction-guest).
 * @var array $questions @var string $actionUrl @var bool $guest
 */
?>
<form method="post" action="<?= e($actionUrl) ?>">
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
                    <?php elseif ($q['type'] === 'pilihan'): ?>
                        <span class="badge text-bg-light border ms-1">pilihan</span>
                    <?php else: ?>
                        <span class="badge text-bg-light border ms-1">Teks — isian singkat/feedback/saran</span>
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
                <?php elseif ($q['type'] === 'pilihan'): ?>
                    <!-- Item 11 — pilihan dirender dari daftar opsi pertanyaan (fallback Ya|Tidak). -->
                    <div class="d-flex gap-2 flex-wrap">
                        <?php foreach (SatisfactionQuestion::optionsOf($q) as $pi => $opt): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="answers[<?= (int)$q['id'] ?>]"
                                       value="<?= e($opt) ?>" id="q<?= (int)$q['id'] ?>_p<?= $pi ?>" required>
                                <label class="form-check-label small" for="q<?= (int)$q['id'] ?>_p<?= $pi ?>"><?= e($opt) ?></label>
                            </div>
                        <?php endforeach; ?>
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
        <?php if (empty($guest)): ?>
            <a class="btn btn-outline-secondary" href="<?= e(url('/ami/satisfaction')) ?>">Batal</a>
        <?php endif; ?>
    <?php endif; ?>
</form>
