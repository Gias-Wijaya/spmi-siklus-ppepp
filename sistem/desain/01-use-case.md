# 01 — USE CASE DIAGRAM & DAFTAR USE CASE

## A. Aktor

| Kode | Aktor | Peran | Keterangan |
|---|---|---|---|
| A1 | **Super Admin (LPM)** | Pengelola sistem | Mengelola pengguna, dokumen, instrumen, jadwal, verifikasi tindak lanjut, laporan |
| A2 | **Anggota (Waket I–IV)** | Pengguna operasional | Mengisi Monev, mengisi penilaian AMI, mengunggah bukti, mengunggah dokumen unit |
| A3 | **Sistem** *(aktor sekunder)* | Timer & otomasi | Mengubah status masa berlaku dokumen, mengirim notifikasi tenggat |

> Sesuai hasil wawancara LPM: **LPM = Super Admin; Waket I, II, III, IV = Anggota.** Auditor AMI ditetapkan sebagai penugasan (*assignment*) dari anggota, bukan role terpisah.

## B. Daftar Use Case

| Kode | Use Case | Aktor | Modul | Ringkas |
|---|---|---|---|---|
| **Umum & M5** ||||
| UC-01 | Masuk / keluar sistem | A1, A2 | M5 | Autentikasi, sesi, profil |
| UC-02 | Kelola pengguna & hak akses | A1 | M5 | Tambah/ubah/nonaktifkan pengguna, tetapkan role |
| UC-03 | Kelola unit kerja | A1 | M5 | Unit beserta penanggung jawab |
| UC-04 | Lihat dasbor | A1, A2 | M5 | Ringkasan capaian, temuan, dokumen kritis |
| UC-05 | Terima notifikasi | A1, A2 | M5 | Pengingat masa berlaku, tenggat, persetujuan |
| UC-06 | Jejak audit | A1 | M5 | Meninjau riwayat aksi penting |
| UC-07 | Cetak / ekspor laporan | A1, A2 | M5 | PDF & Excel |
| **M1 — Dokumen SPMI** ||||
| UC-08 | Kelola kategori dokumen | A1 | M1 | Hierarki: kebijakan→pedoman→manual/SOP→instrumen→laporan |
| UC-09 | Unggah dokumen | A1, A2 | M1 | Metadata: nomor, versi, tanggal & **masa berlaku**, berkas |
| UC-10 | Ajukan dokumen untuk persetujuan | A2 | M1 | Status → *submitted* |
| UC-11 | Setujui / kembalikan dokumen | A1 | M1 | *approved* / *rejected* + komentar |
| UC-12 | Revisi dokumen (versi baru) | A1, A2 | M1 | Versi sebelumnya diarsipkan, riwayat tersimpan |
| UC-13 | Arsipkan / hapus dokumen | A1 | M1 | Menjadikan **merah** (tidak berlaku) |
| UC-14 | Cari & filter dokumen | A1, A2 | M1 | Kategori, status, masa berlaku, kata kunci |
| UC-15 | Perbarui status masa berlaku *(aktor sistem)* | A3 | M1 | 🟢 hijau → 🟡 kuning (H-30) → 🔴 merah (kedaluwarsa) |
| **M2 — Monev** ||||
| UC-16 | Kelola periode & indikator Monev | A1 | M2 | Periode 3-bulanan, daftar indikator standar |
| UC-17 | Isi capaian unit | A2 | M2 | Nilai per indikator + unggah bukti |
| UC-18 | Review & tutup periode | A1 | M2 | Rekap, tandai selesai |
| UC-19 | Lihat tren capaian | A1, A2 | M2 | Grafik antarperiode & antarunit, status lampu |
| **M3 — AMI & Evaluasi** ||||
| UC-20 | Susun & sunting instrumen AMI | A1 | M3 | **Indikator fleksibel**: tambah/ubah/hapus + jejak perubahan |
| UC-21 | Jadwalkan AMI & tetapkan auditor–auditee | A1 | M3 | Tahunan, pasangan auditor/unit |
| UC-22 | Isi penilaian & catat temuan | A2 | M3 | Skor 0–4, bukti, deskripsi temuan |
| UC-23 | Hitung skor & rekap otomatis | A1 | M3 | Skor per unit, per standar, per indikator |
| UC-24 | Susun laporan temuan AMI | A1 | M3 | Laporan siap ekspor |
| UC-25 | Isi formulir kepuasan | A2 | M3 | Kuisioner periode berjalan |
| UC-26 | Rekap kepuasan | A1 | M3 | Skor & grafik kepuasan |
| **M4 — Pengendalian–Peningkatan** ||||
| UC-27 | Konversi temuan menjadi rencana aksi | A1 | M4 | Otomatis dari UC-22/UC-23 |
| UC-28 | Tetapkan penanggung jawab & tenggat | A1 | M4 | PIC dari Waket terkait |
| UC-29 | Laksanakan & unggah bukti | A2 | M4 | Status *in_progress* → *done* |
| UC-30 | Verifikasi & tutup tindak lanjut | A1 | M4 | *verified*; ditolak → kembali berjalan |
| UC-31 | Umpan balik revisi dokumen | A1 | M4 | Memicu UC-12 (revisi dokumen = masuk siklus berikutnya) |

