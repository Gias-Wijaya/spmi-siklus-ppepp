<?php
declare(strict_types=1);

/**
 * Uji iterasi 5 — Modul M4 Pengendalian–Peningkatan (FR-09, FR-10, FR-11, FR-12).
 * Jalankan: php test-followup.php  (server :8080 + MariaDB harus hidup)
 *
 * Cakupan:
 *  A. Masuk & akses halaman M4 (RBAC)   E. Bukti & verifikasi (FR-11)
 *  B. Konversi temuan → rencana aksi     F. Umpan balik revisi dokumen (FR-12)
 *     (FR-09)                            G. Dasbor, notifikasi, jejak audit & saringan
 *  C. Status tindak lanjut (FR-10)       H. CSRF & 404
 *  D. Penyuntingan rencana (Super)       I. Pembersihan data uji
 *
 * Temuan sumber dibuat melalui alur M3 (penugasan + temuan manual) — membuktikan
 * integrasi M3 → M4; tidak ada aksi tanpa temuan (aturan bisnis P4).
 */

$base = 'http://127.0.0.1:8080';
$tmp  = __DIR__ . '/followup-test';
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

$bukti1 = $tmp . '/bukti-pertama.pdf';
$bukti2 = $tmp . '/bukti-perbaikan.pdf';
$dokRevisi = $tmp . '/revisi-dokumen.pdf';
file_put_contents($bukti1, "%PDF-1.4\n% uji bukti tindak lanjut 1\n%%EOF\n");
file_put_contents($bukti2, "%PDF-1.4\n% uji bukti tindak lanjut 2\n%%EOF\n");
file_put_contents($dokRevisi, "%PDF-1.4\n% uji revisi dokumen M4\n%%EOF\n");

// Pemanasan penjaga tenggat (FR-15): satu permintaan agar notifikasi tenggat
// data benih tercipta lebih dulu, sehingga jumlah notifikasi stabil berapa pun
// urutan suite dijalankan.
@unlink($tmp . '/warm.txt');
(new Client($base, $tmp . '/warm.txt'))->get('/login');

// Baseline data asli (tanpa data uji) untuk dipastikan kembali utuh setelah pembersihan.
$baseline = [];
foreach ([
    'ami_assignments', 'ami_findings', 'action_plans', 'action_evidence',
    'documents', 'document_versions', 'notifications',
] as $t) {
    $baseline[$t] = (int)val($pdo, "SELECT COUNT(*) FROM {$t}");
}

$admin  = new Client($base, $cookieA);
$member = new Client($base, $cookieM);

echo "=== A. Masuk & akses halaman M4 (RBAC) ===\n";
$r = $admin->post('/login', ['_token' => $admin->token(), 'email' => 'admin@stikomelrahma.ac.id', 'password' => 'admin123']);
ok(str_contains($r['url'], '/'), 'login Super Admin berhasil');
$r = $member->post('/login', ['_token' => $member->token(), 'email' => 'waket1@stikomelrahma.ac.id', 'password' => 'waket123']);
ok(str_contains($r['url'], '/'), 'login Anggota (Waket I) berhasil');

$r = $admin->get('/followup');
ok($r['code'] === 200 && str_contains($r['body'], 'Pengendalian–Peningkatan'), 'dasbor M4 terbuka untuk Super Admin');
ok(str_contains($r['body'], 'FR-09'), 'uraian halaman memuat rujukan FR-09–FR-12');

$r = $member->get('/followup');
ok($r['code'] === 200, 'dasbor M4 terbuka untuk anggota');
ok(str_contains($r['body'], 'dilakukan LPM') && !str_contains($r['body'], 'Buat rencana aksi & tugaskan'),
    'anggota tidak melihat formulir konversi temuan (hanya LPM)');

$r = $admin->get('/followup/plans/999999');
ok($r['code'] === 404, 'rencana aksi tidak ada → 404');

