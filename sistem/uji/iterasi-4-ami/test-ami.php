<?php
declare(strict_types=1);

/**
 * Uji iterasi 4 — Modul M3 AMI & Evaluasi (FR-06, FR-07, FR-08).
 * Jalankan: php test-ami.php  (server :8080 + MariaDB harus hidup)
 *
 * Cakupan:
 *  A. Akses & RBAC halaman        F. Penilaian skor 0–4 + temuan otomatis
 *  B. Jadwal AMI (siklus status)   G. Kunci jadwal tertutup
 *  C. Instrumen fleksibel + jejak  H. Temuan: filter, manual, transisi status
 *  D. Penugasan auditor–auditee    I. Laporan & rekap kepuasan
 *  E. CSRF & 404                   J. Pembersihan data uji
 */

$base = 'http://127.0.0.1:8080';
$tmp  = __DIR__ . '/ami-test';
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

$bukti = $tmp . '/bukti-ami.pdf';
file_put_contents($bukti, "%PDF-1.4\n% uji unggah bukti AMI\n%%EOF\n");

/** Catatan: curl PHP tidak meratakan array bersarang — kunci dibuat literal (scores[1]). */
function flat(string $prefix, array $values): array
{
    $out = [];
    foreach ($values as $k => $v) {
        $out[$prefix . '[' . $k . ']'] = $v;
    }
    return $out;
}

// Pemanasan penjaga tenggat (FR-15): satu permintaan agar notifikasi tenggat
// data benih tercipta lebih dulu, sehingga jumlah notifikasi stabil berapa pun
// urutan suite dijalankan.
@unlink($tmp . '/warm.txt');
(new Client($base, $tmp . '/warm.txt'))->get('/login');

// Baseline data asli (tanpa data uji) untuk dipastikan kembali utuh setelah pembersihan.
$baseline = [];
foreach ([
    'ami_schedules', 'ami_instruments', 'ami_assignments', 'ami_assessments',
    'ami_findings', 'ami_instrument_logs',
    'satisfaction_surveys', 'satisfaction_questions', 'satisfaction_responses',
    'satisfaction_answers', 'notifications',
] as $t) {
    $baseline[$t] = (int)val($pdo, "SELECT COUNT(*) FROM {$t}");
}

$admin  = new Client($base, $cookieA);
$member = new Client($base, $cookieM);

echo "=== A. Masuk & akses dasbor M3 ===\n";
$r = $admin->post('/login', ['_token' => $admin->token(), 'email' => 'admin@stikomelrahma.ac.id', 'password' => 'admin123']);
ok(str_contains($r['url'], '/'), 'login Super Admin berhasil');
$r = $member->post('/login', ['_token' => $member->token(), 'email' => 'waket1@stikomelrahma.ac.id', 'password' => 'waket123']);
ok(str_contains($r['url'], '/'), 'login anggota berhasil');

$r = $admin->get('/ami');
ok($r['code'] === 200 && str_contains($r['body'], 'Progres penilaian'), 'dasbor AMI terbuka (FR-07/FR-08)');
ok(str_contains($r['body'], 'chartUnits') && str_contains($r['body'], 'chartStd'), 'grafik skor per unit & standar tampil');
ok(str_contains($r['body'], 'Formulir kepuasan'), 'ringkasan kepuasan tampil di dasbor');

$r = $member->get('/ami');
ok($r['code'] === 200, 'anggota dapat melihat dasbor AMI');

$r = $member->get('/ami/report');
ok($r['code'] === 403, 'anggota ditolak membuka laporan AMI (403)');
$r = $member->get('/ami/schedules');
ok($r['code'] === 403, 'anggota ditolak membuka jadwal AMI (403)');
$r = $member->get('/ami/instruments');
ok($r['code'] === 403, 'anggota ditolak membuka kelola instrumen (403)');
$r = $member->get('/ami/assignments');
ok($r['code'] === 403, 'anggota ditolak membuka kelola penugasan (403)');

echo "\n=== B. Jadwal AMI (siklus draft → berjalan → ditutup) ===\n";
$r = $admin->get('/ami/schedules');
ok($r['code'] === 200 && str_contains($r['body'], 'AMI 2026'), 'halaman jadwal terbuka, jadwal seed tampil');

$r = $admin->post('/ami/schedules', [
    '_token' => $admin->token(),
    'year'   => '2027',
    'name'   => 'AMI 2027-TEST',
    'start_date' => '2027-01-01',
    'end_date'   => '2027-06-30',
]);
ok(str_contains($r['body'], 'dibuat'), 'jadwal uji dibuat (status draf)');
$sid = (int)val($pdo, 'SELECT id FROM ami_schedules WHERE name = ?', ['AMI 2027-TEST']);
ok($sid > 0, 'jadwal tersimpan di basis data');
ok(val($pdo, 'SELECT status FROM ami_schedules WHERE id = ?', [$sid]) === 'draft', 'status awal draf');

$r = $admin->post('/ami/schedules', ['_token' => $admin->token(), 'year' => '2027', 'name' => 'AMI 2027-TEST']);
ok(str_contains($r['body'], 'sudah ada'), 'tahun ganda ditolak');
$r = $admin->post('/ami/schedules', ['_token' => $admin->token(), 'year' => '2027', 'name' => '']);
ok(str_contains($r['body'], 'wajib diisi'), 'nama kosong ditolak');

