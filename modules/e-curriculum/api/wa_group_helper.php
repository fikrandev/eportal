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
    $waUrl = get_setting('wa_gateway_url', 'http://localhost:3000/send');
    $waUrl = trim($waUrl);
    $baseUrl = preg_replace('#/(send|status|groups|logout)/?$#', '', $waUrl);
    $baseUrl = rtrim($baseUrl, '/');
    if (empty($baseUrl)) $baseUrl = 'http://localhost:3000';
    $sendUrl = $baseUrl . '/send';

    $groupId = get_setting('wa_group_guru_id');
    if (empty($groupId)) {
        return ['success' => false, 'message' => 'Grup WA Tujuan Notifikasi Guru belum dipilih/dikonfigurasi di menu WA Gateway.'];
    }

    // Indonesian Day and Month Names
    $days = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
    ];
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    $ts = strtotime($tanggal);
    $dayName = $days[date('l', $ts)] ?? date('l', $ts);
    $d = date('j', $ts);
    $m = (int)date('n', $ts);
    $y = date('Y', $ts);
    $dateFormatted = "{$dayName}, {$d} " . ($months[$m] ?? '') . " {$y}";
    $currentTime = date('H:i') . ' WIB';

    $namaSekolah = get_setting('nama_sekolah', 'SMAS Wachid Hasyim 1 Surabaya');
    $headerTitle = ($tipe === 'masuk') ? '📋 *LAPORAN ABSENSI PAGI GURU*' : (($tipe === 'istirahat') ? '📋 *LAPORAN ABSENSI ISTIRAHAT GURU*' : '📋 *LAPORAN ABSENSI PULANG GURU*');

    // Fetch teachers with mapped PINs ordered by PIN numerical value ASC
    $jamMulaiPulang = get_setting('wa_guru_mulai_pulang', '13:00:00');
    if (strlen($jamMulaiPulang) === 5) $jamMulaiPulang .= ':00';
    $waktuIstirahatMulai = get_setting('waktu_istirahat_guru_mulai', '12:00:00');
    if (strlen($waktuIstirahatMulai) === 5) $waktuIstirahatMulai .= ':00';

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

    // Fetch logs from absen_logs for date:
    $stmtLogs = db()->prepare("
        SELECT TRIM(LEADING '0' FROM mesin_pin) COLLATE utf8mb4_unicode_ci as clean_pin, 
               MIN(CASE WHEN TIME(waktu_absen) < ? THEN TIME(waktu_absen) END) as jam_masuk,
               MIN(CASE WHEN TIME(waktu_absen) >= ? AND TIME(waktu_absen) < ? THEN TIME(waktu_absen) END) as jam_istirahat,
               MAX(CASE WHEN TIME(waktu_absen) >= ? THEN TIME(waktu_absen) END) as jam_pulang
        FROM absen_logs 
        WHERE DATE(waktu_absen) = ? 
        GROUP BY clean_pin
    ");
    $stmtLogs->execute([$waktuIstirahatMulai, $waktuIstirahatMulai, $jamMulaiPulang, $jamMulaiPulang, $tanggal]);
    $eAbsenLogs = [];
    while ($l = $stmtLogs->fetch()) {
        $eAbsenLogs[$l['clean_pin']] = $l;
    }

    // Manual overrides
    $stmtA = db()->prepare("SELECT guru_id, status, keterangan FROM acad_absensi_guru WHERE tanggal = ? AND sesi = ?");
    $stmtA->execute([$tanggal, $tipe]);
    $manualMap = [];
    while ($row = $stmtA->fetch()) {
        $manualMap[$row['guru_id']] = $row;
    }

    $lines = [];
    $no = 1;
    $countHadir = 0;
    $countSakit = 0;
    $countIzin = 0;
    $countAlpha = 0;
    $countPulang = 0;
    $countIstirahat = 0;

    foreach ($teachers as $t) {
        $tid = $t['id'];
        $nama = strtoupper(trim($t['nama']));
        $cleanPin = $t['clean_pin'];
        $displayNo = (!empty($cleanPin)) ? $cleanPin : $no;
        $numPadded = sprintf('%02d.', (int)$displayNo);

        $jamMasuk = ($cleanPin && isset($eAbsenLogs[$cleanPin]) && !empty($eAbsenLogs[$cleanPin]['jam_masuk'])) ? substr($eAbsenLogs[$cleanPin]['jam_masuk'], 0, 5) : null;
        $jamIstirahat = ($cleanPin && isset($eAbsenLogs[$cleanPin]) && !empty($eAbsenLogs[$cleanPin]['jam_istirahat'])) ? substr($eAbsenLogs[$cleanPin]['jam_istirahat'], 0, 5) : null;
        $jamPulang = ($cleanPin && isset($eAbsenLogs[$cleanPin]) && !empty($eAbsenLogs[$cleanPin]['jam_pulang'])) ? substr($eAbsenLogs[$cleanPin]['jam_pulang'], 0, 5) : null;

        $manualStatus = isset($manualMap[$tid]) ? $manualMap[$tid]['status'] : null;

        if ($tipe === 'masuk') {
            if ($jamMasuk && $manualStatus !== 'A' && $manualStatus !== 'S' && $manualStatus !== 'I') {
                $countHadir++;
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
                    $emoji = "🖤";
                }

                $lines[] = "*{$numPadded}* *{$nama}*\n      ⏰ *{$jamMasuk}* WIB  •  {$emoji}";
            } elseif ($manualStatus === 'S') {
                $countSakit++;
                $lines[] = "*{$numPadded}* *{$nama}*\n      🏥 *SAKIT (S)*";
            } elseif ($manualStatus === 'I') {
                $countIzin++;
                $lines[] = "*{$numPadded}* *{$nama}*\n      📝 *IZIN (I)*";
            } else {
                $countAlpha++;
                $lines[] = "*{$numPadded}* *{$nama}*\n      ❌ *NN / TH / TA / BA*";
            }
        } elseif ($tipe === 'istirahat') {
            if ($jamIstirahat && $manualStatus !== 'A' && $manualStatus !== 'S' && $manualStatus !== 'I') {
                $countIstirahat++;
                $lines[] = "*{$numPadded}* *{$nama}*\n      ☕ *{$jamIstirahat}* WIB  •  *HADIR ISTIRAHAT*";
            } elseif ($manualStatus === 'S') {
                $countSakit++;
                $lines[] = "*{$numPadded}* *{$nama}*\n      🏥 *SAKIT (S)*";
            } elseif ($manualStatus === 'I') {
                $countIzin++;
                $lines[] = "*{$numPadded}* *{$nama}*\n      📝 *IZIN (I)*";
            } else {
                $countAlpha++;
                $lines[] = "*{$numPadded}* *{$nama}*\n      ❌ *BELUM SCAN ISTIRAHAT*";
            }
        } else {
            // Tipe Pulang
            if ($jamPulang) {
                $countPulang++;
                $countHadir++;
                $inNote = $jamMasuk ? " (Masuk: {$jamMasuk})" : "";
                $lines[] = "*{$numPadded}* *{$nama}*\n      🏠 *{$jamPulang}* WIB{$inNote}";
            } elseif ($jamMasuk) {
                $countHadir++;
                $lines[] = "*{$numPadded}* *{$nama}*\n      ⏳ *BELUM PULANG* (Masuk: {$jamMasuk})";
            } elseif ($manualStatus === 'S') {
                $countSakit++;
                $lines[] = "*{$numPadded}* *{$nama}*\n      🏥 *SAKIT (S)*";
            } elseif ($manualStatus === 'I') {
                $countIzin++;
                $lines[] = "*{$numPadded}* *{$nama}*\n      📝 *IZIN (I)*";
            } else {
                $countAlpha++;
                $lines[] = "*{$numPadded}* *{$nama}*\n      ❌ *NN / TH / TA / BA*";
            }
        }

        $no++;
    }

    $header = "━━━━━━━━━━━━━━━━━━━━\n"
            . "{$headerTitle}\n"
            . "🏫 *{$namaSekolah}*\n"
            . "🗓️ *Tanggal:* {$dateFormatted}\n"
            . "⏰ *Waktu Update:* {$currentTime}\n"
            . "━━━━━━━━━━━━━━━━━━━━\n\n";

    $body = implode("\n\n", $lines);

    $totalGuru = count($teachers);
    $footer = "\n\n━━━━━━━━━━━━━━━━━━━━\n";

    if ($tipe === 'masuk') {
        $footer .= "*📊 RINGKASAN KEHADIRAN:*\n";
        $footer .= "👥 *Total Guru* : {$totalGuru} Orang\n";
        $footer .= "✅ *Hadir* : {$countHadir} Orang\n";
        if ($countSakit > 0) $footer .= "🏥 *Sakit* : {$countSakit} Orang\n";
        if ($countIzin > 0) $footer .= "📝 *Izin* : {$countIzin} Orang\n";
        if ($countAlpha > 0) $footer .= "❌ *NN / TH / TA / BA* : {$countAlpha} Orang\n";
    } elseif ($tipe === 'istirahat') {
        $footer .= "*📊 RINGKASAN ISTIRAHAT:*\n";
        $footer .= "👥 *Total Guru* : {$totalGuru} Orang\n";
        $footer .= "☕ *Hadir Istirahat* : {$countIstirahat} Orang\n";
        $belumIstirahat = $totalGuru - $countIstirahat - $countSakit - $countIzin;
        if ($belumIstirahat > 0) $footer .= "⏳ *Belum Istirahat* : {$belumIstirahat} Orang\n";
        if ($countSakit > 0) $footer .= "🏥 *Sakit* : {$countSakit} Orang\n";
        if ($countIzin > 0) $footer .= "📝 *Izin* : {$countIzin} Orang\n";
    } else {
        $footer .= "*📊 RINGKASAN KEPULANGAN:*\n";
        $footer .= "👥 *Total Guru* : {$totalGuru} Orang\n";
        $footer .= "🏠 *Sudah Pulang* : {$countPulang} Orang\n";
        $belumPulang = $countHadir - $countPulang;
        if ($belumPulang > 0) $footer .= "⏳ *Belum Pulang* : {$belumPulang} Orang\n";
        if ($countSakit > 0) $footer .= "🏥 *Sakit* : {$countSakit} Orang\n";
        if ($countIzin > 0) $footer .= "📝 *Izin* : {$countIzin} Orang\n";
        if ($countAlpha > 0) $footer .= "❌ *NN / TH / TA / BA* : {$countAlpha} Orang\n";
    }
    $footer .= "━━━━━━━━━━━━━━━━━━━━\n";
    $footer .= "_⚡ Pesan otomatis E-Portal System_";

    $fullMessage = $header . $body . $footer;

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
            $labelSesi = ($tipe === 'masuk') ? 'Absen Pagi' : (($tipe === 'istirahat') ? 'Absen Istirahat' : 'Absen Pulang');
            return ['success' => true, 'message' => "Laporan {$labelSesi} berhasil terkirim ke Grup WA!"];
        } else {
            $msg = isset($resData['message']) ? $resData['message'] : 'Gagal mengirim ke WA Gateway.';
            return ['success' => false, 'message' => $msg];
        }
    } else {
        return ['success' => false, 'message' => 'Server WA Gateway tidak merespon.'];
    }
}