$r = $member->post('/followup/plans', [
    '_token' => $member->token(), 'finding_id' => '1', 'description' => 'x',
    'pic_user_id' => '2', 'due_date' => '2027-05-01',
]);
ok($r['code'] === 403, 'anggota ditolak membuat rencana aksi (403)');

$r = $member->post('/followup/plans/1/revision', ['_token' => $member->token(), 'document_id' => '1', 'note' => 'x']);
ok($r['code'] === 403, 'anggota ditolak memberi permintaan revisi dokumen (403)');

echo "\n=== B. Konversi temuan → rencana aksi (FR-09) ===\n";
// Sumber: penugasan + temuan melalui alur M3 (schedule seed AMI 2026).
$sid = (int)val($pdo, "SELECT id FROM ami_schedules WHERE status = 'running' ORDER BY id LIMIT 1");
ok($sid > 0, 'jadwal AMI berjalan tersedia sebagai wadah temuan');

$admin->post('/ami/assignments', [
    '_token' => $admin->token(), 'schedule_id' => (string)$sid, 'unit_id' => '3',
    // Item 8: auditor wajib berasal dari unit LPM (user 1 = Super Admin/LPM).
    'auditor_user_id' => '1', 'audit_date' => '2027-04-01',
]);
$aid = (int)val($pdo, 'SELECT id FROM ami_assignments WHERE schedule_id = ? AND unit_id = ?', [$sid, 3]);
ok($aid > 0, 'penugasan unit W2 dibuat sebagai wadah temuan uji');

$admin->post('/ami/findings', [
    '_token' => $admin->token(), 'schedule_id' => (string)$sid, 'assignment_id' => (string)$aid,
    'description' => 'Dokumen SOP mutu belum diperbarui setelah AMI terakhir',
    'finding_type' => 'ketidaksesuaian', 'severity' => 'tinggi',
]);
$f1 = (int)val($pdo, "SELECT id FROM ami_findings WHERE assignment_id = ? AND severity = 'tinggi' ORDER BY id DESC LIMIT 1", [$aid]);
ok($f1 > 0, 'temuan sumber #1 tercatat (skor/temuan M3 → bahan M4)');

$admin->post('/ami/findings', [
    '_token' => $admin->token(), 'schedule_id' => (string)$sid, 'assignment_id' => (string)$aid,
    'description' => 'Bukti penilaian unit W2 sebagian belum lengkap',
    'finding_type' => 'observasi', 'severity' => 'sedang',
]);
$f2 = (int)val($pdo, "SELECT id FROM ami_findings WHERE assignment_id = ? AND severity = 'sedang' ORDER BY id DESC LIMIT 1", [$aid]);
ok($f2 > 0, 'temuan sumber #2 tercatat');

$r = $admin->get('/followup?schedule_id=' . $sid);
ok(str_contains($r['body'], 'Temuan (sumber wajib)'), 'formulir konversi temuan tampil di dasbor M4');
ok(str_contains($r['body'], 'belum ada aksi'), 'temuan yang belum punya aksi ditandai pada daftar pilihan');

// Validasi FR-09.
$r = $admin->post('/followup/plans', [
    '_token' => $admin->token(), 'finding_id' => '0', 'description' => 'x',
    'pic_user_id' => '2', 'due_date' => '2027-05-01',
]);
ok(str_contains($r['body'], 'Temuan tidak ditemukan'), 'rencana aksi tanpa temuan sah ditolak (tidak ada aksi tanpa temuan)');

$r = $admin->post('/followup/plans', [
    '_token' => $admin->token(), 'finding_id' => (string)$f1, 'description' => '',
    'pic_user_id' => '2', 'due_date' => '2027-05-01',
]);
ok(str_contains($r['body'], 'Uraian tindak lanjut wajib diisi'), 'uraian kosong ditolak');

