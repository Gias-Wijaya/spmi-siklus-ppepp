-- ============================================================================
-- MIGRASI — Masukan Review LPM (25 November 2026), foto-B2 & foto-B4:
--            1. STANDAR S9 KERJASAMA — satu standar SPMI ditambahkan sehingga
--               pilihan "Standar SPMI terkait" mencakup S1–S9.
--            2. BATAS SATUAN % — target dan nilai indikator ber satuan "%"
--               yang melebihi 100 diturunkan menjadi 100 (aturan baru pada
--               aplikasi: persentase tidak boleh melebihi 100); capaian dan
--               skor total dihitung ulang mengikuti rumus aplikasi
--               (achievement = value / target × 100, dibatasi 100).
-- Untuk basis data LIVE yang sudah berisi data (tanpa impor ulang penuh).
-- Jalankan sekali via phpMyAdmin / mysql CLI pada database `spmi_stikom`.
-- (Basis data baru/kosong: cukup impor 03-database.sql — sudah mencakup ini.)
-- Idempoten: aman dijalankan lebih dari sekali.
-- ============================================================================

-- 1) Standar S9 Kerjasama (hanya bila belum ada).
INSERT INTO `standards` (`code`, `name`, `category`)
SELECT 'S9', 'Standar Kerjasama', 'Operasional'
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `standards` WHERE `code` = 'S9');

-- 2a) Target ber satuan % maksimal 100.
UPDATE `monev_indicators`
   SET `target` = 100
 WHERE `unit_of_measure` = '%'
   AND `target` > 100;

-- 2b) Nilai isian ber satuan % maksimal 100.
UPDATE `monev_values` v
  JOIN `monev_indicators` i ON i.`id` = v.`indicator_id`
   SET v.`value` = 100
 WHERE i.`unit_of_measure` = '%'
   AND v.`value` > 100;

-- 2c) Hitung ulang capaian tiap nilai (rumus identik dengan
--     MonevReport::recalculate — idempoten).
UPDATE `monev_values` v
  JOIN `monev_indicators` i ON i.`id` = v.`indicator_id`
   SET v.`achievement_percent` = CASE
         WHEN i.`target` > 0 THEN LEAST(ROUND(v.`value` / i.`target` * 100, 2), 100)
         ELSE 0
       END;

-- 2d) Hitung ulang skor total tiap laporan (rerata tertimbang, dibatasi 100).
UPDATE `monev_reports` r
   SET r.`total_score` = (
         SELECT ROUND(SUM(LEAST(ROUND(v.`value` / i.`target` * 100, 2), 100) * i.`weight`)
                      / SUM(i.`weight`), 2)
           FROM `monev_values` v
           JOIN `monev_indicators` i ON i.`id` = v.`indicator_id`
          WHERE v.`report_id` = r.`id`
            AND i.`target` > 0
       )
 WHERE EXISTS (
         SELECT 1
           FROM `monev_values` v
           JOIN `monev_indicators` i ON i.`id` = v.`indicator_id`
          WHERE v.`report_id` = r.`id`
            AND i.`target` > 0
       );