$r = $admin->post("/ami/schedules/{$sid}/toggle", ['_token' => $admin->token()]);
ok(str_contains($r['body'], 'dijalankan'), 'jadwal dapat dijalankan');
ok(val($pdo, 'SELECT status FROM ami_schedules WHERE id = ?', [$sid]) === 'running', 'status berjalan tersimpan');

echo "\n=== C. Instrumen fleksibel + jejak perubahan (FR-06, RM 3) ===\n";
$r = $admin->get("/ami/instruments?schedule_id={$sid}");
ok($r['code'] === 200 && str_contains($r['body'], 'Tambah indikator'), 'halaman instrumen terbuka');

$r = $admin->post('/ami/instruments', [
    '_token'       => $admin->token(),
    'schedule_id'  => (string)$sid,
    'code'         => 'TEST.1',
    'question'     => 'Indikator uji pertama: kelengkapan dokumen SPMI',
    'standard_id'  => '1',
    'weight'       => '1',
    'sort_order'   => '0',
    'is_active'    => '1',
]);
ok(str_contains($r['body'], 'ditambahkan'), 'indikator TEST.1 ditambahkan');
$t1 = (int)val($pdo, 'SELECT id FROM ami_instruments WHERE schedule_id = ? AND code = ?', [$sid, 'TEST.1']);
ok($t1 > 0, 'indikator TEST.1 tersimpan');

$r = $admin->post('/ami/instruments', [
    '_token'       => $admin->token(),
    'schedule_id'  => (string)$sid,
    'code'         => 'TEST.1',
    'question'     => 'Duplikat',
    'weight'       => '1',
]);
ok(str_contains($r['body'], 'sudah dipakai'), 'kode indikator ganda ditolak');

$r = $admin->post('/ami/instruments', [
    '_token'      => $admin->token(),
    'schedule_id' => (string)$sid,
    'code'        => 'TEST.2',
    'question'    => '',
    'weight'      => '2',
]);
ok(str_contains($r['body'], 'wajib diisi'), 'uraian kosong ditolak');

$r = $admin->post('/ami/instruments', [
    '_token'      => $admin->token(),
    'schedule_id' => (string)$sid,
    'code'        => 'TEST.DEL',
    'question'    => 'Indikator yang akan dihapus',
    'weight'      => '0',
]);
ok(str_contains($r['body'], 'lebih besar dari 0'), 'bobot nol ditolak');

// Indikator kedua (untuk penilaian) — bobot 2 agar skor tertimbang teruji.
$admin->post('/ami/instruments', [
    '_token'      => $admin->token(),
    'schedule_id' => (string)$sid,
    'code'        => 'TEST.2',
    'question'    => 'Indikator uji kedua: pelaksanaan monev berkala',
    'standard_id' => '1',
    'weight'      => '2',
    'sort_order'  => '1',
    'is_active'   => '1',
]);
$t2 = (int)val($pdo, 'SELECT id FROM ami_instruments WHERE schedule_id = ? AND code = ?', [$sid, 'TEST.2']);
ok($t2 > 0, 'indikator TEST.2 tersimpan');

// Indikator ketiga untuk uji hapus (tanpa nilai).
$admin->post('/ami/instruments', [
    '_token'      => $admin->token(),
    'schedule_id' => (string)$sid,
    'code'        => 'TEST.DEL',
    'question'    => 'Indikator yang akan dihapus',
    'weight'      => '1',
    'is_active'   => '1',
]);
$tdel = (int)val($pdo, 'SELECT id FROM ami_instruments WHERE schedule_id = ? AND code = ?', [$sid, 'TEST.DEL']);
ok($tdel > 0, 'indikator TEST.DEL tersimpan');

// Edit + alasan → jejak perubahan.
$r = $admin->get("/ami/instruments/{$t2}/edit");
ok($r['code'] === 200 && str_contains($r['body'], 'Edit instrumen'), 'form edit indikator terbuka');

$r = $admin->post("/ami/instruments/{$t2}", [
    '_token'            => $admin->token(),
    'code'              => 'TEST.2',
    'question'          => 'Indikator uji kedua: pelaksanaan monev berkala (revisi)',
    'assessment_guide'  => 'Periksa laporan monev dua periode',
    'standard_id'       => '1',
    'weight'            => '2',
    'sort_order'        => '1',
    'is_active'         => '1',
    'reason'            => 'penyesuaian istilah',
]);
ok(str_contains($r['body'], 'perubahan tercatat'), 'edit disimpan & disebutkan jejaknya');
ok((string)val($pdo, 'SELECT question FROM ami_instruments WHERE id = ?', [$t2]) === 'Indikator uji kedua: pelaksanaan monev berkala (revisi)', 'uraian indikator berubah di DB');
ok((int)val($pdo, 'SELECT COUNT(*) FROM ami_instrument_logs WHERE instrument_id = ? AND reason = ?', [$t2, 'penyesuaian istilah']) === 1, 'alasan perubahan tersimpan di jejak');
ok((int)val($pdo, 'SELECT COUNT(*) FROM ami_instrument_logs WHERE instrument_id = ?', [$t2]) === 1, 'tepat satu baris jejak perubahan untuk TEST.2');
$logs = (string)val($pdo, 'SELECT old_value FROM ami_instrument_logs WHERE instrument_id = ?', [$t2]);
ok(str_contains($logs, 'indikator'), 'jejak memuat nilai lama');
$logsNew = (string)val($pdo, 'SELECT new_value FROM ami_instrument_logs WHERE instrument_id = ?', [$t2]);
ok(str_contains($logsNew, '(revisi)'), 'jejak memuat nilai baru');

