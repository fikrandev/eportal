<?php
/**
 * E-Absen Auto Sync API (Daemon/Cron)
 * Menarik data dari semua mesin fingerprint secara otomatis di background
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/../../../api/wa_gateway_client.php';
require_once __DIR__ . '/../../../api/ZKLibrary.php';

// Verifikasi akses khusus robot/cron (bukan untuk user biasa)
$cronToken = isset($_GET['cron_token']) ? $_GET['cron_token'] : '';
if ($cronToken !== 'eportal_auto_sync_secret') {
    json_response(403, false, 'Akses ditolak. Token tidak valid.');
}

try {
    // 1. CRON JOB: Check WA Group Guru Absensi Batch (Runs regardless of fingerprint status)
    try {
        require_once __DIR__ . '/../../e-curriculum/api/wa_group_helper.php';
        checkAndSendWaGroupGuruAbsensiBatch();
    } catch (Exception $ex) {}

    // 2. FINGERPRINT SYNC: Tarik log absen
    $stmt = db()->query("SELECT * FROM absen_mesin WHERE status = 1");
    $mesins = $stmt->fetchAll();
    
    if (!$mesins || count($mesins) === 0) {
        json_response(400, false, 'Cron WA berhasil dicek, tetapi tidak ada mesin aktif untuk sinkronisasi fingerprint.');
    }

    $waTemplate = get_setting('wa_message_template', '');

    $active_year = get_active_academic_year();
    $year_id = $active_year['id'] ?? 0;
    $waktu_terlambat_siswa = substr(get_setting('waktu_terlambat_siswa', '06:30:00') ?: '06:30:00', 0, 5);
    $waktu_istirahat_siswa_mulai = get_setting('waktu_istirahat_siswa_mulai', '09:30:00') ?: '09:30:00';
    if (strlen($waktu_istirahat_siswa_mulai) === 5) $waktu_istirahat_siswa_mulai .= ':00';

    $newLogsCount = 0;
    $pendingWa = [];
    
    foreach ($mesins as $mesin) {
        if (empty($mesin['ip_address']) || trim($mesin['ip_address']) === '') continue;
        $zk = new ZKLibrary($mesin['ip_address'], $mesin['port']);
        $connected = $zk->connect();
        
        if (!$connected) continue;
        
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
            $isNew = ($stmtInsert->rowCount() > 0);
            if (!$isNew) continue;
            $newLogsCount++;
            
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
                try {
                    $stmtKelas = db()->prepare("SELECT id FROM sch_kelas WHERE nama_kelas = ? LIMIT 1");
                    $stmtKelas->execute([$siswa['kelas']]);
                    $kelasId = $stmtKelas->fetchColumn() ?: 0;
                    
                    if ($kelasId > 0 && $year_id > 0) {
                        $ts_absen = strtotime($log['timestamp']);
                        $tanggal_absen = date('Y-m-d', $ts_absen);
                        $jam_full = date('H:i:s', $ts_absen);
                        $jam_short = substr($jam_full, 0, 5);

                        if ($jam_full < $waktu_istirahat_siswa_mulai) {
                            $autoStatus = ($jam_short <= $waktu_terlambat_siswa) ? 'H' : 'T';
                            $autoKet = ($autoStatus === 'T' ? 'Terlambat' : 'Hadir') . ' via Fingerprint';
                            $stmtAbsensi = db()->prepare("
                                INSERT INTO acad_absensi (student_id, kelas_id, academic_year_id, tanggal, jam_ke, status, keterangan, dicatat_oleh)
                                VALUES (?, ?, ?, ?, 0, ?, ?, NULL)
                                ON DUPLICATE KEY UPDATE
                                    status = IF(dicatat_oleh IS NULL, VALUES(status), status),
                                    keterangan = IF(dicatat_oleh IS NULL, VALUES(keterangan), keterangan)
                            ");
                            $stmtAbsensi->execute([$siswa['id'], $kelasId, $year_id, $tanggal_absen, $autoStatus, $autoKet]);
                        }
                    }
                } catch (Exception $ex) {}

                if ($waTemplate && !empty($siswa['no_hp_ortu'])) {
                    $statusText = $log['state'] == 0 ? 'Hadir (Masuk)' : 'Pulang';
                    $msg = str_replace(
                        ['{nama}', '{status_absen}', '{waktu}'], 
                        [$siswa['nama'], $statusText, $log['timestamp']], 
                        $waTemplate
                    );
                    $pendingWa[] = ['phone' => $siswa['no_hp_ortu'], 'msg' => $msg];
                }
            }
        }
        
        db()->prepare("UPDATE absen_mesin SET last_sync = NOW() WHERE id = ?")->execute([$mesin['id']]);
        db()->commit();
    }

    foreach ($pendingWa as $item) {
        triggerWAGateway($item['phone'], $item['msg']);
    }
    
    json_response(200, true, "Auto-Sync selesai. $newLogsCount log baru.");
} catch (Exception $e) {
    if (db()->inTransaction()) db()->rollBack();
    json_response(500, false, 'Kesalahan Auto-Sync: ' . $e->getMessage());
}
