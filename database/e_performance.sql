-- =============================================
-- E-Performance Module Database Schema
-- Sistem Penilaian Kinerja PTK Sekolah
-- =============================================

SET NAMES utf8mb4;

-- =============================================
-- MASTER DATA
-- =============================================

-- Data PTK (Pendidik & Tenaga Kependidikan)
CREATE TABLE IF NOT EXISTS `perf_ptk` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `niy` VARCHAR(30) NOT NULL,
  `nama` VARCHAR(150) NOT NULL,
  `tmt` DATE DEFAULT NULL,
  `tempat_lahir` VARCHAR(100) DEFAULT NULL,
  `tgl_lahir` DATE DEFAULT NULL,
  `jabatan` VARCHAR(100) DEFAULT NULL,
  `mata_pelajaran` VARCHAR(150) DEFAULT NULL,
  `jenis_ptk` VARCHAR(100) NOT NULL DEFAULT '-',
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_niy` (`niy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User/Login khusus E-Performance
CREATE TABLE IF NOT EXISTS `perf_users` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `perf_ptk_id` INT(11) UNSIGNED DEFAULT NULL,
  `nama_lengkap` VARCHAR(150) NOT NULL,
  `role` ENUM('admin','kepsek','guru','tu','it','pustakawan','siswa') NOT NULL,
  `eportal_user_id` INT(11) UNSIGNED DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_ptk` (`perf_ptk_id`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data Siswa
CREATE TABLE IF NOT EXISTS `perf_siswa` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_siswa` VARCHAR(150) NOT NULL,
  `kelas` VARCHAR(30) NOT NULL,
  `username` VARCHAR(50) DEFAULT NULL,
  `password_plain` VARCHAR(50) DEFAULT NULL,
  `perf_user_id` INT(11) UNSIGNED DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kelas` (`kelas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- PERIODE & INSTRUMEN
-- =============================================

-- Periode Penilaian
CREATE TABLE IF NOT EXISTS `perf_periode` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_periode` VARCHAR(100) NOT NULL,
  `tahun_ajaran` VARCHAR(20) NOT NULL,
  `semester` ENUM('1','2') NOT NULL DEFAULT '1',
  `tgl_mulai` DATE DEFAULT NULL,
  `tgl_selesai` DATE DEFAULT NULL,
  `status` ENUM('draft','aktif','selesai') NOT NULL DEFAULT 'draft',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Instrumen Penilaian (template pertanyaan)
CREATE TABLE IF NOT EXISTS `perf_instrumen` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode` VARCHAR(20) NOT NULL,
  `kategori` ENUM('kepsek','sejawat','diri','siswa') NOT NULL,
  `target_jabatan` ENUM('guru','tu','it','pustakawan') NOT NULL DEFAULT 'guru',
  `pertanyaan` TEXT NOT NULL,
  `urutan` INT(5) NOT NULL DEFAULT 0,
  `bobot` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kategori` (`kategori`, `target_jabatan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- PENUGASAN & SAMPLING
-- =============================================

-- Sampling siswa yang dipilih admin
CREATE TABLE IF NOT EXISTS `perf_sampling` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `perf_siswa_id` INT(11) UNSIGNED NOT NULL,
  `periode_id` INT(11) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sampling` (`perf_siswa_id`, `periode_id`),
  KEY `idx_periode` (`periode_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Penugasan penilaian teman sejawat (acak)
CREATE TABLE IF NOT EXISTS `perf_penugasan_sejawat` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `periode_id` INT(11) UNSIGNED NOT NULL,
  `penilai_ptk_id` INT(11) UNSIGNED NOT NULL,
  `dinilai_ptk_id` INT(11) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_penugasan` (`periode_id`, `penilai_ptk_id`, `dinilai_ptk_id`),
  KEY `idx_penilai` (`penilai_ptk_id`),
  KEY `idx_dinilai` (`dinilai_ptk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- PENILAIAN & HASIL
-- =============================================

-- Jawaban / Hasil penilaian
CREATE TABLE IF NOT EXISTS `perf_penilaian` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `periode_id` INT(11) UNSIGNED NOT NULL,
  `penilai_type` ENUM('kepsek','guru','tu','it','pustakawan','siswa') NOT NULL,
  `penilai_id` INT(11) UNSIGNED NOT NULL,
  `dinilai_ptk_id` INT(11) UNSIGNED NOT NULL,
  `instrumen_id` INT(11) UNSIGNED NOT NULL,
  `nilai` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=Kurang, 2=Cukup, 3=Baik, 4=Amat Baik',
  `catatan` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_periode_dinilai` (`periode_id`, `dinilai_ptk_id`),
  KEY `idx_penilai` (`penilai_type`, `penilai_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Progress tracking per penilai
CREATE TABLE IF NOT EXISTS `perf_progress` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `periode_id` INT(11) UNSIGNED NOT NULL,
  `penilai_type` ENUM('kepsek','guru','tu','it','pustakawan','siswa') NOT NULL,
  `penilai_id` INT(11) UNSIGNED NOT NULL,
  `target_ptk_id` INT(11) UNSIGNED NOT NULL,
  `status` ENUM('belum','selesai') NOT NULL DEFAULT 'belum',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_progress` (`periode_id`, `penilai_type`, `penilai_id`, `target_ptk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Session table khusus e-performance (untuk login mandiri)
CREATE TABLE IF NOT EXISTS `perf_sessions` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `perf_user_id` INT(11) UNSIGNED NOT NULL,
  `token` VARCHAR(128) NOT NULL,
  `expired_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_user` (`perf_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