$r = $admin->get("/ami/instruments?schedule_id={$sid}");
ok(str_contains($r['body'], 'Jejak perubahan indikator'), 'daftar jejak perubahan tampil');
ok(str_contains($r['body'], 'penyesuaian istilah'), 'alasan perubahan tampil di daftar jejak');

// Nonaktifkan → aktifkan → hapus (tanpa nilai).
$r = $admin->post("/ami/instruments/{$tdel}/toggle", ['_token' => $admin->token()]);
ok(val($pdo, 'SELECT is_active FROM ami_instruments WHERE id = ?', [$tdel]) == 0, 'indikator dapat dinonaktifkan');
$r = $admin->post("/ami/instruments/{$tdel}/toggle", ['_token' => $admin->token()]);
ok(val($pdo, 'SELECT is_active FROM ami_instruments WHERE id = ?', [$tdel]) == 1, 'indikator dapat diaktifkan kembali');

$r = $admin->post("/ami/instruments/{$tdel}/delete", ['_token' => $admin->token()]);
ok(str_contains($r['body'], 'dihapus'), 'indikator tanpa nilai dapat dihapus');
ok((int)val($pdo, 'SELECT COUNT(*) FROM ami_instruments WHERE id = ?', [$tdel]) === 0, 'indikator TEST.DEL hilang dari DB');

$r = $member->post('/ami/instruments', ['_token' => $member->token(), 'schedule_id' => (string)$sid, 'code' => 'X', 'question' => 'x']);
ok($r['code'] === 403, 'anggota ditolak menambah instrumen (403)');
$r = $member->post("/ami/instruments/{$t1}", ['_token' => $member->token(), 'code' => 'TEST.1', 'question' => 'x', 'weight' => '1']);
ok($r['code'] === 403, 'anggota ditolak mengubah instrumen (403)');

echo "\n=== D. Penugasan auditor–auditee (FR-07) ===\n";
$r = $admin->get("/ami/assignments?schedule_id={$sid}");
ok($r['code'] === 200 && str_contains($r['body'], 'Tambah penugasan'), 'halaman penugasan terbuka');

$r = $admin->post('/ami/assignments', [
    '_token'      => $admin->token(),
    'schedule_id' => (string)$sid,
    'unit_id'     => '1',
    'auditor_user_id' => '1',
    'audit_date'  => '2027-03-01',
]);
ok(str_contains($r['body'], 'berhasil dibuat'), 'penugasan unit LPM dibuat');
$a1 = (int)val($pdo, 'SELECT id FROM ami_assignments WHERE schedule_id = ? AND unit_id = ?', [$sid, 1]);
ok($a1 > 0, 'penugasan unit 1 tersimpan');

$r = $admin->post('/ami/assignments', [
    '_token'      => $admin->token(),
    'schedule_id' => (string)$sid,
    'unit_id'     => '2',
    'auditor_user_id' => '2',
    'audit_date'  => '2027-03-02',
]);
ok(str_contains($r['body'], 'berhasil dibuat'), 'penugasan unit Bidang Akademik dibuat');
$a2 = (int)val($pdo, 'SELECT id FROM ami_assignments WHERE schedule_id = ? AND unit_id = ?', [$sid, 2]);
ok($a2 > 0, 'penugasan unit 2 tersimpan');
ok(val($pdo, 'SELECT auditor_user_id FROM ami_assignments WHERE id = ?', [$a2]) == 2, 'auditor = anggota (penugasan, bukan role)');

$r = $admin->post('/ami/assignments', [
    '_token'      => $admin->token(),
    'schedule_id' => (string)$sid,
    'unit_id'     => '2',
]);
ok(str_contains($r['body'], 'sudah ditugaskan'), 'unit ganda pada jadwal sama ditolak');

$r = $admin->get("/ami/assignments/{$a2}/edit");
ok($r['code'] === 200 && str_contains($r['body'], 'Edit penugasan'), 'form edit penugasan terbuka');
$r = $admin->post("/ami/assignments/{$a2}", [
    '_token'              => $admin->token(),
    'auditor_user_id'     => '2',
    'auditee_lead_user_id'=> '2',
    'audit_date'          => '2027-03-05',
    'report_number'       => 'LPM/AMI/2027/TEST',
]);
ok(str_contains($r['body'], 'diperbarui'), 'penugasan diperbarui');
ok((string)val($pdo, 'SELECT report_number FROM ami_assignments WHERE id = ?', [$a2]) === 'LPM/AMI/2027/TEST', 'nomor laporan tersimpan');

$r = $member->post('/ami/assignments', ['_token' => $member->token(), 'schedule_id' => (string)$sid, 'unit_id' => '3']);
ok($r['code'] === 403, 'anggota ditolak membuat penugasan (403)');

