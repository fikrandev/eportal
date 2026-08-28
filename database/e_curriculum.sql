-- =============================================
-- E-Curriculum Module Database Schema (Extended)
-- Sistem Informasi Kurikulum - Fitur Lengkap
-- =============================================

USE `eportal_db`;

-- 1. Table: acad_kelas (Academic Classes) - Already exists
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

-- 2. Table: acad_mapel (Academic Subjects) - Already exists
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

-- 3. Table: acad_mengajar (Teacher Teaching Assignments) - Already exists
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

-- =============================================
-- NEW TABLES FOR E-CURRICULUM EXPANSION
-- =============================================

-- 4. Table: acad_jurnal (Teaching Journal / Jurnal Mengajar)
CREATE TABLE IF NOT EXISTS `acad_jurnal` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guru_id` INT(11) UNSIGNED NOT NULL COMMENT 'References users.id',
  `kelas_id` INT UNSIGNED NOT NULL COMMENT 'References acad_kelas.id',
  `mapel_id` INT UNSIGNED NOT NULL COMMENT 'References acad_mapel.id',
  `academic_year_id` INT(11) UNSIGNED NOT NULL,
  `tanggal` DATE NOT NULL,
  `jam_ke` VARCHAR(20) NOT NULL COMMENT 'Misal: 1, 2, 3-4, dst',
  `tujuan_pembelajaran` TEXT COMMENT 'TP - Tujuan Pembelajaran singkat',
  `indikator_tp` TEXT COMMENT 'IPTP - Indikator Pencapaian TP',
  `catatan` TEXT COMMENT 'Catatan Pembelajaran',
  `siswa_tidak_hadir` TEXT COMMENT 'JSON array nama/id siswa yg tidak hadir',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jurnal_guru` (`guru_id`),
  KEY `idx_jurnal_tanggal` (`tanggal`),
  KEY `idx_jurnal_kelas` (`kelas_id`),
  KEY `idx_jurnal_year` (`academic_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table: acad_absensi (Student Attendance / Absensi Siswa)
CREATE TABLE IF NOT EXISTS `acad_absensi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) UNSIGNED NOT NULL COMMENT 'References students.id',
  `kelas_id` INT UNSIGNED NOT NULL COMMENT 'References acad_kelas.id',
  `academic_year_id` INT(11) UNSIGNED NOT NULL,
  `tanggal` DATE NOT NULL,
  `jam_ke` INT NOT NULL DEFAULT 0 COMMENT '0 = keseluruhan hari, 1-12 = jam ke-',
  `status` ENUM('H','S','I','A') NOT NULL DEFAULT 'H' COMMENT 'Hadir/Sakit/Izin/Alpha',
  `keterangan` VARCHAR(255) DEFAULT NULL,
  `dicatat_oleh` INT(11) UNSIGNED DEFAULT NULL COMMENT 'user_id guru yg mencatat',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_absensi` (`student_id`, `tanggal`, `jam_ke`),
  KEY `idx_absensi_tanggal` (`tanggal`),
  KEY `idx_absensi_kelas` (`kelas_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Table: acad_ketidakhadiran (Teacher Absence / Ketidakhadiran Guru)
CREATE TABLE IF NOT EXISTS `acad_ketidakhadiran` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guru_id` INT(11) UNSIGNED NOT NULL COMMENT 'References users.id',
  `tanggal` DATE NOT NULL,
  `jenis` ENUM('Izin','Sakit') NOT NULL DEFAULT 'Izin',
  `catatan` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ketidakhadiran` (`guru_id`, `tanggal`),
  KEY `idx_ketidakhadiran_tanggal` (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Table: acad_piket (Teacher Duty / Piket Guru)
CREATE TABLE IF NOT EXISTS `acad_piket` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tanggal` DATE NOT NULL,
  `guru_id` INT(11) UNSIGNED NOT NULL COMMENT 'Guru piket pengganti',
  `guru_diganti_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'Guru yg digantikan (jika ada)',
  `kelas_id` INT UNSIGNED DEFAULT NULL COMMENT 'Kelas yg dijaga',
  `jam_ke` VARCHAR(20) DEFAULT NULL,
  `catatan` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_piket_tanggal` (`tanggal`),
  KEY `idx_piket_guru` (`guru_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Table: acad_buku_penghubung (Student Communication Book)
CREATE TABLE IF NOT EXISTS `acad_buku_penghubung` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT(11) UNSIGNED NOT NULL COMMENT 'References students.id',
  `kelas_id` INT UNSIGNED NOT NULL,
  `academic_year_id` INT(11) UNSIGNED NOT NULL,
  `jenis` ENUM('Keterlambatan','Pelanggaran','Prestasi','Screening','Konsultasi') NOT NULL,
  `tanggal` DATE NOT NULL,
  `catatan` TEXT NOT NULL,
  `dicatat_oleh` INT(11) UNSIGNED DEFAULT NULL COMMENT 'user_id guru',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_buku_student` (`student_id`),
  KEY `idx_buku_kelas` (`kelas_id`),
  KEY `idx_buku_tanggal` (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Table: acad_jurnal_pipinan (School Leader Journal - Jurnal Pimpinan)
CREATE TABLE IF NOT EXISTS `acad_jurnal_pimpinan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'Pimpinan sekolah',
  `tanggal` DATE NOT NULL,
  `catatan_kegiatan` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jp_tanggal` (`tanggal`),
  KEY `idx_jp_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Table: acad_jurnal_wali (Homeroom Teacher Journal - Jurnal Guru Wali)
CREATE TABLE IF NOT EXISTS `acad_jurnal_wali` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `guru_id` INT(11) UNSIGNED NOT NULL COMMENT 'Wali kelas',
  `kelas_id` INT UNSIGNED NOT NULL,
  `tanggal` DATE NOT NULL,
  `catatan_kegiatan` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_jw_tanggal` (`tanggal`),
  KEY `idx_jw_guru` (`guru_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Register Module in Main Portal
INSERT IGNORE INTO `modules` (`nama_modul`, `slug`, `deskripsi`, `icon_svg`, `url_path`, `color`, `urutan`, `status`) VALUES
('E-Examination', 'e-examination', 'Sistem Ujian Digital CBT', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 15 11 17 15 11"/></svg>', 'modules/e-examination/', '#2563EB', 6, 1),
('E-Curriculum', 'e-curriculum', 'Sistem Informasi Kurikulum', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>', 'modules/e-curriculum/', '#7C3AED', 7, 1);
