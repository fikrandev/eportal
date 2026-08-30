<?php
require_once __DIR__ . '/../api/config.php';

try {
    // Attempt to insert a dummy leave request
    // Let's first query a valid guru ID from users table
    $guru = db()->query("SELECT id, nama_lengkap FROM users WHERE role = 'guru' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$guru) {
        echo "No guru found in users table.\n";
        exit;
    }
    
    $guru_id = $guru['id'];
    $tanggal = date('Y-m-d', strtotime('+1 day')); // Tomorrow so no conflict with today's dummy data
    $jenis = 'Sakit';
    $catatan = 'Test insertion from script';
    
    // Clear any previous test data on this date
    $db = db();
    $db->prepare("DELETE FROM acad_ketidakhadiran WHERE guru_id = ? AND tanggal = ?")->execute([$guru_id, $tanggal]);
    
    // Attempt insert
    $stmt = $db->prepare("
        INSERT INTO acad_ketidakhadiran (guru_id, tanggal, jenis, catatan, status)
        VALUES (?, ?, ?, ?, 'Pending')
    ");
    $stmt->execute([$guru_id, $tanggal, $jenis, $catatan]);
    
    echo "Success! Inserted leave request for {$guru['nama_lengkap']} on {$tanggal}.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
