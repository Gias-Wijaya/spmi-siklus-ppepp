# 04 — DATA FLOW DIAGRAM (DFD)

## A. DFD Level 0 (Konteks)

```
                    ┌──────────────────────────────────────────┐
   Super Admin ────►│                                          │────► Laporan (PDF/Excel)
   (LPM)            │   P0  SISTEM INFORMASI PENJAMINAN        │
   Anggota ────────►│       MUTU INTERNAL – STIKOM EL RAHMA     │
   (Waket I–III)    │                                          │────► Notifikasi
   Tamu ───────────►│                                          │
   (tautan token)   └──────────────────────────────────────────┘
                              ▲                │
                              │                ▼
                         Data mutu        Status, skor,
                         (input)          temuan, aksi
```

**Aliran utama (Level 1):**

```
 Super Admin/Anggota
        │  (1) unggah dokumen & metadata, masa berlaku
        ▼
  ┌─────────────┐  status 🟢🟡🔴   ┌─────────────┐
  │ P1 Kelola   │ ───────────────► │ P2 Pantau   │ ◄── isi capaian 3-bulanan (Anggota)
  │ Dokumen SPMI│                  │ Kinerja     │
  └──────┬──────┘                  └──────┬──────┘
         │ bukti pendukung                │ data capaian
         ▼                                ▼
  ┌──────────────────────────────────────────────┐
  │ P3 Evaluasi Mutu (AMI tahunan + Kepuasan)    │ ◄── sunting instrumen (Super Admin)
  └──────────────────┬───────────────────────────┘    ◄── isi tamu (tautan token)
                     │ temuan
                     ▼
  ┌──────────────────┴───────────────────────────┐
  │ P4 Pengendalian–Peningkatan                  │ ◄── unggah bukti (Anggota)
  └──────────────────┬───────────────────────────┘
                     │ status verifikasi / kebutuhan revisi dokumen
                     ▼
              kembali ke P1  ── (menutup siklus PPEPP)
```

## B. DFD Level 0 — entitas luar, proses, dan penyimpan data

| Kode | Komponen | Keterangan |
|---|---|---|
| E1 | **Super Admin (LPM)** | Mengelola pengguna, dokumen, instrumen, jadwal, verifikasi, laporan |
| E2 | **Anggota (Waket I–III)** | Mengisi Monev, penilaian AMI, unggah dokumen unit & bukti tindak lanjut |
| E3 | **Sistem (Timer)** | Pembaruan status masa berlaku & pengingat tenggat |
| E4 | **Responden Tamu** | Mahasiswa/pengunjung mengisi formulir kepuasan via tautan token tanpa akun |
| P1 | Kelola Dokumen SPMI | M1 |
| P2 | Pantau Kinerja (Monev) | M2 |
| P3 | Evaluasi Mutu (AMI + Kepuasan) | M3 |
| P4 | Pengendalian–Peningkatan | M4 |
| P5 | Layanan Pendukung (autentikasi, notifikasi, jejak audit, pelaporan) | M5 |
| D1 | Penyimpanan dokumen & versi | `documents`, `document_versions` |
| D2 | Penyimpanan capaian Monev | `monev_periods`, `monev_indicators`, `monev_reports`, `monev_values` |
| D3 | Penyimpanan instrumen & penilaian AMI | `ami_schedules`, `ami_instruments`, `ami_assignments`, `ami_assessments`, `ami_findings` |
| D4 | Penyimpanan tindak lanjut | `action_plans`, `action_evidence` |
| D5 | Penyimpanan pengguna & pengaturan | `users`, `units`, `standards`, `settings` |
| D6 | Penyimpanan kepuasan | `satisfaction_*` |
| D7 | Penyimpanan jejak & notifikasi | `audit_logs`, `notifications` |

## C. Tabel aliran data (Level 1)