$r = $admin->post('/followup/plans', [
    '_token' => $admin->token(), 'finding_id' => (string)$f1, 'description' => 'x',
    'pic_user_id' => '999999', 'due_date' => '2027-05-01',
]);
ok(str_contains($r['body'], 'Penanggung jawab harus pengguna aktif'), 'PIC bukan pengguna aktif ditolak');

$r = $admin->post('/followup/plans', [
    '_token' => $admin->token(), 'finding_id' => (string)$f1, 'description' => 'x',
    'pic_user_id' => '2', 'due_date' => '01/05/2027',
]);
ok(str_contains($r['body'], 'Tenggat waktu wajib diisi'), 'tenggat dengan format salah ditolak');

// Rencana aksi sah #1 — PIC Waket I (user 2).
$r = $admin->post('/followup/plans', [
    '_token' => $admin->token(), 'finding_id' => (string)$f1,
    'description' => 'Perbarui SOP mutu dan sosialisasi hasil AMI',
    'pic_user_id' => '2', 'due_date' => '2027-05-01', 'priority' => 'tinggi',
]);
ok(str_contains($r['body'], 'Rencana aksi berhasil dibuat'), 'rencana aksi #1 dibuat & ditugaskan');
$p1 = (int)val($pdo, 'SELECT id FROM action_plans WHERE finding_id = ? ORDER BY id LIMIT 1', [$f1]);
ok($p1 > 0, 'baris rencana aksi tersimpan di action_plans');
$row = row($pdo, 'SELECT * FROM action_plans WHERE id = ?', [$p1]);
ok($row['status'] === 'open', 'status awal = terbuka (open)');
ok($row['priority'] === 'tinggi' && $row['due_date'] === '2027-05-01', 'prioritas & tenggat tersimpan');
ok((int)$row['pic_user_id'] === 2 && (int)$row['pic_unit_id'] === 2, 'PIC + unit PIC tersimpan');
ok((int)val($pdo, "SELECT COUNT(*) FROM notifications WHERE user_id = 2 AND type = 'temuan' AND ref_table = 'action_plans' AND ref_id = ?", [$p1]) === 1,
    'notifikasi penugasan terkirim ke penanggung jawab');

// Rencana aksi sah #2 — PIC Super Admin (uji pembatasan akses anggota).
$admin->post('/followup/plans', [
    '_token' => $admin->token(), 'finding_id' => (string)$f2,
    'description' => 'Lengkapi berkas penilaian unit W2',
    'pic_user_id' => '1', 'due_date' => '2027-05-10', 'priority' => 'sedang',
]);
$p2 = (int)val($pdo, 'SELECT id FROM action_plans WHERE finding_id = ? ORDER BY id LIMIT 1', [$f2]);
ok($p2 > 0 && $p2 !== $p1, 'rencana aksi #2 (PIC berbeda) tersimpan');

$r = $admin->get('/followup?schedule_id=' . $sid);
ok(str_contains($r['body'], 'sudah 1 aksi'), 'daftar konversi menampilkan jumlah aksi per temuan');

$r = $member->get('/followup');
ok(str_contains($r['body'], 'Perbarui SOP mutu') && !str_contains($r['body'], 'Lengkapi berkas penilaian unit W2'),
    'anggota hanya melihat rencana aksi miliknya (bukan milik PIC lain)');

echo "\n=== C. Status tindak lanjut (FR-10) ===\n";
$r = $member->get("/followup/plans/{$p1}");
ok($r['code'] === 200 && str_contains($r['body'], 'Rencana Aksi #'), 'penanggung jawab membuka detail rencana aksi');
ok(str_contains($r['body'], 'Dokumen SOP mutu belum diperbarui'), 'temuan sumber tampil pada detail');

$r = $member->get("/followup/plans/{$p2}");
ok($r['code'] === 403, 'anggota ditolak membuka rencana aksi PIC lain (403)');
$r = $member->post("/followup/plans/{$p2}/status", ['_token' => $member->token(), 'status' => 'in_progress']);
ok($r['code'] === 403, 'anggota ditolak mengubah status rencana PIC lain (403)');