## C. PlantUML — Use Case Diagram

> Salin ke https://www.plantuml.com/plantuml/uml atau impor ke StarUML (plugin PlantUML) → ekspor PNG ke `skripsi/05-gambar/gambar-4-x-usecase.png`.

```plantuml
@startuml
left to right direction
skinparam actorStyle awesome
skinparam packageStyle rectangle
title Use Case Diagram — Sistem Informasi Penjaminan Mutu Internal STIKOM El Rahma

actor "Super Admin (LPM)" as SA
actor "Anggota (Waket I-IV)" as AG
actor "Sistem (Timer)" as SYS

rectangle "Sistem Informasi PPM" {
  ' ---- M5 ----
  usecase "UC-01 Masuk/Keluar Sistem" as UC01
  usecase "UC-02 Kelola Pengguna & Hak Akses" as UC02
  usecase "UC-03 Kelola Unit Kerja" as UC03
  usecase "UC-04 Lihat Dasbor" as UC04
  usecase "UC-05 Terima Notifikasi" as UC05
  usecase "UC-06 Jejak Audit" as UC06
  usecase "UC-07 Cetak/Ekspor Laporan" as UC07

  ' ---- M1 ----
  package "M1 Dokumen SPMI" {
    usecase "UC-08 Kelola Kategori Dokumen" as UC08
    usecase "UC-09 Unggah Dokumen\n(masa berlaku)" as UC09
    usecase "UC-10 Ajukan Persetujuan" as UC10
    usecase "UC-11 Setujui/Kembalikan" as UC11
    usecase "UC-12 Revisi (versi baru)" as UC12
    usecase "UC-13 Arsipkan Dokumen" as UC13
    usecase "UC-14 Cari & Filter Dokumen" as UC14
    usecase "UC-15 Perbarui Status Masa Berlaku\n(hijau-kuning-merah)" as UC15
  }

  ' ---- M2 ----
  package "M2 Monev" {
    usecase "UC-16 Kelola Periode & Indikator" as UC16
    usecase "UC-17 Isi Capaian Unit" as UC17
    usecase "UC-18 Review & Tutup Periode" as UC18
    usecase "UC-19 Lihat Tren Capaian" as UC19
  }

  ' ---- M3 ----
  package "M3 AMI & Evaluasi" {
    usecase "UC-20 Susun & Sunting Instrumen\n(indikator fleksibel)" as UC20
    usecase "UC-21 Jadwalkan AMI &\nAuditor-Auditee" as UC21
    usecase "UC-22 Isi Penilaian & Temuan" as UC22
    usecase "UC-23 Hitung Skor & Rekap" as UC23
    usecase "UC-24 Laporan Temuan AMI" as UC24
    usecase "UC-25 Isi Formulir Kepuasan" as UC25
    usecase "UC-26 Rekap Kepuasan" as UC26
  }

  ' ---- M4 ----
  package "M4 Pengendalian-Peningkatan" {
    usecase "UC-27 Konversi Temuan ke\nRencana Aksi" as UC27
    usecase "UC-28 Tetapkan PIC & Tenggat" as UC28
    usecase "UC-29 Unggah Bukti Tindak Lanjut" as UC29
    usecase "UC-30 Verifikasi & Tutup Aksi" as UC30
    usecase "UC-31 Umpan Balik Revisi Dokumen" as UC31
  }
}

SA --> UC01
AG --> UC01
SA --> UC02
SA --> UC03
SA --> UC04
AG --> UC04
AG --> UC05
SA --> UC05
SA --> UC06
SA --> UC07
AG --> UC07

SA --> UC08
SA --> UC09
AG --> UC09
AG --> UC10
SA --> UC11
SA --> UC12
AG --> UC12
SA --> UC13
SA --> UC14
AG --> UC14
SYS --> UC15
UC15 ..> UC13 : merah / kedaluwarsa
UC15 ..> UC11 : kuning / perlu revisi

SA --> UC16
AG --> UC17
SA --> UC18
SA --> UC19
AG --> UC19

SA --> UC20
SA --> UC21
AG --> UC22
SA --> UC23
SA --> UC24
AG --> UC25
SA --> UC26

SA --> UC27
SA --> UC28
AG --> UC29
SA --> UC30
UC30 ..> UC31 : temuan revisi dokumen
UC31 ..> UC12 : siklus berikutnya
@enduml
```

## D. Aturan hak akses (untuk implementasi RBAC)

| Aktor | M1 | M2 | M3 | M4 | M5 |
|---|---|---|---|---|---|
| Super Admin (LPM) | C R U D + persetujuan | C R U D + tutup periode | C R U D + instrumen + verifikasi | C R U D + verifikasi | Kelola pengguna, jejak audit, ekspor |
| Anggota (Waket) | U (unit sendiri), ajukan, revisi | R (semua) + U (capaian bidang) | R + U (penilaian & temuan bidang) + isi kepuasan | R + U (unggah bukti PIC-nya) | Dasbor, notifikasi, ekspor |

Keterangan: C=Create, R=Read, U=Update, D=Delete.