| # | Aliran | Asal | Tujuan | Data yang mengalir |
|---|---|---|---|---|
| F1 | Data dokumen | E1/E2 | P1 | judul, nomor, kategori, versi, tanggal & masa berlaku, berkas |
| F2 | Status masa berlaku | P1 | D1 | status 🟢🟡🔴, sisa hari |
| F3 | Dokumen berlaku | D1 | P2/P3 | dokumen acuan sebagai bukti |
| F4 | Periode & indikator Monev | E1 | P2 | periode 3-bulanan, indikator, target |
| F5 | Capaian unit | E2 | P2 | nilai per indikator + unggah bukti |
| F6 | Rekap capaian | P2 | E1/E2 | skor total, grafik tren, status lampu |
| F7 | Instrumen AMI | E1 | P3 | tambah/ubah/hapus indikator + alasan perubahan |
| F8 | Penugasan auditor | E1 | P3 | auditor (wajib unit LPM) – auditee (waket) per unit |
| F9 | Bukti & penilaian AMI | E2 → E1 | P3 | auditee unggah bukti (skor kosong), auditor LPM menilai 0–4 + temuan |
| F10 | Laporan temuan | P3 | E1 | rekap skor, daftar temuan, analisis gap |
| F11 | Rencana aksi | P1/E1 | P4 | deskripsi aksi, PIC, tenggat, prioritas |
| F12 | Bukti tindak lanjut | E2 | P4 | berkas bukti + keterangan |
| F13 | Verifikasi | E1 | P4 | setujui/tolak bukti, catatan verifikasi |
| F14 | Umpan balik revisi dokumen | P4 | P1 | permintaan revisi dokumen → siklus berikutnya |
| F15 | Notifikasi | P5 | E1/E2 | pengingat masa berlaku, tenggat, temuan, verifikasi |
| F16 | Laporan/ekspor | P5 | E1/E2 | PDF/Excel |
| F17 | Jejak audit | P1–P4 | D7 | aksi penting beserta pelakunya |
| F18 | Formulir kepuasan | E2 | P3 | jawaban kepuasan anggota |
| F19 | Isi formulir tamu | E4 | P3 | tautan `/puas/{token}` — jawaban tanpa akun, responden "Tamu" |
| F20 | Rekap kepuasan | P3 | E1 | skor & grafik kepuasan, daftar responden (anggota & Tamu) |

## D. Mermaid — DFD Level 0 (untuk dijadikan gambar)

```mermaid
flowchart LR
  subgraph Eksisting
    SA["Super Admin (LPM)"]
    AG["Anggota (Waket I-III)"]
    SYS["Sistem (Timer)"]
  end

  subgraph Sistem["Sistem Informasi PPM STIKOM El Rahma"]
    P0(("P0"))
    P1["P1 Kelola Dokumen SPMI"]
    P2["P2 Pantau Kinerja (Monev)"]
    P3["P3 Evaluasi Mutu (AMI + Kepuasan)"]
    P4["P4 Pengendalian-Peningkatan"]
    P5["P5 Layanan Pendukung"]
  end

  L["Laporan PDF/Excel"]
  N["Notifikasi"]

  SA --> P0
  AG --> P0
  SYS --> P0
  P0 --> L
  P0 --> N

  P0 --- P1
  P0 --- P2
  P0 --- P3
  P0 --- P4
  P0 --- P5
```

## E. Aturan bisnis yang harus diimplementasikan

1. Status dokumen dihitung: **merah** bila kedaluwarsa/diarsipkan; **kuning** bila ≤ 30 hari menuju masa berlaku habis (`settings.document_validity_warning_days`); **hijau** selain itu. Tidak ada alur persetujuan — versi baru langsung aktif (Revisi Review LPM).
2. Satu laporan Monev hanya boleh ada **satu per unit per periode** (unique key sudah didefinisikan).
3. Instrumen AMI **hanya dapat disunting Super Admin**, dan setiap perubahan wajib tercatat di `ami_instrument_logs`.
4. Temuan AMI (`ami_findings`) menjadi sumber tunggal rencana aksi — tidak ada aksi tanpa temuan.
5. Tindak lanjut dinyatakan tuntas hanya setelah bukti **diverifikasi** Super Admin (status `verified`).
6. Verifikasi yang berujung pada perubahan dokumen memicu alur revisi dokumen (F14 → F1).
7. Penilaian AMI: auditee (waket) hanya **mengunggah bukti** — skor tetap kosong (`NULL`) sampai auditor LPM menilai; penilaian dianggap lengkap hanya bila seluruh indikator sudah bernilai.
8. Auditee wajib unit waket (W1–W3) — unit LPM ditolak; auditor wajib pengguna aktif pada unit LPM.
9. Pertanyaan kepuasan bertipe `pilihan` wajib punya minimal 2 opsi (`options`, dipisah `|`); semua pertanyaan skala & pilihan wajib dijawab sebelum kirim.
10. Tautan tamu `/puas/{token}` aktif per formulir dan dapat dimatikan; respons tamu tercatat tanpa akun (`respondent_user_id` NULL → "Tamu") — maksimum sekali per sesi perangkat.
11. Status tindak lanjut mengikuti konsep disetujui: **Terbuka → Berjalan → Selesai → Terverifikasi**; pengembalian bukti mengembalikan status ke Berjalan; temuan baru periode berikutnya menjadi rencana aksi baru.