echo "\n=== E. CSRF & 404 ===\n";
$r = $admin->post('/ami/schedules', []); // tanpa token
ok($r['code'] === 419, 'permintaan tanpa token CSRF ditolak (419)');
$r = $admin->get('/ami/assignments/999999/assess');
ok($r['code'] === 404, 'penugasan tidak ada → 404');
$r = $admin->get('/ami/instruments/999999/edit');
ok($r['code'] === 404, 'instrumen tidak ada → 404');
$r = $admin->get('/ami/satisfaction/999999/fill');
ok($r['code'] === 404, 'formulir tidak ada → 404');
$r = $admin->get('/ami/evidence/999999');
ok($r['code'] === 404, 'bukti tidak ada → 404');

echo "\n=== F. Penilaian skor 0–4 + temuan otomatis (FR-07/FR-08) ===\n";
$r = $member->get("/ami/assignments/{$a2}/assess");
ok($r['code'] === 200 && str_contains($r['body'], 'Daftar indikator'), 'auditor membuka formulir penilaian');
ok(str_contains($r['body'], 'TEST.1') && str_contains($r['body'], 'TEST.2'), 'indikator aktif tampil pada formulir');

$r = $member->get("/ami/assignments/{$a1}/assess");
ok($r['code'] === 403, 'auditor unit lain ditolak membuka penilaian (403)');

// Skor tidak valid.
$r = $member->post("/ami/assignments/{$a2}/assess", [
    '_token' => $member->token(),
] + flat('scores', [$t1 => '9']));
ok(str_contains($r['body'], 'tidak valid'), 'skor di luar 0–4 ditolak');

// Skor ≤ 2 tanpa catatan temuan.
$r = $member->post("/ami/assignments/{$a2}/assess", [
    '_token' => $member->token(),
] + flat('scores', [$t2 => '2']));
ok(str_contains($r['body'], 'wajib disertai catatan temuan'), 'skor ≤ 2 tanpa catatan temuan ditolak');

// Penilaian sah: TEST.1=4 (bobot 1), TEST.2=1 (bobot 2) + bukti.
$r = $member->post("/ami/assignments/{$a2}/assess", [
    '_token' => $member->token(),
] + flat('scores', [$t1 => '4', $t2 => '1'])
  + flat('notes', [$t2 => 'SOP monev belum tersedia']), ['evidence[' . $t1 . ']' => $bukti]);
ok(str_contains($r['body'], '2 indikator dinilai'), 'penilaian 2 indikator tersimpan');
ok((int)val($pdo, 'SELECT COUNT(*) FROM ami_assessments WHERE assignment_id = ?', [$a2]) === 2, 'dua baris penilaian di DB');
ok(val($pdo, 'SELECT score FROM ami_assessments WHERE assignment_id = ? AND instrument_id = ?', [$a2, $t1]) == 4, 'skor TEST.1 = 4');
ok((string)val($pdo, 'SELECT evidence_file FROM ami_assessments WHERE assignment_id = ? AND instrument_id = ?', [$a2, $t1]) !== '', 'bukti TEST.1 terunggah');
ok(val($pdo, 'SELECT status FROM ami_assignments WHERE id = ?', [$a2]) === 'completed', 'status penugasan → penilaian lengkap');

// Temuan otomatis dari skor 1.
$fAuto = (int)val($pdo, 'SELECT id FROM ami_findings WHERE assessment_id IN (SELECT id FROM ami_assessments WHERE assignment_id = ? AND instrument_id = ?)', [$a2, $t2]);
ok($fAuto > 0, 'temuan otomatis terbentuk dari skor ≤ 2');
ok(val($pdo, 'SELECT severity FROM ami_findings WHERE id = ?', [$fAuto]) === 'tinggi', 'skor 1 → keparahan tinggi');
ok(val($pdo, 'SELECT status FROM ami_findings WHERE id = ?', [$fAuto]) === 'open', 'temuan otomatis berstatus terbuka');
ok(str_contains((string)val($pdo, 'SELECT description FROM ami_findings WHERE id = ?', [$fAuto]), 'SOP monev belum tersedia'), 'uraian temuan = catatan auditor');

// Unduh bukti.
$assessmentId = (int)val($pdo, 'SELECT id FROM ami_assessments WHERE assignment_id = ? AND instrument_id = ?', [$a2, $t1]);
$r = $admin->get("/ami/evidence/{$assessmentId}");
ok($r['code'] === 200 && str_contains($r['body'], 'uji unggah bukti AMI'), 'bukti dapat diunduh');

// Perbarui skor TEST.2 → 2 (temuan ikut diperbarui selama masih terbuka).
$r = $member->post("/ami/assignments/{$a2}/assess", [
    '_token' => $member->token(),
] + flat('scores', [$t2 => '2'])
  + flat('notes', [$t2 => 'Bukti sebagian — segera lengkapi']));
ok(val($pdo, 'SELECT score FROM ami_assessments WHERE assignment_id = ? AND instrument_id = ?', [$a2, $t2]) == 2, 'skor TEST.2 diperbarui ke 2');
ok((string)val($pdo, 'SELECT description FROM ami_findings WHERE id = ?', [$fAuto]) === 'Bukti sebagian — segera lengkapi', 'temuan mengikuti catatan terbaru');
ok(val($pdo, 'SELECT severity FROM ami_findings WHERE id = ?', [$fAuto]) === 'sedang', 'skor 2 → keparahan sedang');
$unit2 = row($pdo, 'SELECT ROUND(SUM(aa.score * i.weight) / SUM(i.weight), 2) AS s FROM ami_assessments aa JOIN ami_instruments i ON i.id = aa.instrument_id WHERE aa.assignment_id = ?', [$a2]);
ok((float)$unit2['s'] === 2.67, 'skor tertimbang unit 2 = (4×1 + 2×2)/3 = 2,67');

