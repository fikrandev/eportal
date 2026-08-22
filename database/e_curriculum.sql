-- =============================================
-- E-Curriculum Module Database Schema
-- Sistem Informasi Kurikulum
-- =============================================

USE `eportal_db`;

-- 1. Table: acad_kelas (Academic Classes)
CREATE TABLE IF NOT EXISTS `acad_kelas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_kelas` VARCHAR(50) NOT NULL,
  `tingkat` TINYINT NOT NULL COMMENT '10, 11, 12, etc.',
  `wali_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'References users.id (role=guru)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nama_kelas` (`nama_kelas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table: acad_mapel (Academic Subjects)
CREATE TABLE IF NOT EXISTS `acad_mapel` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_mapel` VARCHAR(50) NOT NULL,
  `nama_mapel` VARCHAR(150) NOT NULL,
  `kelompok` VARCHAR(50) NOT NULL COMMENT 'Kelompok A, Kelompok B, Pilihan, dll',
  `kkm` TINYINT NOT NULL DEFAULT 75,
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Aktif, 0 = Nonaktif',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kode_mapel` (`kode_mapel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table: acad_mengajar (Teacher Teaching Assignments)
CREATE TABLE IF NOT EXISTS `acad_mengajar` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guru_id` INT(11) UNSIGNED NOT NULL COMMENT 'References users.id',
  `mapel_id` INT UNSIGNED NOT NULL COMMENT 'References acad_mapel.id',
  `kelas_id` INT UNSIGNED NOT NULL COMMENT 'References acad_kelas.id',
  `academic_year_id` INT(11) UNSIGNED NOT NULL COMMENT 'References academic_years.id',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mengajar` (`guru_id`, `mapel_id`, `kelas_id`, `academic_year_id`),
  CONSTRAINT `fk_acad_mengajar_guru` FOREIGN KEY (`guru_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_acad_mengajar_mapel` FOREIGN KEY (`mapel_id`) REFERENCES `acad_mapel` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_acad_mengajar_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `acad_kelas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_acad_mengajar_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Register Modules in Main Portal
INSERT IGNORE INTO `modules` (`nama_modul`, `slug`, `deskripsi`, `icon_svg`, `url_path`, `color`, `urutan`, `status`) VALUES
('E-Examination', 'e-examination', 'Sistem Ujian Digital CBT', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 15 11"/></svg>', 'modules/e-examination/', '#2563EB', 6, 1),
('E-Curriculum', 'e-curriculum', 'Sistem Informasi Kurikulum', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>', 'modules/e-curriculum/', '#7C3AED', 7, 1);
