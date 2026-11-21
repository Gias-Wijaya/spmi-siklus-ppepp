<?php
declare(strict_types=1);

/**
 * Uji iterasi 3 — Modul M2 Monev (FR-04, FR-05).
 * Jalankan: php test-monev.php  (server :8080 + MariaDB harus hidup)
 */

$base = 'http://127.0.0.1:8080';
$tmp  = __DIR__ . '/monev-test';
@mkdir($tmp, 0777, true);
$cookieA = $tmp . '/admin.txt';
$cookieM = $tmp . '/member.txt';
@unlink($cookieA);
@unlink($cookieM);

$pass = 0;
$fail = 0;

function ok(bool $cond, string $label): void
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "[OK]   $label\n";
    } else {
        $fail++;
        echo "[FAIL] $label\n";
    }
}

final class Client
{
    private $ch;
    private string $base;

    public function __construct(string $base, string $cookieFile)
    {
        $this->base = $base;
        $this->ch   = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_COOKIEFILE     => $cookieFile,
            CURLOPT_COOKIEJAR      => $cookieFile,
            CURLOPT_HEADER         => false,
        ]);
    }

    public function get(string $path): array
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->base . $path);
        // POSTFIELDS=null dulu, HTTPGET sesudahnya — urutan penting agar metode kembali GET.
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, null);
        curl_setopt($this->ch, CURLOPT_HTTPGET, true);
        $body = curl_exec($this->ch);
        return [
            'code' => (int)curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE),
            'body' => (string)$body,
            'url'  => (string)curl_getinfo($this->ch, CURLINFO_EFFECTIVE_URL),
        ];
    }

    /** Permintaan GET beserta blok header (untuk menguji Cache-Control: no-store). */
    public function getRaw(string $path): array
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->base . $path);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, null);
        curl_setopt($this->ch, CURLOPT_HTTPGET, true);
        curl_setopt($this->ch, CURLOPT_HEADER, true);
        $raw  = curl_exec($this->ch);
        $code = (int)curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE);
        $size = (int)curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
        curl_setopt($this->ch, CURLOPT_HEADER, false);

        return [
            'code'    => $code,
            'headers' => substr((string)$raw, 0, $size),
            'body'    => substr((string)$raw, $size),
            'url'     => (string)curl_getinfo($this->ch, CURLINFO_EFFECTIVE_URL),
        ];
    }

    public function post(string $path, array $fields = [], array $files = []): array
    {
        $data = $fields;
        foreach ($files as $name => $file) {
            $data[$name] = new CURLFile($file, 'application/pdf', basename($file));
        }
        curl_setopt($this->ch, CURLOPT_URL, $this->base . $path);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
        $body = curl_exec($this->ch);
        return [
            'code' => (int)curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE),
            'body' => (string)$body,
            'url'  => (string)curl_getinfo($this->ch, CURLINFO_EFFECTIVE_URL),
        ];
    }

    public function token(): string
    {
        // Halaman mana pun memuat token (form keluar di dasbor / form masuk).
        foreach (['/', '/login'] as $path) {
            $r = $this->get($path);
            if (preg_match('/name="_token" value="([^"]+)"/', $r['body'], $m)) {
                return $m[1];
            }
        }
        return '';
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }
}

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=spmi_stikom;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

function val(PDO $pdo, string $sql, array $p = []): mixed
{
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st->fetchColumn();
}

function row(PDO $pdo, string $sql, array $p = []): ?array
{
    $st = $pdo->prepare($sql);
    $st->execute($p);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r === false ? null : $r;
}

$bukti = $tmp . '/bukti-uji.pdf';
file_put_contents($bukti, "%PDF-1.4\n% uji unggah bukti Monev\n%%EOF\n");

/** Catatan: curl PHP tidak meratakan array bersarang — kunci dibuat literal (values[1]). */
function flat(string $prefix, array $values): array
{
    $out = [];
    foreach ($values as $k => $v) {
        $out[$prefix . '[' . $k . ']'] = $v;
    }
    return $out;
}

$admin  = new Client($base, $cookieA);
$member = new Client($base, $cookieM);

