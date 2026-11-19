<?php /** @var string|null $email */ ?>
<form method="post" action="<?= e(url('/login')) ?>">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input class="form-control" type="email" id="email" name="email"
                   value="<?= e($email ?? '') ?>" placeholder="nama@stikomelrahma.ac.id" required autofocus>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label" for="password">Kata sandi</label>
        <div class="password-wrap">
            <input class="form-control" type="password" id="password" name="password"
                   placeholder="Masukkan kata sandi" required>
            <button class="password-toggle" type="button" id="passwordToggle"
                    aria-label="Tampilkan kata sandi" title="Tampilkan kata sandi">
                <i class="bi bi-eye"></i>
            </button>
        </div>
    </div>

    <button class="btn btn-primary w-100 py-2" type="submit">
        <i class="bi bi-box-arrow-in-right me-1"></i>Masuk
    </button>
</form>

<div class="text-center text-muted small mt-3 mb-1">
    LPM = Super Admin · Waket I–III = Anggota
</div>

<script>
(function () {
    var btn = document.getElementById('passwordToggle');
    var input = document.getElementById('password');
    if (!btn || !input) return;
    btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        btn.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    });
})();
</script>