// Notifikasi ke LPM.
$r = $admin->get('/notifications');
ok(str_contains($r['body'], 'Temuan AMI baru dari penilaian'), 'notifikasi temuan baru sampai ke LPM');
ok((int)val($pdo, "SELECT COUNT(*) FROM notifications WHERE ref_table = 'ami_assignments' AND user_id = 1") > 0, 'notifikasi tersimpan untuk Super Admin');

// LPM menilai penugasannya sendiri (unit LPM).
$r = $admin->post("/ami/assignments/{$a1}/assess", [
    '_token' => $admin->token(),
] + flat('scores', [$t1 => '3', $t2 => '4']));
ok(str_contains($r['body'], '2 indikator dinilai'), 'LPM menindaklanjuti penilaian unitnya');
ok(val($pdo, 'SELECT status FROM ami_assignments WHERE id = ?', [$a1]) === 'completed', 'penugasan unit LPM juga lengkap');
$unit1 = row($pdo, 'SELECT ROUND(SUM(aa.score * i.weight) / SUM(i.weight), 2) AS s FROM ami_assessments aa JOIN ami_instruments i ON i.id = aa.instrument_id WHERE aa.assignment_id = ?', [$a1]);
ok((float)$unit1['s'] === 3.67, 'skor tertimbang unit 1 = (3×1 + 4×2)/3 = 3,67');

$r = $member->post("/ami/assignments/{$a1}/assess", ['_token' => $member->token()] + flat('scores', [$t1 => '4']));
ok($r['code'] === 403, 'anggota ditolak mengisi penilaian unit lain (403)');

echo "\n=== G. Jadwal ditutup mengunci seluruh isian ===\n";
$r = $admin->post("/ami/schedules/{$sid}/toggle", ['_token' => $admin->token()]); // running → closed
ok(str_contains($r['body'], 'ditutup'), 'jadwal dapat ditutup');

$r = $member->post("/ami/assignments/{$a2}/assess", ['_token' => $member->token()] + flat('scores', [$t1 => '5']));
ok(str_contains($r['body'], 'tidak sedang berjalan'), 'penilaian pada jadwal tertutup ditolak');
$r = $member->get("/ami/assignments/{$a2}/assess");
ok(str_contains($r['body'], 'baca saja'), 'penilaian tampil baca-saja saat jadwal tertutup');
$r = $admin->post('/ami/instruments', [
    '_token' => $admin->token(), 'schedule_id' => (string)$sid, 'code' => 'X.9', 'question' => 'x', 'weight' => '1',
]);
ok(str_contains($r['body'], 'terkunci'), 'penambahan instrumen pada jadwal tertutup ditolak');
$r = $admin->post('/ami/assignments', ['_token' => $admin->token(), 'schedule_id' => (string)$sid, 'unit_id' => '4']);
ok(str_contains($r['body'], 'terkunci'), 'penambahan penugasan pada jadwal tertutup ditolak');

$r = $admin->post("/ami/schedules/{$sid}/toggle", ['_token' => $admin->token()]); // closed → running
ok(str_contains($r['body'], 'dibuka kembali'), 'jadwal dapat dibuka kembali');

echo "\n=== H. Temuan: manual, filter, transisi status ===\n";
$r = $admin->get("/ami/findings?schedule_id={$sid}");
ok($r['code'] === 200 && str_contains($r['body'], 'Bukti sebagian'), 'daftar temuan menampilkan temuan otomatis');

$r = $member->post('/ami/findings', [
    '_token'       => $member->token(),
    'schedule_id'  => (string)$sid,
    'assignment_id'=> (string)$a2,
    'finding_type' => 'observasi',
    'severity'     => 'tinggi',
    'description'  => 'Formulir kepuasan belum tersedia di unit',
]);
ok(str_contains($r['body'], 'berhasil dicatat'), 'auditor mencatat temuan manual');
$fManual = (int)val($pdo, "SELECT id FROM ami_findings WHERE description = 'Formulir kepuasan belum tersedia di unit'");
ok($fManual > 0, 'temuan manual tersimpan');
ok(val($pdo, 'SELECT status FROM ami_findings WHERE id = ?', [$fManual]) === 'open', 'temuan manual berstatus terbuka');

$r = $member->post('/ami/findings', [
    '_token'       => $member->token(),
    'schedule_id'  => (string)$sid,
    'assignment_id'=> (string)$a1,
    'finding_type' => 'observasi',
    'severity'     => 'sedang',
    'description'  => 'Ikut campur unit lain',
]);
ok($r['code'] === 403, 'auditor ditolak mencatat temuan pada penugasan orang lain (403)');

$r = $admin->get("/ami/findings?schedule_id={$sid}&severity=tinggi");
ok(str_contains($r['body'], 'Formulir kepuasan belum tersedia') && !str_contains($r['body'], 'Bukti sebagian'), 'filter keparahan bekerja');
$r = $admin->get("/ami/findings?schedule_id={$sid}&status=closed");
ok(str_contains($r['body'], 'Tidak ada temuan pada saringan ini'), 'filter status kosong menampilkan keterangan');

