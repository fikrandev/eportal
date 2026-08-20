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
