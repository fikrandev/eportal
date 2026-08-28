<?php
/**
 * Cron Job: Tarik Data Absensi Otomatis
 * Jalankan file ini menggunakan Windows Task Scheduler (e.g., setiap 15 menit)
 * Command: e:\xampp\php\php.exe e:\xampp\htdocs\eportal\cron\tarik_absen.php
 */

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/ZKLibrary.php';

echo "[".date('Y-m-d H:i:s')."] Memulai penarikan data otomatis...\n";

try {
    // Ambil mesin aktif
    $stmt = db()->query("SELECT * FROM absen_mesin WHERE status = 1");
    $mesins = $stmt->fetchAll();
    
    if (empty($mesins)) {
        echo "Tidak ada mesin fingerprint yang aktif.\n";
        exit;
    }
    
    $stmtInsert = db()->prepare("
        INSERT IGNORE INTO absen_logs (mesin_id, mesin_pin, waktu_absen, status_absen, verify_type)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($mesins as $mesin) {
        echo "Menghubungkan ke mesin: {$mesin['nama_mesin']} ({$mesin['ip_address']}:{$mesin['port']})...\n";
        
        $zk = new ZKLibrary($mesin['ip_address'], $mesin['port']);
        $connected = $zk->connect();
        
        if ($connected) {
            $attendance = $zk->getAttendance();
            $zk->disconnect();
            
            if ($attendance && is_array($attendance)) {
                $newLogsCount = 0;
                db()->beginTransaction();
                
                foreach ($attendance as $log) {
                    $stmtInsert->execute([
                        $mesin['id'],
                        $log['uid'],
                        $log['timestamp'],
                        $log['state'],
                        $log['type']
                    ]);
                    
                    if ($stmtInsert->rowCount() > 0) {
                        $newLogsCount++;
                    }
                }
                
                db()->query("UPDATE absen_mesin SET last_sync = NOW() WHERE id = " . $mesin['id']);
                db()->commit();
                
                echo "-> Selesai! $newLogsCount data baru berhasil ditarik.\n";
            } else {
                echo "-> Tidak ada data baru atau memori log kosong.\n";
            }
        } else {
            echo "-> Gagal terhubung ke mesin (Offline / Jaringan Terputus).\n";
        }
    }
} catch (Exception $e) {
    if (db()->inTransaction()) db()->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "[".date('Y-m-d H:i:s')."] Cron job selesai.\n";
