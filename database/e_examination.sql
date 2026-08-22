-- =============================================
-- E-Examination Module Database Schema
-- Sistem Ujian Digital (CBT) — Tes Penilaian & Psikologi
-- =============================================

SET NAMES utf8mb4;

-- =============================================
-- MASTER DATA
-- =============================================

-- Mata Pelajaran / Kategori
CREATE TABLE IF NOT EXISTS `exam_mapel` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_mapel` VARCHAR(150) NOT NULL,
  `kode` VARCHAR(20) DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bank Soal (Container)
CREATE TABLE IF NOT EXISTS `exam_bank_soal` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mapel_id` INT UNSIGNED NOT NULL,
  `judul` VARCHAR(200) NOT NULL,
  `jenis` ENUM('penilaian','psikologi') NOT NULL DEFAULT 'penilaian',
  `kategori_ujian` VARCHAR(50) DEFAULT NULL COMMENT 'UHB, STS, SAS, dll',
  `tahun_ajaran` VARCHAR(20) DEFAULT NULL,
  `semester` ENUM('1','2') DEFAULT '1',
  `kelas` VARCHAR(30) DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mapel` (`mapel_id`),
  KEY `idx_jenis` (`jenis`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Soal (Items)
CREATE TABLE IF NOT EXISTS `exam_soal` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bank_soal_id` INT UNSIGNED NOT NULL,
  `tipe_soal` ENUM('benar_salah','menjodohkan','pilihan_satu','pilihan_banyak','jawaban_singkat','esai') NOT NULL,
  `pertanyaan` TEXT NOT NULL,
  `opsi` JSON DEFAULT NULL COMMENT 'Array opsi: [{label:"A", text:"...", gambar:"..."}]',
  `kunci_jawaban` JSON DEFAULT NULL COMMENT 'Jawaban benar: "A" / ["A","C"] / "teks" / [{pasangan}]',
  `pembahasan` TEXT DEFAULT NULL,
  `bobot` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `gambar` VARCHAR(255) DEFAULT NULL COMMENT 'Path gambar soal',
  `audio` VARCHAR(255) DEFAULT NULL COMMENT 'Path audio listening',
  `urutan` INT NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bank` (`bank_soal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Soal Psikologi - Mapping jawaban ke hasil
CREATE TABLE IF NOT EXISTS `exam_psikologi_hasil` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bank_soal_id` INT UNSIGNED NOT NULL,
  `kode_hasil` VARCHAR(50) NOT NULL COMMENT 'misal: Introvert, Ekstrovert',
  `deskripsi` TEXT NOT NULL,
  `rentang_min` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `rentang_max` DECIMAL(5,2) NOT NULL DEFAULT 100,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bank` (`bank_soal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- UJIAN
-- =============================================

-- Ujian (Exam Session)
CREATE TABLE IF NOT EXISTS `exam_ujian` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `judul` VARCHAR(200) NOT NULL,
  `bank_soal_id` INT UNSIGNED NOT NULL,
  `jenis` ENUM('penilaian','psikologi') NOT NULL DEFAULT 'penilaian',
  `durasi_menit` INT NOT NULL DEFAULT 60,
  `acak_soal` TINYINT(1) NOT NULL DEFAULT 0,
  `acak_opsi` TINYINT(1) NOT NULL DEFAULT 0,
  `tampil_nilai` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Tampilkan nilai ke siswa?',
  `token` VARCHAR(10) DEFAULT NULL,
  `tgl_mulai` DATETIME DEFAULT NULL,
  `tgl_selesai` DATETIME DEFAULT NULL,
  `status` ENUM('draft','aktif','selesai') NOT NULL DEFAULT 'draft',
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bank` (`bank_soal_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kelas yang bisa akses ujian
CREATE TABLE IF NOT EXISTS `exam_ujian_kelas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ujian_id` INT UNSIGNED NOT NULL,
  `kelas` VARCHAR(30) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ujian_kelas` (`ujian_id`, `kelas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- PENGERJAAN & JAWABAN
-- =============================================

-- Sesi pengerjaan siswa
CREATE TABLE IF NOT EXISTS `exam_sesi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ujian_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL COMMENT 'dari tabel students eportal',
  `waktu_mulai` DATETIME NOT NULL,
  `waktu_selesai` DATETIME DEFAULT NULL,
  `sisa_detik` INT DEFAULT NULL COMMENT 'Untuk resume jika keluar',
  `status` ENUM('berlangsung','selesai','didiskualifikasi') NOT NULL DEFAULT 'berlangsung',
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `pelanggaran` INT NOT NULL DEFAULT 0 COMMENT 'Counter anti-cheat',
  `nilai_akhir` DECIMAL(5,2) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sesi` (`ujian_id`, `student_id`),
  KEY `idx_ujian` (`ujian_id`),
  KEY `idx_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Jawaban siswa per soal
CREATE TABLE IF NOT EXISTS `exam_jawaban` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sesi_id` INT UNSIGNED NOT NULL,
  `soal_id` INT UNSIGNED NOT NULL,
  `urutan` INT NOT NULL DEFAULT 0,
  `opsi_acak` TEXT DEFAULT NULL,
  `jawaban` TEXT DEFAULT NULL COMMENT 'Jawaban siswa (text / JSON)',
  `jawaban_voice` VARCHAR(255) DEFAULT NULL COMMENT 'Path voice recording',
  `is_ragu` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Tandai ragu-ragu',
  `skor` DECIMAL(5,2) DEFAULT NULL COMMENT 'Skor (null = belum dikoreksi)',
  `ai_feedback` TEXT DEFAULT NULL COMMENT 'Feedback dari AI',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_jawaban` (`sesi_id`, `soal_id`),
  KEY `idx_sesi` (`sesi_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Log anti-cheat
CREATE TABLE IF NOT EXISTS `exam_cheat_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sesi_id` INT UNSIGNED NOT NULL,
  `jenis` VARCHAR(50) NOT NULL COMMENT 'tab_switch, copy_paste, right_click, fullscreen_exit',
  `detail` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sesi` (`sesi_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
