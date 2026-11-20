# 05 — DIAGRAM AKTIVITAS (3 ALUR UTAMA)

> Salin tiap blok ke https://www.plantuml.com/plantuml/uml atau StarUML → ekspor PNG → `skripsi/05-gambar/gambar-4-x-aktivitas-*.png`

---

## A. Alur Pengelolaan Dokumen (M1) — mendukung tahap Penetapan (tanpa alur persetujuan)

```plantuml
@startuml
title Aktivitas — Pengelolaan Dokumen SPMI
start
:Anggota/Super Admin mengunggah dokumen
(judul, nomor, kategori, versi, tanggal & masa berlaku);
:Dokumen tersimpan & langsung berlaku
(status 🟢; versi tercatat di document_versions);
:Notifikasi ke pengunggah;

partition "Siklus masa berlaku (otomatis)" {
  :Sistem menghitung sisa hari;
  if (Masa berlaku habis?) then (Ya)
    :status = merah (tidak berlaku);
    :Notifikasi pengarsipan;
  elseif (≤ 30 hari?) then (Ya)
    :status = kuning (perlu revisi);
    :Notifikasi pengingat revisi;
  else (Tidak)
    :status = hijau (berlaku);
  endif
}

:Revisi dilakukan → versi baru dicatat
di document_versions (langsung aktif);
stop
@enduml
```

---

## B. Alur Evaluasi Mutu: AMI Tahunan (M3) — mendukung tahap Evaluasi

```plantuml
@startuml
title Aktivitas — Pelaksanaan AMI (tahunan, instrumen fleksibel)
start
:Super Admin menyusun instrumen AMI;
note right
  Indikator DAPAT diedit
  (tambah/ubah/hapus),
  setiap perubahan tercatat
  di ami_instrument_logs
end note

:Super Admin menetapkan jadwal & pasangan
auditor (LPM)–auditee (waket) per unit;
note right
  Auditee = unit waket (W1–W3);
  auditor wajib pengguna
  unit LPM (Revisi Review LPM)
end note
:Auditee (waket) mengunggah bukti
(dokumen, wawancara, observasi) per indikator;

partition "Penilaian per indikator" {
  :Bukti tercatat dengan skor kosong
  (menunggu penilaian auditor);
  :Auditor (LPM) memberi skor 0–4
  + catatan penilaian;
  if (Tidak memenuhi standar?) then (Ya)
    :Mencatat TEMUAN
    (ketidaksesuaian/observasi/rekomendasi);
  else (Tidak)
  endif
}

:Sistem menghitung skor otomatis
(per unit, per standar, per indikator);
:Super Admin menyusun laporan temuan AMI;
if (Ada temuan?) then (Ya)
  :Mengirim temuan ke modul
  Pengendalian–Peningkatan (M4);
else (Tidak)
  :Laporan AMI ditutup;
endif
stop
@enduml
```

---

## C. Alur Tindak Lanjut Berkelanjutan (M4) — mendukung tahap Pengendalian & Peningkatan

```plantuml
@startuml
title Aktivitas — Pengendalian & Peningkatan (tindak lanjut berkelanjutan)
start
:Temuan dari M3 menjadi
daftar rencana aksi (status = Terbuka);

:Super Admin menetapkan deskripsi aksi,
penanggung jawab (Waket terkait),
tenggat waktu, dan prioritas;
:Notifikasi tugas ke penanggung jawab;

partition "Pelaksanaan" {
  :Penanggung jawab memulai pelaksanaan
  (status = Terbuka → Berjalan);
  :Menyelesaikan perbaikan;
  :Mengunggah bukti penyelesaian;
  :status = Selesai (menunggu verifikasi);
}

if (Bukti diterima Super Admin?) then (Ya)
  :status = Terverifikasi;
  :Catatan verifikasi tersimpan;
else (Tidak)
  :Bukti dikembalikan (rejected)
  + catatan perbaikan;
  :status kembali Berjalan;
  :Kembali ke pelaksanaan;
endif

if (Perlu perubahan dokumen/standar?) then (Ya)
  :Umpan balik → permintaan REVISI DOKUMEN;
  :Masuk siklus PPEPP berikutnya
  (kembali ke tahap Penetapan / M1);
else (Tidak)
  :Temuan ditutup;
endif

:Rekap tindak lanjut ditampilkan
pada dasbor (Terbuka/Berjalan/Selesai/Terverifikasi);
stop
@enduml
```

---

## D. Alur pengisian Monev (M2) — pendukung tahap Pelaksanaan (ringkas)

```plantuml
@startuml
title Aktivitas — Pelaporan Monev 3-Bulanan
start
:Super Admin membuka periode Monev;
:Anggota (Waket) mengisi capaian
per indikator pada bidangnya;
:Unggah bukti pendukung;
if (Semua indikator terisi?) then (Tidak)
  :Lengkapi isian;
else (Ya)
  :Ajukan laporan → status submitted;
  :Super Admin meninjau;
  if (Disetujui?) then (Ya)
    :status = approved, skor dihitung;
    :Tampil pada dasbor & tren antarperiode;
  else (Tidak)
    :Dikembalikan untuk dilengkapi;
  endif
endif
stop
@enduml
```
