# Hasil Uji Iterasi 4 — Modul M3 AMI & Evaluasi

- **Tanggal:** 16 November 2026
- **Skrip:** `test-ami.php` — jalankan dengan `php test-ami.php` (server `:8080` + MariaDB hidup)
- **Akun uji:** `admin@stikomelrahma.ac.id` (Super Admin/LPM) & `waket1@stikomelrahma.ac.id` (Anggota)
- **Cakupan:** FR-06 (instrumen fleksibel), FR-07 (pelaksanaan AMI tahunan + kepuasan), FR-08 (skor/rekap/laporan/gap otomatis)
- **Hasil akhir: LULUS 162 pemeriksaan — GAGAL 0**

## Ringkasan hasil

| Seksi | Pemeriksaan | Temuan |
|---|---|---|
| A. Akses & dasbor M3 | 9 | Dasbor, grafik unit/standar, ringkasan kepuasan; anggota 403 di halaman kelola |
| B. Jadwal AMI (siklus) | 8 | Tahun unik, nama wajib, draft → berjalan; seed AMI 2026 tampil |
| C. Instrumen fleksibel + jejak (FR-06) | 22 | CRUD, kode ganda/uraian kosong/bobot 0 ditolak; **edit → 1 baris `ami_instrument_logs` (nilai lama, baru, alasan)**; nonaktifkan/hapus; anggota 403 |
| D. Penugasan auditor–auditee (FR-07) | 10 | Pasangan auditor/unit, unit ganda ditolak, edit nomor laporan, auditor = penugasan bukan role |
| E. CSRF & 404 | 5 | 419 tanpa token; 404 untuk penugasan/instrumen/formulir/bukti tak ada |
| F. Penilaian 0–4 + temuan otomatis | 25 | Skor di luar 0–4 & skor ≤2 tanpa catatan ditolak; **bukti terunggah & terunduh**; skor tertimbang **(4×1+2×2)/3 = 2,67** dan **(3×1+4×2)/3 = 3,67**; skor 1 → temuan *tinggi*, diperbarui ke 2 → *sedang*; notifikasi ke LPM |
| G. Kunci jadwal tertutup | 6 | Penilaian/instrumen/penugasan terkunci; penilaian tampil baca-saja |
| H. Temuan (manual, filter, status) | 11 | Auditor catat temuan sendiri, ditolak di unit lain; filter keparahan/status; transisi status + notifikasi pencatat; anggota 403 ubah status |
| I. Laporan & rekap kepuasan (FR-08) | 36 | Laporan 5 bagian A–E + skor unit + **gap terbesar ditandai**; kepuasan: buka → isi (skala+teks) → **rerata (4+5)/2 = 4,5 → 90,0%** → tutup mengunci; pertanyaan berjawab → dinonaktifkan; satu jawaban per pengguna |
| J. Jejak audit & RBAC tambahan | 7 | `ami_instruments`, `ami_assignments`, `ami_findings/assessments`, `satisfaction*` tercatat; 403 anggota |
| K. Pembersihan data uji | 6 | Cascade menghapus jadwal uji & formulir uji; **seluruh 11 tabel kembali persis ke jumlah data awal** |
| **Total** | **162** | **0 gagal** |

## Perbaikan yang lahir dari pengujian

1. **Log jejak perubahan — perbandingan numerik.** Field `weight` (DECIMAL `2.00`) dibandingkan sebagai string dengan nilai `2.0` sehingga *selalu* dianggap berubah (log sampah di setiap simpan). Diperbaiki: field numerik (`weight`, `sort_order`, `standard_id`, `is_active`) dibandingkan secara numerik.
2. **Pesan galat penugasan & pertanyaan.** `storeAssignment`/`storeQuestion` hanya menampilkan flash generik (“Periksa kembali isian”) sehingga pengguna tidak tahu penyebabnya (mis. “unit sudah ditugaskan”). Kini detail galat ditampilkan.
3. *(dari skrip uji)* Argumen berkas pada skrip harus dipisah koma sebagai parameter ketiga — array `evidence[...]` yang tergabung ke *fields* terkirim sebagai teks, bukan unggahan.

## Catatan desain yang terverifikasi

- **Skor tertimbang AMI:** `Σ(skor×bobot) ÷ Σ(bobot)` atas indikator aktif yang sudah dinilai; lampu ≥3,0 hijau · ≥2,0 kuning · <2,0 merah.
- **Temuan otomatis:** skor ≤ 2 wajib bercatatan → satu baris `ami_findings` (`ketidaksesuaian`, status `open`, keparahan mengikuti skor: ≤1 tinggi, 2 sedang); diperbarui selama masih `open`.
- **Gap analisis:** `4,00 − rerata skor standar`; standar dengan gap terbesar ditandai “prioritas peningkatan” di laporan.
- **Kepuasan:** satu respons per pengguna per formulir; rerata tertimbang hanya atas pertanyaan skala terjawab (÷5 ×100%); pertanyaan berjawab tidak dapat dihapus (dinonaktifkan).
- **Penguncian jadwal `closed`:** instrumen, penugasan, penilaian, dan pertanyaan terkunci — tetap dapat dibuka kembali (`closed → running`).