$r = $member->post("/followup/plans/{$p1}/status", ['_token' => $member->token(), 'status' => 'verified']);
ok(str_contains($r['body'], 'Status tujuan tidak valid'), 'transisi langsung ke verified ditolak (wajib melalui verifikasi bukti)');

$r = $member->post("/followup/plans/{$p1}/status", ['_token' => $member->token(), 'status' => 'in_progress']);
ok(str_contains($r['body'], 'berjalan'), 'penanggung jawab memulai pelaksanaan: open → in_progress');
ok((string)val($pdo, 'SELECT status FROM action_plans WHERE id = ?', [$p1]) === 'in_progress', 'status tersimpan in_progress');
ok((int)val($pdo, "SELECT COUNT(*) FROM notifications WHERE user_id = 1 AND ref_table = 'action_plans' AND ref_id = ? AND title = 'Tindak lanjut dimulai'", [$p1]) === 1,
    'LPM diberi tahu pelaksanaan dimulai');

$r = $member->post("/followup/plans/{$p1}/status", ['_token' => $member->token(), 'status' => 'in_progress']);
ok(str_contains($r['body'], 'Hanya rencana berstatus terbuka'), 'transisi ganda dari status sama ditolak');

echo "\n=== D. Penyuntingan rencana aksi (Super Admin) ===\n";
$r = $member->post("/followup/plans/{$p1}", [
    '_token' => $member->token(), 'description' => 'x', 'pic_user_id' => '2',
    'due_date' => '2027-05-01', 'priority' => 'sedang',
]);
ok($r['code'] === 403, 'anggota ditolak menyunting rencana aksi (403)');

$r = $admin->post("/followup/plans/{$p1}", [
    '_token' => $admin->token(), 'description' => 'Perbarui SOP mutu dan sosialisasi hasil AMI',
    'pic_user_id' => '2', 'due_date' => '2027-05-05', 'priority' => 'sedang',
]);
ok(str_contains($r['body'], 'diperbarui'), 'Super Admin menyunting tenggat & prioritas');
$row = row($pdo, 'SELECT * FROM action_plans WHERE id = ?', [$p1]);
ok($row['due_date'] === '2027-05-05' && $row['priority'] === 'sedang', 'perubahan tenggat & prioritas tersimpan');
ok((int)val($pdo, "SELECT COUNT(*) FROM audit_logs WHERE table_name = 'action_plans' AND row_id = ?", [$p1]) > 0,
    'penyuntingan tercatat di jejak audit');

echo "\n=== E. Bukti & verifikasi (FR-11) ===\n";
$r = $member->post("/followup/plans/{$p1}/evidence", ['_token' => $member->token()]);
ok(str_contains($r['body'], 'Berkas wajib diunggah'), 'unggahan tanpa berkas ditolak');

$r = $member->post("/followup/plans/{$p1}/evidence", [
    '_token' => $member->token(), 'note' => 'Bukti sosialisasi tahap pertama',
], ['file' => $bukti1]);
ok(str_contains($r['body'], 'Bukti terunggah'), 'penanggung jawab mengunggah bukti');
$ev1 = (int)val($pdo, 'SELECT id FROM action_evidence WHERE action_plan_id = ? ORDER BY id LIMIT 1', [$p1]);
ok($ev1 > 0 && (string)val($pdo, 'SELECT status FROM action_evidence WHERE id = ?', [$ev1]) === 'pending',
    'bukti tersimpan berstatus menunggu verifikasi');
ok((string)val($pdo, 'SELECT status FROM action_plans WHERE id = ?', [$p1]) === 'done',
    'status rencana → selesai (done) menunggu verifikasi');
ok((int)val($pdo, "SELECT COUNT(*) FROM notifications WHERE user_id = 1 AND ref_table = 'action_plans' AND ref_id = ? AND title LIKE 'Bukti%'", [$p1]) === 1,
    'LPM diberi tahu bukti menunggu verifikasi');

