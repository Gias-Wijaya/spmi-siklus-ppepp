# Hasil Uji Iterasi 3 — Modul M2 Monev

**Tanggal:** 16 November 2026 (diperbarui 19 Nov 2026 — Revisi Review LPM) · **Metode:** uji hitung-mundur otomatis (79 pemeriksaan) + verifikasi langsung ke basis data
**Berkas uji:** `test-monev.php` — jalankan `php test-monev.php` (server `:8080` + MariaDB harus hidup)
**Status: LULUS 79 / GAGAL 0**

## Cakupan uji

| # | Seksi | Jumlah | Cakupan |
|---|---|---|---|
| A | Masuk sebagai Super Admin (LPM) | 2 | login admin → dasbor |
| B | Halaman & RBAC | 9 | dasbor Monev (FR-05), **tombol "Kelola Indikator" permanen (foto-13)**, **label "Rata-rata (n unit)" + dropdown filter unit (foto-07)**, halaman periode/indikator, penolakan CSRF 419 |
| C | Kelola periode | 7 | buat periode, tolak nama ganda, tolak rentang terbalik, tutup/buka kembali |
| D | Kelola indikator | 6 | tambah, tolak kode ganda, halaman edit, ubah target, validasi |
| E | Isian capaian unit (FR-04) | 15 | formulir **tanpa cache (foto-14)**, simpan draf + bukti unggah, **capaian 90%/100%**, **skor tertimbang 96%**, **ajukan penuh wajib nilai+bukti+catatan (foto-02)**, laporan tetap draf bila ditolak, **capaian >100% dibatasi 100% (foto-03 — kasus persis review)**, ajukan → menunggu review, **skor 98,46%** |
| F | Unduh bukti | 1 | berkas bukti dapat diunduh |
| G | Review LPM | 3 | setujui, status tersimpan, persetujuan ganda ditolak |
| H | Dasbor | 4 | skor 98,5% tampil, pemilih periode, **detail tanpa cache (foto-14)**, **filter unit belum mengisi "Rata-rata (0 unit)" (foto-07)** |
| I | RBAC anggota (Waket I) | 13 | buka dasbor, **403** di kelola periode/indikator/edit, **403** isi unit lain, isi unit sendiri, skor 100%, **notifikasi "menunggu review" tepat 1 (foto-05)**, lihat laporan lain, **403** menyetujui |
| J | Pengembalian & notifikasi | 10 | tolak pengembalian tanpa catatan, kembalikan + catatan tersimpan, notifikasi ke anggota, edit & ajukan ulang, **pengajuan ulang mengganti notifikasi lama — tetap 1 (foto-05)**, **capaian 110% tetap ≤100% (foto-03)** |
| K | Periode tertutup | 2 | pengisian pada periode ditutup ditolak (GET & POST) |
| L | Jejak audit | 2 | aksi monev_reports & monev_periods tercatat |
| M | Kondisi tidak ada | 1 | laporan tak ditemukan → 404 |
| N | Pembersihan data uji | 4 | data uji dihapus, DB kembali bersih |
| | **Total** | **79** | **0 gagal** |

## Perhitungan yang diverifikasi ke basis data

| Uji | Rumus | Hasil |
|---|---|---|
| Capaian indikator 1 | 90 ÷ 100 × 100 | **90%** |
| Capaian indikator 2 | 80 ÷ 80 × 100 | **100%** |
| Skor total tertimbang | (90×1 + 100×1,5) ÷ (1+1,5) | **96%** |
| Skor anggota (4 indikator tepat target) | rerata tertimbang | **100%** |
| Skor total (5 indikator, capaian dikunci 100%) | (90+150+100+100+200) ÷ 6,5 | **98,46% → tampil 98,5%** |

Aturan lampu: **≥100 hijau · ≥80 kuning · <80 merah** (berlaku untuk indikator maupun skor laporan).

## Perbaikan yang lahir dari pengujian

1. **Alur edit laporan** — tautan "Edit / lengkapi isian" sebelumnya berputar ke halaman detail; `report()` kini menampilkan formulir terisi ulang untuk draf, mengunci laporan yang sudah disetujui, dan menolak periode tertutup.
2. **`notifications.type` ENUM** — nilai `'monev'` belum ada sehingga pengiriman notifikasi gagal (500) setelah status berubah; ENUM diperluas menjadi `('persetujuan','tenggat','monev','dokumen','temuan','umum')` pada `03-database.sql` **dan** basis data aktif (`ALTER TABLE`). *(Revisi Review LPM 19 Nov 2026: tipe `'persetujuan'` dihapus — kini `('tenggat','monev','dokumen','temuan','umum')`.)*

> Catatan: dua kegagalan awal berasal dari skrip uji (metode curl yang salah & array bersarang), bukan dari aplikasi.

## Penambahan Revisi Review LPM (19 November 2026)

Batch A (65 → **79** pemeriksaan) — seluruhnya LULUS:

1. **Validasi submit penuh (foto-02)** — pengajuan ditolak bila ada indikator tanpa nilai/bukti/catatan; laporan tetap draf.
2. **Capaian dibatasi 100% (foto-03)** — nilai di atas target (112,5% / 120% / 110%) dipotong menjadi 100% sejak penyimpanan.
3. **Notifikasi tunggal (foto-05)** — deduplikasi: pengajuan ulang mengganti notifikasi lama, tepat 1 per laporan.
4. **`indicatorAverages($periodId, ?$unitId)`** — rerata lintas unit + dropdown filter unit; label **"Rata-rata (n unit)"** (foto-07), termasuk "Rata-rata (0 unit)".
5. **Tombol "Kelola Indikator" permanen** (foto-13).
6. **`Cache-Control: no-store`** pada formulir isian & detail laporan (foto-14).
