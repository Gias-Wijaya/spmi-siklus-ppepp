# Hasil Uji Iterasi 4 — Modul M3 AMI & Evaluasi

- **Tanggal:** 16 November 2026 (diperbarui 19 Nov 2026 — Revisi Review LPM)
- **Skrip:** `test-ami.php` — jalankan dengan `php test-ami.php` (server `:8080` + MariaDB hidup)
- **Akun uji:** `admin@stikomelrahma.ac.id` (Super Admin/LPM), `waket1@stikomelrahma.ac.id` (Anggota/auditee), `staf-lpm-uji@stikomelrahma.ac.id` (staf LPM — auditor)
- **Cakupan:** FR-06 (instrumen fleksibel), FR-07 (pelaksanaan AMI tahunan + kepuasan), FR-08 (skor/rekap/laporan/gap otomatis) + Revisi Review LPM: Item 4 (bukti sebelum skor), Item 5 (grafik), Item 8 (auditor LPM/auditee waket), Item 11 (tipe kepuasan), Item 12 (mode tamu)
- **Hasil akhir: LULUS 223 pemeriksaan — GAGAL 0**

## Ringkasan hasil

| Seksi | Pemeriksaan | Temuan |
|---|---|---|
| A. Akses & dasbor M3 | 16 | Dasbor, grafik unit/standar (**judul "rata-rata skala 0–4" tanpa "gap", "Belum ada penilaian", sumbu seluruh standar S1–S8 — foto-05**), ringkasan kepuasan; anggota 403 di halaman kelola |
| B. Jadwal AMI (siklus) | 8 | Tahun unik, nama wajib, draft → berjalan; seed AMI 2026 tampil |
| C. Instrumen fleksibel + jejak (FR-06) | 23 | CRUD, kode ganda/uraian kosong/bobot 0 ditolak; **edit → 1 baris `ami_instrument_logs` (nilai lama, baru, alasan)**; nonaktifkan/hapus; anggota 403 |
| D. Penugasan auditor–auditee (FR-07, Item 8) | 20 | **auditee wajib waket — LPM ditolak**; **auditor wajib pengguna unit LPM (staf LPM) — non-LPM ditolak (form buat & edit)**; pasangan auditor/unit, unit ganda ditolak, edit nomor laporan |
| E. CSRF & 404 | 5 | 419 tanpa token; 404 untuk penugasan/instrumen/formulir/bukti tak ada |
| F. Penilaian 0–4 + temuan otomatis (Item 4) | 39 | **mode auditee: unggah bukti saja — POST skor 403**, baris skor `NULL`, status tetap; **auditor menilai → skor tersimpan**, skor ≤2 tanpa catatan ditolak, temuan otomatis `identified_by` auditor; **bukti terunggah & terunduh**; skor tertimbang **2,67** & **3,67**; admin menimpa bukti auditee; notifikasi ke LPM |
| G. Jadwal ditutup mengunci seluruh isian | 7 | Penilaian/instrumen/penugasan terkunci; penilaian tampil baca-saja; **auditee ikut terkunci** |
| H. Temuan: manual, filter, transisi status | 11 | Auditor catat temuan sendiri, ditolak di unit lain; filter keparahan/status; transisi status + notifikasi pencatat; anggota 403 ubah status |
| I. Laporan AMI (FR-08) & rekap kepuasan | 45 | Laporan 5 bagian A–E + skor unit + **gap terbesar ditandai**; kepuasan: buka → isi (skala+teks) → **rerata (4+5)/2 = 4,5 → 90,0%** → tutup mengunci; pertanyaan berjawab → dinonaktifkan; satu jawaban per pengguna |
| I2. Tipe kepuasan & mode tamu (Item 11 + 12) | 34 | **form Tambah 3 jenis + label "Teks — isian singkat/feedback/saran"**, pilihan tanpa opsi ditolak, opsi tersimpan `Ya|Tidak`; **tautan `/puas/{token}`**: aktif/dinonaktifkan, halaman tamu tanpa login, pilihan dirender radio, token salah 404, wajib pilih, respons `respondent_user_id` NULL → **"Tamu"** di rekap, sekali per sesi, formulir ditutup → terkunci |
| J. Jejak audit & keamanan RBAC tambahan | 8 | `ami_instruments`, `ami_assignments`, `ami_findings/assessments`, `satisfaction*` tercatat; 403 anggota |
| K. Pembersihan data uji | 7 | Cascade menghapus jadwal uji, formulir uji & respons tamu; **seluruh tabel kembali persis ke jumlah data awal** |
| **Total** | **223** | **0 gagal** |

## Perbaikan yang lahir dari pengujian

1. **Log jejak perubahan — perbandingan numerik.** Field `weight` (DECIMAL `2.00`) dibandingkan sebagai string dengan nilai `2.0` sehingga *selalu* dianggap berubah (log sampah di setiap simpan). Diperbaiki: field numerik (`weight`, `sort_order`, `standard_id`, `is_active`) dibandingkan secara numerik.
2. **Pesan galat penugasan & pertanyaan.** `storeAssignment`/`storeQuestion` hanya menampilkan flash generik (“Periksa kembali isian”) sehingga pengguna tidak tahu penyebabnya (mis. “unit sudah ditugaskan”). Kini detail galat ditampilkan.
3. *(dari skrip uji)* Argumen berkas pada skrip harus dipisah koma sebagai parameter ketiga — array `evidence[...]` yang tergabung ke *fields* terkirim sebagai teks, bukan unggahan.

## Catatan desain yang terverifikasi

- **Skor tertimbang AMI:** `Σ(skor×bobot) ÷ Σ(bobot)` atas indikator aktif yang sudah dinilai; lampu ≥3,0 hijau · ≥2,0 kuning · <2,0 merah.
- **Temuan otomatis:** skor ≤ 2 wajib bercatatan → satu baris `ami_findings` (`ketidaksesuaian`, status `open`, keparahan mengikuti skor: ≤1 tinggi, 2 sedang); diperbarui selama masih `open`.
- **Gap analisis:** `4,00 − rerata skor standar`; standar dengan gap terbesar ditandai “prioritas peningkatan” di laporan.
- **Kepuasan:** satu respons per pengguna per formulir; rerata tertimbang hanya atas pertanyaan skala terjawab (÷5 ×100%); pertanyaan berjawab tidak dapat dihapus (dinonaktifkan). **Tiga jenis lengkap** (skala/pilihan/teks) — pilihan wajib ≥2 opsi (`options`, dipisah `|`); **mode tamu** `/puas/{token}`: tanpa akun (`respondent_user_id` NULL → "Tamu"), sekali per sesi perangkat, tautan dapat dimatikan.
- **Penguncian jadwal `closed`:** instrumen, penugasan, penilaian, dan pertanyaan terkunci — tetap dapat dibuka kembali (`closed → running`).
- **Bukti sebelum skor (Item 4):** auditee hanya mengunggah bukti (skor `NULL`); auditor LPM menilai — status "penilaian lengkap" hanya bila seluruh indikator sudah bernilai.
