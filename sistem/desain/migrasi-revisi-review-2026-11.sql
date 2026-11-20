-- ============================================================================
-- MIGRASI — Revisi Review LPM (19 November 2026), Item 1 & 10:
--            HAPUS ALUR PERSETUJUAN DOKUMEN (M1)
--            + Item 4: penilaian AMI — bukti auditee sebelum skor (score NULL)
--            + Item 11: tipe kepuasan pilihan (kolom opsi)
--            + Item 12: mode tamu — token tautan per formulir kepuasan
-- Untuk basis data LIVE yang sudah berisi data (tanpa impor ulang penuh).
-- Jalankan sekali via phpMyAdmin / mysql CLI pada database `spmi_stikom`.
-- (Basis data baru/kosong: cukup impor 03-database.sql — sudah mencakup ini.)
-- ============================================================================

-- 1) Notifikasi: baris lama bertipe 'persetujuan' dialihkan ke 'umum',
--    lalu ENUM dibersihkan.
UPDATE `notifications` SET `type` = 'umum' WHERE `type` = 'persetujuan';
ALTER TABLE `notifications`
  MODIFY `type` ENUM('tenggat','monev','dokumen','temuan','umum') NOT NULL DEFAULT 'umum';

-- 2) Dokumen: buang kolom & indeks alur persetujuan.
ALTER TABLE `documents`
  DROP INDEX `idx_doc_status`,
  ADD KEY `idx_doc_color` (`status_color`),
  DROP COLUMN `approval_status`,
  DROP COLUMN `submitted_at`,
  DROP COLUMN `approved_at`;

-- 3) Riwayat versi: murni unggah versi baru (tanpa status persetujuan versi).
ALTER TABLE `document_versions`
  DROP FOREIGN KEY `fk_dv_app`,
  DROP COLUMN `approved_by`,
  DROP COLUMN `status`;

-- 4) Tabel tahap persetujuan tidak lagi dipakai.
DROP TABLE IF EXISTS `document_approvals`;

-- 5) View status masa berlaku: tanpa cabang 'archived'.
CREATE OR REPLACE VIEW `v_documents_status` AS
SELECT
  d.*,
  CASE
    WHEN d.expiry_date IS NULL THEN 'green'
    WHEN d.expiry_date <  CURDATE() THEN 'red'
    WHEN d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'yellow'
    ELSE 'green'
  END AS computed_status_color,
  DATEDIFF(d.expiry_date, CURDATE()) AS days_to_expiry
FROM `documents` d;

-- 6) Item 4 — AMI: skor boleh NULL = bukti sudah diunggah auditee, belum
--    dinilai auditor. Skor & catatan temuan tetap hanya Super Admin/auditor.
ALTER TABLE `ami_assessments`
  MODIFY `score` TINYINT NULL DEFAULT NULL;

-- 7) Item 11 — pertanyaan kepuasan bertipe 'pilihan' menyimpan daftar opsi
--    dipisah '|' (mis. "Ya|Tidak"); NULL untuk skala/teks.
ALTER TABLE `satisfaction_questions`
  ADD COLUMN `options` VARCHAR(255) NULL DEFAULT NULL AFTER `type`;

-- 8) Item 12 — mode tamu: tautan `/puas/{token}` per formulir kepuasan.
--    Token dibuat saat admin mengaktifkan tautan; `guest_enabled = 0` = dimatikan.
ALTER TABLE `satisfaction_surveys`
  ADD COLUMN `guest_token` VARCHAR(64) NULL DEFAULT NULL AFTER `status`,
  ADD COLUMN `guest_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `guest_token`;

-- ----------------------------------------------------------------------------
-- Catatan pendamping (bila belum diterapkan di live):
-- Revisi Waket IV — hanya Waket I–III (hapus unit W4):
--   DELETE FROM `units` WHERE `code` = 'W4';
-- ============================================================================
