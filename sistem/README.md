# SISTEM INFORMASI PENJAMINAN MUTU INTERNAL — STIKOM EL RAHMA

**Judul:** Sistem Informasi Penjaminan Mutu Internal untuk Pengelolaan Dokumen dan Kinerja di STIKOM El Rahma Berbasis Siklus PPEPP

## Spesifikasi

| Item | Nilai |
|---|---|
| Bahasa | PHP 8.5 |
| Basis data | MySQL 8 / MariaDB 10.4+ |
| Antarmuka | Bootstrap 5 |
| Hosting | Hosting kampus |
| Desain | Figma (wireframe), Draw.io (DFD/alur), StarUML (UML) |

## Modul

| Kode | Modul | Fungsi | Tahap PPEPP |
|---|---|---|---|
| **M1** | Dokumen SPMI | Unggah, klasifikasi berjenjang, **masa berlaku**, **status 🟢🟡🔴**, riwayat versi (pengesahan TTD basah di luar sistem) | Penetapan & Pelaksanaan |
| **M2** | Monev | Isian capaian **3-bulanan** per unit, grafik tren, telusur ke bukti | Pelaksanaan |
| **M3** | AMI & Evaluasi | **Instrumen fleksibel (indikator bisa diedit)**, jadwal AMI tahunan, penilaian, skor, laporan temuan, **formulir kepuasan** | Evaluasi |
| **M4** | Pengendalian–Peningkatan | Rencana aksi, PIC, tenggat, unggah bukti, verifikasi, umpan balik revisi dokumen | Pengendalian & Peningkatan |
| **M5** | Pendukung | **RBAC** (LPM = Super Admin; Waket I–III = anggota), notifikasi (**penjaga tenggat otomatis**), jejak audit, **ekspor CSV siap Excel + cetak/PDF** | Seluruh tahap |

## Berkas desain

```
sistem/
├── README.md                     ← file ini
├── uji/
│   ├── iterasi-3-monev/          skrip + hasil uji (79 pemeriksaan)
│   ├── iterasi-4-ami/            skrip + hasil uji (223 pemeriksaan)
│   ├── iterasi-5-followup/       skrip + hasil uji (101 pemeriksaan)
│   └── iterasi-6/                skrip + hasil uji (55 pemeriksaan; jalankan lebih dulu)
└── desain/
    ├── 01-use-case.md            daftar aktor + use case + PlantUML
    ├── 02-erd.md                 entitas, relasi, Mermaid ERD
    ├── 03-database.sql           DDL MySQL lengkap + seed data  ← impor dulu
    ├── 04-dfd.md                 DFD Level 0 & Level 1
    └── 05-activity-diagram.md    4 diagram aktivitas
```

## ✅ Status kode: Iterasi 1–6 SELESAI (17 Nov 2026)

Aplikasi nyata sudah dibangun di root proyek dan **teruji end-to-end**:

| Bagian | Berkas |
|---|---|
| Front controller + rute | `public/index.php`, `public/.htaccess`, `public/router.php` (server bawaan PHP) |
| Inti (tanpa framework) | `app/core/` — Database (PDO), Router, Controller, Model, Auth, helpers |
| Model | `app/models/` — User, Unit, Document (+Category/Version), **Monev* (M2)**, **AmiSchedule/Instrument/Assignment/Assessment, Finding, Satisfaction* (M3)**, **ActionPlan, ActionEvidence (M4)**, Notification, AuditLog, Standard |
| Kontroler | `app/controllers/` — Auth, Dashboard, Document (**M1**), **Monev (M2)**, **Ami (M3)**, **ActionPlan (M4)**, User, Unit, Notification, Audit |
| Tampilan | `app/views/` — layout sidebar responsif, dasbor, dokumen, **monev (6)**, **ami (15: dasbor, jadwal, instrumen+jejak, penugasan, penilaian, temuan, laporan, kepuasan×6 termasuk halaman tamu)**, **followup (2: dasbor rencana aksi + detail/bukti/verifikasi/revisi)**, pengguna, unit, notifikasi, jejak audit, 403/404 · **tema: `public/assets/css/app.css` (design system SaaS modern — sidebar gelap navy + biru royal #2563eb, font Inter, kartu radius besar & bayangan berlapis, topbar kaca; Bootstrap Icons)** · **logo institusi otomatis** dari `public/assets/img/logo.{svg,png,webp,jpg}` (helper `logo_file()`; sidebar + halaman masuk + favicon; fallback ikon perisai bila belum ada) · **notifikasi berbasis aksi: SweetAlert2** (toast hasil aksi menggantikan alert halaman + jendela konfirmasi/peringatan `data-confirm` menggantikan `confirm()`/`alert()` browser; cadangan otomatis bila CDN gagal) · **tombol responsif** (`inline-flex` — ikon tak menutupi teks) |

**Sudah teruji (M1):** login/logout · unggah dokumen → **langsung berlaku tanpa alur persetujuan** (versi tercatat) · **status 🟢🟡🔴 otomatis** (48 hari→hijau, 18 hari→kuning, kedaluwarsa→merah) · revisi versi baru · unduh berkas · notifikasi ke pemilik · jejak audit · **RBAC 403** untuk anggota · unit anggota terkunci · tamu dialihkan ke login · 404.

**Sudah teruji (M2, 19 Nov 2026 — LULUS 79/79):** kelola periode & indikator (Super Admin) · isi capaian per unit (draf → ajukan) · unggah/unduh bukti · **hitung capaian & skor tertimbang** · **ajukan penuh wajib nilai+bukti+catatan (foto-02)** · **capaian >100% dibatasi 100% (foto-03)** · review setujui/kembalikan + catatan · **notifikasi tunggal per laporan (foto-05)** · **rerata lintas unit "Rata-rata (n unit)" + filter unit (foto-07)** · **tombol Kelola Indikator permanen (foto-13)** · **formulir tanpa cache (foto-14)** · **RBAC 403** anggota · kunci unit & periode tertutup · jejak audit · bukti di `uji/iterasi-3-monev/`.

**Sudah teruji (M3, 19 Nov 2026 — LULUS 223/223):** jadwal AMI tahunan (draf → berjalan → ditutup) · **instrumen fleksibel + jejak perubahan** (nilai lama → baru + alasan) · penugasan **auditor wajib unit LPM — auditee waket (LPM ditolak)** · **bukti auditee sebelum skor (skor NULL → 403 bila auditee menilai)** · penilaian **skor 0–4 + bukti** (skor ≤2 wajib catatan → **temuan otomatis**) · skor tertimbang (lampu ≥3,0/≥2,0/<2,0) · **grafik "rata-rata skala 0–4" tanpa "gap" + "Belum ada penilaian" (foto-05)** · **analisis gap 4,00 − rerata** · laporan temuan A–E siap cetak · formulir kepuasan **tiga jenis (skala/pilihan/teks — foto-11)** · **mode tamu `/puas/{token}` — responden "Tamu", tautan bisa dimatikan (foto-12)** · kunci jadwal & formulir tertutup · **RBAC 403** · notifikasi · jejak audit · bukti di `uji/iterasi-4-ami/`.

**Sudah teruji (M4, 16 Nov 2026 — LULUS 101/101):** **konversi temuan → rencana aksi** (sumber wajib temuan M3; PIC, tenggat, prioritas) · **status tindak lanjut `open → in_progress → done → verified`** dengan pengembalian ke berjalan · **unggah bukti** (satu tertunda per rencana) + **verifikasi Super Admin** (diterima/dikembalikan + catatan wajib saat pengembalian) · **umpan balik FR-12: permintaan revisi dokumen** → notifikasi pemilik → revisi via M1 → **siklus PPEPP tertutup** · RBAC (anggota hanya rencana miliknya) · notifikasi bertaut · jejak audit · bukti di `uji/iterasi-5-followup/`. *(19 Nov 2026: penugasan uji memakai auditor unit LPM sesuai Item 8.)*

**Sudah teruji (M5 pelengkap FR-14/FR-15, 17 Nov 2026 — LULUS 55/55):** **ekspor CSV siap Excel** di 4 titik (`/documents/export`, `/monev/export`, `/ami/export`, `/followup/export`) — BOM UTF-8, pemisah titik koma, kutip RFC 4180; saringan halaman asal ikut terbawa; **RBAC**: ekspor AMI khusus Super Admin (403), ekspor anggota terbatas pada PIC-nya, tamu → login · **Cetak/PDF** pada detail laporan Monev · **penjaga tenggat FR-15** berjalan setiap permintaan: masa berlaku dokumen ≤30 hari/kedaluwarsa → pemilik + LPM, periode Monev & jadwal AMI ±7 hari → LPM, rencana aksi ±7 hari → PIC + LPM — **deduplikasi permanen** (pemuatan ulang tidak menggandakan) + tautan `monev_periods`/`ami_schedules` pada daftar notifikasi · pembersihan ke baseline · bukti di `uji/iterasi-6/`. **Regresi penuh 458/458** (79 + 223 + 101 + 55).

**Revisi Review LPM (19 Nov 2026 — regresi penuh 458/458):** Item 1/10 — **alur persetujuan dokumen dihapus** (kolom, tabel `document_approvals`, notifikasi `'persetujuan'`, ekspor 11 kolom) · Item 4 — **bukti auditee sebelum skor** (`ami_assessments.score` NULL) · Item 5 — **grafik rata-rata skala 0–4 + empty state** · Item 6/7/13/14 — Monev: validasi penuh, cap 100%, dedupe notif, rerata lintas unit, tombol indikator, `noStore` · Item 8 — **auditor wajib unit LPM, auditee waket** · Item 9 — **konsep status tindak lanjut disetujui** (`USULAN-STATUS.md`) · Item 11 — **tipe kepuasan skala/pilihan/teks** · Item 12 — **mode tamu `/puas/{token}`** · desain (use case/ERD/DFD/aktivitas) disesuaikan · bukti di tiap `hasil-uji.md`.

**Masukan review LPM lanjutan (25 Nov 2026 — regresi penuh 508/508):** B1 unit pemilik unggah dokumen sebagai peran **LPM, Waket 1–3** · B2 **standar S9 Kerjasama** (benih + `migrasi-masukan-lpm-2026-11-25.sql`) · B3 **dropdown filter unit Capaian per indikator dihapus** (tidak terpakai — tabel selalu merangkum seluruh unit pengisi) · B4 **target & nilai satuan % maksimal 100** · B5 pintasan **"Kembali ke dasbor"** pada halaman Instrumen AMI — bukti: `test-pelengkap.php` (B1/B2), `test-monev.php` (B3/B4), `test-ami.php` (B5); sebelumnya 21 Nov 2026: pencarian global paritas medan + 4 masukan foto-1…4 → 495/495.

**Optimasi UI/UX (16 Nov 2026 — teruji ulang 328/328):** sidebar **responsif** (hamburger → offcanvas di HP), **Bootstrap Icons + favicon** (emoji terhapus), design system `app.css` (kartu/tabel/form/tombol/empty state), **judul ganda dihapus** (topbar = `<h1>` tunggal), **anti double-submit** (spinner), lihat-sandi di login, avatar inisial — seluruh teks yang dipantau skrip uji dipertahankan.

**Optimasi UI/UX tahap 2 (18 Nov 2026 — regresi penuh 458/458):** **topbar seragam** (remah rot otomatis mengikuti rute + `<h1>` halaman) · **pencarian global** `GET /search` — satu kotak mencari dokumen SPMI, temuan AMI, dan rencana aksi (PIC difilter untuk anggota) dengan sorotan kata kunci, pintasan tekan `/` · **menu pengguna dropdown** (nama + unit + peran, Notifikasi, Jejak Audit, Keluar) menggantikan tombol "Keluar" · **sidebar mode ringkas** (klik toggle → ikon saja, preferensi disimpan di `localStorage`) · **page-head seragam** di seluruh halaman (ikon + deskripsi + aksi di slot kanan) · **strip mini-stat dasbor** (4 metrik dalam satu kartu) — seluruh teks yang dipantau skrip uji dipertahankan.

### Cara menjalankan

```bat
1. Buka XAMPP Control Panel → Start Apache & MySQL
2. phpMyAdmin → Import → pilih sistem/desain/03-database.sql   (sekali saja)
3. Browser: http://localhost/lpm/public/
```

**Akun uji (ganti setelah pengujian):**

| Peran | Email | Sandi |
|---|---|---|
| Super Admin (LPM) | `admin@stikomelrahma.ac.id` | `admin123` |
| Anggota (Waket I) | `waket1@stikomelrahma.ac.id` | `waket123` |

> Alternatif tanpa Apache: `php -S 127.0.0.1:8080 -t public public/router.php`
> Konfigurasi DB ada di `app/config/config.php`. Unggahan tersimpan di `public/uploads/` (dieksekusi script-nya diblok).

**Urutan penggunaan:**
1. Impor `03-database.sql` ke MySQL → struktur data jadi.
2. Baca `01-use-case.md` → daftar layar/fungsi yang harus dibuat.
3. Gambar ulang `01/04/05` ke StarUML & Draw.io (hasil cetak jadi lampiran BAB IV).
4. Bangun aplikasi mengikuti urutan modul (lihat bawah).

## Urutan pengembangan yang disarankan

| Iterasi | Modul | Alasan | Status |
|---|---|---|---|
| 1 | Autentikasi + RBAC + manajemen pengguna + unit | fondasi semua modul | ✅ selesai |
| 2 | **M1 Dokumen SPMI** | paling sederhana, langsung terasa manfaatnya | ✅ selesai |
| 3 | **M2 Monev** | memakai data unit & dokumen yang sudah ada | ✅ selesai |
| 4 | **M3 AMI & Evaluasi** | terberat: instrumen dinamis + skoring | ✅ selesai |
| 5 | **M4 Pengendalian–Peningkatan** | bergantung pada temuan dari M3; menutup siklus PPEPP | ✅ selesai (LULUS 101/101) |
| 6 | Pelengkap: ekspor laporan, notifikasi tenggat, perbaikan hasil umpan balik | menyempurnakan FR-14 & FR-15 | ✅ selesai (LULUS 55/55) |

## Struktur folder yang disarankan (tanpa framework)

```
lpm/ ← root (document root XAMPP: localhost/lpm)
├── public/
│   ├── index.php            ← front controller
│   ├── assets/css|img/       (logo institusi: assets/img/logo.* — otomatis dipakai bila ada)
│   └── uploads/dokumen|bukti|instrumen/   (diblok .htaccess)
├── app/
│   ├── config/database.php
│   ├── core/ (Router, Controller, Model, Auth, View, Flash)
│   ├── controllers/ (AuthController, DocumentController, MonevController, ...)
│   └── models/ (User, Document, Monev, Ami, ActionPlan, ...)
├── views/
│   ├── layouts/ (header, sidebar, footer)
│   ├── auth/ documents/ monev/ ami/ action/ dashboard/ users/
├── database/ (backup .sql)
└── sistem/desain/           ← berkas desain ini
```

> Jika memakai **Laravel**, pindahkan `public/` ke document root dan sesuaikan penamaan; skema database tetap sama.

## Pemetaan desain → BAB IV

| Berkas desain | Subbab BAB IV |
|---|---|
| `01-use-case.md` | §4.3.1 Use case diagram |
| `05-activity-diagram.md` | §4.3.2 Diagram aktivitas |
| `02-erd.md` + `03-database.sql` | §4.3.4 ERD & Tabel struktur basis data |
| `04-dfd.md` | §4.3.5 DFD Level 0 & 1 |
| wireframe (Figma) | §4.3.6 Wireframe |
| hasil coding + screenshot | §4.4 Implementasi |
