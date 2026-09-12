<?php
require_once 'api/config.php';
try {
    db()->exec("
        CREATE TABLE IF NOT EXISTS acad_hari_libur (
            id INT AUTO_INCREMENT PRIMARY KEY,
            academic_year_id INT NOT NULL,
            tanggal DATE NOT NULL,
            keterangan VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_date_year (tanggal, academic_year_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Table acad_hari_libur created successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
