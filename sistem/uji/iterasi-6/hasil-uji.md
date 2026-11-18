# Hasil Uji Iterasi 6 — Pelengkap M5: FR-14 Ekspor & FR-15 Notifikasi Tenggat

- **Tanggal:** 17 November 2026
- **Skrip:** `test-pelengkap.php` — jalankan dengan `php test-pelengkap.php` (server `:8080` + MySQL hidup)
- **Akun uji:** `admin@stikomelrahma.ac.id` (Super Admin/LPM) & `waket1@stikomelrahma.ac.id` (Anggota, Waket I)
- **Cakupan:** FR-14 (ekspor CSV siap Excel di 4 titik + Cetak/PDF) & FR-15 (penjaga tenggat otomatis) + saringan, RBAC, pelolakan CSV, deduplikasi notifikasi, pembersihan data uji
- **Hasil akhir: LULUS 55 pemeriksaan — GAGAL 0**
- **Regresi penuh setelah uji:** 65/65 (iterasi 3) + 162/162 (iterasi 4) + 101/101 (iterasi 5) + 55/55 (iterasi 6) = **383/383**

> **Urutan wajib:** jalankan skrip ini **lebih dulu** sebelum suite iterasi 3–5. Skrip ini
> sekaligus "pemanasan" penjaga tenggat atas data benih (dokumen seed kedaluwarsa dalam
> 30 hari), sehingga jumlah notifikasi pada suite lama stabil berapa pun urutannya.
> Setelah reset basis data, buka aplikasi sekali sebelum suite regresi.

## Ringkasan hasil

| Seksi | Pemeriksaan | Temuan |
|---|---|---|
| Pemanasan FR-15 + masuk | 3 | Penjaga tenggat sudah menyapa data benih sebelum baseline; kedua akun masuk |
| A. Ekspor dokumen SPMI (FR-14) | 11 | CSV sah dengan BOM UTF-8 & `Content-Disposition`; saringan status/kata kunci ikut terbawa; sel berisi pemisah & kutip ganda dikutip RFC 4180; tamu dialihkan ke login |
| B. Ekspor rekap Monev | 5 | Dua blok (per unit + rerata per indikator) terisi; anggota boleh mengekspor; periode tidak ditemukan → kembali ke dasbor |
| C. Ekspor laporan AMI & rencana aksi | 12 | Tiga blok AMI terisi; **anggota ditolak 403** pada ekspor AMI; saringan status/prioritas ikut; **ekspor anggota terbatas pada PIC miliknya** |
| D. Cetak/PDF halaman laporan | 2 | Detail laporan Monev menyediakan Cetak/PDF (baru); laporan AMI tetap tersedia |
| E. Notifikasi tenggat (FR-15) | 15 | Empat jenis tenggat terkirim (masa berlaku dokumen, dokumen kedaluwarsa, periode Monev, jadwal AMI, rencana aksi) ke penerima yang berwenang; tautan `monev_periods`/`ami_schedules` tampil; **deduplikasi permanen** — pemuatan ulang tidak menggandakan |
| F. Pembersihan data uji | 7 | Seluruh fixture terhapus; 8 tabel kembali persis ke baseline; notifikasi tenggat kembali ke jumlah data benih |
| **Total** | **55** | **0 gagal** |

## Bukti fitur

### FR-14 — Ekspor laporan (4 titik, CSV siap Excel)

| Endpoint | Isi berkas | Akses |
|---|---|---|
| `GET /documents/export` | 12 kolom daftar dokumen + label warna/persetujuan Indonesia | Semua peran (anggota terbatas unitnya) |
| `GET /monev/export?period_id=` | Blok rekap per unit + blok rerata capaian per indikator | Semua peran |
| `GET /ami/export?schedule_id=` | Blok skor per unit + rekap per standar + temuan audit | **Khusus Super Admin (403)** |
| `GET /followup/export` | Rencana aksi lengkap (PIC, tenggat, bukti, verifikasi, dokumen revisi) | Super Admin semua; anggota hanya PIC-nya |

- Berkas CSV: **BOM UTF-8**, pemisah **titik koma**, kutip ganda **RFC 4180** — ditulis
  manual melalui `export_csv()` agar ringan dan bebas deprecation `fputcsv` PHP 8.4.
- **Saringan halaman asal ikut terbawa** sehingga ekspor identik dengan tampilan layar.
- Rute `/documents/export` didaftarkan **sebelum** `/documents/{id}` agar tidak tertelan
  regex parameter `{id}`.
- **Cetak/PDF** (`window.print()` + `@media print`) ditambahkan ke halaman detail
  laporan Monev (sebelumnya hanya laporan AMI & rekap kepuasan).

### FR-15 — Notifikasi tenggat (penjaga otomatis)

`Notification::checkDeadlines()` berjalan pada **setiap permintaan** (padanan "timer
pengingat" aktor A3):

| Peristiwa | Jendela | Penerima |
|---|---|---|
| Masa berlaku dokumen disetujui mendekat | ≤ 30 hari | Pemilik dokumen + LPM |
| Dokumen sudah kedaluwarsa | ≤ 0 hari | Pemilik dokumen + LPM |
| Periode Monev hampir berakhir | ± 7 hari dari akhir periode | LPM |
| Jadwal AMI hampir berakhir | ± 7 hari dari akhir jadwal | LPM |
| Tenggat rencana aksi | ± 7 hari dari tenggat | PIC + LPM |

- ENUM `notifications.type` sudah memuat `'tenggat'` — **tanpa ALTER tabel**.
- **Deduplikasi permanen** per (penerima, `type='tenggat'`, `ref_table`, `ref_id`) —
  pemuatan halaman berulang tidak menggandakan notifikasi.
- Cabang tautan baru `monev_periods` dan `ami_schedules` pada daftar notifikasi
  (tautan `documents`, `monev_reports`, `ami_assignments`, `ami_findings`,
  `action_plans`, `satisfaction_surveys` sudah ada).

## Pembersihan data uji

Fixture skrip (2 dokumen uji, 1 periode Monev uji, 1 laporan Monev uji, 1 jadwal AMI
uji + penugasan + temuan, 2 rencana aksi uji, dan seluruh notifikasi terkait) dihapus di
akhir skrip. Delapan tabel dibandingkan dengan baseline: `notifications`, `documents`,
`monev_periods`, `monev_reports`, `ami_schedules`, `ami_assignments`, `ami_findings`,
`action_plans` — seluruhnya kembali persis ke jumlah data awal, dan jumlah notifikasi
tenggat kembali ke jumlah data benih (1).

## Pemeriksaan regresi

Suite iterasi 3–5 masing-masing diberi **baris pemanasan FR-15** (satu permintaan
`GET /login` sebelum baseline ditangkap) sehingga jumlah notifikasi stabil berapa pun
urutan suite dijalankan. Setelah suite iterasi 6 selesai:

| Suite | Skrip | Hasil |
|---|---|---|
| Iterasi 3 — Monev | `iterasi-3-monev/test-monev.php` | LULUS 65/65 |
| Iterasi 4 — AMI | `iterasi-4-ami/test-ami.php` | LULUS 162/162 |
| Iterasi 5 — M4 | `iterasi-5-followup/test-followup.php` | LULUS 101/101 |
| Iterasi 6 — M5 pelengkap | `iterasi-6/test-pelengkap.php` | LULUS 55/55 |
| **Total** | | **LULUS 383/383** |