echo "=== A. Masuk sebagai Super Admin (LPM) ===\n";
$r = $admin->post('/login', ['_token' => $admin->token(), 'email' => 'admin@stikomelrahma.ac.id', 'password' => 'admin123']);
ok(str_contains($r['url'], '/'), 'login admin berhasil (diarahkan ke dasbor)');
ok(str_contains($r['body'], 'Dasbor'), 'halaman dasbor tampil setelah login');

echo "\n=== B. Halaman & RBAC ===\n";
$r = $admin->get('/monev');
ok($r['code'] === 200 && str_contains($r['body'], 'Dasbor Monev'), 'dasbor Monev terbuka (FR-05)');
ok(str_contains($r['body'], 'Tren rata-rata capaian antarperiode'), 'panel grafik tren tampil');
ok(str_contains($r['body'], 'Kelola Indikator'), 'tombol "Kelola Indikator" permanen — tidak hanya saat indikator kosong (foto-13)');
ok(str_contains($r['body'], 'Rata-rata ('), 'label "Rata-rata (n unit)" pada tabel capaian indikator (foto-07)');
ok(str_contains($r['body'], 'Semua unit'), 'dropdown filter unit tersedia pada tabel capaian indikator (foto-07)');

$r = $admin->get('/monev/periods');
ok($r['code'] === 200 && str_contains($r['body'], 'Kelola Periode Monev'), 'halaman periode terbuka (Super Admin)');
ok(str_contains($r['body'], 'Monev 2026-1') && str_contains($r['body'], 'Monev 2026-2'), 'periode seed terdaftar');

$r = $admin->get('/monev/indicators');
ok($r['code'] === 200 && str_contains($r['body'], 'Kelola Indikator Monev'), 'halaman indikator terbuka (Super Admin)');

$r = $admin->post('/monev/periods', []); // tanpa CSRF
ok($r['code'] === 419, 'permintaan tanpa token CSRF ditolak (419)');

echo "\n=== C. Kelola periode ===\n";
$r = $admin->post('/monev/periods', [
    '_token'     => $admin->token(),
    'name'       => 'Monev 2026-3',
    'start_date' => '2026-07-01',
    'end_date'   => '2026-12-31',
]);
ok(str_contains($r['body'], 'berhasil dibuat'), 'periode baru dibuat');
$period3 = (int)val($pdo, 'SELECT id FROM monev_periods WHERE name = ?', ['Monev 2026-3']);
ok($period3 > 0, 'periode tersimpan di basis data');

$r = $admin->post('/monev/periods', [
    '_token'     => $admin->token(),
    'name'       => 'Monev 2026-3',
    'start_date' => '2026-07-01',
    'end_date'   => '2026-12-31',
]);
ok(str_contains($r['body'], 'sudah dipakai'), 'nama periode ganda ditolak');

$r = $admin->post('/monev/periods', [
    '_token'     => $admin->token(),
    'name'       => 'Monev 2026-4',
    'start_date' => '2027-01-01',
    'end_date'   => '2026-06-30',
]);
ok(str_contains($r['body'], 'tidak boleh sebelum'), 'rentang tanggal terbalik ditolak');

$r = $admin->post("/monev/periods/{$period3}/toggle", ['_token' => $admin->token()]);
ok(str_contains($r['body'], 'ditutup'), 'periode dapat ditutup');
ok(val($pdo, 'SELECT status FROM monev_periods WHERE id = ?', [$period3]) === 'closed', 'status tertutup tersimpan');
$r = $admin->post("/monev/periods/{$period3}/toggle", ['_token' => $admin->token()]);
ok(str_contains($r['body'], 'dibuka kembali'), 'periode dapat dibuka kembali');

echo "\n=== D. Kelola indikator ===\n";
$r = $admin->post('/monev/indicators', [
    '_token'         => $admin->token(),
    'code'           => 'IND-99',
    'name'           => 'Indikator uji coba',
    'standard_id'    => '1',
    'unit_of_measure'=> '%',
    'target'         => '50',
    'weight'         => '2',
    'is_active'      => '1',
]);
ok(str_contains($r['body'], 'berhasil ditambahkan'), 'indikator baru dibuat');
$ind99 = (int)val($pdo, 'SELECT id FROM monev_indicators WHERE code = ?', ['IND-99']);
ok($ind99 > 0, 'indikator tersimpan di basis data');

