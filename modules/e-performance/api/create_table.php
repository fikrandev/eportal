<?php
require 'e:/xampp/htdocs/eportal/api/config.php';

$db = db();
$sql = "CREATE TABLE IF NOT EXISTS `perf_deskripsi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tupoksi` varchar(100) NOT NULL,
  `min_nilai` decimal(5,2) NOT NULL,
  `max_nilai` decimal(5,2) NOT NULL,
  `deskripsi` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

try {
    $db->exec($sql);
    echo "Table perf_deskripsi created successfully.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
