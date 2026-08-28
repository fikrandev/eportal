<?php
/**
 * E-Absen Auto Sync API (Daemon/Cron)
 * Menarik data dari semua mesin fingerprint secara otomatis di background
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/../../../api/ZKLibrary.php';

// Verifikasi akses khusus robot/cron (bukan untuk user biasa)
$cronToken = isset($_GET['cron_token']) ? $_GET['cron_token'] : '';
if ($cronToken !== 'eportal_auto_sync_secret') {
    json_response(403, false, 'Akses ditolak. Token tidak valid.');
}

function triggerWAGateway($phone, $message) {
    $stmt = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_gateway_url'");
    $url = $stmt->fetchColumn();
    if (!$url) return;

    $data = json_encode(['number' => $phone, 'message' => $message]);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($data)]);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500); 
    curl_exec($ch);
    curl_close($ch);
}

try {
    $stmt = db()->query("SELECT * FROM absen_mesin WHERE status = 1");
    $mesins = $stmt->fetchAll();
    
    if (!$mesins || count($mesins) === 0) {
        json_response(400, false, 'Tidak ada mesin aktif.');
    }

    $waTemplateStmt = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_message_template'");
    $waTemplate = $waTemplateStmt->fetchColumn();

    $newLogsCount = 0;
    
    foreach ($mesins as $mesin) {
        $zk = new ZKLibrary($mesin['ip_address'], $mesin['port']);
        $connected = $zk->connect();
        
        if (!$connected) continue; // Skip jika mesin mati
        
        $attendance = $zk->getAttendance();
        $zk->disconnect();
        
        if ($attendance === false) continue;
        
        db()->beginTransaction();
        $stmtInsert = db()->prepare("
            INSERT IGNORE INTO absen_logs (mesin_id, mesin_pin, waktu_absen, status_absen, verify_type)
            VALUES (?, ?, ?, ?, ?)
        ");
        
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

                // Trigger WA jika milik siswa
                if ($waTemplate) {
                    $stmtSiswa = db()->prepare("SELECT nama, no_hp_ortu FROM students WHERE nis = ? AND no_hp_ortu IS NOT NULL AND no_hp_ortu != ''");
                    $stmtSiswa->execute([$log['uid']]);
                    $siswa = $stmtSiswa->fetch();

                    if ($siswa) {
                        $statusText = $log['state'] == 0 ? 'Hadir (Masuk)' : 'Pulang';
                        $msg = str_replace(
                            ['{nama}', '{status_absen}', '{waktu}'], 
                            [$siswa['nama'], $statusText, $log['timestamp']], 
                            $waTemplate
                        );
                        triggerWAGateway($siswa['no_hp_ortu'], $msg);
                    }
                }
            }
        }
        
        db()->query("UPDATE absen_mesin SET last_sync = NOW() WHERE id = " . $mesin['id']);
        db()->commit();
    }
    
    json_response(200, true, "Auto-Sync selesai. $newLogsCount log baru.");
} catch (Exception $e) {
    if (db()->inTransaction()) db()->rollBack();
    json_response(500, false, 'Kesalahan Auto-Sync: ' . $e->getMessage());
}
