-- =============================================
-- E-Sarpras Database Schema - Phase 2 (Enhanced)
-- Sistem Manajemen Sarana & Prasarana Sekolah
-- =============================================

USE `eportal_db`;

-- 1. Kategori Sarpras
CREATE TABLE IF NOT EXISTS `kategori_sarpras` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama` VARCHAR(100) NOT NULL,
    `kode` VARCHAR(10) NOT NULL,
    `jenis` ENUM('sarana', 'prasarana') NOT NULL DEFAULT 'sarana',
    `keterangan` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tanah
CREATE TABLE IF NOT EXISTS `tanah` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama` VARCHAR(150) NOT NULL,
    `lokasi` TEXT NOT NULL,
    `luas_m2` DECIMAL(10,2) NOT NULL,
    `panjang_m` DECIMAL(10,2) DEFAULT NULL,
    `lebar_m` DECIMAL(10,2) DEFAULT NULL,
    `harga_perolehan` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `tahun_perolehan` YEAR NOT NULL,
    `status_kepemilikan` VARCHAR(50) NOT NULL DEFAULT 'Milik Sendiri',
    `no_sertifikat` VARCHAR(100) DEFAULT NULL,
    `asal_anggaran` VARCHAR(100) DEFAULT NULL,
    `keterangan` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Foto Tanah (max 5)
CREATE TABLE IF NOT EXISTS `tanah_foto` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tanah_id` INT(11) UNSIGNED NOT NULL,
    `foto_path` VARCHAR(255) NOT NULL,
    `keterangan` VARCHAR(200) DEFAULT NULL,
    `urutan` INT(11) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_tanah_foto` FOREIGN KEY (`tanah_id`) REFERENCES `tanah`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bangunan
CREATE TABLE IF NOT EXISTS `bangunan` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tanah_id` INT(11) UNSIGNED NOT NULL,
    `nama` VARCHAR(150) NOT NULL,
    `luas_m2` DECIMAL(10,2) NOT NULL,
    `panjang_m` DECIMAL(10,2) DEFAULT NULL,
    `lebar_m` DECIMAL(10,2) DEFAULT NULL,
    `tinggi_m` DECIMAL(10,2) DEFAULT NULL,
    `jumlah_lantai` INT(11) DEFAULT 1,
    `tahun_dibangun` YEAR NOT NULL,
    `harga_perolehan` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `asal_anggaran` VARCHAR(100) DEFAULT NULL,
    `kondisi` ENUM('Baik', 'Rusak Ringan', 'Rusak Berat') DEFAULT 'Baik',
    `keterangan` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_bangunan_tanah` FOREIGN KEY (`tanah_id`) REFERENCES `tanah`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Foto Bangunan (max 5)
CREATE TABLE IF NOT EXISTS `bangunan_foto` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `bangunan_id` INT(11) UNSIGNED NOT NULL,
    `foto_path` VARCHAR(255) NOT NULL,
    `keterangan` VARCHAR(200) DEFAULT NULL,
    `urutan` INT(11) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_bangunan_foto` FOREIGN KEY (`bangunan_id`) REFERENCES `bangunan`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Ruang
CREATE TABLE IF NOT EXISTS `ruang` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `bangunan_id` INT(11) UNSIGNED NOT NULL,
    `nama` VARCHAR(100) NOT NULL,
    `kode_ruang` VARCHAR(20) NOT NULL,
    `panjang_m` DECIMAL(10,2) DEFAULT NULL,
    `lebar_m` DECIMAL(10,2) DEFAULT NULL,
    `lantai` INT(11) DEFAULT 1,
    `jenis_ruang` VARCHAR(50) NOT NULL,
    `kapasitas` INT(11) DEFAULT NULL,
    `kondisi` ENUM('Baik', 'Rusak Ringan', 'Rusak Berat') DEFAULT 'Baik',
    `keterangan` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_kode_ruang` (`kode_ruang`),
    CONSTRAINT `fk_ruang_bangunan` FOREIGN KEY (`bangunan_id`) REFERENCES `bangunan`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Foto Ruang (max 5)
CREATE TABLE IF NOT EXISTS `ruang_foto` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `ruang_id` INT(11) UNSIGNED NOT NULL,
    `foto_path` VARCHAR(255) NOT NULL,
    `keterangan` VARCHAR(200) DEFAULT NULL,
    `urutan` INT(11) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_ruang_foto` FOREIGN KEY (`ruang_id`) REFERENCES `ruang`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Sarpras (Inventaris Item)
CREATE TABLE IF NOT EXISTS `sarpras` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `ruang_id` INT(11) UNSIGNED NOT NULL,
    `kategori_id` INT(11) UNSIGNED NOT NULL,
    `nama` VARCHAR(200) NOT NULL,
    `kode_inventaris` VARCHAR(50) NOT NULL,
    `merk` VARCHAR(100) DEFAULT NULL,
    `spesifikasi` TEXT DEFAULT NULL,
    `jumlah` INT(11) NOT NULL DEFAULT 1,
    `kondisi_baik` INT(11) NOT NULL DEFAULT 0,
    `kondisi_rusak_ringan` INT(11) NOT NULL DEFAULT 0,
    `kondisi_rusak_berat` INT(11) NOT NULL DEFAULT 0,
    `tahun_perolehan` YEAR NOT NULL,
    `harga_perolehan` DECIMAL(15,2) NOT NULL DEFAULT 0,
    `asal_perolehan` VARCHAR(50) NOT NULL DEFAULT 'APBD',
    `masa_manfaat_tahun` INT(11) NOT NULL DEFAULT 5,
    `judul_buku` VARCHAR(255) DEFAULT NULL,
    `pengarang` VARCHAR(150) DEFAULT NULL,
    `penerbit` VARCHAR(150) DEFAULT NULL,
    `keterangan` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_kode_inventaris` (`kode_inventaris`),
    CONSTRAINT `fk_sarpras_ruang` FOREIGN KEY (`ruang_id`) REFERENCES `ruang`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sarpras_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_sarpras`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Foto Sarpras (max 5)
CREATE TABLE IF NOT EXISTS `sarpras_foto` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `sarpras_id` INT(11) UNSIGNED NOT NULL,
    `foto_path` VARCHAR(255) NOT NULL,
    `keterangan` VARCHAR(200) DEFAULT NULL,
    `urutan` INT(11) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_sarpras_foto` FOREIGN KEY (`sarpras_id`) REFERENCES `sarpras`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Update Periodik (Triwulan)
