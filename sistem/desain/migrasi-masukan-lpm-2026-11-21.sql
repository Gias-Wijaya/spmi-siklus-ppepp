-- ============================================================================
-- MIGRASI — Masukan Review LPM (21 November 2026), foto-1:
--            INDIKATOR MONEV TERIKAT UNIT PENGISI (unit_id)
--            — isian capaian satu unit hanya menampilkan indikator miliknya
--              atau indikator "semua unit" (unit_id NULL); indikator unit lain
--              tidak lagi ikut terbawa ("bukan bagian dia untuk mengisi").
-- Untuk basis data LIVE yang sudah berisi data (tanpa impor ulang penuh).
-- Jalankan sekali via phpMyAdmin / mysql CLI pada database `spmi_stikom`.
-- (Basis data baru/kosong: cukup impor 03-database.sql — sudah mencakup ini.)
-- ============================================================================

ALTER TABLE `monev_indicators`
  ADD COLUMN `unit_id` INT UNSIGNED NULL DEFAULT NULL AFTER `standard_id`,
  ADD KEY `idx_mi_unit` (`unit_id`),
  ADD CONSTRAINT `fk_mi_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL;
