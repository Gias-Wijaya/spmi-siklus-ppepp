# Hasil Uji Iterasi 5 — Modul M4 Pengendalian–Peningkatan

- **Tanggal:** 16 November 2026
- **Skrip:** `test-followup.php` — jalankan dengan `php test-followup.php` (server `:8080` + MariaDB hidup)
- **Akun uji:** `admin@stikomelrahma.ac.id` (Super Admin/LPM) & `waket1@stikomelrahma.ac.id` (Anggota, PIC)
- **Cakupan:** FR-09 (konversi temuan → rencana aksi), FR-10 (pelacakan status tindak lanjut), FR-11 (bukti + verifikasi), FR-12 (umpan balik revisi dokumen) + RBAC, jejak audit, notifikasi
- **Hasil akhir: LULUS 101 pemeriksaan — GAGAL 0**

## Ringkasan hasil

| Seksi | Pemeriksaan | Temuan |
|---|---|---|
| A. Masuk & akses halaman M4 (RBAC) | 9 | Dasbor M4 terbuka bagi keduanya; anggota tidak melihat formulir konversi; rencana tidak ada → 404; anggota 403 membuat rencana & permintaan revisi |
| B. Konversi temuan → rencana aksi (FR-09) | 19 | Sumber wajib temuan M3 (penugasan + temuan manual pada jadwal AMI 2026); **tanpa temuan ditolak**; uraian/PIC aktif/tenggat tervalidasi; PIC + unit PIC tersimpan; notifikasi penugasan ke PIC; daftar menampilkan jumlah aksi per temuan; anggota hanya melihat rencana miliknya |
| C. Status tindak lanjut (FR-10) | 9 | PIC membuka detailnya sendiri; rencana PIC lain → 403; lompat ke *verified* ditolak (wajib melalui verifikasi bukti); `open → in_progress` berhasil + LPM diberi tahu; transisi ganda ditolak |
| D. Penyuntingan rencana (Super) | 4 | Anggota 403; Super menyunting tenggat & prioritas; perubahan tersimpan; tercatat di jejak audit |
| E. Bukti & verifikasi (FR-11) | 25 | Unggahan tanpa berkas ditolak; bukti → status *pending* + rencana → *done*; unggah ganda saat menunggu ditolak; keputusan tidak valid & pengembalian tanpa catatan ditolak; **dikembalikan → rencana kembali berjalan + catatan tersimpan + PIC diberi tahu**; unggah ulang; **diterima → `verified` + verifier & waktu tersimpan**; unggah/verifikasi ganda setelah terverifikasi ditolak; unduhan bukti oleh Super & PIC; jejak audit `action_evidence` |
| F. Umpan balik revisi dokumen (FR-12) | 15 | Formulir hanya setelah verifikasi; sebelum verifikasi, dokumen tidak valid, dan catatan kosong ditolak; **permintaan tersimpan (dokumen + catatan + waktu) & pemilik dokumen diberi notifikasi**; permintaan ganda ditolak; penanggung jawab melihat statusnya; **siklus ditutup: pemilik merevisi dokumen via M1 → versi 1.1 → antrean persetujuan (Penetapan berikutnya)** |
| G. Dasbor, notifikasi, jejak audit & saringan | 9 | Statistik tindak lanjut di dasbor bertaut ke M4; sidebar M4 aktif (label "segera" dihapus); notifikasi bertaut langsung ke halaman rencana; audit `action_plans`/`action_evidence` terekam; saringan status & prioritas berfungsi |
| H. CSRF & 404 | 4 | 419 tanpa token; unduhan/status/verifikasi tidak ada → 404 |
| I. Pembersihan data uji | 7 | Cascade penugasan → temuan → rencana → bukti; dokumen, versi, notifikasi & jejak audit uji terhapus; seluruh tabel kembali ke jumlah baseline (dokumen seed 4, notifikasi 9) |

## Temuan selama pengujian & tindak perbaikan

| No | Temuan | Asal | Tindak perbaikan |
|---|---|---|---|
| 1 | **Validasi tenggat memicu fatal error 500** — `DateTime::createFromFormat()` mengembalikan `false` untuk tanggal salah, sedangkan nullsafe operator `?->format()` hanya menangani `null` sehingga pemanggilan pada `false` melempar `Error` | Uji seksi B (format `01/05/2027`) | `ActionPlanController::store()/update()` — cek `$due === false` secara eksplisit sebelum `format()`; galat kini tampil sebagai pesan validasi yang benar |
| 2 | Kartu FR-12 semula hanya dirender untuk Super Admin — penanggung jawab tidak dapat melihat status permintaan revisi dokumennya | Peninjauan desain tampilan sebelum uji | `followup/show.php` — kartu ditampilkan bila terdapat permintaan revisi (status) sedangkan formulir tetap khusus Super Admin |
| 3 | Asersi "belum ada aksi" berhuruf besar tidak cocok dengan label pada tampilan (huruf kecil) | Uji seksi B | Aserturasi skrip disesuaikan |
| 4 | Saringan prioritas diuji dengan `tinggi`, tetapi rencana uji telah disunting ke `sedang` pada seksi D | Uji seksi G | Aserturasi skrip disesuaikan (`priority=sedang`) |
| 5 | `array_map('@rmdir', …)` bukan callback valid — fatal pada pembersihan | Uji seksi I | Diganti closure `fn ($d) => @rmdir($d)` |

Perbaikan nomor 1 ditemukan dan diperbaiki lewat pengujian; nomor 2 diperbaiki sebelum putaran uji final; nomor 3–5 murni perbaikan skrip uji.

## Catatan integrasi & desain

- **Sumber tunggal:** seluruh rencana aksi lahir dari `ami_findings` (aturan bisnis P4.4 — *tidak ada aksi tanpa temuan*); pembuatan tanpa temuan sah ditolak.
- **State machine tindak lanjut:** `open → in_progress → done → verified`, dengan pengembalian bukti mengembalikan rencana ke `in_progress` (dengan catatan verifikasi) — selaras dengan diagram aktivitas *Pengendalian & Peningkatan*.
- **Satu bukti tertunda per rencana** — pengunggahan berikutnya terbuka setelah keputusan verifikasi diberikan (deterministik & mudah ditelusuri).
- **Skema:** tidak menambah tabel — tiga kolom `revision_document_id`, `revision_note`, `revision_requested_at` ditambahkan ke `action_plans` (tercantum di `sistem/desain/03-database.sql`); jumlah tabel tetap 26 + 1 view.
- **Penutupan siklus PPEPP:** permintaan revisi (FR-12) memicu notifikasi `dokumen` bertaut ke halaman dokumen; pemilik mengunggah versi baru lewat alur M1 yang sudah ada → persetujuan → siklus Penetapan berikutnya (diuji ujung-ke-ujung pada seksi F).
- **Data uji bersih:** seluruh data buatan skrip terhapus (cascade); DB kembali ke baseline (0 rencana aksi, 0 temuan, 4 dokumen seed, 9 notifikasi).
