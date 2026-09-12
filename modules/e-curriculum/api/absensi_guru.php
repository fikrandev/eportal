<?php
/**
 * E-Curriculum Absensi Guru API
 * Manages teacher attendance integrated with E-Absen logs & manual overrides
 */
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/wa_group_helper.php';
require_once __DIR__ . '/hari_libur.php';

$user = acad_auth();
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Opportunistic auto-broadcast check (e.g. afternoon cutoff at 17:00)
try {
    checkAndSendWaGroupGuruAbsensiBatch();
} catch (Exception $e) {}

switch ($action) {
    case 'list':
        listAbsensiGuru($user);
        break;
    case 'save':
        saveAbsensiGuru($user);
        break;
    case 'rekap':
        rekapAbsensiGuru($user);
        break;
    case 'get_settings':
        getSettingsGuru($user);
        break;
    case 'save_settings':
        saveSettingsGuru($user);
        break;
    case 'send_wa_group':
        sendWaGroupAbsensiGuru($user);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List absensi for all teachers on a given date (merging E-Absen logs and acad_absensi_guru for 3 sessions)
 */
function listAbsensiGuru($user) {
    try {
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $sesi = isset($_GET['sesi']) && in_array($_GET['sesi'], ['masuk', 'istirahat', 'pulang']) ? $_GET['sesi'] : 'masuk';

        $waktu_terlambat = get_setting('waktu_terlambat_guru', '06:30:00');
        $waktu_istirahat_mulai = get_setting('waktu_istirahat_guru_mulai', '12:00:00');
        $waktu_istirahat_selesai = get_setting('waktu_istirahat_guru_selesai', '13:00:00');
        $waktu_pulang = get_setting('waktu_pulang_guru', '15:30:00');
        $jam_mulai_pulang = get_setting('wa_guru_mulai_pulang', '13:00:00');

        if (strlen($waktu_terlambat) === 5) $waktu_terlambat .= ':00';
        if (strlen($waktu_istirahat_mulai) === 5) $waktu_istirahat_mulai .= ':00';
        if (strlen($waktu_istirahat_selesai) === 5) $waktu_istirahat_selesai .= ':00';
        if (strlen($waktu_pulang) === 5) $waktu_pulang .= ':00';
        if (strlen($jam_mulai_pulang) === 5) $jam_mulai_pulang .= ':00';

        // Get all active teachers
        $stmtG = db()->query("SELECT id, kode_guru, nama_lengkap as nama FROM users WHERE role = 'guru' AND status = 1 ORDER BY nama_lengkap");
        $teachers = $stmtG->fetchAll();

        // Get existing manual absensi from acad_absensi_guru for active session
        $stmtA = db()->prepare("
            SELECT guru_id, status, keterangan 
            FROM acad_absensi_guru 
            WHERE tanggal = ? AND sesi = ?
        ");
        $stmtA->execute([$tanggal, $sesi]);
        $existingManual = [];
        while ($row = $stmtA->fetch()) {
            $existingManual[$row['guru_id']] = $row;
        }

        // Get E-Absen logs for this date efficiently
        $stmtMap = db()->query("SELECT user_id, TRIM(LEADING '0' FROM mesin_pin) as clean_pin FROM absen_user_map");
        $userMap = [];
        while($m = $stmtMap->fetch()) {
             $userMap[$m['user_id']] = $m['clean_pin'];
        }

        $stmtLogs = db()->prepare("
            SELECT TRIM(LEADING '0' FROM mesin_pin) COLLATE utf8mb4_unicode_ci as clean_pin, 
                   MIN(CASE WHEN TIME(waktu_absen) < ? THEN TIME(waktu_absen) END) as jam_masuk,
                   MIN(CASE WHEN TIME(waktu_absen) >= ? AND TIME(waktu_absen) < ? THEN TIME(waktu_absen) END) as jam_istirahat,
                   MAX(CASE WHEN TIME(waktu_absen) >= ? THEN TIME(waktu_absen) END) as jam_pulang
            FROM absen_logs 
            WHERE DATE(waktu_absen) = ? 
            GROUP BY clean_pin
        ");
        $stmtLogs->execute([$waktu_istirahat_mulai, $waktu_istirahat_mulai, $jam_mulai_pulang, $jam_mulai_pulang, $tanggal]);
        $eAbsenLogs = [];
        while ($l = $stmtLogs->fetch()) {
            $eAbsenLogs[$l['clean_pin']] = $l;
        }

        $waktu_terlambat_short = substr($waktu_terlambat, 0, 5);

        // Merge teacher list with E-Absen log and manual override for active session
        $result = [];
        foreach ($teachers as $t) {
            $tid = $t['id'];
            $cleanPin = isset($userMap[$tid]) ? $userMap[$tid] : null;
            $logData = ($cleanPin && isset($eAbsenLogs[$cleanPin])) ? $eAbsenLogs[$cleanPin] : null;
            $jamMasuk = ($logData && !empty($logData['jam_masuk'])) ? $logData['jam_masuk'] : null;
            $jamIstirahat = ($logData && !empty($logData['jam_istirahat'])) ? $logData['jam_istirahat'] : null;
            $jamPulang = ($logData && !empty($logData['jam_pulang'])) ? $logData['jam_pulang'] : null;

            // Determine default status based on active session
            $calculatedStatus = 'A'; // Default jika belum scan adalah Alpha
            $scanInfo = 'Belum Scan Mesin';
            $jamScanSesi = null;

            if ($sesi === 'masuk') {
                $jamScanSesi = $jamMasuk;
                if ($jamMasuk !== null) {
                    $masukFormatted = substr($jamMasuk, 0, 5);
                    if ($masukFormatted <= $waktu_terlambat_short) {
                        $calculatedStatus = 'H';
                        $scanInfo = "Hadir ({$masukFormatted})";
                    } else {
                        $calculatedStatus = 'T';
                        $scanInfo = "Terlambat ({$masukFormatted})";
                    }
                } else {
                    $calculatedStatus = 'A';
                    $scanInfo = 'Belum Scan Mesin';
                }
            } else if ($sesi === 'istirahat') { // Absen Istirahat (tampilan jam saja)
                $jamScanSesi = $jamIstirahat;
                if ($jamIstirahat !== null) {
                    $calculatedStatus = 'H';
                    $scanInfo = substr($jamIstirahat, 0, 5);
                } else {
                    $calculatedStatus = 'A';
                    $scanInfo = 'Belum Scan';
                }
            } else if ($sesi === 'pulang') { // Absen Pulang (tampilan jam saja)
                $jamScanSesi = $jamPulang;
                if ($jamPulang !== null) {
                    $calculatedStatus = 'H';
                    $scanInfo = substr($jamPulang, 0, 5);
                } else {
                    $calculatedStatus = 'A';
                    $scanInfo = 'Belum Scan';
                }
            }

            // Manual override takes precedence if recorded in acad_absensi_guru
            $finalStatus = isset($existingManual[$tid]) ? $existingManual[$tid]['status'] : $calculatedStatus;
            $keterangan = isset($existingManual[$tid]) ? $existingManual[$tid]['keterangan'] : ($jamScanSesi !== null ? "Fingerprint: $jamScanSesi" : '');

            $result[] = [
                'guru_id' => $tid,
                'kode_guru' => $t['kode_guru'],
                'nama' => $t['nama'],
                'jam_scan' => $jamScanSesi,
                'jam_masuk' => $jamMasuk,
                'jam_istirahat' => $jamIstirahat,
                'jam_pulang' => $jamPulang,
                'scan_info' => $scanInfo,
                'status' => $finalStatus,
                'keterangan' => $keterangan
            ];
        }

        json_response(200, true, 'Data absensi guru dimuat.', [
            'sesi' => $sesi,
            'waktu_terlambat' => substr($waktu_terlambat, 0, 5),
            'waktu_istirahat_mulai' => substr($waktu_istirahat_mulai, 0, 5),
            'waktu_istirahat_selesai' => substr($waktu_istirahat_selesai, 0, 5),
            'waktu_pulang' => substr($waktu_pulang, 0, 5),
            'wa_mulai_pulang' => substr($jam_mulai_pulang, 0, 5),
            'teachers' => $result
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Save/update absensi guru on a date per session
 */
function saveAbsensiGuru($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $tanggal = isset($input['tanggal']) ? $input['tanggal'] : date('Y-m-d');
    $sesi = isset($input['sesi']) && in_array($input['sesi'], ['masuk', 'istirahat', 'pulang']) ? $input['sesi'] : 'masuk';
    $absensi = isset($input['absensi']) ? $input['absensi'] : [];

    $active_year = get_active_academic_year();
    $year_id = $active_year['id'] ?? 0;

    if (empty($absensi)) {
        json_response(400, false, 'Data tidak lengkap.');
    }

    try {
        db()->beginTransaction();

        $sesiLabels = [
            'masuk' => 'Masuk',
            'istirahat' => 'Istirahat',
            'pulang' => 'Pulang'
        ];
        $sesiText = $sesiLabels[$sesi] ?? 'Harian';

        foreach ($absensi as $a) {
            $guru_id = (int)$a['guru_id'];
            $status = in_array($a['status'], ['H','S','I','A','T']) ? $a['status'] : 'H';
            $keterangan = isset($a['keterangan']) ? trim($a['keterangan']) : '';

            $stmt = db()->prepare("
                INSERT INTO acad_absensi_guru (guru_id, academic_year_id, tanggal, sesi, status, keterangan, dicatat_oleh)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan), dicatat_oleh = VALUES(dicatat_oleh)
            ");
            $stmt->execute([$guru_id, $year_id, $tanggal, $sesi, $status, $keterangan, $user['user_id']]);
        }

        db()->commit();
        json_response(200, true, "Absensi Guru {$sesiText} berhasil disimpan.");
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Rekapitulasi absensi guru per range tanggal
 */
function rekapAbsensiGuru($user) {
    try {
        $tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-01');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');
        $waktu_terlambat = get_setting('waktu_terlambat_guru', '07:15:00');

        // 1. Get all relevant teachers
        $stmtG = db()->query("SELECT id, kode_guru, nama_lengkap as nama FROM users WHERE role = 'guru' AND status = 1 ORDER BY nama_lengkap");
        $teachers = $stmtG->fetchAll();

        if (empty($teachers)) {
            json_response(200, true, 'Rekap absensi guru dimuat.', [
                'waktu_terlambat' => substr($waktu_terlambat, 0, 5),
                'tanggal_awal' => $tanggal_awal,
                'tanggal_akhir' => $tanggal_akhir,
                'rekap' => []
            ]);
        }

        $teacherIds = array_column($teachers, 'id');

        // 2. Fetch manual records from acad_absensi_guru for date range
        $placeholdersT = implode(',', array_fill(0, count($teacherIds), '?'));
        $stmtA = db()->prepare("
            SELECT guru_id, tanggal, status 
            FROM acad_absensi_guru 
            WHERE guru_id IN ($placeholdersT) 
              AND tanggal BETWEEN ? AND ? 
        ");
        $paramsA = array_merge($teacherIds, [$tanggal_awal, $tanggal_akhir]);
        $stmtA->execute($paramsA);
        
        $manualMap = []; // [guru_id][tanggal] = status
        while ($row = $stmtA->fetch()) {
            $manualMap[$row['guru_id']][$row['tanggal']] = $row['status'];
        }

        // 3. Fetch E-Absen logs for date range efficiently
        $stmtMap = db()->query("SELECT user_id, TRIM(LEADING '0' FROM mesin_pin) as clean_pin FROM absen_user_map");
        $userMap = [];
        $pins = [];
        while($m = $stmtMap->fetch()) {
             if (in_array($m['user_id'], $teacherIds)) {
                 $userMap[$m['user_id']] = $m['clean_pin'];
                 $pins[] = $m['clean_pin'];
             }
        }
        
        $stmtL = false;
        if (count($pins) > 0) {
            $placeholdersPins = implode(',', array_fill(0, count($pins), '?'));
            $stmtL = db()->prepare("
                SELECT TRIM(LEADING '0' FROM mesin_pin) COLLATE utf8mb4_unicode_ci as clean_pin,
                       DATE(waktu_absen) as tgl,
                       MIN(TIME(waktu_absen)) as jam_masuk
                FROM absen_logs 
                WHERE DATE(waktu_absen) BETWEEN ? AND ?
                  AND TRIM(LEADING '0' FROM mesin_pin) COLLATE utf8mb4_unicode_ci IN ($placeholdersPins)
                GROUP BY clean_pin, tgl
            ");
            $paramsL = array_merge([$tanggal_awal, $tanggal_akhir], $pins);
            $stmtL->execute($paramsL);
        }

        $eAbsenMap = []; // [clean_pin][tgl] = jam_masuk
        if ($stmtL) {
            while ($l = $stmtL->fetch()) {
                $eAbsenMap[$l['clean_pin']][$l['tgl']] = $l['jam_masuk'];
            }
        }

        // 4. Calculate attendance per teacher
        $rekap = [];
        $hari_efektif = get_hari_efektif($tanggal_awal, $tanggal_akhir);

        foreach ($teachers as $t) {
            $tid = $t['id'];

            $countH = 0; // Hadir tepat waktu
            $countT = 0; // Terlambat
            $countS = 0; // Sakit
            $countI = 0; // Izin
            $countA = 0; // Alpha

            $dates = [];
            if (isset($manualMap[$tid])) {
                foreach (array_keys($manualMap[$tid]) as $d) $dates[$d] = true;
            }
            $cleanPin = isset($userMap[$tid]) ? $userMap[$tid] : null;
            if ($cleanPin && isset($eAbsenMap[$cleanPin])) {
                foreach (array_keys($eAbsenMap[$cleanPin]) as $d) $dates[$d] = true;
            }

            foreach (array_keys($dates) as $d) {
                // Manual record takes precedence if set
                if (isset($manualMap[$tid][$d])) {
                    $st = $manualMap[$tid][$d];
                    if ($st === 'H') $countH++;
                    else if ($st === 'T') $countT++;
                    else if ($st === 'S') $countS++;
                    else if ($st === 'I') $countI++;
                    else if ($st === 'A') $countA++;
                } else if ($cleanPin && isset($eAbsenMap[$cleanPin][$d])) {
                    $jamMasuk = $eAbsenMap[$cleanPin][$d];
                    if ($jamMasuk <= $waktu_terlambat) {
                        $countH++;
                    } else {
                        $countT++;
                    }
                }
            }

            $totalHadir = $countH + $countT;
            $recordedDaysCount = $countH + $countT + $countS + $countI + $countA;
            $unrecordedDays = $hari_efektif - $recordedDaysCount;
            if ($unrecordedDays > 0) {
                $countA += $unrecordedDays;
            }
            
            $persentase = $hari_efektif > 0 ? round(($totalHadir / $hari_efektif) * 100, 1) : 0;
            if ($persentase > 100) $persentase = 100;

            $rekap[] = [
                'guru_id' => $tid,
                'kode_guru' => $t['kode_guru'],
                'nama' => $t['nama'],
                'hadir' => $countH,
                'terlambat' => $countT,
                'sakit' => $countS,
                'izin' => $countI,
                'alpha' => $countA,
                'total_hadir' => $totalHadir,
                'total_hari' => $hari_efektif,
                'persentase' => $persentase
            ];
        }

        json_response(200, true, 'Rekap absensi guru dimuat.', [
            'waktu_terlambat' => substr($waktu_terlambat, 0, 5),
            'tanggal_awal' => $tanggal_awal,
            'tanggal_akhir' => $tanggal_akhir,
            'hari_efektif' => $hari_efektif,
            'rekap' => $rekap
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get setting waktu absensi guru & batas kirim WA
 */
function getSettingsGuru($user) {
    json_response(200, true, 'Setting dimuat.', [
        'waktu_terlambat' => substr(get_setting('waktu_terlambat_guru', '06:30:00'), 0, 5),
        'waktu_istirahat_mulai' => substr(get_setting('waktu_istirahat_guru_mulai', '12:00:00'), 0, 5),
        'waktu_istirahat_selesai' => substr(get_setting('waktu_istirahat_guru_selesai', '13:00:00'), 0, 5),
        'waktu_pulang' => substr(get_setting('waktu_pulang_guru', '15:30:00'), 0, 5),
        'wa_cutoff_masuk' => substr(get_setting('wa_guru_cutoff_masuk', '06:30:00'), 0, 5),
        'wa_cutoff_pulang' => substr(get_setting('wa_guru_cutoff_pulang', '17:00:00'), 0, 5),
        'wa_mulai_pulang' => substr(get_setting('wa_guru_mulai_pulang', '13:00:00'), 0, 5)
    ]);
}

/**
 * Save setting waktu absensi guru & batas kirim WA
 */
function saveSettingsGuru($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    if ($user['role'] !== 'superadmin' && $user['role'] !== 'admin') {
        json_response(403, false, 'Hanya admin yang dapat mengedit setting.');
    }

    $input = get_input();
    
    // Check if the server mistakenly parsed JSON as form-urlencoded
    if (!isset($input['waktu_terlambat']) && !isset($input['waktu_istirahat_mulai']) && !isset($input['waktu_pulang'])) {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $input = array_merge($input, $json);
            }
        }
    }

    if (isset($input['waktu_terlambat'])) {
        $waktu = trim($input['waktu_terlambat']);
        if (strlen($waktu) === 5) $waktu .= ':00';
        upsert_setting('waktu_terlambat_guru', $waktu, 'text', 'Batas jam masuk / terlambat absensi guru');
    }

    if (isset($input['waktu_istirahat_mulai'])) {
        $waktu = trim($input['waktu_istirahat_mulai']);
        if (strlen($waktu) === 5) $waktu .= ':00';
        upsert_setting('waktu_istirahat_guru_mulai', $waktu, 'text', 'Jam mulai istirahat guru');
    }

    if (isset($input['waktu_istirahat_selesai'])) {
        $waktu = trim($input['waktu_istirahat_selesai']);
        if (strlen($waktu) === 5) $waktu .= ':00';
        upsert_setting('waktu_istirahat_guru_selesai', $waktu, 'text', 'Jam selesai istirahat guru');
    }

    if (isset($input['waktu_pulang'])) {
        $waktu = trim($input['waktu_pulang']);
        if (strlen($waktu) === 5) $waktu .= ':00';
        upsert_setting('waktu_pulang_guru', $waktu, 'text', 'Jam batas pulang guru');
    }

    if (isset($input['wa_cutoff_masuk'])) {
        $cutoffM = trim($input['wa_cutoff_masuk']);
        if (strlen($cutoffM) === 5) $cutoffM .= ':00';
        upsert_setting('wa_guru_cutoff_masuk', $cutoffM, 'text', 'Batas waktu kirim WA otomatis absen pagi');
    }

    if (isset($input['wa_cutoff_pulang'])) {
        $cutoffP = trim($input['wa_cutoff_pulang']);
        if (strlen($cutoffP) === 5) $cutoffP .= ':00';
        upsert_setting('wa_guru_cutoff_pulang', $cutoffP, 'text', 'Waktu kirim WA otomatis absen pulang');
    }

    if (isset($input['wa_mulai_pulang'])) {
        $mulaiP = trim($input['wa_mulai_pulang']);
        if (strlen($mulaiP) === 5) $mulaiP .= ':00';
        upsert_setting('wa_guru_mulai_pulang', $mulaiP, 'text', 'Jam mulai tap mesin ditampung sebagai absen pulang');
    }

    json_response(200, true, 'Pengaturan jam absensi guru berhasil disimpan.', [
        'waktu_terlambat' => substr(get_setting('waktu_terlambat_guru', '06:30:00'), 0, 5),
        'waktu_istirahat_mulai' => substr(get_setting('waktu_istirahat_guru_mulai', '12:00:00'), 0, 5),
        'waktu_istirahat_selesai' => substr(get_setting('waktu_istirahat_guru_selesai', '13:00:00'), 0, 5),
        'waktu_pulang' => substr(get_setting('waktu_pulang_guru', '15:30:00'), 0, 5),
        'wa_cutoff_masuk' => substr(get_setting('wa_guru_cutoff_masuk', '06:30:00'), 0, 5),
        'wa_cutoff_pulang' => substr(get_setting('wa_guru_cutoff_pulang', '17:00:00'), 0, 5),
        'wa_mulai_pulang' => substr(get_setting('wa_guru_mulai_pulang', '13:00:00'), 0, 5)
    ]);
}

/**
 * Send real-time / daily teacher attendance report to WA Group
 */
function sendWaGroupAbsensiGuru($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $tanggal = isset($input['tanggal']) ? $input['tanggal'] : date('Y-m-d');
    $tipe = isset($input['tipe']) ? $input['tipe'] : 'masuk';

    $res = sendWaGroupAbsensiGuruDirect($tanggal, $tipe);
    if ($res['success']) {
        json_response(200, true, $res['message']);
    } else {
        json_response(400, false, $res['message']);
    }
}