$r = $admin->post("/ami/findings/{$fAuto}", ['_token' => $admin->token(), 'status' => 'in_progress']);
ok(val($pdo, 'SELECT status FROM ami_findings WHERE id = ?', [$fAuto]) === 'in_progress', 'status temuan → ditindaklanjuti');
$r = $member->get('/notifications');
ok(str_contains($r['body'], 'Status temuan diperbarui'), 'pencatat temuan menerima notifikasi perubahan status');

$r = $member->post("/ami/findings/{$fAuto}", ['_token' => $member->token(), 'status' => 'closed']);
ok($r['code'] === 403, 'anggota ditolak mengubah status temuan (403)');

$r = $admin->post("/ami/findings/{$fManual}", ['_token' => $admin->token(), 'status' => 'closed']);
ok(val($pdo, 'SELECT status FROM ami_findings WHERE id = ?', [$fManual]) === 'closed', 'temuan manual → selesai');

echo "\n=== I. Laporan AMI (FR-08) & rekap kepuasan ===\n";
$r = $admin->get("/ami/report?schedule_id={$sid}");
ok($r['code'] === 200, 'laporan AMI terbuka (Super Admin)');
ok(str_contains($r['body'], 'A. Ringkasan pelaksanaan'), 'bagian ringkasan ada');
ok(str_contains($r['body'], 'B. Rekap skor per unit'), 'bagian rekap unit ada');
ok(str_contains($r['body'], '2,67/4') && str_contains($r['body'], '3,67/4'), 'skor tertimbang kedua unit tampil');
ok(str_contains($r['body'], 'C. Rekap per standar &amp; analisis gap'), 'bagian analisis gap ada');
ok(str_contains($r['body'], 'gap terbesar'), 'standar terbesar gap-nya ditandai');
ok(str_contains($r['body'], 'D. Rekap per indikator'), 'bagian rekap indikator ada');
ok(str_contains($r['body'], 'E. Daftar temuan'), 'bagian daftar temuan ada');
ok(str_contains($r['body'], 'Formulir kepuasan belum tersedia'), 'temuan manual masuk laporan');
ok(str_contains($r['body'], '100%'), 'progres penilaian penuh tercatat');

$r = $member->get("/ami/report?schedule_id={$sid}");
ok($r['code'] === 403, 'anggota ditolak membuka laporan (403)');

// — Formulir kepuasan —
$r = $admin->get('/ami/satisfaction');
ok($r['code'] === 200 && str_contains($r['body'], 'Kepuasan Pengguna Layanan Mutu 2026'), 'daftar kepuasan terbuka, seed tampil');
$r = $member->get('/ami/satisfaction');
ok($r['code'] === 200 && str_contains($r['body'], 'Isi sekarang'), 'anggota melihat formulir seed yang terbuka');

$r = $admin->post('/ami/satisfaction', [
    '_token' => $admin->token(),
    'name'   => 'Kepuasan Uji 2027',
]);
ok(str_contains($r['body'], 'dibuat'), 'formulir uji dibuat (draf)');
$sv = (int)val($pdo, 'SELECT id FROM satisfaction_surveys WHERE name = ?', ['Kepuasan Uji 2027']);
ok($sv > 0 && val($pdo, 'SELECT status FROM satisfaction_surveys WHERE id = ?', [$sv]) === 'draft', 'status awal draf');

$r = $admin->post('/ami/satisfaction', ['_token' => $admin->token(), 'name' => 'Kepuasan Uji 2027']);
ok(str_contains($r['body'], 'sudah dipakai'), 'nama formulir ganda ditolak');

// Pertanyaan: 2 skala + 1 teks.
$admin->post("/ami/satisfaction/{$sv}/questions", [
    '_token' => $admin->token(), 'question' => 'Seberapa puas Anda terhadap layanan uji A?',
    'type' => 'skala', 'weight' => '1', 'sort_order' => '0',
]);
$admin->post("/ami/satisfaction/{$sv}/questions", [
    '_token' => $admin->token(), 'question' => 'Masukan untuk uji',
    'type' => 'teks', 'weight' => '1', 'sort_order' => '1',
]);
$admin->post("/ami/satisfaction/{$sv}/questions", [
    '_token' => $admin->token(), 'question' => 'Seberapa puas Anda terhadap layanan uji B?',
    'type' => 'skala', 'weight' => '1', 'sort_order' => '2',
]);
ok((int)val($pdo, 'SELECT COUNT(*) FROM satisfaction_questions WHERE survey_id = ?', [$sv]) === 3, 'tiga pertanyaan tersimpan');
$qA = (int)val($pdo, 'SELECT id FROM satisfaction_questions WHERE survey_id = ? AND question LIKE ?', [$sv, 'Seberapa puas Anda terhadap layanan uji A%']);
$qT = (int)val($pdo, 'SELECT id FROM satisfaction_questions WHERE survey_id = ? AND question LIKE ?', [$sv, 'Masukan untuk uji%']);
$qB = (int)val($pdo, 'SELECT id FROM satisfaction_questions WHERE survey_id = ? AND question LIKE ?', [$sv, 'Seberapa puas Anda terhadap layanan uji B%']);

$r = $admin->get("/ami/satisfaction/{$sv}/questions");
ok($r['code'] === 200 && str_contains($r['body'], 'Seberapa puas'), 'halaman kelola pertanyaan terbuka');

