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

    $active_year = get_active_academic_year();
    $year_id = $active_year['id'] ?? 0;

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
            
            // Cek apakah ini milik siswa (PIN = NIS)
            $stmtSiswa = db()->prepare("
                SELECT id, nama, no_hp_ortu, kelas 
                FROM students 
                WHERE TRIM(LEADING '0' FROM nis) = TRIM(LEADING '0' FROM ?) AND status = 1 
                LIMIT 1
            ");
            $stmtSiswa->execute([$log['uid']]);
            $siswa = $stmtSiswa->fetch();

            if ($siswa) {
                // 1. Automatically register student attendance in acad_absensi (jam_ke = 0 for daily attendance)
                try {
                    $stmtKelas = db()->prepare("SELECT id FROM sch_kelas WHERE nama_kelas = ? LIMIT 1");
                    $stmtKelas->execute([$siswa['kelas']]);
                    $kelasId = $stmtKelas->fetchColumn() ?: 0;
                    
                    if ($kelasId > 0 && $year_id > 0) {
                        $tanggal_absen = date('Y-m-d', strtotime($log['timestamp']));
                        
                        $stmtAbsensi = db()->prepare("
                            INSERT INTO acad_absensi (student_id, kelas_id, academic_year_id, tanggal, jam_ke, status, keterangan, dicatat_oleh)
                            VALUES (?, ?, ?, ?, 0, 'H', 'Hadir via Fingerprint', NULL)
                            ON DUPLICATE KEY UPDATE status = 'H', keterangan = 'Hadir via Fingerprint'
                        ");
                        $stmtAbsensi->execute([$siswa['id'], $kelasId, $year_id, $tanggal_absen]);
                    }
                } catch (Exception $ex) {
                    // Ignore
                }
            }

            if ($stmtInsert->rowCount() > 0) {
                $newLogsCount++;

                // 2. Trigger WA Gateway for parents if new log & parents phone number is set
                if ($siswa && $waTemplate && !empty($siswa['no_hp_ortu'])) {
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
        
        db()->query("UPDATE absen_mesin SET last_sync = NOW() WHERE id = " . $mesin['id']);
        db()->commit();
    }
    
    json_response(200, true, "Auto-Sync selesai. $newLogsCount log baru.");
} catch (Exception $e) {
    if (db()->inTransaction()) db()->rollBack();
    json_response(500, false, 'Kesalahan Auto-Sync: ' . $e->getMessage());
}