/**
 * Check if teacher attendance reports should be broadcasted to WA Group:
 * - Absen Masuk (Pagi)  : Broadcast in batches of 10 teachers up until cutoff (default: 06:30:59)
 * - Absen Pulang (Sore) : Broadcast ONLY once at 17:00 (default: 17:00:00) per day
 */
function checkAndSendWaGroupGuruAbsensiBatch($tanggal = null) {
    if (!$tanggal) $tanggal = date('Y-m-d');

    $groupId = get_setting('wa_group_guru_id');
    if (empty($groupId)) return false;

    $currentTime = date('H:i:s');

    $cutoffMasuk = get_setting('wa_guru_cutoff_masuk', '06:30:59');
    if (strlen($cutoffMasuk) === 5) $cutoffMasuk .= ':59';

    $jamKirimPulang = get_setting('wa_guru_cutoff_pulang', '17:00:00');
    if (strlen($jamKirimPulang) === 5) $jamKirimPulang .= ':00';

    $jamMulaiPulang = get_setting('wa_guru_mulai_pulang', '13:00:00');
    if (strlen($jamMulaiPulang) === 5) $jamMulaiPulang .= ':00';

    // 1. PERIODE PAGI (MASUK): Sebelum jam 12:00
    if ($currentTime < '12:00:00') {
        // STRICT CUTOFF: Jika waktu sekarang melebihi cutoff masuk (default 06:30), jangan kirim update otomatis lagi
        if ($currentTime > $cutoffMasuk) {
            return false;
        }

        // Hitung jumlah guru yang sudah tap masuk pagi hari (sebelum 12:00:00)
        $stmtCount = db()->prepare("
            SELECT COUNT(DISTINCT TRIM(LEADING '0' FROM l.mesin_pin))
            FROM absen_logs l
            WHERE DATE(l.waktu_absen) = ? AND TIME(l.waktu_absen) < '12:00:00'
        ");
        $stmtCount->execute([$tanggal]);
        $totalTeachersTapped = (int)$stmtCount->fetchColumn();

        if ($totalTeachersTapped < 10) return false;

        $currentBatch = (int)floor($totalTeachersTapped / 10);

        // Cek settings untuk batch terakhir yang dikirim pada tanggal ini
        $lastDate = get_setting('wa_guru_last_batch_date_masuk');
        $lastBatch = ($lastDate === $tanggal) ? (int)get_setting('wa_guru_last_batch_num_masuk', 0) : 0;

        if ($currentBatch > $lastBatch || $lastDate !== $tanggal) {
            // Kirim laporan WA masuk
            sendWaGroupAbsensiGuruDirect($tanggal, 'masuk');

            // Simpan status batch di settings
            upsert_setting('wa_guru_last_batch_date_masuk', $tanggal, 'text', 'Tanggal batch terakhir WA absen pagi guru');
            upsert_setting('wa_guru_last_batch_num_masuk', $currentBatch, 'number', 'Batch nomor terakhir WA absen pagi guru');

            return true;
        }
    } 
    // 2. PERIODE PULANG: Kirim jika sudah mencapai jam cutoff pulang (default 17:00)
    else if ($currentTime >= $jamKirimPulang) {
        // Cek apakah laporan pulang sudah terkirim hari ini
        $lastDatePulang = get_setting('wa_guru_last_sent_date_pulang');
        if ($lastDatePulang === $tanggal) {
            return false; // Sudah dikirim hari ini
        }

        // Cek hari Minggu (hanya skip jika hari Minggu dan benar-benar tidak ada data apapun)
        $dayOfWeek = (int)date('w', strtotime($tanggal)); // 0 = Sunday
        if ($dayOfWeek === 0) {
            $stmtLogsCheck = db()->prepare("SELECT COUNT(*) FROM absen_logs WHERE DATE(waktu_absen) = ?");
            $stmtLogsCheck->execute([$tanggal]);
            $totalLogsHariIni = (int)$stmtLogsCheck->fetchColumn();

            $stmtManualCheck = db()->prepare("SELECT COUNT(*) FROM acad_absensi_guru WHERE tanggal = ?");
            $stmtManualCheck->execute([$tanggal]);
            $totalManualHariIni = (int)$stmtManualCheck->fetchColumn();

            if ($totalLogsHariIni === 0 && $totalManualHariIni === 0) {
                return false;
            }
        }

        // Kirim laporan WA absen pulang lengkap
        $result = sendWaGroupAbsensiGuruDirect($tanggal, 'pulang');

        if (!empty($result['success'])) {
            upsert_setting('wa_guru_last_sent_date_pulang', $tanggal, 'text', 'Tanggal terakhir WA absen pulang guru dikirim');
            return true;
        }
    }

    return false;
}

