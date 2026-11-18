-- ============================================================================
-- SISTEM INFORMASI PENJAMINAN MUTU INTERNAL — STIKOM EL RAHMA
-- Basis data: MySQL 8.0 / MariaDB 10.4+   |   Karakter: utf8mb4
-- Berkas: sistem/desain/03-database.sql   (impor via phpMyAdmin / mysql CLI)
-- Kaitan: desain/02-erd.md
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `spmi_stikom`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `spmi_stikom`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `action_evidence`, `action_plans`, `satisfaction_answers`,
  `satisfaction_responses`, `satisfaction_questions`, `satisfaction_surveys`,
  `ami_findings`, `ami_assessments`, `ami_assignments`, `ami_instrument_logs`,
  `ami_instruments`, `ami_schedules`, `monev_values`, `monev_reports`,
  `monev_indicators`, `monev_periods`, `document_approvals`, `document_versions`,
  `documents`, `document_categories`, `standards`, `audit_logs`, `notifications`,
  `users`, `units`, `settings`;
DROP VIEW IF EXISTS `v_documents_status`;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- M5 — FONDASI: unit, pengguna, standar, pengaturan, jejak audit, notifikasi
-- ----------------------------------------------------------------------------

CREATE TABLE `units` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(10)  NOT NULL,
  `name`        VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_units_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id`       INT UNSIGNED NULL,
  `name`          VARCHAR(100) NOT NULL,
  `email`         VARCHAR(120) NOT NULL,
  `password`      VARCHAR(255) NOT NULL,             -- password_hash() PHP
  `role`          ENUM('super_admin','anggota') NOT NULL DEFAULT 'anggota',
  `nip`           VARCHAR(30)  NULL,
  `phone`         VARCHAR(20)  NULL,
  `photo`         VARCHAR(255) NULL,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `last_login_at` DATETIME     NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_unit` (`unit_id`),
  CONSTRAINT `fk_users_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `standards` (                      -- standar SPMI (bidang)
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(10)  NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `category`    VARCHAR(60)  NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_standards_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `settings` (
  `key`        VARCHAR(64) NOT NULL,
  `value`      TEXT NULL,
  `description` VARCHAR(255) NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NULL,
  `action`      VARCHAR(60)  NOT NULL,            -- create/update/delete/approve/login
  `table_name`  VARCHAR(64)  NULL,
  `row_id`      INT UNSIGNED NULL,
  `detail`      TEXT NULL,                        -- JSON: {"old":..., "new":...}
  `ip_address`  VARCHAR(45)  NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_table` (`table_name`, `row_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `notifications` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `title`      VARCHAR(150) NOT NULL,
  `message`    TEXT NOT NULL,
  `type`       ENUM('persetujuan','tenggat','monev','dokumen','temuan','umum') NOT NULL DEFAULT 'umum',
  `ref_table`  VARCHAR(64) NULL,
  `ref_id`     INT UNSIGNED NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user_read` (`user_id`, `is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- M1 — DOKUMEN SPMI  (masa berlaku + status hijau/kuning/merah + versi + approval)
-- ----------------------------------------------------------------------------

CREATE TABLE `document_categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id`  INT UNSIGNED NULL,                 -- hierarki: kebijakan > pedoman > manual/SOP > instrumen
  `name`       VARCHAR(100) NOT NULL,
  `level`      TINYINT      NOT NULL DEFAULT 1,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_cat_parent` (`parent_id`),
  CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `document_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `documents` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`     INT UNSIGNED NULL,
  `standard_id`     INT UNSIGNED NULL,
  `unit_id`         INT UNSIGNED NULL,            -- unit pemilik dokumen
  `owner_user_id`   INT UNSIGNED NULL,            -- pengunggah
  `title`           VARCHAR(200) NOT NULL,
  `doc_number`      VARCHAR(60)  NULL,
  `version`         VARCHAR(20)  NOT NULL DEFAULT '1.0',
  `effective_date`  DATE NULL,                    -- tanggal mulai berlaku
  `expiry_date`     DATE NULL,                    -- MASA BERLAKU (kunci fitur M1)
  `file_path`       VARCHAR(255) NOT NULL,
  `approval_status` ENUM('draft','submitted','approved','rejected','archived')
                    NOT NULL DEFAULT 'draft',
  `status_color`    ENUM('green','yellow','red') NOT NULL DEFAULT 'green',  -- 🟢🟡🔴
  `description`     TEXT NULL,
  `submitted_at`    DATETIME NULL,
  `approved_at`     DATETIME NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc_category` (`category_id`),
  KEY `idx_doc_standard` (`standard_id`),
  KEY `idx_doc_unit` (`unit_id`),
  KEY `idx_doc_status` (`approval_status`, `status_color`),
  KEY `idx_doc_expiry` (`expiry_date`),
  CONSTRAINT `fk_doc_cat` FOREIGN KEY (`category_id`) REFERENCES `document_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_doc_std` FOREIGN KEY (`standard_id`) REFERENCES `standards` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_doc_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_doc_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `document_versions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`   INT UNSIGNED NOT NULL,
  `version`       VARCHAR(20)  NOT NULL,
  `file_path`     VARCHAR(255) NOT NULL,
  `change_note`   TEXT NULL,
  `submitted_by`  INT UNSIGNED NULL,
  `approved_by`   INT UNSIGNED NULL,
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dv_doc` (`document_id`),
  CONSTRAINT `fk_dv_doc` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dv_sub` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dv_app` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `document_approvals` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id`      INT UNSIGNED NOT NULL,
  `step`             TINYINT NOT NULL DEFAULT 1,
  `approver_user_id` INT UNSIGNED NULL,
  `status`           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `comment`          TEXT NULL,
  `acted_at`         DATETIME NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_da_doc` (`document_id`),
  CONSTRAINT `fk_da_doc` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_da_user` FOREIGN KEY (`approver_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- M2 — MONEV (6 bulan sekali)
-- ----------------------------------------------------------------------------

CREATE TABLE `monev_periods` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(80) NOT NULL,              -- contoh: "Monev 2026-1"
  `start_date` DATE NOT NULL,
  `end_date`   DATE NOT NULL,
  `status`     ENUM('open','closed') NOT NULL DEFAULT 'open',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `monev_indicators` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `standard_id`     INT UNSIGNED NULL,
  `code`            VARCHAR(20) NOT NULL,
  `name`            VARCHAR(200) NOT NULL,
  `unit_of_measure` VARCHAR(40) NULL,             -- %, jumlah, skor
  `target`          DECIMAL(10,2) NULL,
  `weight`          DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_mi_std` (`standard_id`),
  CONSTRAINT `fk_mi_std` FOREIGN KEY (`standard_id`) REFERENCES `standards` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `monev_reports` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `period_id`     INT UNSIGNED NOT NULL,
  `unit_id`       INT UNSIGNED NOT NULL,
  `submitted_by`  INT UNSIGNED NULL,
  `status`        ENUM('draft','submitted','approved') NOT NULL DEFAULT 'draft',
  `total_score`   DECIMAL(6,2) NULL,              -- hasil rekap otomatis
  `notes`         TEXT NULL,
  `submitted_at`  DATETIME NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_report_period_unit` (`period_id`, `unit_id`),
  KEY `idx_mr_unit` (`unit_id`),
  CONSTRAINT `fk_mr_period` FOREIGN KEY (`period_id`) REFERENCES `monev_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mr_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mr_user` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `monev_values` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id`          INT UNSIGNED NOT NULL,
  `indicator_id`       INT UNSIGNED NOT NULL,
  `value`              DECIMAL(10,2) NULL,
  `achievement_percent` DECIMAL(6,2) NULL,        -- value / target × 100
  `evidence_file`      VARCHAR(255) NULL,         -- unggah bukti
  `notes`              TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mv_report_indicator` (`report_id`, `indicator_id`),
  CONSTRAINT `fk_mv_report` FOREIGN KEY (`report_id`) REFERENCES `monev_reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mv_ind` FOREIGN KEY (`indicator_id`) REFERENCES `monev_indicators` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- M3 — AMI & EVALUASI (instrumen fleksibel + formulir kepuasan)
-- ----------------------------------------------------------------------------

CREATE TABLE `ami_schedules` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `year`       YEAR NOT NULL,
  `name`       VARCHAR(80) NOT NULL,              -- "AMI 2026"
  `start_date` DATE NULL,
  `end_date`   DATE NULL,
  `status`     ENUM('draft','running','closed') NOT NULL DEFAULT 'draft',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ami_year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ami_instruments` (                  -- INDIKATOR FLEKSIBEL (bisa diedit)
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `schedule_id`     INT UNSIGNED NOT NULL,
  `standard_id`     INT UNSIGNED NULL,
  `code`            VARCHAR(20)  NOT NULL,        -- A.1, A.2, dst.
  `question`        TEXT NOT NULL,                -- indikator yang dapat disunting
  `assessment_guide` TEXT NULL,                   -- pedoman penilaian
  `weight`          DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `is_active`       TINYINT(1)  NOT NULL DEFAULT 1,
  `sort_order`      INT         NOT NULL DEFAULT 0,
  `created_by`      INT UNSIGNED NULL,
  `updated_by`      INT UNSIGNED NULL,
  `created_at`      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_schedule` (`schedule_id`),
  KEY `idx_ai_std` (`standard_id`),
  CONSTRAINT `fk_ai_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `ami_schedules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_std` FOREIGN KEY (`standard_id`) REFERENCES `standards` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ai_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ai_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ami_instrument_logs` (              -- jejak perubahan indikator
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `instrument_id` INT UNSIGNED NOT NULL,
  `changed_by`    INT UNSIGNED NULL,
  `old_value`     TEXT NULL,
  `new_value`     TEXT NULL,
  `reason`        TEXT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ail_instr` (`instrument_id`),
  CONSTRAINT `fk_ail_instr` FOREIGN KEY (`instrument_id`) REFERENCES `ami_instruments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ail_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ami_assignments` (                  -- pasangan auditor – auditee
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `schedule_id`           INT UNSIGNED NOT NULL,
  `unit_id`               INT UNSIGNED NOT NULL,
  `auditor_user_id`       INT UNSIGNED NULL,
  `auditee_lead_user_id`  INT UNSIGNED NULL,      -- Waket terkait
  `status`                ENUM('assigned','in_progress','completed') NOT NULL DEFAULT 'assigned',
  `report_number`         VARCHAR(40) NULL,
  `audit_date`            DATE NULL,
  `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assign_schedule_unit` (`schedule_id`, `unit_id`),
  KEY `idx_aa_unit` (`unit_id`),
  KEY `idx_aa_auditor` (`auditor_user_id`),
  CONSTRAINT `fk_aa_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `ami_schedules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aa_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aa_auditor` FOREIGN KEY (`auditor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_aa_lead` FOREIGN KEY (`auditee_lead_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ami_assessments` (                  -- penilaian per indikator (skor 0–4)
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` INT UNSIGNED NOT NULL,
  `instrument_id` INT UNSIGNED NOT NULL,
  `score`         TINYINT NOT NULL DEFAULT 0,     -- 0,1,2,3,4
  `evidence_file` VARCHAR(255) NULL,
  `finding_note`  TEXT NULL,
  `filled_by`     INT UNSIGNED NULL,
  `filled_at`     DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assess_assignment_instr` (`assignment_id`, `instrument_id`),
  KEY `idx_as_instr` (`instrument_id`),
  CONSTRAINT `fk_as_assign` FOREIGN KEY (`assignment_id`) REFERENCES `ami_assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_as_instr` FOREIGN KEY (`instrument_id`) REFERENCES `ami_instruments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_as_user` FOREIGN KEY (`filled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ami_findings` (                     -- TEMUAN → bahan M4
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assessment_id`  INT UNSIGNED NULL,
  `assignment_id`  INT UNSIGNED NOT NULL,
  `finding_type`   ENUM('ketidaksesuaian','observasi','rekomendasi') NOT NULL DEFAULT 'ketidaksesuaian',
  `severity`       ENUM('tinggi','sedang','rendah') NOT NULL DEFAULT 'sedang',
  `description`    TEXT NOT NULL,
  `identified_by`  INT UNSIGNED NULL,
  `status`         ENUM('open','in_progress','verified','closed') NOT NULL DEFAULT 'open',
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_find_assign` (`assignment_id`),
  KEY `idx_find_assess` (`assessment_id`),
  KEY `idx_find_status` (`status`),
  CONSTRAINT `fk_find_assess` FOREIGN KEY (`assessment_id`) REFERENCES `ami_assessments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_find_assign` FOREIGN KEY (`assignment_id`) REFERENCES `ami_assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_find_user` FOREIGN KEY (`identified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `satisfaction_surveys` (             -- FORMULIR KEPUSASAN
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150) NOT NULL,
  `start_date` DATE NULL,
  `end_date`   DATE NULL,
  `status`     ENUM('draft','open','closed') NOT NULL DEFAULT 'draft',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `satisfaction_questions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `survey_id`  INT UNSIGNED NOT NULL,
  `question`   TEXT NOT NULL,
  `type`       ENUM('skala','pilihan','teks') NOT NULL DEFAULT 'skala',
  `weight`     DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_sq_survey` (`survey_id`),
  CONSTRAINT `fk_sq_survey` FOREIGN KEY (`survey_id`) REFERENCES `satisfaction_surveys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `satisfaction_responses` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `survey_id`         INT UNSIGNED NOT NULL,
  `respondent_user_id` INT UNSIGNED NULL,         -- NULL = anonim (bila diizinkan)
  `submitted_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sr_survey` (`survey_id`),
  CONSTRAINT `fk_sr_survey` FOREIGN KEY (`survey_id`) REFERENCES `satisfaction_surveys` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sr_user` FOREIGN KEY (`respondent_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `satisfaction_answers` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `response_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `score`       DECIMAL(5,2) NULL,
  `answer_text` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sa_response` (`response_id`),
  CONSTRAINT `fk_sa_response` FOREIGN KEY (`response_id`) REFERENCES `satisfaction_responses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sa_question` FOREIGN KEY (`question_id`) REFERENCES `satisfaction_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- M4 — PENGENDALIAN & PENINGKATAN (tindak lanjut berkelanjutan)
-- ----------------------------------------------------------------------------

CREATE TABLE `action_plans` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `finding_id`        INT UNSIGNED NOT NULL,
  `pic_user_id`       INT UNSIGNED NULL,          -- Waket sebagai penanggung jawab
  `pic_unit_id`       INT UNSIGNED NULL,
  `description`       TEXT NOT NULL,
  `due_date`          DATE NULL,                  -- tenggat
  `status`            ENUM('open','in_progress','done','verified') NOT NULL DEFAULT 'open',
  `priority`          ENUM('tinggi','sedang','rendah') NOT NULL DEFAULT 'sedang',
  `verified_by`       INT UNSIGNED NULL,
  `verified_at`       DATETIME NULL,
  `verification_note` TEXT NULL,
  `revision_document_id` INT UNSIGNED NULL,      -- FR-12: dokumen yang diminta direvisi
  `revision_note`     TEXT NULL,                 -- catatan umpan balik revisi
  `revision_requested_at` DATETIME NULL,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ap_finding` (`finding_id`),
  KEY `idx_ap_pic` (`pic_user_id`),
  KEY `idx_ap_status_due` (`status`, `due_date`),
  CONSTRAINT `fk_ap_finding` FOREIGN KEY (`finding_id`) REFERENCES `ami_findings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ap_pic` FOREIGN KEY (`pic_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ap_unit` FOREIGN KEY (`pic_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ap_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ap_revision_doc` FOREIGN KEY (`revision_document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `action_evidence` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action_plan_id` INT UNSIGNED NOT NULL,
  `file_path`      VARCHAR(255) NOT NULL,
  `note`           TEXT NULL,
  `uploaded_by`    INT UNSIGNED NULL,
  `status`         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ae_action` (`action_plan_id`),
  CONSTRAINT `fk_ae_action` FOREIGN KEY (`action_plan_id`) REFERENCES `action_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ae_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- VIEW — status masa berlaku dokumen (hijau / kuning / merah)
-- Aturan: kuning bila ≤ 30 hari menuju kedaluwarsa; merah bila sudah lewat.
-- ----------------------------------------------------------------------------
CREATE OR REPLACE VIEW `v_documents_status` AS
SELECT
  d.*,
  CASE
    WHEN d.approval_status = 'archived' THEN 'red'
    WHEN d.expiry_date IS NULL THEN 'green'
    WHEN d.expiry_date <  CURDATE() THEN 'red'
    WHEN d.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'yellow'
    ELSE 'green'
  END AS computed_status_color,
  DATEDIFF(d.expiry_date, CURDATE()) AS days_to_expiry
FROM `documents` d;

-- ============================================================================
-- SEED DATA
-- ============================================================================

-- Unit kerja [SESUAIKAN dengan struktur STIKOM El Rahma]
INSERT INTO `units` (`code`, `name`, `description`) VALUES
('LPM',   'Lembaga Penjaminan Mutu', 'Super Admin sistem'),
('W1',    'Bidang Akademik',          'Wakil Ketua I'),
('W2',    'Bidang Keuangan & SDM',    'Wakil Ketua II'),
('W3',    'Bidang Kemahasiswaan',     'Wakil Ketua III'),
('W4',    'Bidang Pengembangan',      'Wakil Ketua IV');

-- Pengguna awal — GANTI HASH-nya: php -r "echo password_hash('katasandi', PASSWORD_DEFAULT);"
INSERT INTO `users` (`unit_id`, `name`, `email`, `password`, `role`) VALUES
(1, 'Admin LPM', 'admin@stikomelrahma.ac.id', '$2y$10$REPLACE_DENGAN_HASH_PASSWORD', 'super_admin');

-- Standar SPMI (mengacu SN Dikti)
INSERT INTO `standards` (`code`, `name`, `category`) VALUES
('S1', 'Standar Pendidikan',               'Akademik'),
('S2', 'Standar Penelitian',               'Tri Dharma'),
('S3', 'Standar Pengabdian Kepada Masyarakat', 'Tri Dharma'),
('S4', 'Standar Sumber Daya Manusia',      'Operasional'),
('S5', 'Standar Sarana dan Prasarana',     'Operasional'),
('S6', 'Standar Pembiayaan',               'Operasional'),
('S7', 'Standar Penjaminan Mutu Internal', 'Penjaminan Mutu'),
('S8', 'Standar Data dan Informasi',       'Operasional');

-- Hierarki dokumen SPMI
INSERT INTO `document_categories` (`parent_id`, `name`, `level`, `sort_order`) VALUES
(NULL, 'Kebijakan Mutu',   1, 1),
(NULL, 'Pedoman SPMI',     1, 2),
(NULL, 'Manual Mutu',      1, 3),
(NULL, 'SOP',              1, 4),
(NULL, 'Instrumen',        1, 5),
(NULL, 'Formulir',         1, 6),
(NULL, 'Laporan Mutu',     1, 7);

-- Periode Monev (6 bulan sekali)
INSERT INTO `monev_periods` (`name`, `start_date`, `end_date`, `status`) VALUES
('Monev 2026-1', '2026-01-01', '2026-06-30', 'open'),
('Monev 2026-2', '2026-07-01', '2026-12-31', 'open');

-- Indikator Monev contoh (samakan dengan standar kampus)
INSERT INTO `monev_indicators` (`standard_id`, `code`, `name`, `unit_of_measure`, `target`, `weight`) VALUES
(1, 'M1.1', 'Terselenggaranya pembelajaran sesuai RPS', '%', 100, 1.00),
(1, 'M1.2', 'Ketuntasan belajar mahasiswa',            '%', 80,  1.50),
(4, 'M4.1', 'Dosen bergelar magister sesuai kualifikasi', '%', 80, 1.00),
(7, 'M7.1', 'Laporan Monev disetujui tepat waktu',      '%', 100, 1.00);

-- Jadwal AMI tahunan + instrumen contoh (INDIKATOR FLEKSIBEL)
INSERT INTO `ami_schedules` (`year`, `name`, `start_date`, `end_date`, `status`) VALUES
(2026, 'AMI 2026', '2026-08-01', '2026-11-30', 'running');

INSERT INTO `ami_instruments`
  (`schedule_id`, `standard_id`, `code`, `question`, `assessment_guide`, `weight`, `sort_order`, `created_by`)
VALUES
(1, 1, 'A.1', 'Standar pembelajaran telah ditetapkan dan disetujui',
       'Lihat dokumen pedoman & bukti persetujuan', 1.00, 1, 1),
(1, 1, 'A.2', 'RPS seluruh mata kuliah memenuhi standar',
       'Telaah RPS acak minimal 20%', 1.50, 2, 1),
(1, 7, 'A.3', 'Evaluasi mutu dilakukan berkala dan hasilnya ditindaklanjuti',
       'Lihat laporan AMI/Monev & bukti tindak lanjut', 2.00, 3, 1);

-- Formulir kepuasan contoh
INSERT INTO `satisfaction_surveys` (`name`, `start_date`, `end_date`, `status`) VALUES
('Kepuasan Pengguna Layanan Mutu 2026', '2026-09-01', '2026-10-31', 'open');

INSERT INTO `satisfaction_questions` (`survey_id`, `question`, `type`, `weight`, `sort_order`) VALUES
(1, 'Kecepatan layanan pengelolaan dokumen mutu', 'skala', 1.00, 1),
(1, 'Kemudahan mengakses dokumen dan laporan',   'skala', 1.00, 2),
(1, 'Kejelasan informasi status dokumen',        'skala', 1.00, 3);

-- Pengaturan sistem
INSERT INTO `settings` (`key`, `value`, `description`) VALUES
('institution_name', 'Sekolah Tinggi Ilmu Komputer El Rahma', 'Nama institusi'),
('institution_address', 'Jl. DR. Sumeru No. 21G, Kebon Kalapa, Kec. Bogor Tengah, Kota Bogor, Jawa Barat 16125', 'Alamat'),
('document_validity_warning_days', '30', 'Hari peringatan sebelum masa berlaku dokumen habis (kuning)');

-- ============================================================================
-- CATATAN IMPLEMENTASI
-- 1. Password admin di atas PLACEHOLDER — wajib diganti dengan hash asli.
-- 2. `status_color` diisi otomatis oleh job/cron harian:
--        UPDATE documents SET status_color = computed... (atau pakai view + trigger)
--    Alternatif sederhana: hitung saat tampil memakai `v_documents_status`.
-- 3. Untuk MariaDB, YEAR type dan ENUM sama-sama didukung.
-- 4. Backup berkala: mysqldump -u root -p spmi_stikom > database/backup_YYYYMMDD.sql
-- ============================================================================
