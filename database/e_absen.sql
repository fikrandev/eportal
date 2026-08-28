-- =============================================
-- E-Absen Module Database Schema (Fingerprint)
-- =============================================

USE `eportal_db`;

-- 1. Table: absen_mesin (Settings for fingerprint machines)
CREATE TABLE IF NOT EXISTS `absen_mesin` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_mesin` VARCHAR(100) NOT NULL,
  `ip_address` VARCHAR(50) NOT NULL,
  `port` INT NOT NULL DEFAULT 4370,
  `com_key` VARCHAR(50) DEFAULT '0' COMMENT 'Communication Key / Password',
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `last_sync` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Table: absen_user_map (Mapping User ID in Machine to Web User ID)
CREATE TABLE IF NOT EXISTS `absen_user_map` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'References users.id (Guru/Karyawan)',
  `mesin_pin` VARCHAR(50) NOT NULL COMMENT 'PIN or UID in fingerprint machine',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_mesin` (`user_id`),
  UNIQUE KEY `uk_mesin_pin` (`mesin_pin`),
  CONSTRAINT `fk_absen_map_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Table: absen_logs (Raw logs pulled from machine)
CREATE TABLE IF NOT EXISTS `absen_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mesin_id` INT UNSIGNED NOT NULL,
  `mesin_pin` VARCHAR(50) NOT NULL,
  `waktu_absen` DATETIME NOT NULL,
  `status_absen` TINYINT NOT NULL COMMENT '0: Check-In, 1: Check-Out, 2: Break-Out, 3: Break-In, 4: OT-In, 5: OT-Out',
  `verify_type` TINYINT NOT NULL COMMENT '0: Password, 1: Fingerprint, 2: Card',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_log` (`mesin_id`, `mesin_pin`, `waktu_absen`),
  KEY `idx_waktu` (`waktu_absen`),
  KEY `idx_pin` (`mesin_pin`),
  CONSTRAINT `fk_absen_log_mesin` FOREIGN KEY (`mesin_id`) REFERENCES `absen_mesin` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Table: absen_jadwal_kerja (Work Schedule settings for calculation)
CREATE TABLE IF NOT EXISTS `absen_jadwal_kerja` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hari` VARCHAR(20) NOT NULL,
  `jam_masuk` TIME NOT NULL,
  `batas_masuk` TIME NOT NULL COMMENT 'Setelah jam ini dihitung terlambat',
  `jam_pulang` TIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_hari` (`hari`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default work schedule
INSERT IGNORE INTO `absen_jadwal_kerja` (`hari`, `jam_masuk`, `batas_masuk`, `jam_pulang`) VALUES 
('Senin', '07:00:00', '07:15:00', '15:00:00'),
('Selasa', '07:00:00', '07:15:00', '15:00:00'),
('Rabu', '07:00:00', '07:15:00', '15:00:00'),
('Kamis', '07:00:00', '07:15:00', '15:00:00'),
('Jumat', '07:00:00', '07:15:00', '14:00:00');

-- 5. Table: absen_rekap (Processed daily attendance)
CREATE TABLE IF NOT EXISTS `absen_rekap` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL,
  `tanggal` DATE NOT NULL,
  `jam_masuk` TIME DEFAULT NULL,
  `jam_pulang` TIME DEFAULT NULL,
  `status` ENUM('Hadir', 'Terlambat', 'Alpha', 'Izin', 'Sakit') NOT NULL DEFAULT 'Alpha',
  `keterangan` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rekap_user` (`user_id`, `tanggal`),
  KEY `idx_rekap_tanggal` (`tanggal`),
  CONSTRAINT `fk_absen_rekap_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Register Module in Main Portal
INSERT IGNORE INTO `modules` (`nama_modul`, `slug`, `deskripsi`, `icon_svg`, `url_path`, `color`, `urutan`, `status`) VALUES
('E-Absen', 'e-absen', 'Sistem Absensi Fingerprint Terintegrasi', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><path d="M12 8v8"></path><path d="M8 12h8"></path></svg>', 'modules/e-absen/', '#10B981', 8, 1);
