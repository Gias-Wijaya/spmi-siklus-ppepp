<?php
/**
 * M3 — Kelola pertanyaan formulir kepuasan (Super Admin): tambah/ubah/hapus.
 * @var array $survey @var array $questions @var array $errors
 */
$locked = $survey['status'] === 'closed';
$typeLabel = static fn (string $t): string => match ($t) {
    'skala'   => 'Skala 1–5',
    'pilihan' => 'Pilihan',
    'teks'    => 'Teks — isian singkat/feedback/saran',
    default   => $t,
};
?>

<div class="page-head">
    <div class="page-desc">
        <i class="bi bi-question-circle me-1"></i>
        <strong>Pertanyaan: <?= e((string)$survey['name']) ?></strong>, status
        <?= e((string)$survey['status']) ?> · tiga jenis tersedia: <strong>skala 1–5</strong> dinilai &amp;
        diringkas otomatis; <strong>pilihan</strong> memilih salah satu opsi (mis. Ya|Tidak);
        <strong>Teks — isian singkat/feedback/saran</strong> dikumpulkan sebagai masukan kualitatif.
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('/ami/satisfaction')) ?>">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar formulir
        </a>
    </div>
</div>

<?php if ($locked): ?>
    <div class="alert alert-warning py-2">Formulir sudah <strong>ditutup</strong> — pertanyaan terkunci agar jawaban responden tidak berubah.</div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger py-2 small">
        <?php foreach ($errors as $e1): ?><?= e($e1) ?><br><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white"><strong>Tambah pertanyaan</strong></div>
            <div class="card-body">
                <?php if ($locked): ?>
                    <p class="text-muted small mb-0">Formulir terkunci.</p>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/ami/satisfaction/' . (int)$survey['id'] . '/questions')) ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label">Uraian pertanyaan</label>
                            <textarea name="question" rows="3" class="form-control" required
                                      placeholder="Seberapa puas Anda terhadap kecepatan layanan LPM?"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="form-label">Jenis</label>
                                <!-- Item 11 — tiga jenis lengkap dengan label jelas. -->
                                <select name="type" class="form-select">
                                    <option value="skala">Skala 1–5</option>
                                    <option value="pilihan">Pilihan</option>
                                    <option value="teks">Teks — isian singkat/feedback/saran</option>
                                </select>
                            </div>
                            <div class="col-3 mb-2">
                                <label class="form-label">Bobot</label>
                                <input type="number" step="0.5" min="0.5" name="weight" class="form-control" value="1">
                            </div>
                            <div class="col-3 mb-2">
                                <label class="form-label">Urutan</label>
                                <input type="number" name="sort_order" class="form-control" value="<?= (int)count($questions) ?>">
                            </div>
                        </div>
                        <div class="mb-2" id="optsBox" style="display:none">
                            <label class="form-label">Opsi pilihan</label>
                            <input type="text" name="options" class="form-control" value="Ya|Tidak" placeholder="Ya|Tidak">
                            <div class="form-text">Pisahkan tiap opsi dengan <code>|</code> — minimal 2 opsi (khusus jenis Pilihan).</div>
                        </div>
                        <button class="btn btn-primary w-100 mt-1" type="submit">Tambah</button>
                    </form>
                    <script>
                        (function () {
                            var box = document.getElementById('optsBox');
                            if (!box) return;
                            var sync = function (sel) { box.style.display = sel && sel.value === 'pilihan' ? '' : 'none'; };
                            var card = box.closest('form');
                            var sel = card ? card.querySelector('select[name="type"]') : null;
                            if (sel) {
                                sel.addEventListener('change', function () { sync(sel); });
                                sync(sel);
                            }
                        })();
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header bg-white"><strong>Daftar pertanyaan (<?= count($questions) ?>)</strong></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th style="width:50px">Urut</th>
                        <th>Pertanyaan</th>
                        <th style="width:110px">Jenis</th>
                        <th style="width:70px" class="text-center">Bobot</th>
                        <th class="text-center" style="width:80px">Jawaban</th>
                        <th class="text-end" style="width:200px">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$questions): ?>
                        <tr><td colspan="6" class="text-muted">Belum ada pertanyaan — tambahkan pada formulir sebelah.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($questions as $q): ?>
                        <tr class="<?= (int)$q['is_active'] === 0 ? 'table-secondary' : '' ?>">
                            <td colspan="6" class="p-2">
                                <form method="post" action="<?= e(url('/ami/questions/' . (int)$q['id'])) ?>" class="row g-2 align-items-center">
                                    <?= csrf_field() ?>
                                    <div class="col-1">
                                        <input type="number" name="sort_order" class="form-control form-control-sm" value="<?= (int)$q['sort_order'] ?>">
                                    </div>
                                    <div class="col-5">
                                        <input type="text" name="question" class="form-control form-control-sm" value="<?= e((string)$q['question']) ?>"
                                               <?= $locked ? 'disabled' : '' ?>>
                                    </div>
                                    <div class="col-2">
                                        <select name="type" class="form-select form-select-sm" <?= $locked ? 'disabled' : '' ?>>
                                            <?php foreach (['skala', 'pilihan', 'teks'] as $t): ?>
                                                <option value="<?= $t ?>" <?= $q['type'] === $t ? 'selected' : '' ?>><?= e($typeLabel($t)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-1">
                                        <input type="number" step="0.5" min="0.5" name="weight" class="form-control form-control-sm"
                                               value="<?= e((string)$q['weight']) ?>" <?= $locked ? 'disabled' : '' ?>>
                                    </div>
                                    <div class="col-1 text-center small"><?= (int)$q['answer_count'] ?></div>
                                    <div class="col-2 text-end text-nowrap">
                                        <?php if (!$locked): ?>
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Simpan</button>
                                        <?php endif; ?>
                                        <?php if ((int)$q['is_active'] === 1): ?>
                                            <span class="badge text-bg-success">aktif</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">nonaktif</span>
                                        <?php endif; ?>
                                        <?php if (!$locked): ?>
                                            <button class="btn btn-sm btn-outline-danger" type="button"
                                                    data-confirm="Hapus pertanyaan ini?"
                                                    data-form-action="<?= e(url('/ami/questions/' . (int)$q['id'] . '/delete')) ?>">
                                                Hapus
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="form-text mt-2">
            Pertanyaan yang sudah memiliki jawaban tidak dapat dihapus — akan dinonaktifkan agar data responden tetap utuh.
        </div>
    </div>
</div>