$r = $member->post("/followup/plans/{$p1}/evidence", ['_token' => $member->token()], ['file' => $bukti2]);
ok(str_contains($r['body'], 'masih menunggu verifikasi'), 'unggah ganda saat menunggu verifikasi ditolak');

$r = $admin->post("/followup/evidence/{$ev1}/verify", ['_token' => $admin->token(), 'decision' => 'maybe', 'note' => 'x']);
ok(str_contains($r['body'], 'Keputusan verifikasi tidak valid'), 'keputusan tidak valid ditolak');
$r = $admin->post("/followup/evidence/{$ev1}/verify", ['_token' => $admin->token(), 'decision' => 'rejected', 'note' => '']);
ok(str_contains($r['body'], 'Catatan wajib diisi'), 'pengembalian tanpa catatan ditolak');

$r = $admin->post("/followup/evidence/{$ev1}/verify", [
    '_token' => $admin->token(), 'decision' => 'rejected', 'note' => 'Bukti belum lengkap — lampiran kurang',
]);
ok(str_contains($r['body'], 'dikembalikan'), 'Super Admin mengembalikan bukti dengan catatan');
ok((string)val($pdo, 'SELECT status FROM action_evidence WHERE id = ?', [$ev1]) === 'rejected', 'bukti berstatus rejected');
$row = row($pdo, 'SELECT * FROM action_plans WHERE id = ?', [$p1]);
ok($row['status'] === 'in_progress' && str_contains((string)$row['verification_note'], 'Bukti belum lengkap'),
    'rencana kembali berjalan + catatan pengembalian tersimpan');
ok((int)val($pdo, "SELECT COUNT(*) FROM notifications WHERE user_id = 2 AND ref_table = 'action_plans' AND ref_id = ? AND title LIKE 'Bukti%dikembalikan'", [$p1]) === 1,
    'penanggung jawab menerima pemberitahuan pengembalian');

$r = $member->get("/followup/plans/{$p1}");
ok(str_contains($r['body'], 'Bukti belum lengkap'), 'catatan pengembalian tampil di halaman rencana');

$r = $member->post("/followup/plans/{$p1}/evidence", [
    '_token' => $member->token(), 'note' => 'Bukti perbaikan final',
], ['file' => $bukti2]);
ok(str_contains($r['body'], 'Bukti terunggah'), 'bukti perbaikan diunggah ulang setelah pengembalian');
$ev2 = (int)val($pdo, 'SELECT id FROM action_evidence WHERE action_plan_id = ? AND id <> ? ORDER BY id DESC LIMIT 1', [$p1, $ev1]);
ok((string)val($pdo, 'SELECT status FROM action_evidence WHERE id = ?', [$ev2]) === 'pending', 'bukti baru menunggu verifikasi');
ok((string)val($pdo, 'SELECT status FROM action_plans WHERE id = ?', [$p1]) === 'done', 'rencana kembali berstatus selesai (done)');

$r = $admin->post("/followup/evidence/{$ev2}/verify", [
    '_token' => $admin->token(), 'decision' => 'approved', 'note' => 'Bukti diterima LPM',
]);
ok(str_contains($r['body'], 'diverifikasi'), 'Super Admin menerima bukti → terverifikasi');
$row = row($pdo, 'SELECT * FROM action_plans WHERE id = ?', [$p1]);
ok($row['status'] === 'verified' && (int)$row['verified_by'] === 1 && $row['verified_at'] !== null,
    'status verified + verifier & waktu verifikasi tersimpan');
ok((string)val($pdo, 'SELECT status FROM action_evidence WHERE id = ?', [$ev2]) === 'approved', 'bukti berstatus approved');
ok((int)val($pdo, "SELECT COUNT(*) FROM notifications WHERE user_id = 2 AND ref_table = 'action_plans' AND ref_id = ? AND title = 'Tindak lanjut terverifikasi'", [$p1]) === 1,
    'penanggung jawab menerima pemberitahuan verifikasi');

