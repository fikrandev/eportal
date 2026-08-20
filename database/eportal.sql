-- =============================================
-- E-Portal Database Schema - Phase 1
-- Created: 2026-04-02
-- =============================================

CREATE DATABASE IF NOT EXISTS `eportal_db` 
DEFAULT CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `eportal_db`;

-- =============================================
-- Table: users
-- =============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `nama_lengkap` VARCHAR(100) NOT NULL,
    `role` ENUM('superadmin','user','guru') NOT NULL DEFAULT 'user',
    `tempat_lahir` VARCHAR(100) DEFAULT NULL,
    `tgl_lahir` DATE DEFAULT NULL,
    `tupoksi` VARCHAR(150) DEFAULT NULL,
    `jabatan` VARCHAR(150) DEFAULT NULL,
    `status_guru` VARCHAR(100) DEFAULT NULL,
    `tpg` ENUM('Ya','Tidak') NOT NULL DEFAULT 'Tidak',
    `tmt` DATE DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
    `last_login` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: modules
-- =============================================
CREATE TABLE IF NOT EXISTS `modules` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama_modul` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `deskripsi` TEXT DEFAULT NULL,
    `icon_svg` TEXT NOT NULL COMMENT 'SVG markup or path',
    `url_path` VARCHAR(255) NOT NULL,
    `color` VARCHAR(20) DEFAULT '#1565C0' COMMENT 'Module accent color',
    `urutan` INT(11) NOT NULL DEFAULT 0,
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: settings
-- =============================================
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    `setting_type` ENUM('text','number','boolean','json','file') NOT NULL DEFAULT 'text',
    `keterangan` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: sessions
-- =============================================
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `expired_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_token` (`token`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_expired` (`expired_at`),
    CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: academic_years