$r = $admin->get("/monev/indicators/{$ind99}/edit");
ok($r['code'] === 200 && str_contains($r['body'], 'Edit indikator'), 'formulir edit indikator terbuka');

$r = $admin->post("/monev/indicators/{$ind99}", [
    '_token'         => $admin->token(),
    'code'           => 'IND-99',
    'name'           => 'Indikator uji coba (diubah)',
    'standard_id'    => '1',
    'unit_of_measure'=> '%',
    'target'         => '60',
    'weight'         => '2',
    'is_active'      => '1',
]);
ok(str_contains($r['body'], 'berhasil diperbarui'), 'indikator diperbarui');
ok(val($pdo, 'SELECT target FROM monev_indicators WHERE id = ?', [$ind99]) === '60.00', 'perubahan target tersimpan');

$r = $admin->post('/monev/indicators', [
    '_token'      => $admin->token(),
    'code'        => 'M1.1', // sudah ada pada seed
    'name'        => 'Duplikat',
    'target'      => '10',
    'weight'      => '1',
    'is_active'   => '1',
]);
ok(str_contains($r['body'], 'sudah dipakai'), 'kode indikator ganda ditolak');

echo "\n=== E. Isian capaian unit (FR-04) — Super Admin ===\n";
$r = $admin->get('/monev/report?period_id=1&unit_id=1');
ok($r['code'] === 200 && str_contains($r['body'], 'Isi Capaian Monev'), 'formulir isian terbuka');
$r = $admin->getRaw('/monev/report?period_id=1&unit_id=1');
ok(str_contains($r['headers'], 'Cache-Control: no-store'), 'formulir Monev memakai Cache-Control: no-store (foto-14)');

$r = $admin->post('/monev/report', [
    '_token'    => $admin->token(),
    'period_id' => '1',
    'unit_id'   => '1',
] + flat('values', ['1' => '90', '2' => '80']) + flat('notes', ['1' => 'Data dari laporan triwulan']), ['evidence[1]' => $bukti]);
ok((bool)preg_match('#/monev/reports/(\d+)#', $r['url'], $m1), 'draf tersimpan → halaman detail laporan');
$adminReport = (int)($m1[1] ?? 0);
ok(str_contains($r['body'], 'Draf') || str_contains($r['body'], 'berhasil disimpan'), 'konfirmasi simpan draf tampil');