// Buka untuk pengisian → notifikasi anggota.
$r = $admin->post("/ami/satisfaction/{$sv}/toggle", ['_token' => $admin->token()]);
ok(str_contains($r['body'], 'dibuka'), 'formulir dibuka untuk pengisian');
ok(val($pdo, 'SELECT status FROM satisfaction_surveys WHERE id = ?', [$sv]) === 'open', 'status open tersimpan');
$r = $member->get('/notifications');
ok(str_contains($r['body'], 'Formulir kepuasan dibuka'), 'anggota menerima notifikasi pembukaan formulir');

// Pengisian.
$r = $member->get("/ami/satisfaction/{$sv}/fill");
ok($r['code'] === 200 && str_contains($r['body'], 'Kepuasan Uji 2027'), 'formulir pengisian terbuka untuk anggota');

$r = $member->post("/ami/satisfaction/{$sv}/fill", [
    '_token' => $member->token(),
] + flat('answers', [$qA => '4']));
ok(str_contains($r['body'], 'wajib dijawab'), 'skala yang belum dijawab ditolak');
ok((int)val($pdo, 'SELECT COUNT(*) FROM satisfaction_responses WHERE survey_id = ?', [$sv]) === 0, 'respons belum dibuat saat validasi gagal');

$r = $member->post("/ami/satisfaction/{$sv}/fill", [
    '_token' => $member->token(),
] + flat('answers', [$qA => '4', $qB => '5', $qT => 'Layanan sudah cepat']));
ok(str_contains($r['body'], 'tersimpan'), 'jawaban tersimpan');
ok((int)val($pdo, 'SELECT COUNT(*) FROM satisfaction_responses WHERE survey_id = ?', [$sv]) === 1, 'satu respons tercatat');
ok((int)val($pdo, 'SELECT COUNT(*) FROM satisfaction_answers a JOIN satisfaction_responses r ON r.id = a.response_id WHERE r.survey_id = ?', [$sv]) === 3, 'tiga jawaban tersimpan');
ok(val($pdo, 'SELECT score FROM satisfaction_answers WHERE question_id = ?', [$qA]) == 4, 'skor pertanyaan A = 4');

$r = $member->get("/ami/satisfaction/{$sv}/fill");
ok(str_contains($r['body'], 'sudah mengisi'), 'responden kedua kali diblokir (satu jawaban per pengguna)');

// Rekap otomatis.
$r = $admin->get("/ami/satisfaction/{$sv}/recap");
ok($r['code'] === 200 && str_contains($r['body'], 'Rerata skor'), 'rekap kepuasan terbuka');
ok(str_contains($r['body'], '90,0%'), 'rerata tertimbang (4+5)/2 = 4,5 → 90%');
ok(str_contains($r['body'], 'chartPuas'), 'grafik rekap tampil');
ok(str_contains($r['body'], 'Layanan sudah cepat'), 'masukan teks tampil di rekap');
ok(str_contains($r['body'], 'Waket I Akademik'), 'responden terdaftar di rekap');

$r = $member->get("/ami/satisfaction/{$sv}/recap");
ok($r['code'] === 403, 'anggota ditolak membuka rekap (403)');
$r = $member->get("/ami/satisfaction/{$sv}/questions");
ok($r['code'] === 403, 'anggota ditolak membuka kelola pertanyaan (403)');
$r = $member->post('/ami/satisfaction', ['_token' => $member->token(), 'name' => 'Formulir Anggota']);
ok($r['code'] === 403, 'anggota ditolak membuat formulir (403)');
$r = $member->post("/ami/satisfaction/{$sv}/toggle", ['_token' => $member->token()]);
ok($r['code'] === 403, 'anggota ditolak membuka/menutup formulir (403)');

// Ubah & hapus pertanyaan.
$r = $admin->post("/ami/questions/{$qB}", [
    '_token' => $admin->token(),
    'question' => 'Seberapa puas Anda terhadap layanan uji B?',
    'type' => 'skala', 'weight' => '2', 'sort_order' => '2',
]);
ok(str_contains($r['body'], 'diperbarui'), 'pertanyaan diperbarui');
ok((float)val($pdo, 'SELECT weight FROM satisfaction_questions WHERE id = ?', [$qB]) === 2.0, 'bobot pertanyaan B = 2');

// Pertanyaan berjawab → dinonaktifkan, bukan dihapus.
$r = $admin->post("/ami/questions/{$qT}/delete", ['_token' => $admin->token()]);
ok(str_contains($r['body'], 'dinonaktifkan'), 'pertanyaan berjawab dinonaktifkan');
ok((int)val($pdo, 'SELECT COUNT(*) FROM satisfaction_questions WHERE id = ?', [$qT]) === 1, 'pertanyaan berjawab tidak hilang dari DB');
ok(val($pdo, 'SELECT is_active FROM satisfaction_questions WHERE id = ?', [$qT]) == 0, 'status nonaktif tersimpan');

// Pertanyaan tanpa jawaban → hapus permanen.
$admin->post("/ami/satisfaction/{$sv}/questions", [
    '_token' => $admin->token(), 'question' => 'Pertanyaan sementara', 'type' => 'skala', 'weight' => '1', 'sort_order' => '9',
]);
$qC = (int)val($pdo, 'SELECT id FROM satisfaction_questions WHERE survey_id = ? AND question = ?', [$sv, 'Pertanyaan sementara']);
$r = $admin->post("/ami/questions/{$qC}/delete", ['_token' => $admin->token()]);
ok(str_contains($r['body'], 'dihapus'), 'pertanyaan kosong dihapus permanen');
ok((int)val($pdo, 'SELECT COUNT(*) FROM satisfaction_questions WHERE id = ?', [$qC]) === 0, 'pertanyaan hilang dari DB');

