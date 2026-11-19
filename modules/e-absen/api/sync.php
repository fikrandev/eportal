<?php
/**
 * E-Absen Sync API
 * Menarik data dari semua mesin fingerprint dan memicu WA Gateway
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/../../../api/wa_gateway_client.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../../../api/ZKLibrary.php';

$user = acad_auth();
acad_require_admin($user);

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'pull') {
    try {
        $stmt = db()->query("SELECT * FROM absen_mesin WHERE status = 1");
        $mesins = $stmt->fetchAll();
        
        if (!$mesins || count($mesins) === 0) {
            json_response(400, false, 'Tidak ada mesin aktif yang dikonfigurasi.');
        }

        $waTemplate = get_setting('wa_message_template', '');

        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        $newLogsCount = 0;
        $mesinErrors = [];
        $admsCount = 0;
        $pendingWa = [];

        foreach ($mesins as $mesin) {
            if (empty($mesin['ip_address']) || trim($mesin['ip_address']) === '') {
                try {
                    db()->prepare("UPDATE absen_mesin SET force_sync = 1 WHERE id = ?")->execute([$mesin['id']]);
                    $admsCount++;
                } catch (Exception $e) {}
                continue;
            }

            $zk = new ZKLibrary($mesin['ip_address'], $mesin['port']);
            $connected = $zk->connect();
            
            if (!$connected) {
                $mesinErrors[] = "Mesin {$mesin['nama_mesin']} ({$mesin['ip_address']}) gagal dihubungi.";
                continue;
            }
            
            $attendance = $zk->getAttendance();
            $zk->disconnect();
            
            if ($attendance === false) {
                continue;
            }
            
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
                            
                            if ($year_id > 0) {
                                $tanggal_absen = date('Y-m-d', strtotime($log['timestamp']));
                                
                                $stmtAbsensi = db()->prepare("
                                    INSERT INTO acad_absensi (student_id, kelas_id, academic_year_id, tanggal, jam_ke, status, keterangan, dicatat_oleh)
                                    VALUES (?, ?, ?, ?, 0, 'H', 'Hadir via Fingerprint', NULL)
                                    ON DUPLICATE KEY UPDATE status = 'H', keterangan = 'Hadir via Fingerprint'
                                ");
                                $stmtAbsensi->execute([$siswa['id'], $kelasId, $year_id, $tanggal_absen]);
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
            }
            
            db()->prepare("UPDATE absen_mesin SET last_sync = NOW() WHERE id = ?")->execute([$mesin['id']]);
            db()->commit();
        }

        foreach ($pendingWa as $item) {
            triggerWAGateway($item['phone'], $item['msg']);
        }

        // Check if 10 teachers threshold reached for WA Group auto notification
        try {
            require_once __DIR__ . '/../../e-curriculum/api/wa_group_helper.php';
            checkAndSendWaGroupGuruAbsensiBatch();
        } catch (Exception $ex) {}
        
        $msg = "Sinkronisasi selesai. $newLogsCount log baru ditarik.";
        if ($admsCount > 0) {
            $msg .= " Perintah tarik ulang data telah dikirimkan ke $admsCount mesin ADMS (akan sync saat heartbeat berikutnya).";
        }
        if (count($mesinErrors) > 0) {
            $msg .= " Namun terdapat error: " . implode(", ", $mesinErrors);
        }

        json_response(200, true, $msg);
    } catch (Exception $e) {
        if (db()->inTransaction()) db()->rollBack();
        json_response(500, false, 'Terjadi kesalahan: ' . $e->getMessage());
    }
} else if ($action === 'logs') {
    try {
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        
        $stmt = db()->prepare("
            SELECT l.*, m.nama_mesin, 
                COALESCE(s.nama, u.nama_lengkap, l.mesin_pin) as nama_pegawai 
            FROM absen_logs l
            LEFT JOIN absen_mesin m ON l.mesin_id = m.id
            LEFT JOIN absen_user_map map ON l.mesin_pin = map.mesin_pin
            LEFT JOIN users u ON map.user_id = u.id
            LEFT JOIN students s ON TRIM(LEADING '0' FROM l.mesin_pin) COLLATE utf8mb4_unicode_ci = TRIM(LEADING '0' FROM s.nis) COLLATE utf8mb4_unicode_ci
            WHERE DATE(l.waktu_absen) = ?
            ORDER BY l.waktu_absen DESC
        ");
        $stmt->execute([$tanggal]);
        
        json_response(200, true, 'Log dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
} else {
    json_response(400, false, 'Action tidak valid.');
}
