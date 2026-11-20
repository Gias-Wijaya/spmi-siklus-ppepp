<?php
declare(strict_types=1);

/**
 * Uji iterasi 6 — pelengkap M5: FR-14 (ekspor laporan) & FR-15 (notifikasi tenggat).
 * Jalankan: php test-pelengkap.php  (server :8080 + MySQL harus hidup)
 *
 * Cakupan:
 *  A. Ekspor dokumen SPMI (CSV, saringan, RBAC, escaping)
 *  B. Ekspor rekap Monev
 *  C. Ekspor laporan AMI (RBAC) & rencana aksi (saringan + PIC)
 *  D. Cetak/PDF halaman laporan
 *  E. Notifikasi tenggat (FR-15)
 *  F. Pembersihan data uji & pembanding baseline
 *
 * Urutan pengujian: jalankan skrip ini lebih dulu sebelum suite iterasi 3–5 —
 * skrip ini sekaligus pemanasan penjaga tenggat atas data benih, sehingga
 * jumlah notifikasi pada suite lama stabil berapa pun urutannya.
 */

$base = 'http://127.0.0.1:8080';
$tmp  = __DIR__ . '/pelengkap-test';
@mkdir($tmp, 0777, true);
$cookieA = $tmp . '/admin.txt';
$cookieM = $tmp . '/member.txt';
$cookieG = $tmp . '/guest.txt';
@unlink($cookieA);
@unlink($cookieM);
@unlink($cookieG);

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
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, null);
        curl_setopt($this->ch, CURLOPT_HTTPGET, true);
        $body = curl_exec($this->ch);
        return [
            'code' => (int)curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE),
            'body' => (string)$body,
            'url'  => (string)curl_getinfo($this->ch, CURLINFO_EFFECTIVE_URL),
        ];
    }

    /** Permintaan GET beserta blok header (untuk menguji header unduhan CSV). */
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

    public function post(string $path, array $fields = []): array
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->base . $path);
        curl_setopt($this->ch, CURLOPT_POST, true);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $fields);
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

/* ------------------------------------------------- Pemanasan FR-15 (sebelum baseline) */

@unlink($tmp . '/warm.txt');
$warm = new Client($base, $tmp . '/warm.txt');
$warm->get('/login');

$baseline = [];
foreach ([
    'notifications', 'documents', 'monev_periods', 'monev_reports',
    'ami_schedules', 'ami_assignments', 'ami_findings', 'action_plans',
] as $t) {
    $baseline[$t] = (int)val($pdo, "SELECT COUNT(*) FROM {$t}");
}
$baselineTenggat = (int)val($pdo, "SELECT COUNT(*) FROM notifications WHERE type = 'tenggat'");
ok($baselineTenggat >= 1, 'penjaga tenggat sudah menyapa data benih sebelum uji dimulai');

$admin  = new Client($base, $cookieA);
$member = new Client($base, $cookieM);
$guest  = new Client($base, $cookieG);

$r = $admin->post('/login', ['_token' => $admin->token(), 'email' => 'admin@stikomelrahma.ac.id', 'password' => 'admin123']);
ok(str_contains($r['url'], '/'), 'login Super Admin berhasil');
$r = $member->post('/login', ['_token' => $member->token(), 'email' => 'waket1@stikomelrahma.ac.id', 'password' => 'waket123']);
ok(str_contains($r['url'], '/'), 'login Anggota (Waket I) berhasil');

/* ============================================== A. Ekspor dokumen SPMI (FR-14) === */

echo "\n=== A. Ekspor dokumen SPMI ===\n";

$r = $admin->getRaw('/documents/export');
ok($r['code'] === 200, 'ekspor dokumen terbuka untuk Super Admin');
ok(str_contains($r['headers'], 'Content-Type: text/csv'), 'jawaban memuat Content-Type teks/csv');
ok(str_contains($r['headers'], 'Content-Disposition: attachment; filename="dokumen-spmi-'), 'berkas ditawarkan sebagai unduhan');
ok(str_starts_with($r['body'], "\xEF\xBB\xBF"), 'awal berkas memuat BOM UTF-8');
ok(str_contains($r['body'], 'Judul;Nomor dokumen'), 'baris judul kolom CSV ikut tertulis');
ok(str_contains($r['body'], 'SOP Evaluasi Dosen'), 'isi ekspor sama dengan isi halaman daftar');

