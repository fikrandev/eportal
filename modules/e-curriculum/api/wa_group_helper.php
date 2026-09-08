<?php
/**
 * Helper file for WhatsApp Group Teacher Attendance Broadcasts
 */

require_once __DIR__ . '/../../../api/config.php';

/**
 * Direct function to generate & send Teacher Attendance Report to configured WA Group
 */
function sendWaGroupAbsensiGuruDirect($tanggal = null, $tipe = 'masuk') {
    if (!$tanggal) $tanggal = date('Y-m-d');

    // Get WA Gateway URL and WA Group Target ID
    $stmtUrl = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_gateway_url'");
    $waUrl = $stmtUrl ? $stmtUrl->fetchColumn() : null;
    if (!$waUrl) $waUrl = 'http://localhost:3000/send';

    $waUrl = trim($waUrl);
    $baseUrl = preg_replace('#/(send|status|groups|logout)/?$#', '', $waUrl);
    $baseUrl = rtrim($baseUrl, '/');
    if (empty($baseUrl)) $baseUrl = 'http://localhost:3000';
    $sendUrl = $baseUrl . '/send';

    $stmtGrp = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_group_guru_id'");
    $groupId = $stmtGrp ? $stmtGrp->fetchColumn() : null;

    if (empty($groupId)) {
        return ['success' => false, 'message' => 'Grup WA Tujuan Notifikasi Guru belum dipilih/dikonfigurasi di menu WA Gateway.'];
    }

    // Format Indonesian Date: e.g. "8 SEPTEMBER 2026"
    $months = [
        1 => 'JANUARI', 2 => 'FEBRUARI', 3 => 'MARET', 4 => 'APRIL',
        5 => 'MEI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AGUSTUS',
        9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOPEMBER', 12 => 'DESEMBER'
    ];
    $ts = strtotime($tanggal);
    $d = date('j', $ts);
    $m = (int)date('n', $ts);
    $y = date('Y', $ts);
    $dateStr = $d . ' ' . ($months[$m] ?? '') . ' ' . $y;

    if ($tipe === 'masuk') {
        $header = "ABSEN PAGI TANGGAL " . $dateStr . "\n\n";
    } else {
        $header = "ABSEN PULANG TANGGAL " . $dateStr . "\n\n";
    }

    // Fetch teachers with mapped PINs ordered by PIN numerical value ASC
    $stmtG = db()->query("
        SELECT u.id, u.kode_guru, u.nama_lengkap as nama, 
               TRIM(LEADING '0' FROM m.mesin_pin) as clean_pin,
               CAST(TRIM(LEADING '0' FROM m.mesin_pin) AS UNSIGNED) as pin_num
        FROM users u
        LEFT JOIN absen_user_map m ON u.id = m.user_id
        WHERE u.role = 'guru' AND u.status = 1
        ORDER BY 
            CASE WHEN m.mesin_pin IS NULL OR m.mesin_pin = '' THEN 1 ELSE 0 END ASC,
            CAST(TRIM(LEADING '0' FROM m.mesin_pin) AS UNSIGNED) ASC,
            u.id ASC
    ");
    $teachers = $stmtG->fetchAll();

    // Fetch logs from absen_logs for date
    $stmtLogs = db()->prepare("
        SELECT TRIM(LEADING '0' FROM mesin_pin) COLLATE utf8mb4_unicode_ci as clean_pin, 
               MIN(TIME(waktu_absen)) as jam_masuk,
               MAX(TIME(waktu_absen)) as jam_pulang
        FROM absen_logs 
        WHERE DATE(waktu_absen) = ? 
        GROUP BY clean_pin
    ");
    $stmtLogs->execute([$tanggal]);
    $eAbsenLogs = [];
    while ($l = $stmtLogs->fetch()) {
        $eAbsenLogs[$l['clean_pin']] = $l;
    }

    // Manual overrides
    $stmtA = db()->prepare("SELECT guru_id, status, keterangan FROM acad_absensi_guru WHERE tanggal = ?");
    $stmtA->execute([$tanggal]);
    $manualMap = [];
    while ($row = $stmtA->fetch()) {
        $manualMap[$row['guru_id']] = $row;
    }

    $lines = [];
    $no = 1;

    foreach ($teachers as $t) {
        $tid = $t['id'];
        $nama = strtoupper(trim($t['nama']));
        $cleanPin = $t['clean_pin'];
        $displayNo = (!empty($cleanPin)) ? $cleanPin : $no;

        $jamMasuk = ($cleanPin && isset($eAbsenLogs[$cleanPin])) ? substr($eAbsenLogs[$cleanPin]['jam_masuk'], 0, 5) : null;
        $jamPulang = ($cleanPin && isset($eAbsenLogs[$cleanPin]) && $eAbsenLogs[$cleanPin]['jam_pulang'] !== $eAbsenLogs[$cleanPin]['jam_masuk']) ? substr($eAbsenLogs[$cleanPin]['jam_pulang'], 0, 5) : null;

        $manualStatus = isset($manualMap[$tid]) ? $manualMap[$tid]['status'] : null;

        if ($tipe === 'masuk') {
            if ($jamMasuk && $manualStatus !== 'A' && $manualStatus !== 'S' && $manualStatus !== 'I') {
                $timeVal = strtotime($tanggal . ' ' . $jamMasuk);
                $t0500 = strtotime($tanggal . ' 05:00:00');
                $t0545 = strtotime($tanggal . ' 05:45:59');
                $t0600 = strtotime($tanggal . ' 06:00:59');
                $t0615 = strtotime($tanggal . ' 06:15:59');
                $t0620 = strtotime($tanggal . ' 06:20:59');
                $t0630 = strtotime($tanggal . ' 06:30:59');

                if ($timeVal >= $t0500 && $timeVal <= $t0545) {
                    $emoji = "❤️️❤️️❤️️❤️️❤️️";
                } elseif ($timeVal > $t0545 && $timeVal <= $t0600) {
                    $emoji = "💛💛💛💛";
                } elseif ($timeVal > $t0600 && $timeVal <= $t0615) {
                    $emoji = "💙💙💙";
                } elseif ($timeVal > $t0615 && $timeVal <= $t0620) {
                    $emoji = "💚💚";
                } elseif ($timeVal > $t0620 && $timeVal <= $t0630) {
                    $emoji = "🖤";
                } else {
                    $emoji = "💙💙💙";
                }

                $lines[] = "{$displayNo}, {$nama}, {$jamMasuk}, {$emoji}";
            } elseif ($manualStatus === 'S') {
                $lines[] = "{$displayNo}, {$nama}, SAKIT / S";
            } elseif ($manualStatus === 'I') {
                $lines[] = "{$displayNo}, {$nama}, IZIN / I";
            } else {
                $lines[] = "{$displayNo}, {$nama}, TIDAK HADIR / TA";
            }
        } else {
            // Tipe Pulang
            if ($jamPulang) {
                $lines[] = "{$displayNo}, {$nama}, {$jamPulang}";
            } elseif ($jamMasuk) {
                $lines[] = "{$displayNo}, {$nama}, BELUM PULANG";
            } elseif ($manualStatus === 'S') {
                $lines[] = "{$displayNo}, {$nama}, SAKIT / S";
            } elseif ($manualStatus === 'I') {
                $lines[] = "{$displayNo}, {$nama}, IZIN / I";
            } else {
                $lines[] = "{$displayNo}, {$nama}, TIDAK HADIR / TA";
            }
        }

        $no++;
    }

    $fullMessage = $header . implode("\n", $lines);

    // Send via cURL to WA Gateway
    $postData = json_encode([
        'number' => $groupId,
        'message' => $fullMessage
    ]);

    $ch = curl_init($sendUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($postData)]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $resData = json_decode($response, true);
        if ($resData && !empty($resData['success'])) {
            return ['success' => true, 'message' => "Laporan " . ($tipe === 'masuk' ? 'Absen Pagi' : 'Absen Pulang') . " berhasil terkirim ke Grup WA!"];
        } else {
            $msg = isset($resData['message']) ? $resData['message'] : 'Gagal mengirim ke WA Gateway.';
            return ['success' => false, 'message' => $msg];
        }
    } else {
        return ['success' => false, 'message' => 'Server WA Gateway tidak merespon.'];
    }
}

/**
 * Check if total teacher taps today reached a new multiple of 10 (10, 20, 30...)
 * and automatically broadcast the updated report to WA Group
 */
function checkAndSendWaGroupGuruAbsensiBatch($tanggal = null) {
    if (!$tanggal) $tanggal = date('Y-m-d');

    $stmtGrp = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_group_guru_id'");
    $groupId = $stmtGrp ? $stmtGrp->fetchColumn() : null;
    if (empty($groupId)) return false;

    // Count how many teachers have tapped attendance today
    $stmtCount = db()->prepare("
        SELECT COUNT(DISTINCT TRIM(LEADING '0' FROM l.mesin_pin))
        FROM absen_logs l
        JOIN absen_user_map m ON TRIM(LEADING '0' FROM l.mesin_pin) = TRIM(LEADING '0' FROM m.mesin_pin)
        JOIN users u ON u.id = m.user_id
        WHERE DATE(l.waktu_absen) = ? AND u.role = 'guru' AND u.status = 1
    ");
    $stmtCount->execute([$tanggal]);
    $totalTeachersTapped = (int)$stmtCount->fetchColumn();

    if ($totalTeachersTapped < 10) return false;

    $currentBatch = (int)floor($totalTeachersTapped / 10);

    // Check settings for last batch date & number
    $stmtLastDate = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_guru_last_batch_date'");
    $lastDate = $stmtLastDate ? $stmtLastDate->fetchColumn() : null;

    $stmtLastBatch = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_guru_last_batch_num'");
    $lastBatch = ($stmtLastBatch && $lastDate === $tanggal) ? (int)$stmtLastBatch->fetchColumn() : 0;

    if ($currentBatch > $lastBatch || $lastDate !== $tanggal) {
        // Send WA report
        sendWaGroupAbsensiGuruDirect($tanggal, 'masuk');

        // Save new batch state in settings table
        db()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('wa_guru_last_batch_date', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$tanggal, $tanggal]);
        db()->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('wa_guru_last_batch_num', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$currentBatch, $currentBatch]);

        return true;
    }

    return false;
}
