# Sistem Informasi Penjaminan Mutu Internal — STIKOM El Rahma

Sistem informasi berbasis **siklus PPEPP** (Perencanaan–Pelaksanaan–Evaluasi–Pengendalian–Peningkatan) untuk pengelolaan dokumen dan kinerja mutu di STIKOM El Rahma. Lima modul terintegrasi dalam satu basis data, dengan hak akses berbasis peran: **LPM sebagai Super Admin**, **Wakil Ketua I–III sebagai anggota**.

- **Teknologi:** PHP 8 *tanpa framework* (PDO, router regex, pola MVC) · MySQL · Bootstrap 5 · font Inter
- **Kebutuhan:** FR-01–FR-15 (fungsional) · NF-01–NF-08 (non-fungsional) · BR-01–BR-16 (aturan bisnis)
- **Status:** iterasi 1–6 selesai & teruji — **66 test case / 383 pemeriksaan lulus (100%)**

## Modul

| Modul | Cakupan |
|---|---|
| **M1 Dokumen SPMI** | unggah dokumen + masa berlaku, status 🟢🟡🔴 otomatis, versi, alur persetujuan |
| **M2 Monev** | isian capaian 3-bulanan per unit, unggah bukti, grafik tren per periode |
| **M3 AMI & Evaluasi** | instrumen fleksibel (jejak perubahan), penilaian auditor 0–4, rekap temuan, formulir kepuasan |
| **M4 Pengendalian–Peningkatan** | temuan → rencana aksi (PIC, tenggat), unggah bukti, verifikasi Super Admin |
| **M5 Pendukung** | RBAC, pengguna & unit, notifikasi tenggat (FR-15), ekspor laporan CSV/cetak (FR-14) |

Alur status tindak lanjut: `open → in_progress → done → verified`.

## Menjalankan

1. Nyalakan XAMPP (Apache + MySQL).
2. Impor **`sistem/desain/03-database.sql`** ke database `spmi_stikom` — sudah termasuk DDL 26 tabel + *seed* data.
3. Sesuaikan kredensial basis data di **`app/config/config.php`** bila berbeda dari default XAMPP (`root`, sandi kosong).
4. Buka **`http://localhost/lpm/public/`** (`base_url` mengikuti path folder, jadi nama folder bebas).
5. Masuk memakai akun uji:

   | Peran | Email | Sandi |
   |---|---|---|
   | Super Admin (LPM) | `admin@stikomelrahma.ac.id` | `admin123` |
   | Anggota (Waket I) | `waket1@stikomelrahma.ac.id` | `waket123` |

   > ⚠️ **Ganti sandi akun default ini sebelum dipakai di luar lingkungan uji.**

## Struktur folder

```
lpm/
├── app/
│   ├── config/        config.php (basis data, upload, aturan bisnis)
│   ├── core/          router, controller dasar, model dasar, autentikasi/RBAC
│   ├── controllers/   10 kontroller (auth, dashboard, dokumen, monev, ami, …)
│   ├── models/        model per tabel
│   └── views/         template layout + tampilan (Bootstrap 5)
├── public/            WEB ROOT — index.php, router.php, assets, uploads/
└── sistem/
    ├── desain/        paket desain (lihat di bawah)
    └── uji/           test suite per iterasi
```

## Paket desain (`sistem/desain/`)

- `01-use-case.md` — 31 use case + PlantUML + matriks hak akses
- `02-erd.md` — 26 entitas, 23 relasi, ERD Mermaid per modul
- `03-database.sql` — DDL MySQL lengkap + *seed* (impor dulu!)
- `04-dfd.md` — DFD Level 0/1 + 20 aliran data + aturan bisnis
- `05-activity-diagram.md` — 4 diagram aktivitas (PlantUML)

## Pengujian (`sistem/uji/`)

| Suite | Cakupan | Hasil |
|---|---|---|
| `iterasi-6/` | M5 pelengkap — ekspor FR-14, notifikasi FR-15 | 55/55 |
| `iterasi-3-monev/` | M2 Monev | 65/65 |
| `iterasi-4-ami/` | M3 AMI & Evaluasi | 162/162 |
| `iterasi-5-followup/` | M4 Pengendalian–Peningkatan | 101/101 |

**Cara menjalankan** (butuh MySQL aktif):

```powershell
# 1. server uji dari root proyek
php -S 127.0.0.1:8080 -t public public/router.php

# 2. jalankan per suite, urutan: iterasi-6 → 3 → 4 → 5
cd sistem/uji/iterasi-6        ; php test-*.php
cd sistem/uji/iterasi-3-monev  ; php test-*.php
cd sistem/uji/iterasi-4-ami    ; php test-*.php
cd sistem/uji/iterasi-5-followup ; php test-*.php
```

Total regresi: **383/383 pemeriksaan lulus**.