$r = $admin->getRaw('/documents/export?status=red');
ok(str_contains($r['body'], 'Pedoman Lama 2024') && !str_contains($r['body'], 'SOP Evaluasi Dosen'),
    'saringan status warna ikut berlaku pada ekspor');

$r = $admin->getRaw('/documents/export?q=SOP');
ok(str_contains($r['body'], 'SOP Evaluasi Dosen') && !str_contains($r['body'], 'Pedoman Lama 2024'),
    'saringan pencarian ikut berlaku pada ekspor');

// Judul berisi pemisah titik koma & kutip ganda — menguji pelolakan RFC 4180.
$escTitle = 'Uji Escaping; "Kutip" FR-14';
$pdo->prepare(
    "INSERT INTO documents (title, doc_number, version, unit_id, owner_user_id, file_path,
                            effective_date, expiry_date, status_color)
     VALUES (?, 'FR14/ESC/2026', '1.0', 1, 1, 'uji/fr14-escaping.pdf', '2026-01-01', ?, 'green')"
)->execute([$escTitle, date('Y-m-d', strtotime('+10 days'))]);
$docEsc = (int)$pdo->lastInsertId();

$r = $admin->getRaw('/documents/export?q=' . rawurlencode('Uji Escaping'));
ok(str_contains($r['body'], '"Uji Escaping; ""Kutip"" FR-14"'),
    'sel berisi pemisah & kutip ganda dikutip sesuai RFC 4180');
ok(!str_contains($r['body'], 'SOP Evaluasi Dosen'), 'ekspor terbatas pada hasil saringan');

$r = $guest->get('/documents/export');
ok(str_contains($r['url'], '/login'), 'pengunjung belum masuk → dialihkan ke halaman masuk');

/* ================================================== B. Ekspor rekap Monev (FR-14) === */

echo "\n=== B. Ekspor rekap Monev ===\n";

$r = $admin->getRaw('/monev/export?period_id=1');
ok($r['code'] === 200 && str_contains($r['headers'], 'Content-Type: text/csv'), 'ekspor Monev terbuka sebagai CSV');
ok(str_contains($r['body'], 'REKAP PER UNIT') && str_contains($r['body'], 'Lembaga Penjaminan Mutu'),
    'blok rekap per unit ikut terisi');
ok(str_contains($r['body'], 'RERATA CAPAIAN PER INDIKATOR') && str_contains($r['body'], 'M1.1'),
    'blok rerata capaian per indikator ikut terisi');

$r = $member->getRaw('/monev/export?period_id=1');
ok($r['code'] === 200 && str_contains($r['headers'], 'Content-Type: text/csv'),
    'anggota juga dapat mengekspor rekap Monev');

$r = $admin->get('/monev/export?period_id=999999');
ok(str_contains($r['url'], '/monev') && str_contains($r['body'], 'Dasbor Monev'),
    'periode tidak ditemukan → kembali ke dasbor Monev');

/* ============================ C. Ekspor laporan AMI (RBAC) & rencana aksi (FR-14) === */

echo "\n=== C. Ekspor laporan AMI & rencana aksi ===\n";

$r = $admin->getRaw('/ami/export?schedule_id=1');
ok($r['code'] === 200 && str_contains($r['headers'], 'Content-Type: text/csv'), 'ekspor laporan AMI terbuka sebagai CSV');
ok(str_contains($r['body'], 'REKAP SKOR PER UNIT'), 'blok rekap skor per unit ikut terisi');
ok(str_contains($r['body'], 'REKAP PER STANDAR') && str_contains($r['body'], 'Standar Pendidikan'),
    'blok rekap per standar ikut terisi');
ok(str_contains($r['body'], 'TEMUAN AUDIT'), 'blok temuan ikut terisi');

$r = $member->get('/ami/export?schedule_id=1');
ok($r['code'] === 403, 'anggota tidak dapat mengekspor laporan AMI (khusus Super Admin)');