$r = $member->post("/followup/plans/{$p1}/evidence", ['_token' => $member->token()], ['file' => $bukti1]);
ok(str_contains($r['body'], 'sudah terverifikasi'), 'unggahan bukti setelah verifikasi ditolak');
$r = $admin->post("/followup/evidence/{$ev2}/verify", ['_token' => $admin->token(), 'decision' => 'approved', 'note' => 'x']);
ok(str_contains($r['body'], 'sudah pernah diputuskan'), 'verifikasi ganda pada bukti yang sama ditolak');

$r = $admin->get("/followup/evidence/{$ev2}/download");
ok($r['code'] === 200 && str_contains($r['body'], '%PDF'), 'Super Admin mengunduh bukti');
$r = $member->get("/followup/evidence/{$ev2}/download");
ok($r['code'] === 200 && str_contains($r['body'], '%PDF'), 'penanggung jawab mengunduh bukti');

$r = $member->get("/followup/plans/{$p1}");
ok(str_contains($r['body'], 'Terverifikasi') && str_contains($r['body'], 'Bukti diterima LPM'),
    'status & catatan verifikasi tampil di halaman rencana');

echo "\n=== F. Umpan balik revisi dokumen (FR-12) ===\n";
$pdo->exec("INSERT INTO documents (title, doc_number, version, file_path, owner_user_id, unit_id, effective_date, expiry_date)
             VALUES ('Dokumen Uji M4', 'UJI/M4/2027', '1.0', 'dokumen/uji-m4.pdf', 2, 2, '2026-01-01', '2027-12-31')");
$docId = (int)$pdo->lastInsertId();
ok($docId > 0, 'dokumen uji disiapkan (pemilik = pengguna anggota)');

$r = $admin->get("/followup/plans/{$p1}");
ok(str_contains($r['body'], 'permintaan revisi dokumen (FR-12)') && str_contains($r['body'], 'Dokumen Uji M4'),
    'formulir permintaan revisi tampil setelah verifikasi');

$r = $admin->post("/followup/plans/{$p2}/revision", [
    '_token' => $admin->token(), 'document_id' => (string)$docId, 'note' => 'x',
]);
ok(str_contains($r['body'], 'hanya dapat diberikan setelah verifikasi'), 'permintaan revisi sebelum verifikasi ditolak');

$r = $admin->post("/followup/plans/{$p1}/revision", [
    '_token' => $admin->token(), 'document_id' => '999999', 'note' => 'x',
]);
ok(str_contains($r['body'], 'Dokumen tujuan tidak ditemukan'), 'dokumen tujuan tidak valid ditolak');

$r = $admin->post("/followup/plans/{$p1}/revision", [
    '_token' => $admin->token(), 'document_id' => (string)$docId, 'note' => '',
]);
ok(str_contains($r['body'], 'Catatan umpan balik revisi wajib diisi'), 'catatan umpan balik kosong ditolak');

$r = $admin->post("/followup/plans/{$p1}/revision", [
    '_token' => $admin->token(), 'document_id' => (string)$docId,
    'note' => 'Perbarui bagian evaluasi sesuai hasil tindak lanjut',
]);
ok(str_contains($r['body'], 'Permintaan revisi dokumen terkirim'), 'permintaan revisi dokumen terkirim');
$row = row($pdo, 'SELECT * FROM action_plans WHERE id = ?', [$p1]);
ok((int)$row['revision_document_id'] === $docId && str_contains((string)$row['revision_note'], 'Perbarui bagian evaluasi'),
    'tautan dokumen + catatan umpan balik tersimpan pada rencana aksi');
ok($row['revision_requested_at'] !== null, 'waktu permintaan revisi tercatat');
ok((int)val($pdo, "SELECT COUNT(*) FROM notifications WHERE user_id = 2 AND type = 'dokumen' AND ref_table = 'documents' AND ref_id = ?", [$docId]) === 1,
    'pemilik dokumen menerima notifikasi permintaan revisi');

$r = $admin->post("/followup/plans/{$p1}/revision", [
    '_token' => $admin->token(), 'document_id' => (string)$docId, 'note' => 'lagi',
]);
ok(str_contains($r['body'], 'sudah pernah dibuat'), 'permintaan ganda untuk rencana sama ditolak');

$r = $member->get("/followup/plans/{$p1}");
ok(str_contains($r['body'], 'revisi diminta') && str_contains($r['body'], 'Dokumen Uji M4'),
    'penanggung jawab melihat status permintaan revisi pada halaman rencana');

// Menutup siklus PPEPP: pemilik dokumen merevisi via alur M1 (unggah versi baru).
$r = $member->post("/documents/{$docId}/revise", [
    '_token' => $member->token(), 'version' => '1.1',
    'change_note' => 'Revisi atas temuan AMI & rencana aksi',
], ['file' => $dokRevisi]);
ok(str_contains($r['body'], 'Dokumen Uji M4'), 'pemilik membuka halaman dokumen setelah revisi');
ok((string)val($pdo, 'SELECT version FROM documents WHERE id = ?', [$docId]) === '1.1', 'dokumen naik ke versi 1.1 (revisi M1)');
ok((string)val($pdo, 'SELECT file_path FROM documents WHERE id = ?', [$docId])
    === (string)val($pdo, 'SELECT file_path FROM document_versions WHERE document_id = ?', [$docId]),
    'versi baru langsung aktif pada dokumen (pengesahan TTD basah di luar sistem)');
ok((int)val($pdo, 'SELECT COUNT(*) FROM document_versions WHERE document_id = ?', [$docId]) === 1,
    'versi dokumen tercatat pada document_versions');

echo "\n=== G. Dasbor, notifikasi, jejak audit & saringan status ===\n";
$r = $admin->get('/');
ok(str_contains($r['body'], 'Tindak lanjut berjalan') && substr_count($r['body'], 'followup') >= 2,
    'dasbor memuat statistik tindak lanjut bertaut ke M4 (sidebar + kartu statistik)');
ok(str_contains($r['body'], 'Pengendalian–Peningkatan') && !str_contains($r['body'], 'nav-disabled">🛠️'),
    'sidebar menautkan modul M4 (label "segera" dihapus)');

$r = $member->get('/notifications');
ok(str_contains($r['body'], 'Buka rencana aksi →'), 'notifikasi rencana aksi bertaut langsung ke halamannya');

$r = $admin->get('/audit');
ok($r['code'] === 200 && str_contains($r['body'], 'action_plans'), 'jejak audit menampilkan aksi action_plans');
ok((int)val($pdo, "SELECT COUNT(*) FROM audit_logs WHERE table_name = 'action_plans'") > 0, 'aksi M4 tercatat di jejak audit (action_plans)');
ok((int)val($pdo, "SELECT COUNT(*) FROM audit_logs WHERE table_name = 'action_evidence'") > 0, 'aksi bukti tercatat di jejak audit (action_evidence)');

$r = $admin->get('/followup?schedule_id=' . $sid . '&status=verified');
ok(str_contains($r['body'], 'Perbarui SOP mutu') && !str_contains($r['body'], 'Lengkapi berkas penilaian unit W2'),
    'saringan status verified menampilkan rencana terverifikasi saja');
$r = $admin->get('/followup?schedule_id=' . $sid . '&status=open');
ok(str_contains($r['body'], 'Lengkapi berkas penilaian unit W2') && !str_contains($r['body'], 'Perbarui SOP mutu'),
    'saringan status terbuka menampilkan rencana terbuka saja');
$r = $admin->get('/followup?schedule_id=' . $sid . '&priority=sedang');
ok(str_contains($r['body'], 'Perbarui SOP mutu'), 'saringan prioritas bekerja');

echo "\n=== H. CSRF & 404 ===\n";
$r = $admin->post('/followup/plans', []);
ok($r['code'] === 419, 'permintaan tanpa token CSRF ditolak (419)');
$r = $admin->get('/followup/evidence/999999/download');
ok($r['code'] === 404, 'unduhan bukti tidak ada → 404');
$r = $admin->post('/followup/plans/999999/status', ['_token' => $admin->token(), 'status' => 'in_progress']);
ok($r['code'] === 404, 'status rencana tidak ada → 404');
$r = $admin->post('/followup/evidence/999999/verify', ['_token' => $admin->token(), 'decision' => 'approved']);
ok($r['code'] === 404, 'verifikasi bukti tidak ada → 404');

echo "\n=== I. Pembersihan data uji ===\n";
// Kumpulkan berkas unggahan milik data uji sebelum barisnya dihapus.
$evFiles    = $pdo->query('SELECT file_path FROM action_evidence')->fetchAll(PDO::FETCH_COLUMN);
$docFiles   = $pdo->query('SELECT file_path FROM documents WHERE id = ' . $docId)->fetchAll(PDO::FETCH_COLUMN);
$verFiles   = $pdo->query('SELECT file_path FROM document_versions WHERE document_id = ' . $docId)->fetchAll(PDO::FETCH_COLUMN);

if ($aid > 0) {
    $pdo->exec("DELETE FROM ami_assignments WHERE id = {$aid}"); // cascade: temuan → rencana aksi → bukti
}
$pdo->exec("DELETE FROM documents WHERE id = {$docId}");          // cascade: riwayat versi
$pdo->exec("DELETE FROM notifications WHERE ref_table IN ('action_plans', 'ami_findings', 'ami_assignments')
              OR (ref_table = 'documents' AND ref_id = {$docId})");
$pdo->exec("DELETE FROM audit_logs WHERE table_name IN ('action_plans', 'action_evidence')
              OR (table_name = 'documents' AND row_id = {$docId})");

// Hapus berkas unggahan milik data uji.
foreach (array_merge($evFiles, $docFiles, $verFiles) as $rel) {
    $full = 'C:/xampp/htdocs/lpm/public/uploads/' . ltrim((string)$rel, '/');
    if (is_file($full)) {
        @unlink($full);
    }
}

// Rapikan folder unggahan kosong peninggalan uji.
foreach (['bukti-tindak-lanjut', 'dokumen'] as $sub) {
    $dir = 'C:/xampp/htdocs/lpm/public/uploads/' . $sub;
    if (is_dir($dir)) {
        array_map(static fn (string $d): bool => (bool)@rmdir($d), glob($dir . '/*/*') ?: []);
        array_map(static fn (string $d): bool => (bool)@rmdir($d), glob($dir . '/*') ?: []);
        @rmdir($dir);
    }
}

ok((int)val($pdo, "SELECT COUNT(*) FROM ami_assignments WHERE id = {$aid}") === 0, 'penugasan uji dihapus');
ok((int)val($pdo, "SELECT COUNT(*) FROM ami_findings") === 0, 'temuan uji ikut terhapus (cascade)');
ok((int)val($pdo, 'SELECT COUNT(*) FROM action_plans') === 0, 'rencana aksi uji ikut terhapus (cascade)');
ok((int)val($pdo, 'SELECT COUNT(*) FROM action_evidence') === 0, 'bukti uji ikut terhapus (cascade)');
ok((int)val($pdo, "SELECT COUNT(*) FROM documents WHERE id = {$docId}") === 0, 'dokumen uji dihapus');
$leftover = array_filter(
    array_merge($evFiles, $docFiles, $verFiles),
    static fn (string $rel): bool => is_file('C:/xampp/htdocs/lpm/public/uploads/' . ltrim($rel, '/'))
);
ok(!$leftover, 'seluruh berkas unggahan uji terhapus dari server');

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
