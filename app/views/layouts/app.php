<?php
/** @var string $content */
$currentUser = Auth::user();
$unread      = $currentUser ? Notification::unreadCount((int)$currentUser['id']) : 0;

$uriPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$base    = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
if ($base !== '' && str_starts_with($uriPath, $base)) {
    $uriPath = substr($uriPath, strlen($base));
}
$current = ($uriPath === '' ? '/' : rtrim($uriPath, '/')) ?: '/';

$active = static function (string $path) use ($current): string {
    if ($path === '/') {
        return $current === '/' ? ' active' : '';
    }
    return str_starts_with($current, $path) ? ' active' : '';
};

$initial = mb_strtoupper(mb_substr((string)($currentUser['name'] ?? '?'), 0, 1));
$logo    = logo_file();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Dasbor') ?> — <?= e(config('app_name')) ?></title>
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
<div class="d-flex">

    <!-- ================================================= Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <?php if ($logo): ?>
                <img src="<?= e(url('/assets/img/' . $logo)) ?>" alt="" class="brand-logo">
            <?php else: ?>
                <i class="bi bi-shield-check me-1"></i>
            <?php endif; ?>SPMI Terpadu
            <small><?= e(config('app_tagline')) ?></small>
        </div>

        <nav class="flex-grow-1">
            <a class="nav-item<?= $active('/') ?>" href="<?= e(url('/')) ?>">
                <i class="bi bi-speedometer2"></i><span>Dasbor</span>
            </a>

            <div class="nav-section">Penjaminan Mutu</div>
            <a class="nav-item<?= $active('/documents') ?>" href="<?= e(url('/documents')) ?>">
                <i class="bi bi-file-earmark-text"></i><span>Dokumen SPMI</span>
            </a>
            <a class="nav-item<?= $active('/monev') ?>" href="<?= e(url('/monev')) ?>">
                <i class="bi bi-graph-up"></i><span>Monev</span>
            </a>
            <a class="nav-item<?= $active('/ami') ?>" href="<?= e(url('/ami')) ?>">
                <i class="bi bi-clipboard2-check"></i><span>AMI &amp; Evaluasi</span>
            </a>
            <a class="nav-item<?= $active('/followup') ?>" href="<?= e(url('/followup')) ?>">
                <i class="bi bi-gear-wide-connected"></i><span>Pengendalian–Peningkatan</span>
            </a>

            <a class="nav-item<?= $active('/notifications') ?>" href="<?= e(url('/notifications')) ?>">
                <i class="bi bi-bell"></i><span>Notifikasi</span>
                <?php if ($unread > 0): ?>
                    <span class="badge text-bg-danger"><?= $unread ?></span>
                <?php endif; ?>
            </a>

            <?php if (Auth::isSuperAdmin()): ?>
                <div class="nav-section">Administrasi (LPM)</div>
                <a class="nav-item<?= $active('/users') ?>" href="<?= e(url('/users')) ?>">
                    <i class="bi bi-people"></i><span>Pengguna</span>
                </a>
                <a class="nav-item<?= $active('/units') ?>" href="<?= e(url('/units')) ?>">
                    <i class="bi bi-buildings"></i><span>Unit Kerja</span>
                </a>
                <a class="nav-item<?= $active('/audit') ?>" href="<?= e(url('/audit')) ?>">
                    <i class="bi bi-clock-history"></i><span>Jejak Audit</span>
                </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <span class="avatar"><?= e($initial) ?></span>
            <div class="meta">
                <strong><?= e($currentUser['name'] ?? '-') ?></strong>
                <span><?= e($currentUser['unit_name'] ?? 'Tanpa unit') ?> ·
                    <?= Auth::isSuperAdmin() ? 'LPM (Super Admin)' : 'Anggota' ?></span>
            </div>
        </div>
    </aside>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- ================================================= Konten -->
    <div class="flex-grow-1 main">
        <header class="topbar d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 min-w-0">
                <button class="btn btn-outline-secondary" id="sidebarToggle" type="button"
                        aria-label="Buka menu navigasi" aria-controls="sidebar">
                    <i class="bi bi-list"></i>
                </button>
                <div class="min-w-0">
                    <h1 class="h5 mb-0 text-truncate"><?= e($title ?? 'Dasbor') ?></h1>
                    <small><?= e(config('app_name')) ?> · <?= e(config('app_tagline')) ?></small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="<?= e(url('/notifications')) ?>" class="btn btn-sm btn-outline-secondary position-relative"
                   aria-label="Notifikasi">
                    <i class="bi bi-bell"></i>
                    <?php if ($unread > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $unread ?></span>
                    <?php endif; ?>
                </a>
                <form method="post" action="<?= e(url('/logout')) ?>" class="mb-0">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-danger" type="submit">
                        <i class="bi bi-box-arrow-right me-1"></i>Keluar
                    </button>
                </form>
            </div>
        </header>

        <main class="p-4">
            <?php $flashList = flashes(); ?>
            <?php if ($flashList): ?>
                <!-- Hasil aksi — dirender sebagai toast SweetAlert2 oleh skrip di bawah;
                     div tersembunyi ini juga menjadi cadangan bila pustaka gagal dimuat. -->
                <div id="swalFlash" hidden
                     data-flashes="<?= e(json_encode($flashList, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
                    <?php foreach ($flashList as $f): ?>
                        <div class="alert alert-<?= e($f['type']) ?> py-2"><?= e($f['message']) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    var toggle = document.getElementById('sidebarToggle');

    function setSidebar(open) {
        if (!sidebar) return;
        sidebar.classList.toggle('show', open);
        if (backdrop) backdrop.classList.toggle('show', open);
        document.body.style.overflow = open ? 'hidden' : '';
    }
    if (toggle) toggle.addEventListener('click', function () {
        setSidebar(!sidebar.classList.contains('show'));
    });
    if (backdrop) backdrop.addEventListener('click', function () { setSidebar(false); });
    if (sidebar) sidebar.addEventListener('click', function (e) {
        if (e.target.closest('a')) setSidebar(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setSidebar(false);
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 768) setSidebar(false);
    });

    /* Cegah pengiriman ganda (double-submit) + beri umpan balik memuat. */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (e.defaultPrevented) return; /* validasi form sudah membatalkan kiriman */
        if (form.dataset.busy === '1') { e.preventDefault(); return; }
        var btn = form.querySelector('button[type="submit"]:not([data-nobusy])');
        if (!btn) return;
        form.dataset.busy = '1';
        btn.disabled = true;
        btn.dataset.label = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Memproses…';
    });

    /* ------------------------------------------ Notifikasi berbasis aksi (SweetAlert2)
       Setiap hasil aksi (simpan/hapus/verifikasi/…) tampil sebagai toast di kanan atas;
       alert bawaan halaman hanya dipakai sebagai cadangan bila pustaka gagal dimuat. */
    var flashBox = document.getElementById('swalFlash');
    if (flashBox) {
        var showFallback = function () { flashBox.hidden = false; };
        try {
            var flashList = JSON.parse(flashBox.getAttribute('data-flashes') || '[]');
            var iconMap = { success: 'success', danger: 'error', error: 'error', warning: 'warning', info: 'info' };
            if (window.Swal && flashList.length) {
                flashList.forEach(function (f, i) {
                    setTimeout(function () {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: iconMap[f.type] || 'info',
                            title: f.message,
                            showConfirmButton: false,
                            timer: 4000,
                            timerProgressBar: true
                        });
                    }, 300 * i);
                });
            } else {
                showFallback();
            }
        } catch (err) {
            showFallback();
        }
    }

    /* Konfirmasi aksi destruktif — menggantikan confirm() bawaan browser. */
    function askConfirm(message, onYes) {
        if (!window.Swal) { if (window.confirm(message)) onYes(); return; }
        Swal.fire({
            title: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, lanjutkan',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#2563eb',
            reverseButtons: true
        }).then(function (r) { if (r.isConfirmed) onYes(); });
    }

    document.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest('[data-confirm]') : null;
        if (!el || el.tagName === 'FORM') return;
        e.preventDefault();
        askConfirm(el.getAttribute('data-confirm'), function () {
            /* Tombol dengan aksi form khusus (mis. mengganti action lalu submit). */
            if (el.dataset.formAction) {
                var f = el.form || el.closest('form');
                if (f) { f.action = el.dataset.formAction; f.submit(); return; }
            }
            el.removeAttribute('data-confirm');
            if (el.tagName === 'A') { window.location.assign(el.href); return; }
            if (el.form) {
                if (typeof el.form.requestSubmit === 'function') { el.form.requestSubmit(el); }
                else { el.form.submit(); }
            } else {
                el.click();
            }
        });
    });
})();
</script>
</body>
</html>