// Fixture M4: jadwal + penugasan + temuan + dua rencana aksi (PIC berbeda).
// Tahun 2027 karena satu tahun hanya boleh punya satu jadwal (uq_ami_year).
$pdo->prepare("INSERT INTO ami_schedules (year, name, start_date, end_date, status)
               VALUES (?, ?, ?, ?, 'running')")
    ->execute([2027, 'AMI UJI FR14', date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('+5 days'))]);
$schedUji = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO ami_assignments (schedule_id, unit_id, auditor_user_id, status)
               VALUES (?, 2, 1, 'assigned')")->execute([$schedUji]);
$assId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO ami_findings (assignment_id, description, severity, finding_type, status)
               VALUES (?, 'Temuan uji FR14/FR15', 'sedang', 'ketidaksesuaian', 'open')")->execute([$assId]);
$findId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO action_plans (finding_id, pic_user_id, pic_unit_id, description, due_date, status, priority)
               VALUES (?, 1, 1, 'Rencana uji admin prioritas tinggi', ?, 'open', 'tinggi')")
    ->execute([$findId, date('Y-m-d', strtotime('+2 days'))]);
$plan1 = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO action_plans (finding_id, pic_user_id, pic_unit_id, description, due_date, status, priority)
               VALUES (?, 2, 2, 'Rencana uji member prioritas sedang', ?, 'in_progress', 'sedang')")
    ->execute([$findId, date('Y-m-d', strtotime('+3 days'))]);
$plan2 = (int)$pdo->lastInsertId();

$qs = 'schedule_id=' . $schedUji;
$r  = $admin->getRaw('/followup/export?' . $qs);
ok($r['code'] === 200 && str_contains($r['headers'], 'Content-Type: text/csv'), 'ekspor rencana aksi terbuka sebagai CSV');
ok(str_contains($r['body'], 'Jadwal AMI;Temuan;Indikator'), 'baris judul kolom rencana aksi tertulis');
ok(str_contains($r['body'], 'AMI UJI FR14'), 'nama jadwal ikut terbawa pada ekspor');
ok(str_contains($r['body'], 'Rencana uji admin prioritas tinggi')
    && str_contains($r['body'], 'Rencana uji member prioritas sedang'),
    'Super Admin melihat seluruh rencana aksi');

$r = $admin->getRaw('/followup/export?' . $qs . '&status=open');
ok(str_contains($r['body'], 'Rencana uji admin') && !str_contains($r['body'], 'Rencana uji member'),
    'saringan status ikut berlaku pada ekspor');

$r = $admin->getRaw('/followup/export?' . $qs . '&priority=tinggi');
ok(str_contains($r['body'], 'Rencana uji admin') && !str_contains($r['body'], 'Rencana uji member'),
    'saringan prioritas ikut berlaku pada ekspor');

$r = $member->getRaw('/followup/export?' . $qs);
ok(str_contains($r['body'], 'Rencana uji member') && !str_contains($r['body'], 'Rencana uji admin'),
    'ekspor anggota terbatas pada rencana aksi dengan PIC-nya sendiri');

/* ================================================== D. Cetak/PDF halaman laporan === */

echo "\n=== D. Cetak/PDF halaman laporan ===\n";

