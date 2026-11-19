<?php
/**
 * Helper file for WhatsApp Group Teacher Attendance Broadcasts
 */

require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/../../../api/wa_gateway_client.php';

/**
 * Direct function to generate & send Teacher Attendance Report to configured WA Group
 */
function sendWaGroupAbsensiGuruDirect($tanggal = null, $tipe = 'masuk') {
    if (!$tanggal) $tanggal = date('Y-m-d');

    $sendUrl = wa_gateway_endpoint_url('send');

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
        SELECT TRIM(LEADING '0' FROM mesin_pin) COLLATE utf8mb4_unicode_ci as clean_pin, TIME(waktu_absen) as jam 
        FROM absen_logs 
        WHERE DATE(waktu_absen) = ? 
        ORDER BY waktu_absen ASC
    ");
    $stmtLogs->execute([$tanggal]);
    
    $rawLogs = [];
    while ($row = $stmtLogs->fetch()) {
        $pin = $row['clean_pin'];
        if (!isset($rawLogs[$pin])) $rawLogs[$pin] = [];
        $rawLogs[$pin][] = $row['jam'];
    }

    $eAbsenLogs = [];
    foreach ($rawLogs as $pin => $scans) {
        $first = $scans[0];
        $last = $scans[count($scans) - 1];
        $masuk = $first;
        $pulang = null;
        $istirahat = null;

        $cutoffPulangTap = !empty($jamMulaiPulang) ? $jamMulaiPulang : '11:00:00';
        if ($first !== $last) {
            // Evaluasi jam pulang: ambil scan terakhir jika sudah mencapai jam mulai pulang
            if ($last >= $cutoffPulangTap) {
                $pulang = $last;
            }
            
            // Evaluasi jam istirahat: cari scan di antara waktu istirahat mulai dan jam pulang
            foreach ($scans as $scan) {
                if ($scan !== $first && $scan !== $pulang && $scan >= $waktuIstirahatMulai) {
                    $istirahat = $scan;
                    break;
                }
            }
        }
        
        $eAbsenLogs[$pin] = [
            'jam_masuk' => $masuk,
            'jam_istirahat' => $istirahat,
            'jam_pulang' => $pulang
        ];
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
    $countIstirahat = 0;
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
    curl_setopt($ch, CURLOPT_HTTPHEADER, wa_gateway_headers($postData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 70);
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
 * - Absen Masuk (Pagi)  : Broadcast ONLY once when cutoff time arrives (default: 06:30:59)
 * - Absen Pulang (Sore) : Broadcast ONLY once at evening cutoff time (default: 17:00:00) per day
 */
function checkAndSendWaGroupGuruAbsensiBatch($tanggal = null) {
    if (!$tanggal) $tanggal = date('Y-m-d');

    $groupId = get_setting('wa_group_guru_id');
    if (empty($groupId)) return false;

    // Batasi pengiriman otomatis HANYA untuk hari Senin (1) s/d Jumat (5)
    $dayOfWeek = (int)date('w', strtotime($tanggal)); // 0 = Minggu, 6 = Sabtu
    if ($dayOfWeek === 0 || $dayOfWeek === 6) {
        return false;
    }

    $currentTime = date('H:i:s');

    $cutoffMasuk = get_setting('wa_guru_cutoff_masuk', '06:30:00');
    if (strlen($cutoffMasuk) === 5) $cutoffMasuk .= ':00';

    $jamKirimPulang = get_setting('wa_guru_cutoff_pulang', '17:00:00');
    if (strlen($jamKirimPulang) === 5) $jamKirimPulang .= ':00';

    $isPagi = ($currentTime < '12:00:00' && $currentTime >= $cutoffMasuk);
    $isSore = ($currentTime >= '12:00:00' && $currentTime >= $jamKirimPulang);

    if (!$isPagi && !$isSore) {
        return false;
    }

    // 1. Fast Path: Cek flag hari ini terlebih dahulu sebelum meminta lock database
    if ($isPagi) {
        $lastDateMasuk = get_setting('wa_guru_last_sent_date_masuk');
        if ($lastDateMasuk === $tanggal) {
            return false; // Sudah dikirim hari ini, jangan kirim ulang!
        }
    } else if ($isSore) {
        $lastDatePulang = get_setting('wa_guru_last_sent_date_pulang');
        if ($lastDatePulang === $tanggal) {
            return false; // Sudah dikirim hari ini, jangan kirim ulang!
        }
    }

    // 2. Gunakan MySQL Named Lock (GET_LOCK) dengan timeout 0
    // Menjamin secara atomik hanya ada 1 proses broadcast yang berjalan pada satu waktu.
    // Jika proses lain sedang berjalan, proses ini langsung keluar tanpa menumpuk.
    try {
        $lockStmt = db()->query("SELECT GET_LOCK('wa_guru_broadcast_lock', 0)");
        $locked = $lockStmt ? (int)$lockStmt->fetchColumn() : 0;
        if ($locked !== 1) {
            return false; // Sedang ada proses broadcast lain yang berjalan
        }
    } catch (Exception $lockEx) {
        // Fallback jika database tidak mendukung GET_LOCK
    }

    try {
        $stmtFresh = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        if ($isPagi) {
            // Double-checked locking setelah lock diperoleh (query langsung ke DB, bukan cache in-memory)
            $stmtFresh->execute(['wa_guru_last_sent_date_masuk']);
            $lastDateMasuk = $stmtFresh->fetchColumn();
            if ($lastDateMasuk === $tanggal) {
                return false;
            }

            // KUNCI TANGGAL SEKARANG: Tandai langsung sebagai sudah diproses untuk tanggal ini!
            upsert_setting('wa_guru_last_sent_date_masuk', $tanggal, 'text', 'Tanggal terakhir WA absen masuk guru dikirim');
            upsert_setting('wa_guru_last_sent_time_masuk', date('Y-m-d H:i:s'), 'text', 'Waktu pengiriman WA absen masuk guru');

            // Kirim laporan WA absen masuk
            $result = sendWaGroupAbsensiGuruDirect($tanggal, 'masuk');
            return !empty($result['success']);
        } else if ($isSore) {
            // Double-checked locking setelah lock diperoleh (query langsung ke DB, bukan cache in-memory)
            $stmtFresh->execute(['wa_guru_last_sent_date_pulang']);
            $lastDatePulang = $stmtFresh->fetchColumn();
            if ($lastDatePulang === $tanggal) {
                return false;
            }

            // KUNCI TANGGAL SEKARANG: Tandai langsung sebagai sudah diproses untuk tanggal ini!
            upsert_setting('wa_guru_last_sent_date_pulang', $tanggal, 'text', 'Tanggal terakhir WA absen pulang guru dikirim');
            upsert_setting('wa_guru_last_sent_time_pulang', date('Y-m-d H:i:s'), 'text', 'Waktu pengiriman WA absen pulang guru');

            // Kirim laporan WA absen pulang
            $result = sendWaGroupAbsensiGuruDirect($tanggal, 'pulang');
            return !empty($result['success']);
        }
    } finally {
        try {
            db()->query("SELECT RELEASE_LOCK('wa_guru_broadcast_lock')");
        } catch (Exception $relEx) {}
    }

    return false;
}