// Tutup formulir → kunci pengisian & pertanyaan.
$r = $admin->post("/ami/satisfaction/{$sv}/toggle", ['_token' => $admin->token()]); // open → closed
ok(str_contains($r['body'], 'ditutup'), 'formulir dapat ditutup');
$r = $member->get("/ami/satisfaction/{$sv}/fill");
ok(str_contains($r['body'], 'tidak sedang terbuka'), 'pengisian setelah penutupan ditolak');
$r = $member->post("/ami/satisfaction/{$sv}/fill", ['_token' => $member->token()] + flat('answers', [$qA => '3']));
ok(str_contains($r['body'], 'sudah ditutup'), 'kirim setelah penutupan ditolak');
$r = $admin->post("/ami/satisfaction/{$sv}/questions", [
    '_token' => $admin->token(), 'question' => 'Pertanyaan baru', 'type' => 'skala', 'weight' => '1',
]);
ok(str_contains($r['body'], 'terkunci'), 'penambahan pertanyaan pada formulir tertutup ditolak');

echo "\n=== J. Jejak audit & keamanan RBAC tambahan ===\n";
$r = $admin->get('/audit');
ok($r['code'] === 200 && str_contains($r['body'], 'satisfaction'), 'halaman jejak audit terbuka & memuat aksi terbaru');
ok((int)val($pdo, "SELECT COUNT(*) FROM audit_logs WHERE table_name = 'ami_instruments'") > 0, 'aksi instrumen tercatat di jejak audit');
ok((int)val($pdo, "SELECT COUNT(*) FROM audit_logs WHERE table_name = 'ami_assignments'") > 0, 'aksi penugasan tercatat di jejak audit');
ok((int)val($pdo, "SELECT COUNT(*) FROM audit_logs WHERE table_name IN ('ami_findings','ami_assessments')") > 0, 'aksi penilaian/temuan tercatat di jejak audit');
ok((int)val($pdo, "SELECT COUNT(*) FROM audit_logs WHERE table_name LIKE 'satisfaction%'") > 0, 'aksi kepuasan tercatat di jejak audit');

$r = $member->post("/ami/schedules/{$sid}/toggle", ['_token' => $member->token()]);
ok($r['code'] === 403, 'anggota ditolak menjadwalkan AMI (403)');
$r = $member->post("/ami/instruments/{$t1}/delete", ['_token' => $member->token()]);
ok($r['code'] === 403, 'anggota ditolak menghapus instrumen (403)');
$r = $member->post("/ami/questions/{$qA}", ['_token' => $member->token(), 'question' => 'x', 'type' => 'skala', 'weight' => '1']);
ok($r['code'] === 403, 'anggota ditolak mengubah pertanyaan (403)');

echo "\n=== K. Pembersihan data uji ===\n";
$pdo->exec("DELETE FROM ami_schedules WHERE name = 'AMI 2027-TEST'");           // cascade instrumen/log/penugasan/nilai/temuan
$pdo->exec("DELETE FROM satisfaction_surveys WHERE name = 'Kepuasan Uji 2027'"); // cascade pertanyaan/respons/jawaban
$pdo->exec("DELETE FROM notifications WHERE ref_table IN ('ami_assignments','ami_findings','satisfaction_surveys')");
$uploadDir = 'C:/xampp/htdocs/lpm/public/uploads/bukti-ami';
if (is_dir($uploadDir)) {
    array_map('unlink', glob($uploadDir . '/*/*/*') ?: []);
    array_map('rmdir', glob($uploadDir . '/*/*') ?: []);
    array_map('rmdir', glob($uploadDir . '/*') ?: []);
    @rmdir($uploadDir);
}

ok((int)val($pdo, "SELECT COUNT(*) FROM ami_schedules WHERE name = 'AMI 2027-TEST'") === 0, 'jadwal uji dihapus');
ok((int)val($pdo, "SELECT COUNT(*) FROM satisfaction_surveys WHERE name = 'Kepuasan Uji 2027'") === 0, 'formulir uji dihapus');
ok((int)val($pdo, "SELECT COUNT(*) FROM ami_findings") === 0, 'temuan uji ikut terhapus (cascade)');
ok((int)val($pdo, "SELECT COUNT(*) FROM ami_assignments") === 0, 'penugasan uji ikut terhapus (cascade)');
ok(!is_dir($uploadDir), 'berkas bukti uji dihapus');

$clean = true;
foreach ($baseline as $t => $n) {
    $now = (int)val($pdo, "SELECT COUNT(*) FROM {$t}");
    if ($now !== $n) {
        $clean = false;
        echo "[FAIL] jumlah {$t}: baseline {$n} → sekarang {$now}\n";
    }
}
ok($clean, 'seluruh tabel kembali ke jumlah data awal (tanpa sisa data uji)');

echo "\n========================================\n";
echo "LULUS: $pass — GAGAL: $fail\n";
exit($fail > 0 ? 1 : 0);
