<?php
/**
 * E-Curriculum Absensi Guru API
 * Manages teacher attendance integrated with E-Absen logs & manual overrides
 */
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
$action = isset($_GET['action']) ? $_GET['action'] : '';

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
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List absensi for all teachers on a given date (merging E-Absen logs and acad_absensi_guru)
 */
function listAbsensiGuru($user) {
    try {
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $waktu_terlambat = get_setting('waktu_terlambat_guru', '07:15:00');

        // Get all active teachers
        $stmtG = db()->query("SELECT id, kode_guru, nama_lengkap as nama FROM users WHERE role = 'guru' AND status = 1 ORDER BY nama_lengkap");
        $teachers = $stmtG->fetchAll();

        // Get existing manual absensi from acad_absensi_guru
        $stmtA = db()->prepare("
            SELECT guru_id, status, keterangan 
            FROM acad_absensi_guru 
            WHERE tanggal = ?
        ");
        $stmtA->execute([$tanggal]);
        $existingManual = [];
        while ($row = $stmtA->fetch()) {
            $existingManual[$row['guru_id']] = $row;
        }

        // Get E-Absen logs for this date
        $stmtLogs = db()->prepare("
            SELECT m.user_id, 
                   MIN(TIME(l.waktu_absen)) as jam_masuk
            FROM absen_user_map m
            JOIN absen_logs l ON TRIM(LEADING '0' FROM m.mesin_pin) COLLATE utf8mb4_unicode_ci = TRIM(LEADING '0' FROM l.mesin_pin) COLLATE utf8mb4_unicode_ci
            WHERE DATE(l.waktu_absen) = ? 
            GROUP BY m.user_id
        ");
        $stmtLogs->execute([$tanggal]);
        $eAbsenLogs = [];
        while ($l = $stmtLogs->fetch()) {
            $eAbsenLogs[$l['user_id']] = $l['jam_masuk'];
        }

        // Merge teacher list with E-Absen log and manual override
        $result = [];
        foreach ($teachers as $t) {
            $tid = $t['id'];
            $jamMasuk = isset($eAbsenLogs[$tid]) ? $eAbsenLogs[$tid] : null;

            // Determine default status based on E-Absen log if available
            $calculatedStatus = 'H';
            $scanInfo = 'Belum Absen Mesin';

            if ($jamMasuk !== null) {
                if ($jamMasuk <= $waktu_terlambat) {
                    $calculatedStatus = 'H';
                    $scanInfo = "Hadir (" . substr($jamMasuk, 0, 5) . ")";
                } else {
                    $calculatedStatus = 'T';
                    $scanInfo = "Terlambat (" . substr($jamMasuk, 0, 5) . ")";
                }
            }

            // Manual override takes precedence if recorded in acad_absensi_guru
            $finalStatus = isset($existingManual[$tid]) ? $existingManual[$tid]['status'] : ($jamMasuk !== null ? $calculatedStatus : 'H');
            $keterangan = isset($existingManual[$tid]) ? $existingManual[$tid]['keterangan'] : ($jamMasuk !== null ? "Fingerprint: $jamMasuk" : '');

            $result[] = [
                'guru_id' => $tid,
                'kode_guru' => $t['kode_guru'],
                'nama' => $t['nama'],
                'jam_masuk' => $jamMasuk,
                'scan_info' => $scanInfo,
                'status' => $finalStatus,
                'keterangan' => $keterangan
            ];
        }

        json_response(200, true, 'Data absensi guru dimuat.', [
            'waktu_terlambat' => substr($waktu_terlambat, 0, 5),
            'teachers' => $result
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Save/update absensi guru on a date
 */
function saveAbsensiGuru($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $tanggal = isset($input['tanggal']) ? $input['tanggal'] : date('Y-m-d');
    $absensi = isset($input['absensi']) ? $input['absensi'] : [];

    $active_year = get_active_academic_year();
    $year_id = $active_year['id'] ?? 0;

    if (empty($absensi)) {
        json_response(400, false, 'Data tidak lengkap.');
    }

    try {
        db()->beginTransaction();

        foreach ($absensi as $a) {
            $guru_id = (int)$a['guru_id'];
            $status = in_array($a['status'], ['H','S','I','A','T']) ? $a['status'] : 'H';
            $keterangan = isset($a['keterangan']) ? trim($a['keterangan']) : '';

            $stmt = db()->prepare("
                INSERT INTO acad_absensi_guru (guru_id, academic_year_id, tanggal, status, keterangan, dicatat_oleh)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan), dicatat_oleh = VALUES(dicatat_oleh)
            ");
            $stmt->execute([$guru_id, $year_id, $tanggal, $status, $keterangan, $user['user_id']]);
        }

        db()->commit();
        json_response(200, true, 'Absensi guru berhasil disimpan.');
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

        // 3. Fetch E-Absen logs for date range
        $stmtL = db()->prepare("
            SELECT m.user_id,
                   DATE(l.waktu_absen) as tgl,
                   MIN(TIME(l.waktu_absen)) as jam_masuk
            FROM absen_user_map m
            JOIN absen_logs l ON TRIM(LEADING '0' FROM m.mesin_pin) COLLATE utf8mb4_unicode_ci = TRIM(LEADING '0' FROM l.mesin_pin) COLLATE utf8mb4_unicode_ci
            WHERE DATE(l.waktu_absen) BETWEEN ? AND ?
              AND m.user_id IN ($placeholdersT)
            GROUP BY m.user_id, tgl
        ");
        $paramsL = array_merge([$tanggal_awal, $tanggal_akhir], $teacherIds);
        $stmtL->execute($paramsL);

        $eAbsenMap = []; // [user_id][tgl] = jam_masuk
        while ($l = $stmtL->fetch()) {
            $eAbsenMap[$l['user_id']][$l['tgl']] = $l['jam_masuk'];
        }

        // 4. Calculate attendance per teacher
        $rekap = [];
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
            if (isset($eAbsenMap[$tid])) {
                foreach (array_keys($eAbsenMap[$tid]) as $d) $dates[$d] = true;
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
                } else if (isset($eAbsenMap[$tid][$d])) {
                    $jamMasuk = $eAbsenMap[$tid][$d];
                    if ($jamMasuk <= $waktu_terlambat) {
                        $countH++;
                    } else {
                        $countT++;
                    }
                }
            }

            $totalHadir = $countH + $countT;
            $totalHariRecorded = count($dates);
            $persentase = $totalHariRecorded > 0 ? round(($totalHadir / $totalHariRecorded) * 100, 1) : 0;

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
                'total_hari' => $totalHariRecorded,
                'persentase' => $persentase
            ];
        }

        json_response(200, true, 'Rekap absensi guru dimuat.', [
            'waktu_terlambat' => substr($waktu_terlambat, 0, 5),
            'tanggal_awal' => $tanggal_awal,
            'tanggal_akhir' => $tanggal_akhir,
            'rekap' => $rekap
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get setting waktu terlambat guru
 */
function getSettingsGuru($user) {
    $waktu_terlambat = get_setting('waktu_terlambat_guru', '07:15:00');
    json_response(200, true, 'Setting dimuat.', [
        'waktu_terlambat' => substr($waktu_terlambat, 0, 5)
    ]);
}

/**
 * Save setting waktu terlambat guru
 */
function saveSettingsGuru($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    if ($user['role'] !== 'superadmin' && $user['role'] !== 'admin') {
        json_response(403, false, 'Hanya admin yang dapat mengedit setting.');
    }

    $input = get_input();
    $waktu = isset($input['waktu_terlambat']) ? trim($input['waktu_terlambat']) : '07:15';

    if (strlen($waktu) === 5) {
        $waktu .= ':00';
    }

    upsert_setting('waktu_terlambat_guru', $waktu, 'text', 'Batas jam terlambat absensi guru');
    json_response(200, true, 'Setting jam terlambat guru berhasil disimpan.');
}
