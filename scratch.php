<?php
require_once __DIR__ . '/api/config.php';

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS `acad_absensi_guru` (
      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `guru_id` int(11) unsigned NOT NULL,
      `academic_year_id` int(11) unsigned DEFAULT NULL,
      `tanggal` date NOT NULL,
      `status` enum('H','S','I','A','T') NOT NULL DEFAULT 'H',
      `keterangan` varchar(255) DEFAULT NULL,
      `dicatat_oleh` int(11) unsigned DEFAULT NULL,
      `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_guru_tanggal` (`guru_id`,`tanggal`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    db()->exec($sql);
    echo "Tabel acad_absensi_guru berhasil dibuat/diperbarui.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