$sum = row($pdo, 'SELECT r.total_score,
                          (SELECT achievement_percent FROM monev_values WHERE report_id = r.id AND indicator_id = 1) AS ap1,
                          (SELECT achievement_percent FROM monev_values WHERE report_id = r.id AND indicator_id = 2) AS ap2
                   FROM monev_reports r WHERE r.id = ?', [$adminReport]);
ok($sum !== null && (float)$sum['ap1'] === 90.0, 'capaian indikator 1 = 90% (nilai 90 / target 100)');
ok($sum !== null && (float)$sum['ap2'] === 100.0, 'capaian indikator 2 = 100% (nilai 80 / target 80)');
ok($sum !== null && (float)$sum['total_score'] === 96.0, 'skor total tertimbang = 96% ((90×1 + 100×1,5) / 2,5)');

$ev = row($pdo, 'SELECT v.evidence_file FROM monev_values v WHERE v.report_id = ? AND v.indicator_id = 1', [$adminReport]);
ok($ev !== null && $ev['evidence_file'] !== null, 'bukti unggahan tersimpan');

// Pengajuan sebagian (hanya 2 indikator, tanpa bukti/catatan) wajib ditolak (foto-02).
$r = $admin->post('/monev/report', [
    '_token'    => $admin->token(),
    'period_id' => '1',
    'unit_id'   => '1',
    'submit'    => '1',
] + flat('values', ['1' => '90', '2' => '80']));
ok(str_contains($r['body'], 'wajib lengkap sebelum diajukan'),
    'pengajuan tanpa kelengkapan ditolak — setiap indikator wajib nilai+bukti+catatan (foto-02)');
ok(val($pdo, 'SELECT status FROM monev_reports WHERE id = ?', [$adminReport]) === 'draft',
    'laporan tetap draf setelah pengajuan ditolak');

// Pengajuan lengkap: seluruh indikator aktif (termasuk IND-99) + bukti + catatan.
$k = (string)$ind99;
$r = $admin->post('/monev/report', [
    '_token'    => $admin->token(),
    'period_id' => '1',
    'unit_id'   => '1',
    'submit'    => '1',
] + flat('values', ['1' => '90', '2' => '80', '3' => '90', '4' => '100', $k => '72'])
  + flat('notes', [
        '1' => 'Data dari laporan triwulan',
        '2' => 'Ketuntasan 80% tercapai',
        '3' => 'Dosen bergelar magister 90%',
        '4' => 'Laporan disetujui tepat waktu',
        $k  => 'Melebihi target — capaian dibatasi 100%',
    ]),
    ['evidence[1]' => $bukti, 'evidence[2]' => $bukti, 'evidence[3]' => $bukti,
     'evidence[4]' => $bukti, 'evidence[' . $ind99 . ']' => $bukti]);
ok(str_contains($r['body'], 'diajukan untuk direview'), 'laporan lengkap diajukan untuk review');
ok(val($pdo, 'SELECT status FROM monev_reports WHERE id = ?', [$adminReport]) === 'submitted', 'status = menunggu review');

// Cap 100% (foto-03): 90/80 = 112,5% dan 72/60 = 120% tidak boleh tampil sebagai capaian penuh.
$cap = row($pdo, 'SELECT r.total_score,
                          (SELECT achievement_percent FROM monev_values WHERE report_id = r.id AND indicator_id = 3) AS ap3,
                          (SELECT achievement_percent FROM monev_values WHERE report_id = r.id AND indicator_id = ?) AS apx
                   FROM monev_reports r WHERE r.id = ?', [$ind99, $adminReport]);
ok($cap !== null && (float)$cap['ap3'] === 100.0, 'capaian 112,5% (nilai 90 / target 80) dibatasi 100% (foto-03)');
ok($cap !== null && (float)$cap['apx'] === 100.0, 'capaian 120% (nilai 72 / target 60) dibatasi 100% — kasus persis review');
ok($cap !== null && (float)$cap['total_score'] === 98.46,
    'skor total tertimbang = 98,46% ((90+150+100+100+200) / 6,5) — tetap ≤ 100%');

echo "\n=== F. Unduh bukti ===\n";
$evId = (int)val($pdo, 'SELECT id FROM monev_values WHERE report_id = ? AND indicator_id = 1', [$adminReport]);
$r = $admin->get("/monev/evidence/{$evId}");
ok($r['code'] === 200 && str_contains($r['body'], 'PDF'), 'bukti dapat diunduh');

echo "\n=== G. Review LPM: setujui ===\n";
$r = $admin->post("/monev/reports/{$adminReport}/approve", ['_token' => $admin->token()]);
ok(str_contains($r['body'], 'Laporan Monev disetujui'), 'laporan disetujui');
ok(val($pdo, 'SELECT status FROM monev_reports WHERE id = ?', [$adminReport]) === 'approved', 'status = disetujui');

$r = $admin->post("/monev/reports/{$adminReport}/approve", ['_token' => $admin->token()]);
ok(str_contains($r['body'], 'menunggu review') || str_contains($r['body'], 'tidak diproses'), 'persetujuan ganda ditolak');

echo "\n=== H. Dasbor menampilkan skor ===\n";
$r = $admin->get('/monev?period_id=1');
ok(str_contains($r['body'], '98,5%'), 'skor 98,5% tampil di dasbor (hasil capaian dibatasi 100%)');
ok(str_contains($r['body'], 'Monev 2026-1'), 'periode seed tampil pada pemilih periode');
$r = $admin->getRaw('/monev/reports/' . $adminReport);
ok(str_contains($r['headers'], 'Cache-Control: no-store'), 'halaman detail laporan memakai Cache-Control: no-store (foto-14)');
$r = $admin->get('/monev?period_id=1&unit_id=2');
ok(str_contains($r['body'], 'Rata-rata (0 unit)'),
    'filter unit bekerja — unit yang belum mengisi menampilkan "Rata-rata (0 unit)" (foto-07)');

echo "\n=== I. RBAC anggota (Waket I, unit 2) ===\n";
$r = $member->post('/login', ['_token' => $member->token(), 'email' => 'waket1@stikomelrahma.ac.id', 'password' => 'waket123']);
ok(str_contains($r['url'], '/'), 'login anggota berhasil');

$r = $member->get('/monev');
ok($r['code'] === 200 && str_contains($r['body'], 'Dasbor Monev'), 'anggota dapat membuka dasbor Monev');

$r = $member->get('/monev/periods');
ok($r['code'] === 403, 'anggota ditolak di kelola periode (403)');
$r = $member->get('/monev/indicators');
ok($r['code'] === 403, 'anggota ditolak di kelola indikator (403)');
$r = $member->get('/monev/indicators/1/edit');
ok($r['code'] === 403, 'anggota ditolak di edit indikator (403)');
$r = $member->get('/monev/report?period_id=1&unit_id=1');
ok($r['code'] === 403, 'anggota ditolak mengisi Monev unit lain (403)');

$r = $member->get('/monev/report?period_id=1&unit_id=2');
ok($r['code'] === 200 && str_contains($r['body'], 'Isi Capaian Monev'), 'anggota dapat mengisi Monev unitnya sendiri');

$k = (string)$ind99;
$r = $member->post('/monev/report', [
    '_token'    => $member->token(),
    'period_id' => '1',
    'unit_id'   => '2',
    'submit'    => '1',
] + flat('values', ['1' => '100', '2' => '80', '3' => '80', '4' => '100', $k => '60'])
  + flat('notes', [
        '1' => 'Sesuai RPS berjalan',
        '2' => 'Ketuntasan 80% tercapai',
        '3' => 'Seluruh dosen bergelar magister',
        '4' => 'Laporan tepat waktu',
        $k  => 'Tepat pada target',
    ]),
    ['evidence[1]' => $bukti, 'evidence[2]' => $bukti, 'evidence[3]' => $bukti,
     'evidence[4]' => $bukti, 'evidence[' . $ind99 . ']' => $bukti]);
ok((bool)preg_match('#/monev/reports/(\d+)#', $r['url'], $m2), 'laporan anggota tersimpan & diajukan');
$memberReport = (int)($m2[1] ?? 0);
ok(val($pdo, 'SELECT status FROM monev_reports WHERE id = ?', [$memberReport]) === 'submitted', 'laporan anggota = menunggu review');
ok((float)val($pdo, 'SELECT total_score FROM monev_reports WHERE id = ?', [$memberReport]) === 100.0, 'skor anggota = 100% (semua indikator tepat target)');
ok((int)val($pdo, "SELECT COUNT(*) FROM notifications
                    WHERE ref_table = 'monev_reports' AND ref_id = ?
                      AND title = 'Laporan Monev menunggu review'", [$memberReport]) === 1,
    'notifikasi "menunggu review" tersimpan tepat 1 kali (foto-05)');

$r = $member->get("/monev/reports/{$adminReport}");
ok($r['code'] === 200 && str_contains($r['body'], 'Rincian capaian indikator'), 'anggota dapat melihat laporan lain (transparansi)');

$r = $member->post("/monev/reports/{$adminReport}/approve", ['_token' => $member->token()]);
ok($r['code'] === 403, 'anggota ditolak menyetujui laporan (403)');

echo "\n=== J. Pengembalian (review LPM) & notifikasi ===\n";
$r = $admin->post("/monev/reports/{$memberReport}/return", ['_token' => $admin->token(), 'comment' => '']);
ok(str_contains($r['body'], 'wajib diisi'), 'pengembalian tanpa catatan ditolak');
ok(val($pdo, 'SELECT status FROM monev_reports WHERE id = ?', [$memberReport]) === 'submitted', 'status tetap menunggu review setelah penolakan');

$r = $admin->post("/monev/reports/{$memberReport}/return", ['_token' => $admin->token(), 'comment' => 'Bukti pendukung belum lengkap, mohon dilengkapi.']);
ok(str_contains($r['body'], 'dikembalikan ke pengisi'), 'laporan dikembalikan dengan catatan');
ok(val($pdo, 'SELECT status FROM monev_reports WHERE id = ?', [$memberReport]) === 'draft', 'status kembali ke draf');
$notes = (string)val($pdo, 'SELECT notes FROM monev_reports WHERE id = ?', [$memberReport]);
ok(str_contains($notes, 'Bukti pendukung belum lengkap'), 'catatan pengembalian tersimpan pada laporan');

$r = $member->get('/notifications');
ok(str_contains($r['body'], 'dikembalikan'), 'notifikasi pengembalian sampai ke anggota');

$r = $member->get("/monev/reports/{$memberReport}");
ok(str_contains($r['body'], 'Edit / lengkapi isian'), 'anggota dapat mengubah laporan yang dikembalikan');

$r = $member->post('/monev/report', [
    '_token'    => $member->token(),
    'period_id' => '1',
    'unit_id'   => '2',
    'submit'    => '1',
] + flat('values', ['1' => '100', '2' => '80', '3' => '88', '4' => '100', (string)$ind99 => '60']));
ok(val($pdo, 'SELECT status FROM monev_reports WHERE id = ?', [$memberReport]) === 'submitted', 'perbaikan dapat diajukan ulang');
ok((int)val($pdo, "SELECT COUNT(*) FROM notifications
                    WHERE ref_table = 'monev_reports' AND ref_id = ?
                      AND title = 'Laporan Monev menunggu review'", [$memberReport]) === 1,
    'pengajuan ulang mengganti notifikasi lama — tetap 1 per laporan (foto-05)');
ok((float)val($pdo, 'SELECT total_score FROM monev_reports WHERE id = ?', [$memberReport]) === 100.0,
    'skor total tetap 100% — capaian indikator 3 (88/80 = 110%) dibatasi 100% (foto-03)');

echo "\n=== K. Periode tertutup mengunci isian ===\n";
$r = $admin->post("/monev/periods/{$period3}/toggle", ['_token' => $admin->token()]); // tutup
$r = $admin->get("/monev/report?period_id={$period3}&unit_id=1");
ok(str_contains($r['body'], 'sudah ditutup'), 'isian pada periode tertutup ditolak');
$r = $member->post('/monev/report', [
    '_token'    => $member->token(),
    'period_id' => (string)$period3,
    'unit_id'   => '2',
] + flat('values', ['1' => '50']));
ok(str_contains($r['body'], 'sudah ditutup'), 'simpan pada periode tertutup ditolak');

echo "\n=== L. Jejak audit ===\n";
$r = $admin->get('/audit');
ok(str_contains($r['body'], 'monev_reports'), 'aksi laporan Monev tercatat di jejak audit');
ok(str_contains($r['body'], 'monev_periods') || str_contains($r['body'], 'monev_indicators'), 'aksi admin periode/indikator tercatat');

echo "\n=== M. Laporan tidak ada ===\n";
$r = $admin->get('/monev/reports/999999');
ok($r['code'] === 404, 'laporan tidak ditemukan → 404');

echo "\n=== N. Sekop indikator per unit pengisi (masukan LPM foto-1) ===\n";
$r = $admin->post('/monev/indicators', [
    '_token'          => $admin->token(),
    'code'            => 'IND-U3',
    'name'            => 'Indikator uji bidang keuangan',
    'standard_id'     => '4',
    'unit_id'         => '3',
    'unit_of_measure' => '%',
    'target'          => '70',
    'weight'          => '1',
    'is_active'       => '1',
]);
ok(str_contains($r['body'], 'berhasil ditambahkan'), 'indikator dengan unit pengisi dibuat');
$admin->post('/monev/indicators', [
    '_token'          => $admin->token(),
    'code'            => 'IND-U2',
    'name'            => 'Indikator uji bidang akademik',
    'standard_id'     => '1',
    'unit_id'         => '2',
    'unit_of_measure' => '%',
    'target'          => '75',
    'weight'          => '1',
    'is_active'       => '1',
]);
$indU3 = (int)val($pdo, 'SELECT id FROM monev_indicators WHERE code = ?', ['IND-U3']);
$indU2 = (int)val($pdo, 'SELECT id FROM monev_indicators WHERE code = ?', ['IND-U2']);
ok($indU3 > 0 && $indU2 > 0, 'kedua indikator unit pengisi tersimpan');

$r = $admin->get('/monev/indicators');
ok(str_contains($r['body'], 'Unit pengisi') && str_contains($r['body'], 'Semua unit'),
    'halaman kelola menyediakan pilihan unit pengisi (foto-1)');
$r = $admin->get("/monev/indicators/{$indU3}/edit");
ok(str_contains($r['body'], 'value="3" selected'), 'form edit menampilkan unit pengisi terpilih');

$r = $member->get('/monev/report?period_id=1&unit_id=2');
ok($r['code'] === 200 && str_contains($r['body'], 'IND-U2'), 'indikator unit sendiri tampil pada isian anggota');
ok(!str_contains($r['body'], 'IND-U3') && !str_contains($r['body'], 'Indikator uji bidang keuangan'),
    'indikator unit lain TIDAK ikut tampil pada isian (foto-1)');

// Pengajuan — cakupan validasi sama dengan formulir: indikator unit lain
// tidak diwajibkan dan tidak menerima nilai.
$scoped = $pdo->query('SELECT id FROM monev_indicators WHERE is_active = 1 AND (unit_id IS NULL OR unit_id = 2)')
    ->fetchAll(PDO::FETCH_ASSOC);
$post  = ['_token' => $member->token(), 'period_id' => '1', 'unit_id' => '2', 'submit' => '1'];
$files = [];
foreach ($scoped as $sRow) {
    $sid = (int)$sRow['id'];
    $post["values[$sid]"]  = '5';
    $post["notes[$sid]"]   = 'Catatan uji sekop indikator';
    $files["evidence[$sid]"] = $bukti;
}
$r = $member->post('/monev/report', $post, $files);
ok(str_contains($r['body'], 'diajukan untuk direview'), 'pengajuan tanpa indikator unit lain tetap sah');
ok((int)val($pdo, 'SELECT COUNT(*) FROM monev_values WHERE indicator_id = ?', [$indU3]) === 0,
    'indikator unit lain tidak menerima nilai pada laporan anggota');

$pdo->exec("DELETE FROM monev_indicators WHERE code IN ('IND-U2', 'IND-U3')");
ok((int)val($pdo, 'SELECT COUNT(*) FROM monev_indicators WHERE code IN (?, ?)', ['IND-U2', 'IND-U3']) === 0,
    'indikator uji sekop dibersihkan');

echo "\n=== O. Pembersihan data uji ===\n";
$pdo->exec('DELETE FROM monev_values WHERE report_id IN (SELECT id FROM monev_reports WHERE period_id = 1)');
$pdo->exec('DELETE FROM monev_reports WHERE period_id = 1');
$pdo->exec('DELETE FROM notifications WHERE ref_table = "monev_reports"');
$pdo->exec("DELETE FROM monev_periods WHERE name = 'Monev 2026-3'");
$pdo->exec("DELETE FROM monev_indicators WHERE code = 'IND-99'");
$uploadDir = 'C:/xampp/htdocs/lpm/public/uploads/bukti-monev';
if (is_dir($uploadDir)) {
    array_map('unlink', glob($uploadDir . '/*/*/*') ?: []);
    array_map('rmdir', glob($uploadDir . '/*/*') ?: []);
    array_map('rmdir', glob($uploadDir . '/*') ?: []);
    @rmdir($uploadDir);
}
ok((int)val($pdo, 'SELECT COUNT(*) FROM monev_reports') === 0, 'data uji laporan dibersihkan');
ok((int)val($pdo, 'SELECT COUNT(*) FROM monev_indicators WHERE code = "IND-99"') === 0, 'indikator uji dihapus');
ok((int)val($pdo, 'SELECT COUNT(*) FROM monev_periods WHERE name = "Monev 2026-3"') === 0, 'periode uji dihapus');
ok(!is_dir($uploadDir), 'berkas bukti uji dihapus');

echo "\n========================================\n";
echo "LULUS: $pass · GAGAL: $fail\n";
exit($fail > 0 ? 1 : 0);
