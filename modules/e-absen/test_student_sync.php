<?php
require 'e:/xampp/htdocs/eportal/api/config.php';
$db = db(); 
echo "=== ACTIVE YEAR ===\n";
try {
    $stmt = $db->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1");
    var_dump($stmt->fetch(PDO::FETCH_ASSOC));
} catch (Exception $e) { echo $e->getMessage(); }

echo "\n=== STUDENTS (sample 3) ===\n";
try {
    $stmt = $db->query("SELECT id, nis, nama, kelas, status FROM students LIMIT 3");
    var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) { echo $e->getMessage(); }

echo "\n=== KELAS (sample 3) ===\n";
try {
    $stmt = $db->query("SELECT id, nama_kelas FROM sch_kelas LIMIT 3");
    var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) { echo $e->getMessage(); }

echo "\n=== RECENT ABSEN LOGS ===\n";
try {
    $stmt = $db->query("SELECT * FROM absen_logs ORDER BY waktu_absen DESC LIMIT 5");
    var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) { echo $e->getMessage(); }