CREATE TABLE IF NOT EXISTS `sarpras_periodik` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `sarpras_id` INT(11) UNSIGNED NOT NULL,
    `periode` ENUM('Q1', 'Q2', 'Q3', 'Q4') NOT NULL,
    `tahun` YEAR NOT NULL,
    `kondisi_baik` INT(11) NOT NULL DEFAULT 0,
    `kondisi_rusak_ringan` INT(11) NOT NULL DEFAULT 0,
    `kondisi_rusak_berat` INT(11) NOT NULL DEFAULT 0,
    `catatan` TEXT DEFAULT NULL,
    `updated_by` INT(11) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_periode_sarpras` (`sarpras_id`, `periode`, `tahun`),
    CONSTRAINT `fk_periodik_sarpras` FOREIGN KEY (`sarpras_id`) REFERENCES `sarpras`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_periodik_user` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Perbaikan
CREATE TABLE IF NOT EXISTS `sarpras_perbaikan` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `sarpras_id` INT(11) UNSIGNED NOT NULL,
    `tanggal` DATE NOT NULL,
    `tanggal_selesai` DATE DEFAULT NULL,
    `deskripsi` TEXT NOT NULL,
    `biaya` DECIMAL(15,2) DEFAULT 0,
    `vendor` VARCHAR(150) DEFAULT NULL,
    `no_spk` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('Diajukan', 'Proses', 'Selesai') DEFAULT 'Diajukan',
    `catatan` TEXT DEFAULT NULL,
    `updated_by` INT(11) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_perbaikan_sarpras` FOREIGN KEY (`sarpras_id`) REFERENCES `sarpras`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_perbaikan_user` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Foto Bukti Perbaikan
CREATE TABLE IF NOT EXISTS `perbaikan_foto` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `perbaikan_id` INT(11) UNSIGNED NOT NULL,
    `foto_path` VARCHAR(255) NOT NULL,
    `tipe` ENUM('sebelum', 'sesudah') DEFAULT 'sebelum',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_perbaikan_foto` FOREIGN KEY (`perbaikan_id`) REFERENCES `sarpras_perbaikan`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Penyusutan
CREATE TABLE IF NOT EXISTS `sarpras_penyusutan` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `sarpras_id` INT(11) UNSIGNED NOT NULL,
    `tahun_penyusutan` YEAR NOT NULL,
    `nilai_perolehan` DECIMAL(15,2) NOT NULL,
    `beban_penyusutan` DECIMAL(15,2) NOT NULL,
    `akumulasi_penyusutan` DECIMAL(15,2) NOT NULL,
    `nilai_buku` DECIMAL(15,2) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tahun_sarpras` (`sarpras_id`, `tahun_penyusutan`),
    CONSTRAINT `fk_penyusutan_sarpras` FOREIGN KEY (`sarpras_id`) REFERENCES `sarpras`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Role Akses E-Sarpras
CREATE TABLE IF NOT EXISTS `sarpras_roles` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) UNSIGNED NOT NULL,
    `role` ENUM('admin_sarpras', 'operator_sarpras', 'viewer_sarpras') NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_role` (`user_id`),
    CONSTRAINT `fk_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Sequence Tracker
CREATE TABLE IF NOT EXISTS `sequence_tracker` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `kategori_kode` VARCHAR(10) NOT NULL,
    `tahun` YEAR NOT NULL,
    `last_seq` INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_seq_kat_tahun` (`kategori_kode`, `tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================
-- SEED DATA
-- =============================================

INSERT IGNORE INTO `kategori_sarpras` (`nama`, `kode`, `jenis`) VALUES
('Meubelair', 'MBL', 'sarana'),
('Alat Peraga', 'APG', 'sarana'),
('Elektronik', 'ELK', 'sarana'),
('Buku', 'BKS', 'sarana'),
('Kendaraan', 'KDR', 'sarana'),
('Alat Olahraga', 'AOR', 'sarana'),
('Media Pendidikan', 'MED', 'sarana'),
('Alat Laboratorium', 'ALB', 'sarana'),
('Alat Kesenian', 'AKS', 'sarana'),
('Peralatan Kantor', 'PKT', 'sarana'),
('Gedung', 'GDG', 'prasarana'),
('Jaringan', 'JRG', 'prasarana'),
('Instalasi', 'INS', 'prasarana'),
('Lainnya', 'LLN', 'sarana');