$pdo->prepare("INSERT INTO monev_reports (period_id, unit_id, submitted_by, status, total_score)
               VALUES (1, 1, 1, 'approved', 88.50)")->execute();
$repId = (int)$pdo->lastInsertId();

$r = $admin->get('/monev/reports/' . $repId);
ok($r['code'] === 200 && str_contains($r['body'], 'window.print()'),
    'halaman detail laporan Monev menyediakan tombol Cetak/PDF');

$r = $admin->get('/ami/report?schedule_id=1');
ok($r['code'] === 200 && str_contains($r['body'], 'window.print()'),
    'halaman laporan AMI menyediakan tombol Cetak/PDF');

/* ================================================== E. Notifikasi tenggat (FR-15) === */

echo "\n=== E. Notifikasi tenggat ===\n";

$r = $admin->get('/');
ok($r['code'] === 200, 'dasbor terbuka dan menjadi pemicu penjaga tenggat');

$n = row($pdo, "SELECT * FROM notifications
                 WHERE type = 'tenggat' AND ref_table = 'documents' AND ref_id = ? AND user_id = 1",
    [$docEsc]);
ok($n !== null && str_contains((string)$n['title'], 'Masa berlaku dokumen'),
    'notifikasi masa berlaku dokumen terkirim ke pemilik + LPM');
ok($n !== null && str_contains((string)$n['message'], 'hari lagi'),
    'pesan memuat sisa masa berlaku dokumen');

$cnt1 = (int)val($pdo, "SELECT COUNT(*) FROM notifications
                         WHERE type = 'tenggat' AND ref_table = 'documents' AND ref_id = ?", [$docEsc]);
$admin->get('/documents');
$cnt2 = (int)val($pdo, "SELECT COUNT(*) FROM notifications
                         WHERE type = 'tenggat' AND ref_table = 'documents' AND ref_id = ?", [$docEsc]);
ok($cnt1 === 1 && $cnt2 === 1, 'pemuatan halaman berulang tidak menggandakan notifikasi');

// Dokumen kedaluwarsa — pemilik (anggota) + LPM.
$pdo->prepare("INSERT INTO documents (title, doc_number, version, unit_id, owner_user_id, file_path,
                                      effective_date, expiry_date, status_color)
               VALUES ('Uji Dokumen Kedaluwarsa FR15', 'FR15/EXP/2026', '1.0', 2, 2,
                       'uji/fr15-expired.pdf', '2025-01-01', ?, 'green')")
    ->execute([date('Y-m-d', strtotime('-3 days'))]);
$docExp = (int)$pdo->lastInsertId();

$member->get('/');
$n = row($pdo, "SELECT * FROM notifications
                 WHERE type = 'tenggat' AND ref_table = 'documents' AND ref_id = ? AND user_id = 2",
    [$docExp]);
ok($n !== null && str_contains((string)$n['message'], 'sudah kedaluwarsa'),
    'dokumen kedaluwarsa memberi tahu pemiliknya');
ok(row($pdo, "SELECT id FROM notifications
               WHERE type = 'tenggat' AND ref_table = 'documents' AND ref_id = ? AND user_id = 1",
        [$docExp]) !== null, 'LPM (Super Admin) ikut menerima pemberitahuan kedaluwarsa');

// Periode Monev yang hampir berakhir.
$pdo->prepare("INSERT INTO monev_periods (name, start_date, end_date, status)
               VALUES ('Monev UJI FR15', ?, ?, 'open')")
    ->execute([date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('+5 days'))]);
$mpId = (int)$pdo->lastInsertId();

$admin->get('/');
$n = row($pdo, "SELECT * FROM notifications
                 WHERE type = 'tenggat' AND ref_table = 'monev_periods' AND ref_id = ? AND user_id = 1",
    [$mpId]);
ok($n !== null && str_contains((string)$n['message'], 'hari lagi'), 'tenggat periode Monev terkirim ke LPM');

$r = $admin->get('/notifications');
ok(str_contains($r['body'], 'Buka periode Monev'), 'tautan periode Monev muncul di daftar notifikasi');
ok(str_contains($r['body'], '>tenggat</span>'), 'jenis notifikasi tenggat ditampilkan pada daftar');

// Jadwal AMI yang hampir berakhir (fixture jadwal dari bagian C).
$n = row($pdo, "SELECT * FROM notifications
                 WHERE type = 'tenggat' AND ref_table = 'ami_schedules' AND ref_id = ? AND user_id = 1",
    [$schedUji]);
ok($n !== null && str_contains((string)$n['message'], 'hari lagi'), 'tenggat jadwal AMI terkirim ke LPM');

$r = $admin->get('/notifications');
ok(str_contains($r['body'], 'Buka jadwal AMI'), 'tautan jadwal AMI muncul di daftar notifikasi');

// Tenggat rencana aksi — PIC + LPM.
$n = row($pdo, "SELECT * FROM notifications
                 WHERE type = 'tenggat' AND ref_table = 'action_plans' AND ref_id = ? AND user_id = 1",
    [$plan1]);
ok($n !== null, 'tenggat rencana aksi terkirim ke PIC (Rencana #1 → Super Admin)');

$n = row($pdo, "SELECT * FROM notifications
                 WHERE type = 'tenggat' AND ref_table = 'action_plans' AND ref_id = ? AND user_id = 2",
    [$plan2]);
$both = (int)val($pdo, "SELECT COUNT(*) FROM notifications
                         WHERE type = 'tenggat' AND ref_table = 'action_plans' AND ref_id = ?", [$plan2]);
ok($n !== null && $both === 2, 'tenggat rencana aksi terkirim ke PIC + LPM');

$r = $member->get('/notifications');
ok(str_contains($r['body'], 'Tenggat tindak lanjut') && str_contains($r['body'], 'Buka rencana aksi'),
    'anggota melihat notifikasi tenggat rencana aksinya');

// Stabilitas: permintaan ulang tidak menambah notifikasi tenggat apa pun.
$before = (int)val($pdo, "SELECT COUNT(*) FROM notifications WHERE type = 'tenggat'");
$admin->get('/');
$member->get('/');
$after = (int)val($pdo, "SELECT COUNT(*) FROM notifications WHERE type = 'tenggat'");
ok($before === $after && $before > $baselineTenggat,
    'jumlah notifikasi tenggat stabil pada permintaan ulang (tidak dobel)');

/* ============================================ F. Pembersihan data uji & baseline === */

echo "\n=== F. Pembersihan data uji ===\n";

$pdo->prepare("DELETE FROM notifications
                WHERE (ref_table = 'documents'     AND ref_id IN (?, ?))
                   OR (ref_table = 'monev_periods' AND ref_id = ?)
                   OR (ref_table = 'ami_schedules' AND ref_id = ?)
                   OR (ref_table = 'action_plans'  AND ref_id IN (?, ?))")
    ->execute([$docEsc, $docExp, $mpId, $schedUji, $plan1, $plan2]);
$pdo->prepare('DELETE FROM action_plans  WHERE id IN (?, ?)')->execute([$plan1, $plan2]);
$pdo->prepare('DELETE FROM ami_findings  WHERE id = ?')->execute([$findId]);
$pdo->prepare('DELETE FROM ami_assignments WHERE id = ?')->execute([$assId]);
$pdo->prepare('DELETE FROM ami_schedules WHERE id = ?')->execute([$schedUji]);
$pdo->prepare('DELETE FROM monev_reports WHERE id = ?')->execute([$repId]);
$pdo->prepare('DELETE FROM monev_periods WHERE id = ?')->execute([$mpId]);
$pdo->prepare('DELETE FROM documents WHERE id IN (?, ?)')->execute([$docEsc, $docExp]);

ok((int)val($pdo, 'SELECT COUNT(*) FROM documents WHERE id IN (?, ?)', [$docEsc, $docExp]) === 0,
    'dokumen uji terhapus');
ok((int)val($pdo, 'SELECT COUNT(*) FROM monev_periods WHERE id = ?', [$mpId]) === 0, 'periode Monev uji terhapus');
ok((int)val($pdo, 'SELECT COUNT(*) FROM monev_reports WHERE id = ?', [$repId]) === 0, 'laporan Monev uji terhapus');
ok((int)val($pdo, 'SELECT COUNT(*) FROM ami_schedules WHERE id = ?', [$schedUji]) === 0, 'jadwal AMI uji terhapus');
ok((int)val($pdo, 'SELECT COUNT(*) FROM action_plans WHERE id IN (?, ?)', [$plan1, $plan2]) === 0,
    'rencana aksi uji terhapus');

$clean = true;
foreach ($baseline as $t => $n) {
    $now = (int)val($pdo, "SELECT COUNT(*) FROM {$t}");
    if ($now !== $n) {
        $clean = false;
        echo "[FAIL] jumlah {$t}: baseline {$n} → sekarang {$now}\n";
    }
}
ok($clean, 'seluruh tabel kembali ke jumlah data awal (tanpa sisa data uji)');

$tenNow = (int)val($pdo, "SELECT COUNT(*) FROM notifications WHERE type = 'tenggat'");
ok($tenNow === $baselineTenggat, 'notifikasi tenggat kembali ke jumlah awal (hanya sisa data benih)');

echo "\n========================================\n";
echo "LULUS: $pass | GAGAL: $fail\n";
exit($fail > 0 ? 1 : 0);
