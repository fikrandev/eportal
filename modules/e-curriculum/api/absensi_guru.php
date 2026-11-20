<?php
/**
 * E-Curriculum Absensi Guru API
 * Manages teacher attendance integrated with E-Absen logs & manual overrides
 */
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/wa_group_helper.php';
require_once __DIR__ . '/hari_libur.php';

if (!defined('ACAD_LIB_ONLY')) {
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
        case 'export_excel':
            exportExcelAbsensiGuru($user);
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
}

/**
 * Resolve scheduled teaching days per active PTK (users.id) from E-Jadwal (sch_distribusi + sch_jadwal / sch_kesediaan).
 * Returns [user_id => ['Senin' => true, ...]] for PTK with teaching distribution, or null for non-teaching PTK (works all effective days).
 */
function getGuruScheduledDaysMap() {
    $map = [];
    try {
        $users = db()->query("SELECT id, username, kode_guru, nama_lengkap FROM users WHERE role = 'guru' AND status = 1")->fetchAll(PDO::FETCH_ASSOC);
        $schGurus = db()->query("SELECT id, kode_guru, singkatan, nama_guru FROM sch_guru")->fetchAll(PDO::FETCH_ASSOC);

        $byKode = [];
        $bySingkatan = [];
        $byNama = [];
        foreach ($schGurus as $sg) {
            $sgId = (int)$sg['id'];
            $k = strtolower(trim((string)($sg['kode_guru'] ?? '')));
            $s = strtolower(trim((string)($sg['singkatan'] ?? '')));
            $n = strtolower(trim((string)($sg['nama_guru'] ?? '')));
            if ($k !== '') $byKode[$k] = $sgId;
            if ($s !== '') $bySingkatan[$s] = $sgId;
            if ($n !== '') $byNama[$n] = $sgId;
        }

        // Teachers who have active distribution in E-Jadwal
        $distGurus = db()->query("SELECT DISTINCT guru_id FROM sch_distribusi WHERE jp > 0")->fetchAll(PDO::FETCH_COLUMN);
        $hasDistSet = array_flip(array_map('intval', $distGurus));

        // Actual timetable days from sch_jadwal
        $jadwalDays = [];
        $stmtJ = db()->query("
            SELECT DISTINCT d.guru_id, jb.hari
            FROM sch_jadwal j
            JOIN sch_distribusi d ON j.distribusi_id = d.id
            JOIN sch_jam_belajar jb ON j.jam_belajar_id = jb.id
        ");
        while ($row = $stmtJ->fetch(PDO::FETCH_ASSOC)) {
            $gid = (int)$row['guru_id'];
            $h = ucfirst(strtolower(trim((string)$row['hari'])));
            if ($gid > 0 && $h !== '') {
                $jadwalDays[$gid][$h] = true;
            }
        }

        // Fallback days from sch_kesediaan if a teacher has distribution but not yet plotted in sch_jadwal
        $kesediaanDays = [];
        $stmtK = db()->query("
            SELECT DISTINCT ks.guru_id, jb.hari
            FROM sch_kesediaan ks
            JOIN sch_jam_belajar jb ON ks.jam_belajar_id = jb.id
        ");
        while ($row = $stmtK->fetch(PDO::FETCH_ASSOC)) {
            $gid = (int)$row['guru_id'];
            $h = ucfirst(strtolower(trim((string)$row['hari'])));
            if ($gid > 0 && $h !== '') {
                $kesediaanDays[$gid][$h] = true;
            }
        }

        foreach ($users as $u) {
            $uid = (int)$u['id'];
            $uName = strtolower(trim((string)($u['username'] ?? '')));
            $uKode = strtolower(trim((string)($u['kode_guru'] ?? '')));
            $uNama = strtolower(trim((string)($u['nama_lengkap'] ?? '')));

            $schGuruId = null;
            if ($uName !== '' && isset($byKode[$uName])) {
                $schGuruId = $byKode[$uName];
            } elseif ($uKode !== '' && isset($bySingkatan[$uKode])) {
                $schGuruId = $bySingkatan[$uKode];
            } elseif ($uKode !== '' && isset($byKode[$uKode])) {
                $schGuruId = $byKode[$uKode];
            } elseif ($uNama !== '' && isset($byNama[$uNama])) {
                $schGuruId = $byNama[$uNama];
            }

            if ($schGuruId !== null && isset($hasDistSet[$schGuruId])) {
                if (!empty($jadwalDays[$schGuruId])) {
                    $map[$uid] = $jadwalDays[$schGuruId];
                } elseif (!empty($kesediaanDays[$schGuruId])) {
                    $map[$uid] = $kesediaanDays[$schGuruId];
                } else {
                    $map[$uid] = [];
                }
            } else {
                // Non-teaching PTK (TU, BK, Kepala Sekolah, dsb.): wajib hadir semua hari efektif
                $map[$uid] = null;
            }
        }
    } catch (Exception $e) {
        // Leave empty map on DB error so non-blocking
    }
    return $map;
}

/**
 * List absensi for all teachers on a given date (merging E-Absen logs and acad_absensi_guru for 3 sessions)
 */
function listAbsensiGuru($user) {
    try {
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $sesi = isset($_GET['sesi']) && in_array($_GET['sesi'], ['masuk', 'istirahat', 'pulang']) ? $_GET['sesi'] : 'masuk';

        $waktu_terlambat = get_setting('waktu_terlambat_guru', '06:30:00') ?: '06:30:00';
        $waktu_istirahat_mulai = get_setting('waktu_istirahat_guru_mulai', '12:00:00') ?: '12:00:00';
        $waktu_istirahat_selesai = get_setting('waktu_istirahat_guru_selesai', '13:00:00') ?: '13:00:00';
        $waktu_pulang = get_setting('waktu_pulang_guru', '15:30:00') ?: '15:30:00';
        $jam_mulai_pulang = get_setting('wa_guru_mulai_pulang', '13:00:00') ?: '13:00:00';

        if (strlen($waktu_terlambat) === 5) $waktu_terlambat .= ':00';
        if (strlen($waktu_istirahat_mulai) === 5) $waktu_istirahat_mulai .= ':00';
        if (strlen($waktu_istirahat_selesai) === 5) $waktu_istirahat_selesai .= ':00';
        if (strlen($waktu_pulang) === 5) $waktu_pulang .= ':00';
        if (strlen($jam_mulai_pulang) === 5) $jam_mulai_pulang .= ':00';

        // Get all active teachers
        $stmtG = db()->query("SELECT id, kode_guru, nama_lengkap as nama, COALESCE(NULLIF(TRIM(tupoksi), ''), '-') as tupoksi FROM users WHERE role = 'guru' AND status = 1 ORDER BY nama_lengkap");
        $teachers = $stmtG->fetchAll();

        $scheduledDaysMap = getGuruScheduledDaysMap();
        $dayMap = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $hariIndo = $dayMap[(int)date('N', strtotime($tanggal))] ?? '';

        // Get existing manual absensi from acad_absensi_guru for active session
        $stmtA = db()->prepare("
            SELECT guru_id, status, keterangan 
            FROM acad_absensi_guru 
            WHERE tanggal = ? AND sesi = ?
        ");
        $stmtA->execute([$tanggal, $sesi]);
        $existingManual = [];
        $resetToMachine = !empty($_GET['reset_to_machine']) && $_GET['reset_to_machine'] == '1';
        if (!$resetToMachine) {
            while ($row = $stmtA->fetch()) {
                $existingManual[$row['guru_id']] = $row;
            }
        }

        // Jika sesi istirahat atau pulang, cek apakah sesi masuk berstatus S, I, A, DNS, atau TA untuk auto-inheritance
        $sesiMasukInherited = [];
        if ($sesi !== 'masuk') {
            $stmtSM = db()->prepare("
                SELECT guru_id, status, keterangan 
                FROM acad_absensi_guru 
                WHERE tanggal = ? AND sesi = 'masuk' AND status IN ('S', 'I', 'A', 'DNS', 'D', 'TA')
            ");
            $stmtSM->execute([$tanggal]);
            while ($sm = $stmtSM->fetch()) {
                $sesiMasukInherited[$sm['guru_id']] = $sm;
            }
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

            // Determine default status based on active session & E-Jadwal distribution
            $isAutoTA = isset($scheduledDaysMap[$tid]) && is_array($scheduledDaysMap[$tid]) && !isset($scheduledDaysMap[$tid][$hariIndo]);
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
                } elseif ($isAutoTA) {
                    $calculatedStatus = 'TA';
                    $scanInfo = 'Tidak Ada Jadwal (TA)';
                } else {
                    $calculatedStatus = 'A';
                    $scanInfo = 'Belum Scan Mesin';
                }
            } else if ($sesi === 'istirahat') { // Absen Istirahat (tampilan jam saja)
                $jamScanSesi = $jamIstirahat;
                if ($jamIstirahat !== null) {
                    $calculatedStatus = 'H';
                    $scanInfo = substr($jamIstirahat, 0, 5);
                } elseif ($isAutoTA) {
                    $calculatedStatus = 'TA';
                    $scanInfo = 'TA';
                } else {
                    $calculatedStatus = 'A';
                    $scanInfo = 'Belum Scan';
                }
            } else if ($sesi === 'pulang') { // Absen Pulang (tampilan jam saja)
                $jamScanSesi = $jamPulang;
                if ($jamPulang !== null) {
                    $calculatedStatus = 'H';
                    $scanInfo = substr($jamPulang, 0, 5);
                } elseif ($isAutoTA) {
                    $calculatedStatus = 'TA';
                    $scanInfo = 'TA';
                } else {
                    $calculatedStatus = 'A';
                    $scanInfo = 'Belum Scan';
                }
            }

            // Manual override takes precedence if recorded in acad_absensi_guru
            // Jika sesi istirahat atau pulang dan belum diset manual, mewarisi status S/I/A/DNS/TA dari sesi masuk
            $inherited = ($sesi !== 'masuk' && !isset($existingManual[$tid]) && isset($sesiMasukInherited[$tid])) ? $sesiMasukInherited[$tid] : null;
            if (isset($existingManual[$tid])) {
                $manRow = $existingManual[$tid];
                $manSt = ($manRow['status'] === 'D') ? 'DNS' : $manRow['status'];
                $manKet = trim((string)($manRow['keterangan'] ?? ''));
                if ($sesi === 'masuk' && $manSt === 'H' && $calculatedStatus === 'T') {
                    $finalStatus = 'T';
                } elseif ($isAutoTA && $jamScanSesi === null && $manSt === 'A' && $manKet === '') {
                    $finalStatus = 'TA';
                } else {
                    $finalStatus = $manSt;
                }
                $keterangan = $manRow['keterangan'];
            } else {
                $finalStatus = $inherited ? (($inherited['status'] === 'D') ? 'DNS' : $inherited['status']) : $calculatedStatus;
                $keterangan = $inherited ? $inherited['keterangan'] : ($jamScanSesi !== null ? "Fingerprint: $jamScanSesi" : '');
            }

            $result[] = [
                'guru_id' => $tid,
                'kode_guru' => $t['kode_guru'],
                'nama' => $t['nama'],
                'tupoksi' => $t['tupoksi'],
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

        // Detect column names dynamically to guarantee 100% compatibility across MySQL schemas
        $colsAg = db()->query("SHOW COLUMNS FROM acad_absensi_guru")->fetchAll(PDO::FETCH_COLUMN);
        $hasAcademicYearId = in_array('academic_year_id', $colsAg);
        $hasYearId = in_array('year_id', $colsAg);

        $insertCols = ['guru_id'];
        $insertVals = ['?'];

        if ($hasAcademicYearId) {
            $insertCols[] = 'academic_year_id';
            $insertVals[] = '?';
        }
        if ($hasYearId) {
            $insertCols[] = 'year_id';
            $insertVals[] = '?';
        }

        $insertCols[] = 'tanggal';
        $insertVals[] = '?';
        $insertCols[] = 'sesi';
        $insertVals[] = '?';
        $insertCols[] = 'status';
        $insertVals[] = '?';
        $insertCols[] = 'keterangan';
        $insertVals[] = '?';
        $insertCols[] = 'dicatat_oleh';
        $insertVals[] = '?';

        $sql = "
            INSERT INTO acad_absensi_guru (" . implode(', ', $insertCols) . ")
            VALUES (" . implode(', ', $insertVals) . ")
            ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan), dicatat_oleh = VALUES(dicatat_oleh)
        ";
        $stmt = db()->prepare($sql);

        $executeRow = function($guru_id, $targetSesi, $status, $keterangan) use ($stmt, $hasAcademicYearId, $hasYearId, $year_id, $tanggal, $user) {
            $params = [$guru_id];
            if ($hasAcademicYearId) $params[] = $year_id;
            if ($hasYearId) $params[] = $year_id;
            $params[] = $tanggal;
            $params[] = $targetSesi;
            $params[] = $status;
            $params[] = $keterangan;
            $params[] = $user['user_id'];
            $stmt->execute($params);
        };

        foreach ($absensi as $a) {
            $guru_id = (int)$a['guru_id'];
            $rawStatus = strtoupper(trim((string)($a['status'] ?? 'H')));
            if ($rawStatus === 'D') $rawStatus = 'DNS';
            $status = in_array($rawStatus, ['H','S','I','A','T','TA','DNS'], true) ? $rawStatus : 'H';
            $keterangan = isset($a['keterangan']) ? trim($a['keterangan']) : '';

            $executeRow($guru_id, $sesi, $status, $keterangan);

            // Jika S / I / A / DNS / TA pada absen kehadiran (sesi masuk), maka sesi istirahat dan pulang otomatis terisi sama
            if ($sesi === 'masuk' && in_array($status, ['S', 'I', 'A', 'DNS', 'TA'], true)) {
                $executeRow($guru_id, 'istirahat', $status, $keterangan);
                $executeRow($guru_id, 'pulang', $status, $keterangan);
            }
        }

        db()->commit();
        json_response(200, true, "Absensi Guru {$sesiText} berhasil disimpan.");
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Helper untuk mengambil seluruh data rekapitulasi absensi guru
 */
function getRekapAbsensiGuruData($tanggal_awal, $tanggal_akhir) {
    $waktu_terlambat = get_setting('waktu_terlambat_guru', '06:30:00') ?: '06:30:00';
    $waktu_istirahat_mulai = get_setting('waktu_istirahat_guru_mulai', '10:00:00') ?: '10:00:00';
    $waktu_istirahat_selesai = get_setting('waktu_istirahat_guru_selesai', '10:15:00') ?: '10:15:00';
    $waktu_pulang = get_setting('waktu_pulang_guru', '15:30:00') ?: '15:30:00';
    $wa_mulai_pulang = get_setting('wa_guru_mulai_pulang', '13:00:00') ?: '13:00:00';

    if (strlen($waktu_terlambat) === 5) $waktu_terlambat .= ':00';
    if (strlen($waktu_istirahat_mulai) === 5) $waktu_istirahat_mulai .= ':00';
    if (strlen($waktu_istirahat_selesai) === 5) $waktu_istirahat_selesai .= ':00';
    if (strlen($waktu_pulang) === 5) $waktu_pulang .= ':00';
    if (strlen($wa_mulai_pulang) === 5) $wa_mulai_pulang .= ':00';

    $nama_sekolah = get_setting('nama_sekolah', 'SMA WACHID HASYIM 1 SURABAYA') ?: 'SMA WACHID HASYIM 1 SURABAYA';

    // Format bulan text
    $bulanIndo = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    $m1 = date('m', strtotime($tanggal_awal));
    $y1 = date('Y', strtotime($tanggal_awal));
    $m2 = date('m', strtotime($tanggal_akhir));
    $y2 = date('Y', strtotime($tanggal_akhir));

    if ($m1 === $m2 && $y1 === $y2) {
        $bulan_text = ($bulanIndo[$m1] ?? '') . ' ' . $y1;
    } else {
        $bulan_text = date('d/m/Y', strtotime($tanggal_awal)) . ' s/d ' . date('d/m/Y', strtotime($tanggal_akhir));
    }

    // Ambil hari libur dari database
    $holidays = [];
    try {
        $active_year = get_active_academic_year();
        $year_id = (int)($active_year['id'] ?? 0);
        $stmtH = db()->prepare("SELECT tanggal FROM acad_hari_libur WHERE academic_year_id = ? AND tanggal BETWEEN ? AND ?");
        $stmtH->execute([$year_id, $tanggal_awal, $tanggal_akhir]);
        $holidays = $stmtH->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception $e) {}

    // Susun daftar hari efektif
    $effectiveDates = [];
    $current_ts = strtotime($tanggal_awal);
    $end_ts = strtotime($tanggal_akhir);

    while ($current_ts <= $end_ts) {
        $dayOfWeek = (int)date('N', $current_ts); // 1 = Senin, 7 = Minggu
        $dateStr = date('Y-m-d', $current_ts);
        if ($dayOfWeek < 6 && !in_array($dateStr, $holidays)) {
            $effectiveDates[] = [
                'date' => $dateStr,
                'day' => (int)date('j', $current_ts),
                'day_name' => date('D', $current_ts)
            ];
        }
        $current_ts = strtotime('+1 day', $current_ts);
    }

    $hari_efektif = count($effectiveDates);

    // 1. Get all relevant teachers
    $stmtG = db()->query("SELECT id, kode_guru, nama_lengkap as nama, nik, COALESCE(NULLIF(TRIM(tupoksi), ''), '-') as tupoksi FROM users WHERE role = 'guru' AND status = 1 ORDER BY nama_lengkap");
    $teachers = $stmtG->fetchAll(PDO::FETCH_ASSOC);

    if (empty($teachers)) {
        return [
            'nama_sekolah' => $nama_sekolah,
            'bulan' => $bulan_text,
            'waktu_terlambat' => substr($waktu_terlambat, 0, 5),
            'tanggal_awal' => $tanggal_awal,
            'tanggal_akhir' => $tanggal_akhir,
            'hari_efektif' => $hari_efektif,
            'effective_dates' => $effectiveDates,
            'rekap' => []
        ];
    }

    $scheduledDaysMap = getGuruScheduledDaysMap();
    $dayMapIndo = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    $teacherIds = array_column($teachers, 'id');

    // 1. Ambil mapping PIN mesin dari absen_user_map
    $userMap = [];
    $placeholdersT = implode(',', array_fill(0, count($teacherIds), '?'));
    try {
        $stmtMap = db()->prepare("SELECT user_id, TRIM(LEADING '0' FROM mesin_pin) as clean_pin FROM absen_user_map WHERE user_id IN ($placeholdersT)");
        $stmtMap->execute($teacherIds);
        while ($m = $stmtMap->fetch(PDO::FETCH_ASSOC)) {
            $userMap[$m['user_id']] = $m['clean_pin'];
        }
    } catch (Exception $e) {}

    // Kumpulkan daftar PIN mesin untuk query log fingerprint (dan NIK jika ada)
    $pinList = array_unique(array_filter(array_values($userMap)));
    foreach ($teachers as $t) {
        if (!empty($t['nik'])) {
            $cleanNik = ltrim($t['nik'], '0');
            if ($cleanNik) $pinList[] = $cleanNik;
        }
    }
    $pinList = array_values(array_unique(array_filter($pinList)));

    // 2. Fetch manual records from acad_absensi_guru
    $manualMap = []; 
    try {
        $stmtA = db()->prepare("
            SELECT guru_id, tanggal, sesi, status, keterangan 
            FROM acad_absensi_guru 
            WHERE guru_id IN ($placeholdersT) 
              AND tanggal BETWEEN ? AND ? 
            ORDER BY tanggal ASC, id ASC
        ");
        $paramsA = array_merge($teacherIds, [$tanggal_awal, $tanggal_akhir]);
        $stmtA->execute($paramsA);
        
        $sesiMap = ['masuk' => 1, 'istirahat' => 2, 'pulang' => 3];
        while ($row = $stmtA->fetch(PDO::FETCH_ASSOC)) {
            $gid = $row['guru_id'];
            $tgl = $row['tanggal'];
            $sesiStr = strtolower(trim($row['sesi']));
            $sNum = $sesiMap[$sesiStr] ?? ((int)$row['sesi'] ?: 1);
            $manualMap[$gid][$tgl][$sNum] = [
                'status' => $row['status'],
                'keterangan' => trim((string)($row['keterangan'] ?? ''))
            ];
        }
    } catch (Exception $e) {}

    // 3. Fetch E-Absen logs partitioned into 3 sessions
    $eAbsenMap = [];
    if (count($pinList) > 0) {
        try {
            $placeholdersPin = implode(',', array_fill(0, count($pinList), '?'));
            $tanggal_akhir_full = $tanggal_akhir . ' 23:59:59';
            $stmtL = db()->prepare("
                SELECT TRIM(LEADING '0' FROM mesin_pin) as clean_pin,
                       DATE(waktu_absen) as tgl,
                       MIN(CASE WHEN TIME(waktu_absen) < ? THEN TIME(waktu_absen) END) as jam_masuk,
                       MIN(CASE WHEN TIME(waktu_absen) >= ? AND TIME(waktu_absen) < ? THEN TIME(waktu_absen) END) as jam_istirahat,
                       MAX(CASE WHEN TIME(waktu_absen) >= ? THEN TIME(waktu_absen) END) as jam_pulang
                FROM absen_logs
                WHERE waktu_absen BETWEEN ? AND ?
                  AND TRIM(LEADING '0' FROM mesin_pin) IN ($placeholdersPin)
                GROUP BY clean_pin, tgl
            ");
            $paramsL = array_merge([$waktu_istirahat_mulai, $waktu_istirahat_mulai, $wa_mulai_pulang, $wa_mulai_pulang, $tanggal_awal, $tanggal_akhir_full], $pinList);
            $stmtL->execute($paramsL);
            while ($l = $stmtL->fetch(PDO::FETCH_ASSOC)) {
                $eAbsenMap[$l['clean_pin']][$l['tgl']] = $l;
            }
        } catch (Exception $e) {}
    }

    // 4. Calculate attendance per teacher
    $rekap = [];
    $waktu_terlambat_short = substr($waktu_terlambat, 0, 5);

    foreach ($teachers as $t) {
        $tid = $t['id'];
        $cleanPin = $userMap[$tid] ?? null;
        $cleanNik = ltrim($t['nik'] ?? '', '0');
        $logKey = ($cleanPin && isset($eAbsenMap[$cleanPin])) ? $cleanPin : (($cleanNik && isset($eAbsenMap[$cleanNik])) ? $cleanNik : null);

        $daily = [];
        $totA = 0; 
        $totB = 0; 
        $totC = 0;

        $countH = 0; 
        $countT = 0; 
        $countS = 0; 
        $countI = 0; 
        $countA = 0;
        $countTA = 0;
        $countDNS = 0;

        foreach ($effectiveDates as $ed) {
            $d = $ed['date'];
            $hariIndo = $dayMapIndo[(int)date('N', strtotime($d))] ?? '';
            $isAutoTA = isset($scheduledDaysMap[$tid]) && is_array($scheduledDaysMap[$tid]) && !isset($scheduledDaysMap[$tid][$hariIndo]);

            $manual = $manualMap[$tid][$d] ?? [];
            $log = $logKey ? ($eAbsenMap[$logKey][$d] ?? null) : null;
            $calcA = ($log && !empty($log['jam_masuk'])) ? ((substr($log['jam_masuk'], 0, 5) <= $waktu_terlambat_short) ? 'H' : 'T') : null;

            // Sesi 1 (A: Datang)
            $stA = '';
            if (isset($manual[1])) {
                $m1St = $manual[1]['status'];
                $m1Ket = $manual[1]['keterangan'] ?? '';
                if ($m1St === 'H' && $calcA === 'T') {
                    $stA = 'T';
                } elseif ($isAutoTA && !$calcA && $m1St === 'A' && $m1Ket === '') {
                    $stA = 'TA';
                } else {
                    $stA = $m1St;
                }
            } elseif ($calcA) {
                $stA = $calcA;
            } elseif ($isAutoTA) {
                $stA = 'TA';
            } else {
                $m2St = isset($manual[2]) ? $manual[2]['status'] : null;
                $m3St = isset($manual[3]) ? $manual[3]['status'] : null;
                if ($m2St && in_array($m2St, ['S','I','A','TA','DNS','D'], true)) {
                    $stA = $m2St;
                } elseif ($m3St && in_array($m3St, ['S','I','A','TA','DNS','D'], true)) {
                    $stA = $m3St;
                }
            }

            if ($stA === 'DNS') $stA = 'D';

            // Sesi 2 (B: Istirahat) & Sesi 3 (C: Pulang)
            if (in_array($stA, ['TA', 'S', 'I', 'A', 'D'], true)) {
                $stB = $stA;
                $stC = $stA;
            } else {
                // Sesi 2 (B: Istirahat)
                $stB = '';
                if (isset($manual[2])) {
                    $m2St = ($manual[2]['status'] === 'DNS') ? 'D' : $manual[2]['status'];
                    $m2Ket = $manual[2]['keterangan'] ?? '';
                    if (in_array($stA, ['H', 'T'], true) && $m2St === 'A' && $m2Ket === '' && empty($log['jam_istirahat'])) {
                        $stB = '';
                    } else {
                        $stB = $m2St;
                    }
                } elseif ($log && !empty($log['jam_istirahat'])) {
                    $stB = 'H';
                }

                // Sesi 3 (C: Pulang)
                $stC = '';
                if (isset($manual[3])) {
                    $m3St = ($manual[3]['status'] === 'DNS') ? 'D' : $manual[3]['status'];
                    $m3Ket = $manual[3]['keterangan'] ?? '';
                    if (in_array($stA, ['H', 'T'], true) && $m3St === 'A' && $m3Ket === '' && empty($log['jam_pulang'])) {
                        $stC = '';
                    } else {
                        $stC = $m3St;
                    }
                } elseif ($log && !empty($log['jam_pulang'])) {
                    $stC = 'H';
                }
            }

            $daily[$d] = [
                'a' => $stA,
                'b' => $stB,
                'c' => $stC
            ];

            if ($stA !== '') $totA++;
            if ($stB !== '') $totB++;
            if ($stC !== '') $totC++;

            if ($stA === 'TA') {
                $countTA++;
            } else {
                if ($stA === 'T') {
                    $countT++;
                }
                if ($stA === 'S') {
                    $countS++;
                } elseif ($stA === 'I') {
                    $countI++;
                } elseif ($stA === 'A') {
                    $countA++;
                } elseif ($stA === 'D') {
                    $countDNS++;
                }
                if (in_array($stA, ['H', 'T', 'D'], true) && in_array($stC, ['H', 'T', 'D'], true)) {
                    $countH++;
                }
            }
        }

        $wh = max(0, $hari_efektif - $countTA);
        $th = $countS + $countI + $countA;
        $persentase = $wh > 0 ? round(($countH / $wh) * 100) : 0;
        if ($persentase > 100) $persentase = 100;

        $rekap[] = [
            'guru_id' => $tid,
            'kode_guru' => $t['kode_guru'],
            'nik' => $t['nik'] ?: '-',
            'nama' => $t['nama'],
            'tupoksi' => $t['tupoksi'],
            'jenis_kelamin' => '-',
            'daily' => $daily,
            'tot_a' => $totA,
            'tot_b' => $totB,
            'tot_c' => $totC,
            'ta' => $countTA,
            'wh' => $wh,
            'hadir' => $countH,
            'terlambat' => $countT,
            'sakit' => $countS,
            'izin' => $countI,
            'alpha' => $countA,
            'dns' => $countDNS,
            'th' => $th,
            'total_hadir' => $countH,
            'total_hari' => $wh,
            'persentase' => $persentase
        ];
    }

    return [
        'nama_sekolah' => $nama_sekolah,
        'bulan' => $bulan_text,
        'waktu_terlambat' => substr($waktu_terlambat, 0, 5),
        'tanggal_awal' => $tanggal_awal,
        'tanggal_akhir' => $tanggal_akhir,
        'hari_efektif' => $hari_efektif,
        'effective_dates' => $effectiveDates,
        'rekap' => $rekap
    ];
}

/**
 * Rekapitulasi absensi guru per range tanggal (JSON response untuk SPA)
 */
function rekapAbsensiGuru($user) {
    try {
        $tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-01');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');

        $data = getRekapAbsensiGuruData($tanggal_awal, $tanggal_akhir);
        json_response(200, true, 'Rekap absensi guru dimuat.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Direct Excel Export Guru (.xls HTML stream)
 */
function exportExcelAbsensiGuru($user) {
    try {
        $tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-01');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');
        $mode = isset($_GET['mode']) ? $_GET['mode'] : 'all';

        $data = getRekapAbsensiGuruData($tanggal_awal, $tanggal_akhir);
        $rekap = $data['rekap'] ?? [];
        $effectiveDates = $data['effective_dates'] ?? [];
        $numDates = count($effectiveDates);

        $filename = "Rekap_Kehadiran_Guru_{$tanggal_awal}_sd_{$tanggal_akhir}.xls";

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF";
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>";
        echo "<style>
            body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; color: #000; }
            table { border-collapse: collapse; margin-bottom: 25px; }
            th, td { border: 1px solid #777; padding: 4px 6px; font-size: 10pt; }
            th { text-align: center; vertical-align: middle; }
            .th-total { background-color: #D9EAD3; font-weight: bold; }
            .th-keh { background-color: #FFF2CC; font-weight: bold; }
            .th-date { background-color: #FCE5CD; font-weight: bold; }
            .th-sesi { background-color: #FFF2CC; font-size: 9pt; }
            .center { text-align: center; }
            .bold { font-weight: bold; }
            .title-h1 { font-size: 14pt; font-weight: bold; border: none; }
            .title-h2 { font-size: 12pt; font-weight: bold; border: none; }
            .no-border { border: none; }
            .legend-title { font-weight: bold; font-size: 10pt; border: none; }
        </style></head><body>";

        if ($mode === 'detail' || $mode === 'all') {
            $totalCols = 3 + ($numDates * 3) + 13;
            echo "<table>";
            echo "<tr><td class='title-h1' colspan='{$totalCols}'>REKAPITULASI KEHADIRAN PENDIDIK DAN TENAGA KEPENDIDIKAN</td></tr>";
            echo "<tr><td class='title-h2' colspan='{$totalCols}'>" . htmlspecialchars(strtoupper($data['nama_sekolah'])) . "</td></tr>";
            echo "<tr><td class='no-border' colspan='{$totalCols}'></td></tr>";
            
            echo "<tr>";
            echo "<td class='no-border bold' colspan='2'>Bulan : " . htmlspecialchars($data['bulan']) . "</td>";
            echo "<td class='no-border' colspan='" . max(1, 1 + ($numDates * 3) + 4) . "'></td>";
            echo "<td class='no-border bold'>A</td><td class='no-border'>:</td><td class='no-border' colspan='6'>Absen datang</td>";
            echo "</tr>";

            echo "<tr>";
            echo "<td class='no-border' colspan='" . (3 + ($numDates * 3) + 4) . "'></td>";
            echo "<td class='no-border bold'>B</td><td class='no-border'>:</td><td class='no-border' colspan='6'>Absen setelah istirahat</td>";
            echo "</tr>";

            echo "<tr>";
            echo "<td class='no-border' colspan='" . (3 + ($numDates * 3) + 4) . "'></td>";
            echo "<td class='no-border bold'>C</td><td class='no-border'>:</td><td class='no-border' colspan='6'>Absen pulang</td>";
            echo "</tr>";

            echo "<tr><td class='no-border' colspan='{$totalCols}'></td></tr>";

            echo "<tr>";
            echo "<th rowspan='2' style='width:35px;'>NO</th>";
            echo "<th rowspan='2' style='width:240px;'>NAMA PTK</th>";
            echo "<th rowspan='2' style='width:140px;'>TUPOKSI</th>";
            foreach ($effectiveDates as $ed) {
                echo "<th colspan='3' class='th-date'>" . $ed['day'] . "</th>";
            }
            echo "<th colspan='3' class='th-total'>TOTAL</th>";
            echo "<th colspan='10' class='th-keh'>KEHADIRAN</th>";
            echo "</tr>";

            echo "<tr>";
            foreach ($effectiveDates as $ed) {
                echo "<th class='th-sesi'>A</th><th class='th-sesi'>B</th><th class='th-sesi'>C</th>";
            }
            echo "<th class='th-total'>A</th><th class='th-total'>B</th><th class='th-total'>C</th>";
            echo "<th class='th-keh'>TA</th><th class='th-keh'>WH</th><th class='th-keh'>H</th><th class='th-keh'>T</th><th class='th-keh'>S</th><th class='th-keh'>I</th><th class='th-keh'>A</th><th class='th-keh'>DNS</th><th class='th-keh'>TH</th><th class='th-keh'>%</th>";
            echo "</tr>";

            foreach ($rekap as $idx => $r) {
                echo "<tr>";
                echo "<td class='center'>" . ($idx + 1) . "</td>";
                echo "<td>" . htmlspecialchars($r['nama'] ?? '') . "</td>";
                echo "<td class='center'>" . htmlspecialchars($r['tupoksi'] ?? '-') . "</td>";

                foreach ($effectiveDates as $ed) {
                    $d = $r['daily'][$ed['date']] ?? [];
                    echo "<td class='center'>" . htmlspecialchars($d['a'] ?? '') . "</td>";
                    echo "<td class='center'>" . htmlspecialchars($d['b'] ?? '') . "</td>";
                    echo "<td class='center'>" . htmlspecialchars($d['c'] ?? '') . "</td>";
                }

                echo "<td class='center bold'>" . (int)($r['tot_a'] ?? 0) . "</td>";
                echo "<td class='center bold'>" . (int)($r['tot_b'] ?? 0) . "</td>";
                echo "<td class='center bold'>" . (int)($r['tot_c'] ?? 0) . "</td>";
                echo "<td class='center'>" . (int)($r['ta'] ?? 0) . "</td>";
                echo "<td class='center bold'>" . (int)($r['wh'] ?? 0) . "</td>";
                echo "<td class='center bold'>" . (int)($r['hadir'] ?? 0) . "</td>";
                echo "<td class='center'>" . (!empty($r['terlambat']) ? (int)$r['terlambat'] : '-') . "</td>";
                echo "<td class='center'>" . (!empty($r['sakit']) ? (int)$r['sakit'] : '-') . "</td>";
                echo "<td class='center'>" . (!empty($r['izin']) ? (int)$r['izin'] : '-') . "</td>";
                echo "<td class='center'>" . (!empty($r['alpha']) ? (int)$r['alpha'] : '-') . "</td>";
                echo "<td class='center'>" . (!empty($r['dns']) ? (int)$r['dns'] : '-') . "</td>";
                echo "<td class='center bold'>" . (int)($r['th'] ?? 0) . "</td>";
                echo "<td class='center bold'>" . (int)($r['persentase'] ?? 0) . "</td>";
                echo "</tr>";
            }

            echo "<tr><td class='no-border' colspan='{$totalCols}'></td></tr>";
            echo "<tr><td class='no-border legend-title' colspan='3'>Keterangan Singkatan :</td><td class='no-border legend-title' colspan='12'>Ketentuan Perhitungan</td></tr>";
            echo "<tr><td class='no-border bold'>WH</td><td class='no-border' colspan='2'>WAJIB HADIR</td><td class='no-border bold' colspan='2'>TOTAL (A, B, C)</td><td class='no-border' colspan='10'>Menghitung apakah absen datang, istirahat, dan pulang terisi. Jika terisi dengan variabel apapun (H, S, I, A, T, DNS), maka akan terhitung.</td></tr>";
            echo "<tr><td class='no-border bold'>H</td><td class='no-border' colspan='2'>HADIR</td><td class='no-border bold' colspan='2'>TA</td><td class='no-border' colspan='10'>Terisi otomatis sesuai dengan DISTRIBUSI yang telah di tentukan pada e-jadwal</td></tr>";
            echo "<tr><td class='no-border bold'>T</td><td class='no-border' colspan='2'>TERLAMBAT</td><td class='no-border bold' colspan='2'>WH</td><td class='no-border' colspan='10'>Semua hari efektif pada bulan tersebut dikurangi dengan yang ada status TA (selama tidak ditentukan libur).</td></tr>";
            echo "<tr><td class='no-border bold'>S</td><td class='no-border' colspan='2'>SAKIT</td><td class='no-border bold' colspan='2'>H</td><td class='no-border' colspan='10'>Dihitung jika A dan C terisi T, H, atau d</td></tr>";
            echo "<tr><td class='no-border bold'>I</td><td class='no-border' colspan='2'>IZIN</td><td class='no-border bold' colspan='2'>T</td><td class='no-border' colspan='10'>Dihitung jika A terisi T</td></tr>";
            echo "<tr><td class='no-border bold'>A</td><td class='no-border' colspan='2'>ALPHA</td><td class='no-border bold' colspan='2'>S / I / A / DNS</td><td class='no-border' colspan='10'>Dihitung jika A terisi S / I / A / D, B dan C terisi otomatis sama</td></tr>";
            echo "<tr><td class='no-border bold'>D</td><td class='no-border' colspan='2'>DNS</td><td class='no-border bold' colspan='2'>TH</td><td class='no-border' colspan='10'>Total Tidak Hadir (S + I + A)</td></tr>";
            echo "<tr><td class='no-border bold'>TOT</td><td class='no-border' colspan='2'>TOTAL</td><td class='no-border bold' colspan='2'>%</td><td class='no-border' colspan='10'>(Hadir / Wajib Hadir) * 100%</td></tr>";
            echo "</table>";
        }

        if ($mode === 'all' || $mode === 'ringkasan') {
            if ($mode === 'all') {
                echo "<br><br><hr style='border:1px dashed #ccc;'><br><br>";
            }
            echo "<table>";
            echo "<tr><td class='title-h1' colspan='16'>REKAPITULASI KEHADIRAN PENDIDIK DAN TENAGA KEPENDIDIKAN (RINGKASAN)</td></tr>";
            echo "<tr><td class='title-h2' colspan='16'>" . htmlspecialchars(strtoupper($data['nama_sekolah'])) . "</td></tr>";
            echo "<tr><td class='no-border' colspan='16'></td></tr>";
            echo "<tr><td class='no-border bold' colspan='16'>Bulan : " . htmlspecialchars($data['bulan']) . "</td></tr>";
            echo "<tr><td class='no-border' colspan='16'></td></tr>";

            echo "<tr>";
            echo "<th rowspan='2' style='width:35px;'>NO</th>";
            echo "<th rowspan='2' style='width:240px;'>NAMA PTK</th>";
            echo "<th rowspan='2' style='width:140px;'>TUPOKSI</th>";
            echo "<th colspan='3' class='th-total'>TOTAL</th>";
            echo "<th colspan='10' class='th-keh'>KEHADIRAN</th>";
            echo "</tr>";

            echo "<tr>";
            echo "<th class='th-total'>A</th><th class='th-total'>B</th><th class='th-total'>C</th>";
            echo "<th class='th-keh'>TA</th><th class='th-keh'>WH</th><th class='th-keh'>H</th><th class='th-keh'>T</th><th class='th-keh'>S</th><th class='th-keh'>I</th><th class='th-keh'>A</th><th class='th-keh'>DNS</th><th class='th-keh'>TH</th><th class='th-keh'>%</th>";
            echo "</tr>";

            foreach ($rekap as $idx => $r) {
                echo "<tr>";
                echo "<td class='center'>" . ($idx + 1) . "</td>";
                echo "<td>" . htmlspecialchars($r['nama'] ?? '') . "</td>";
                echo "<td class='center'>" . htmlspecialchars($r['tupoksi'] ?? '-') . "</td>";
                echo "<td class='center bold'>" . (int)($r['tot_a'] ?? 0) . "</td>";
                echo "<td class='center bold'>" . (int)($r['tot_b'] ?? 0) . "</td>";
                echo "<td class='center bold'>" . (int)($r['tot_c'] ?? 0) . "</td>";
                echo "<td class='center'>" . (int)($r['ta'] ?? 0) . "</td>";
                echo "<td class='center bold'>" . (int)($r['wh'] ?? 0) . "</td>";
                echo "<td class='center bold'>" . (int)($r['hadir'] ?? 0) . "</td>";
                echo "<td class='center'>" . (!empty($r['terlambat']) ? (int)$r['terlambat'] : '-') . "</td>";
                echo "<td class='center'>" . (!empty($r['sakit']) ? (int)$r['sakit'] : '-') . "</td>";
                echo "<td class='center'>" . (!empty($r['izin']) ? (int)$r['izin'] : '-') . "</td>";
                echo "<td class='center'>" . (!empty($r['alpha']) ? (int)$r['alpha'] : '-') . "</td>";
                echo "<td class='center'>" . (!empty($r['dns']) ? (int)$r['dns'] : '-') . "</td>";
                echo "<td class='center bold'>" . (int)($r['th'] ?? 0) . "</td>";
                echo "<td class='center bold'>" . (int)($r['persentase'] ?? 0) . "</td>";
                echo "</tr>";
            }

            echo "<tr><td class='no-border' colspan='16'></td></tr>";
            echo "<tr><td class='no-border legend-title' colspan='16'>Keterangan Singkatan :</td></tr>";
            echo "<tr><td class='no-border bold'>WH</td><td class='no-border' colspan='15'>WAJIB HADIR</td></tr>";
            echo "<tr><td class='no-border bold'>H</td><td class='no-border' colspan='15'>HADIR</td></tr>";
            echo "<tr><td class='no-border bold'>T</td><td class='no-border' colspan='15'>TERLAMBAT</td></tr>";
            echo "<tr><td class='no-border bold'>S</td><td class='no-border' colspan='15'>SAKIT</td></tr>";
            echo "<tr><td class='no-border bold'>I</td><td class='no-border' colspan='15'>IZIN</td></tr>";
            echo "<tr><td class='no-border bold'>A</td><td class='no-border' colspan='15'>ALPHA</td></tr>";
            echo "<tr><td class='no-border bold'>D</td><td class='no-border' colspan='15'>DNS</td></tr>";
            echo "<tr><td class='no-border bold'>TOT</td><td class='no-border' colspan='15'>TOTAL</td></tr>";
            echo "</table>";
        }

        echo "</body></html>";
        exit;
    } catch (PDOException $e) {
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Export Error: ' . $e->getMessage();
        exit;
    }
}


/**
 * Get setting waktu absensi guru & batas kirim WA
 */
function getSettingsGuru($user) {
    json_response(200, true, 'Setting dimuat.', [
        'waktu_terlambat' => substr(get_setting('waktu_terlambat_guru', '06:30:00') ?: '06:30:00', 0, 5),
        'waktu_istirahat_mulai' => substr(get_setting('waktu_istirahat_guru_mulai', '12:00:00') ?: '12:00:00', 0, 5),
        'waktu_istirahat_selesai' => substr(get_setting('waktu_istirahat_guru_selesai', '13:00:00') ?: '13:00:00', 0, 5),
        'waktu_pulang' => substr(get_setting('waktu_pulang_guru', '15:30:00') ?: '15:30:00', 0, 5),
        'wa_cutoff_masuk' => substr(get_setting('wa_guru_cutoff_masuk', '06:30:00') ?: '06:30:00', 0, 5),
        'wa_cutoff_pulang' => substr(get_setting('wa_guru_cutoff_pulang', '17:00:00') ?: '17:00:00', 0, 5),
        'wa_mulai_pulang' => substr(get_setting('wa_guru_mulai_pulang', '13:00:00') ?: '13:00:00', 0, 5)
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
        'waktu_terlambat' => substr(get_setting('waktu_terlambat_guru', '06:30:00') ?: '06:30:00', 0, 5),
        'waktu_istirahat_mulai' => substr(get_setting('waktu_istirahat_guru_mulai', '12:00:00') ?: '12:00:00', 0, 5),
        'waktu_istirahat_selesai' => substr(get_setting('waktu_istirahat_guru_selesai', '13:00:00') ?: '13:00:00', 0, 5),
        'waktu_pulang' => substr(get_setting('waktu_pulang_guru', '15:30:00') ?: '15:30:00', 0, 5),
        'wa_cutoff_masuk' => substr(get_setting('wa_guru_cutoff_masuk', '06:30:00') ?: '06:30:00', 0, 5),
        'wa_cutoff_pulang' => substr(get_setting('wa_guru_cutoff_pulang', '17:00:00') ?: '17:00:00', 0, 5),
        'wa_mulai_pulang' => substr(get_setting('wa_guru_mulai_pulang', '13:00:00') ?: '13:00:00', 0, 5)
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
