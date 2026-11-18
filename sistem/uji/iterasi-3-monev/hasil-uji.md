# Hasil Uji Iterasi 3 — Modul M2 Monev

**Tanggal:** 16 November 2026 · **Metode:** uji hitung-mundur otomatis (65 pemeriksaan) + verifikasi langsung ke basis data
**Berkas uji:** `test-monev.php` — jalankan `php test-monev.php` (server `:8080` + MariaDB harus hidup)
**Status: LULUS 65 / GAGAL 0**

## Cakupan uji

| # | Seksi | Jumlah | Cakupan |
|---|---|---|---|
| A | Masuk sebagai Super Admin | 2 | login admin → dasbor |
| B | Halaman & RBAC | 5 | dasbor Monev (FR-05), halaman periode/indikator, penolakan CSRF 419 |
| C | Kelola periode | 7 | buat periode, tolak nama ganda, tolak rentang terbalik, tutup/buka kembali |
| D | Kelola indikator | 6 | tambah, tolak kode ganda, halaman edit, ubah target, validasi |
| E | Isian capaian unit (FR-04) | 9 | formulir, simpan draf + bukti unggah, **capaian 90%/100%**, **skor tertimbang 96%**, ajukan |
| F | Unduh bukti | 1 | berkas bukti dapat diunduh |
| G | Review LPM | 3 | setujui, status tersimpan, persetujuan ganda ditolak |
| H | Dasbor | 2 | skor 96% tampil, pemilih periode |
| I | RBAC anggota (Waket I) | 12 | buka dasbor, **403** di kelola periode/indikator, **403** isi unit lain, isi unit sendiri, skor 100%, lihat laporan lain, **403** menyetujui |
| J | Pengembalian & notifikasi | 8 | tolak pengembalian tanpa catatan, kembalikan + catatan tersimpan, notifikasi ke anggota, edit & ajukan ulang |
| K | Periode tertutup | 2 | pengisian pada periode ditutup ditolak (GET & POST) |
| L | Jejak audit | 2 | aksi monev_reports & monev_periods tercatat |
| M | Kondisi tidak ada | 1 | laporan tak ditemukan → 404 |
| N | Pembersihan data uji | 4 | data uji dihapus, DB kembali bersih |

## Perhitungan yang diverifikasi ke basis data

| Uji | Rumus | Hasil |
|---|---|---|
| Capaian indikator 1 | 90 ÷ 100 × 100 | **90%** |
| Capaian indikator 2 | 80 ÷ 80 × 100 | **100%** |
| Skor total tertimbang | (90×1 + 100×1,5) ÷ (1+1,5) | **96%** |
| Skor anggota (4 indikator tepat target) | rerata tertimbang | **100%** |

Aturan lampu: **≥100 hijau · ≥80 kuning · <80 merah** (berlaku untuk indikator maupun skor laporan).

## Perbaikan yang lahir dari pengujian

1. **Alur edit laporan** — tautan "Edit / lengkapi isian" sebelumnya berputar ke halaman detail; `report()` kini menampilkan formulir terisi ulang untuk draf, mengunci laporan yang sudah disetujui, dan menolak periode tertutup.
2. **`notifications.type` ENUM** — nilai `'monev'` belum ada sehingga pengiriman notifikasi gagal (500) setelah status berubah; ENUM diperluas menjadi `('persetujuan','tenggat','monev','dokumen','temuan','umum')` pada `03-database.sql` **dan** basis data aktif (`ALTER TABLE`).

> Catatan: dua kegagalan awal berasal dari skrip uji (metode curl yang salah & array bersarang), bukan dari aplikasi.