-- Shared active academic year for all modules
-- =============================================
CREATE TABLE IF NOT EXISTS `academic_years` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tahun_ajaran` VARCHAR(9) NOT NULL,
    `semester` ENUM('1','2') NOT NULL DEFAULT '1',
    `tanggal_mulai` DATE DEFAULT NULL,
    `tanggal_selesai` DATE DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_academic_year_semester` (`tahun_ajaran`, `semester`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: students
-- Master student data for E-Graduation and modules
-- =============================================
CREATE TABLE IF NOT EXISTS `students` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id` INT(11) UNSIGNED NOT NULL,
    `no_urut` INT(11) DEFAULT 0,
    `nis` VARCHAR(50) NOT NULL,
    `nisn` VARCHAR(50) DEFAULT NULL,
    `nama` VARCHAR(150) NOT NULL,
    `tempat_lahir` VARCHAR(100) DEFAULT NULL,
    `jenis_kelamin` ENUM('L','P') NOT NULL,
    `tanggal_lahir` DATE NOT NULL,
    `kelas` VARCHAR(50) NOT NULL,
    `foto_path` VARCHAR(255) DEFAULT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 1,
    `rata_rata` DECIMAL(5,2) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_student_nis_year` (`academic_year_id`, `nis`),
    UNIQUE KEY `uk_student_nisn_year` (`academic_year_id`, `nisn`),
    KEY `idx_student_class` (`kelas`),
    CONSTRAINT `fk_students_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: grad_subject_groups
-- Kelompok mata pelajaran E-Graduation
-- =============================================
CREATE TABLE IF NOT EXISTS `grad_subject_groups` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id` INT(11) UNSIGNED NOT NULL,
    `kode` VARCHAR(20) NOT NULL,
    `nama` VARCHAR(150) NOT NULL,
    `tipe` ENUM('wajib','pilihan','lainnya') NOT NULL DEFAULT 'wajib',
    `deskripsi` TEXT DEFAULT NULL,
    `urutan` INT(11) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_grad_group_year_code` (`academic_year_id`, `kode`),
    KEY `idx_grad_group_year` (`academic_year_id`),
    CONSTRAINT `fk_grad_group_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: grad_subjects
-- Mata pelajaran E-Graduation
-- =============================================
CREATE TABLE IF NOT EXISTS `grad_subjects` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id` INT(11) UNSIGNED NOT NULL,
    `group_id` INT(11) UNSIGNED NOT NULL,
    `kode_mapel` VARCHAR(50) DEFAULT NULL,
    `nama_mapel` VARCHAR(150) NOT NULL,
    `kelas` VARCHAR(100) DEFAULT NULL,
    `urutan` INT(11) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_grad_subject_scope` (`academic_year_id`, `group_id`, `kelas`, `nama_mapel`),
    KEY `idx_grad_subject_year` (`academic_year_id`),
    KEY `idx_grad_subject_group` (`group_id`),
    KEY `idx_grad_subject_class` (`kelas`),
    CONSTRAINT `fk_grad_subject_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_grad_subject_group` FOREIGN KEY (`group_id`) REFERENCES `grad_subject_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: grad_letter_settings
-- Pengaturan nomor surat kelulusan
-- =============================================
CREATE TABLE IF NOT EXISTS `grad_letter_settings` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id` INT(11) UNSIGNED NOT NULL,
    `start_number` INT(11) NOT NULL DEFAULT 1,
    `total` INT(11) NOT NULL DEFAULT 0,
    `letter_format` VARCHAR(255) NOT NULL,
    `graduation_date` DATE DEFAULT NULL,
    `signing_date` DATE DEFAULT NULL,
    `headmaster_user_id` INT(11) UNSIGNED DEFAULT NULL,
    `headmaster_name` VARCHAR(150) DEFAULT NULL,
    `headmaster_niy` VARCHAR(50) DEFAULT NULL,
    `headmaster_position` VARCHAR(100) NOT NULL DEFAULT 'Kepala Sekolah',
    `kop_image` VARCHAR(255) DEFAULT NULL,
    `decision_number` VARCHAR(100) DEFAULT NULL,
    `decision_date` DATE DEFAULT NULL,
    `decision_about` VARCHAR(255) DEFAULT NULL,
    `skl_city` VARCHAR(100) DEFAULT NULL,
    `announcement_status` VARCHAR(20) NOT NULL DEFAULT 'not_set',
    `announcement_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_grad_letter_year` (`academic_year_id`),
    KEY `idx_grad_letter_headmaster` (`headmaster_user_id`),
    CONSTRAINT `fk_grad_letter_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_grad_letter_headmaster` FOREIGN KEY (`headmaster_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: grad_student_letters
-- Nomor surat per siswa
-- =============================================
CREATE TABLE IF NOT EXISTS `grad_student_letters` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id` INT(11) UNSIGNED NOT NULL,
    `student_id` INT(11) UNSIGNED NOT NULL,
    `sequence_no` INT(11) NOT NULL,
    `letter_number` VARCHAR(255) NOT NULL,
    `graduation_date` DATE DEFAULT NULL,
    `signing_date` DATE DEFAULT NULL,
    `headmaster_name` VARCHAR(150) DEFAULT NULL,
    `headmaster_niy` VARCHAR(50) DEFAULT NULL,
    `headmaster_position` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_grad_student_letter` (`academic_year_id`, `student_id`),
    UNIQUE KEY `uk_grad_sequence_letter` (`academic_year_id`, `sequence_no`),
    KEY `idx_grad_student_letter_year` (`academic_year_id`),
    CONSTRAINT `fk_grad_student_letter_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_grad_student_letter_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: grad_student_scores
-- Nilai akhir siswa per mata pelajaran
-- =============================================
CREATE TABLE IF NOT EXISTS `grad_student_scores` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id` INT(11) UNSIGNED NOT NULL,
    `student_id` INT(11) UNSIGNED NOT NULL,
    `subject_id` INT(11) UNSIGNED NOT NULL,
    `nilai_akhir` DECIMAL(5,2) DEFAULT NULL,
    `predikat` VARCHAR(10) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_grad_score_student_subject` (`academic_year_id`, `student_id`, `subject_id`),
    KEY `idx_grad_score_year` (`academic_year_id`),
    KEY `idx_grad_score_subject` (`subject_id`),
    CONSTRAINT `fk_grad_score_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_grad_score_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_grad_score_subject` FOREIGN KEY (`subject_id`) REFERENCES `grad_subjects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: grad_teacher_access
-- Akses guru/wali kelas untuk E-Graduation
-- =============================================
CREATE TABLE IF NOT EXISTS `grad_teacher_access` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `access_role` ENUM('wali_kelas') NOT NULL DEFAULT 'wali_kelas',
    `kelas` VARCHAR(100) NOT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_grad_access_user` (`user_id`),
    KEY `idx_grad_access_class` (`kelas`),
    CONSTRAINT `fk_grad_access_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: grad_student_accounts
-- Akun login siswa E-Graduation
-- =============================================
CREATE TABLE IF NOT EXISTS `grad_student_accounts` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id` INT(11) UNSIGNED NOT NULL,
    `student_id` INT(11) UNSIGNED NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 1,
    `generated_at` DATETIME DEFAULT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_grad_student_account` (`academic_year_id`, `student_id`),
    UNIQUE KEY `uk_grad_student_username` (`academic_year_id`, `username`),
    KEY `idx_grad_student_account_year` (`academic_year_id`),
    CONSTRAINT `fk_grad_student_account_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_grad_student_account_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: grad_student_sessions
-- Session login siswa E-Graduation
-- =============================================
CREATE TABLE IF NOT EXISTS `grad_student_sessions` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id` INT(11) UNSIGNED NOT NULL,
    `token` VARCHAR(100) NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `expired_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_grad_student_session_token` (`token`),
    KEY `idx_grad_student_session_account` (`account_id`),
    CONSTRAINT `fk_grad_student_session_account` FOREIGN KEY (`account_id`) REFERENCES `grad_student_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: xam_exams
-- Master data ujian per tahun ajaran
-- =============================================
CREATE TABLE IF NOT EXISTS `xam_exams` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year_id` INT(11) UNSIGNED NOT NULL,
    `exam_name` VARCHAR(150) NOT NULL,
    `exam_start_date` DATE NOT NULL,
    `exam_end_date` DATE NOT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_xam_exam_year` (`academic_year_id`),
    CONSTRAINT `fk_xam_exam_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: xam_exam_settings
-- Pengaturan surat dan penandatangan ujian
-- =============================================
CREATE TABLE IF NOT EXISTS `xam_exam_settings` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_id` INT(11) UNSIGNED NOT NULL,
    `letter_manual_no` VARCHAR(30) DEFAULT NULL,
    `letter_code` VARCHAR(120) NOT NULL DEFAULT 'I04.1/SMA.WH1',
    `letter_date` DATE DEFAULT NULL,
    `sign_date` DATE DEFAULT NULL,
    `headmaster_user_id` INT(11) UNSIGNED DEFAULT NULL,
    `headmaster_name` VARCHAR(150) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_xam_setting_exam` (`exam_id`),
    KEY `idx_xam_headmaster` (`headmaster_user_id`),
    CONSTRAINT `fk_xam_setting_exam` FOREIGN KEY (`exam_id`) REFERENCES `xam_exams`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_xam_setting_headmaster` FOREIGN KEY (`headmaster_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: xam_exam_classes
-- Relasi ujian dan kelas peserta
-- =============================================
CREATE TABLE IF NOT EXISTS `xam_exam_classes` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_id` INT(11) UNSIGNED NOT NULL,
    `kelas` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_xam_exam_class` (`exam_id`, `kelas`),
    KEY `idx_xam_exam_class_exam` (`exam_id`),
    CONSTRAINT `fk_xam_exam_class_exam` FOREIGN KEY (`exam_id`) REFERENCES `xam_exams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- Table: xam_exam_students
-- Akun ujian dan status siswa per ujian
-- =============================================
CREATE TABLE IF NOT EXISTS `xam_exam_students` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_id` INT(11) UNSIGNED NOT NULL,
    `student_id` INT(11) UNSIGNED NOT NULL,
    `ruang_ujian` VARCHAR(100) DEFAULT NULL,
    `username` VARCHAR(60) NOT NULL,
    `password_hash` VARCHAR(255) DEFAULT NULL,
    `password_plain` VARCHAR(80) DEFAULT NULL,
    `status` ENUM('OKE', 'DITANGGUHKAN') NOT NULL DEFAULT 'DITANGGUHKAN',
    `suspension_note` VARCHAR(255) DEFAULT 'Silakan hubungi Wali Kelas / Waka. Kesiswaan',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_xam_exam_student` (`exam_id`, `student_id`),
    KEY `idx_xam_exam_student_exam` (`exam_id`),
    KEY `idx_xam_exam_student_status` (`status`),
    CONSTRAINT `fk_xam_exam_student_exam` FOREIGN KEY (`exam_id`) REFERENCES `xam_exams`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_xam_exam_student_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SEED DATA
-- =============================================

-- Default Superadmin (password: admin123)
INSERT INTO `users` (`username`, `password`, `nama_lengkap`, `role`, `status`) VALUES
('superadmin', '$2y$10$hiy4ZWnVCU1w4/BbNyx8jOij7bTeUSsmFlwnUdP101i7mV7UDklLe', 'Super Administrator', 'superadmin', 1);

-- Default Settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `keterangan`) VALUES
('nama_sekolah', 'E-Portal Sekolah', 'text', 'Nama sekolah yang ditampilkan'),
('icon_sekolah', '', 'file', 'Path icon/logo sekolah'),
('kepala_sekolah', '', 'text', 'Nama kepala sekolah untuk surat'),
('kop_surat', '', 'file', 'Path kop surat global untuk semua modul'),
('app_version', '1.0.0', 'text', 'Versi aplikasi');

-- Default Modules
INSERT INTO `modules` (`nama_modul`, `slug`, `deskripsi`, `icon_svg`, `url_path`, `color`, `urutan`, `status`) VALUES
('E-Sarpras', 'e-sarpras', 'Manajemen Sarana dan Prasarana', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9h1"/><path d="M9 13h1"/><path d="M9 17h1"/></svg>', 'modules/e-sarpras/', '#1565C0', 1, 1),
('E-Schedule', 'e-schedule', 'Manajemen Jadwal Pelajaran', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>', 'modules/e-schedule/', '#FF8F00', 2, 1),
('E-Performance', 'e-performance', 'Monitoring Kinerja', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12c0 1.66-4 6-9 6s-9-4.34-9-6c0-1.66 4-6 9-6s9 4.34 9 6z"/><path d="M12 10v4"/><path d="M10 12h4"/><line x1="3" y1="3" x2="7" y2="7"/><line x1="17" y1="7" x2="21" y2="3"/><path d="M18 16l2 3"/><path d="M6 16l-2 3"/></svg>', 'modules/e-performance/', '#10B981', 3, 1),
('E-Graduation', 'e-graduation', 'Manajemen kelulusan dan nilai akhir siswa', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10.5 12 5 2 10.5 12 16l10-5.5Z"/><path d="M6 13v4c2 1.5 10 1.5 12 0v-4"/><path d="M12 16v5"/><path d="M8 21h8"/></svg>', 'modules/e-graduation/', '#0F766E', 4, 1),
('E-Xam Card', 'e-xam-card', 'Manajemen kartu ujian siswa', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10"/><path d="M7 12h10"/><path d="M7 16h6"/></svg>', 'modules/e-xam-card/', '#0F766E', 5, 1);
-- =============================================
-- E-Schedule Database Schema
-- Modul Jadwal Pelajaran
-- =============================================

USE `eportal_db`;

-- 1. Tabel Jam Belajar
CREATE TABLE IF NOT EXISTS `sch_jam_belajar` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `hari` VARCHAR(20) NOT NULL COMMENT 'Senin s.d Sabtu',
    `jam_ke` INT(11) NOT NULL,
    `tipe` ENUM('Pembelajaran', 'Istirahat', 'Upacara', 'Pembiasaan') NOT NULL DEFAULT 'Pembelajaran',
    `nama_jam` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_hari_jam` (`hari`, `jam_ke`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabel Mata Pelajaran
CREATE TABLE IF NOT EXISTS `sch_mapel` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `kode_mapel` VARCHAR(50) NOT NULL,
    `nama_mapel` VARCHAR(150) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_kode_mapel` (`kode_mapel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel Data Kelas
CREATE TABLE IF NOT EXISTS `sch_kelas` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `rombel` INT(11) NOT NULL COMMENT '1 s.d 12',
    `nama_kelas` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_nama_kelas` (`nama_kelas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel Data Guru
CREATE TABLE IF NOT EXISTS `sch_guru` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `kode_guru` VARCHAR(50) NOT NULL,
    `nama_guru` VARCHAR(150) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_kode_guru` (`kode_guru`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel Distribusi (Penugasan)
CREATE TABLE IF NOT EXISTS `sch_distribusi` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `guru_id` INT(11) UNSIGNED NOT NULL,
    `kelas_id` INT(11) UNSIGNED NOT NULL,
    `mapel_id` INT(11) UNSIGNED NOT NULL,
    `jp` INT(11) NOT NULL COMMENT '1 s.d 7',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_guru` (`guru_id`),
    KEY `idx_kelas` (`kelas_id`),
    KEY `idx_mapel` (`mapel_id`),
    CONSTRAINT `fk_dist_guru` FOREIGN KEY (`guru_id`) REFERENCES `sch_guru` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dist_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `sch_kelas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_dist_mapel` FOREIGN KEY (`mapel_id`) REFERENCES `sch_mapel` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabel Kesediaan Guru
CREATE TABLE IF NOT EXISTS `sch_kesediaan` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `guru_id` INT(11) UNSIGNED NOT NULL,
    `jam_belajar_id` INT(11) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_guru_jam` (`guru_id`, `jam_belajar_id`),
    CONSTRAINT `fk_kes_guru` FOREIGN KEY (`guru_id`) REFERENCES `sch_guru` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_kes_jam` FOREIGN KEY (`jam_belajar_id`) REFERENCES `sch_jam_belajar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabel Jadwal (Hasil Akhir)
CREATE TABLE IF NOT EXISTS `sch_jadwal` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `kelas_id` INT(11) UNSIGNED NOT NULL,
    `jam_belajar_id` INT(11) UNSIGNED NOT NULL,
    `distribusi_id` INT(11) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_kelas_jam` (`kelas_id`, `jam_belajar_id`),
    CONSTRAINT `fk_jadwal_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `sch_kelas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_jadwal_jam` FOREIGN KEY (`jam_belajar_id`) REFERENCES `sch_jam_belajar` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_jadwal_dist` FOREIGN KEY (`distribusi_id`) REFERENCES `sch_distribusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
