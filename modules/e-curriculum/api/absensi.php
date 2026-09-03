<?php
/**
 * E-Curriculum Absensi Siswa API
 * Manages student attendance integrated with E-Absen logs & manual overrides
 */
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Helper untuk menembak API lokal WhatsApp Server (fire-and-forget dengan timeout singkat)
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

// Ensure database schema supports 'T' (Terlambat) status
try {
    db()->exec("ALTER TABLE acad_absensi MODIFY COLUMN status ENUM('H','S','I','A','T') NOT NULL DEFAULT 'H'");
} catch (Exception $e) {}

switch ($action) {
    case 'list':
        listAbsensi($user);
        break;
    case 'save':
        saveAbsensi($user);
        break;
    case 'students':
        getStudentsByKelas($user);
        break;
    case 'rekap':
        rekapAbsensi($user);
        break;
    case 'get_settings':
        getSettings($user);
        break;
    case 'save_settings':
        saveSettings($user);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List absensi for a class on a given date (merging E-Absen logs and acad_absensi)
 */
function listAbsensi($user) {
    try {
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
        $jam_ke = isset($_GET['jam_ke']) ? (int)$_GET['jam_ke'] : 0;
        $waktu_terlambat = get_setting('waktu_terlambat_siswa', '07:15:00');

        if ($kelas_id <= 0) json_response(400, false, 'Kelas wajib dipilih.');

        // Get class name mapping
        $stmtK = db()->prepare("SELECT nama_kelas FROM sch_kelas WHERE id = ?");
        $stmtK->execute([$kelas_id]);
        $kelas = $stmtK->fetch();
        if (!$kelas) json_response(404, false, 'Kelas tidak ditemukan.');

        // Get students in this class
        $stmtS = db()->prepare("SELECT id, nis, nama FROM students WHERE kelas = ? AND status = 1 ORDER BY nama");
        $stmtS->execute([$kelas['nama_kelas']]);
        $students = $stmtS->fetchAll();

        // Get existing manual absensi from acad_absensi
        $stmtA = db()->prepare("
            SELECT student_id, status, keterangan 
            FROM acad_absensi 
            WHERE tanggal = ? AND kelas_id = ? AND jam_ke = ?
        ");
        $stmtA->execute([$tanggal, $kelas_id, $jam_ke]);
        $existingManual = [];
        while ($row = $stmtA->fetch()) {
            $existingManual[$row['student_id']] = $row;
        }

        // Get E-Absen logs for this date
        $stmtLogs = db()->prepare("
            SELECT TRIM(LEADING '0' FROM mesin_pin) COLLATE utf8mb4_unicode_ci as clean_pin, 
                   MIN(TIME(waktu_absen)) as jam_masuk
            FROM absen_logs 
            WHERE DATE(waktu_absen) = ? 
            GROUP BY clean_pin
        ");
        $stmtLogs->execute([$tanggal]);
        $eAbsenLogs = [];
        while ($l = $stmtLogs->fetch()) {
            $eAbsenLogs[$l['clean_pin']] = $l['jam_masuk'];
        }

        // Merge student list with E-Absen log and manual override
        $result = [];
        foreach ($students as $s) {
            $cleanNis = ltrim($s['nis'], '0');
            $jamMasuk = isset($eAbsenLogs[$cleanNis]) ? $eAbsenLogs[$cleanNis] : null;

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

            // Manual override takes precedence if recorded in acad_absensi
            $finalStatus = isset($existingManual[$s['id']]) ? $existingManual[$s['id']]['status'] : ($jamMasuk !== null ? $calculatedStatus : 'H');
            $keterangan = isset($existingManual[$s['id']]) ? $existingManual[$s['id']]['keterangan'] : ($jamMasuk !== null ? "Fingerprint: $jamMasuk" : '');

            $result[] = [
                'student_id' => $s['id'],
                'nis' => $s['nis'],
                'nama' => $s['nama'],
                'jam_masuk' => $jamMasuk,
                'scan_info' => $scanInfo,
                'status' => $finalStatus,
                'keterangan' => $keterangan
            ];
        }

        json_response(200, true, 'Data absensi dimuat.', [
            'waktu_terlambat' => substr($waktu_terlambat, 0, 5),
            'students' => $result
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Save/update absensi for a class on a date
 */
function saveAbsensi($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $tanggal = isset($input['tanggal']) ? $input['tanggal'] : date('Y-m-d');
    $kelas_id = isset($input['kelas_id']) ? (int)$input['kelas_id'] : 0;
    $jam_ke = isset($input['jam_ke']) ? (int)$input['jam_ke'] : 0;
    $absensi = isset($input['absensi']) ? $input['absensi'] : [];

    $active_year = get_active_academic_year();
    $year_id = $active_year['id'] ?? 0;

    if ($kelas_id <= 0 || empty($absensi)) {
        json_response(400, false, 'Data tidak lengkap.');
    }

    try {
        db()->beginTransaction();

        // Ambil template WA
        $waTemplateStmt = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'wa_message_template'");
        $waTemplate = $waTemplateStmt->fetchColumn();
        
        $statusLabels = [
            'H' => 'Hadir',
            'S' => 'Sakit',
            'I' => 'Izin',
            'A' => 'Alpa',
            'T' => 'Terlambat'
        ];

        foreach ($absensi as $a) {
            $student_id = (int)$a['student_id'];
            $status = in_array($a['status'], ['H','S','I','A','T']) ? $a['status'] : 'H';
            $keterangan = isset($a['keterangan']) ? trim($a['keterangan']) : '';

            // Cek status lama untuk mencegah pengiriman pesan berulang
            $stmtCek = db()->prepare("SELECT status FROM acad_absensi WHERE student_id = ? AND kelas_id = ? AND tanggal = ? AND jam_ke = ?");
            $stmtCek->execute([$student_id, $kelas_id, $tanggal, $jam_ke]);
            $oldData = $stmtCek->fetch();
            $oldStatus = $oldData ? $oldData['status'] : null;

            $stmt = db()->prepare("
                INSERT INTO acad_absensi (student_id, kelas_id, academic_year_id, tanggal, jam_ke, status, keterangan, dicatat_oleh)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan), dicatat_oleh = VALUES(dicatat_oleh)
            ");
            $stmt->execute([$student_id, $kelas_id, $year_id, $tanggal, $jam_ke, $status, $keterangan, $user['user_id']]);

            // Jika status berubah atau baru pertama kali, dan template WA diset, kirim Notifikasi WA
            if ($oldStatus !== $status && $waTemplate) {
                $stmtSiswa = db()->prepare("SELECT nama, no_hp_ortu FROM students WHERE id = ?");
                $stmtSiswa->execute([$student_id]);
                $siswa = $stmtSiswa->fetch();
                
                if ($siswa && !empty($siswa['no_hp_ortu'])) {
                    $statusText = $statusLabels[$status] ?? 'Hadir';
                    $msg = str_replace(
                        ['{nama}', '{status_absen}', '{waktu}'], 
                        [$siswa['nama'], $statusText, $tanggal], 
                        $waTemplate
                    );
                    triggerWAGateway($siswa['no_hp_ortu'], $msg);
                }
            }
        }

        db()->commit();
        json_response(200, true, 'Absensi berhasil disimpan.');
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get students by kelas for dropdown
 */
function getStudentsByKelas($user) {
    $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
    if ($kelas_id <= 0) json_response(400, false, 'Kelas wajib dipilih.');

    try {
        $stmtK = db()->prepare("SELECT nama_kelas FROM sch_kelas WHERE id = ?");
        $stmtK->execute([$kelas_id]);
        $kelas = $stmtK->fetch();
        if (!$kelas) json_response(404, false, 'Kelas tidak ditemukan.');

        $stmt = db()->prepare("SELECT id, nis, nisn, nama, jenis_kelamin FROM students WHERE kelas = ? AND status = 1 ORDER BY nama");
        $stmt->execute([$kelas['nama_kelas']]);
        json_response(200, true, 'Siswa dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Rekapitulasi absensi per kelas & range tanggal (tgl_awal s/d tgl_akhir)
 */
function rekapAbsensi($user) {
    try {
        $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
        $tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-01');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');
        $waktu_terlambat = get_setting('waktu_terlambat_siswa', '07:15:00');

        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        // Get class name filter if specific class selected
        $nama_kelas_filter = null;
        if ($kelas_id > 0) {
            $stmtK = db()->prepare("SELECT nama_kelas FROM sch_kelas WHERE id = ?");
            $stmtK->execute([$kelas_id]);
            $nama_kelas_filter = $stmtK->fetchColumn();
        }

        // 1. Get all relevant students
        if ($nama_kelas_filter) {
            $stmtS = db()->prepare("SELECT id, nis, nama, kelas FROM students WHERE kelas = ? AND status = 1 ORDER BY kelas, nama");
            $stmtS->execute([$nama_kelas_filter]);
        } else {
            $stmtS = db()->query("SELECT id, nis, nama, kelas FROM students WHERE status = 1 ORDER BY kelas, nama");
        }
        $students = $stmtS->fetchAll();

        if (empty($students)) {
            json_response(200, true, 'Rekap absensi dimuat.', [
                'waktu_terlambat' => substr($waktu_terlambat, 0, 5),
                'tanggal_awal' => $tanggal_awal,
                'tanggal_akhir' => $tanggal_akhir,
                'rekap' => []
            ]);
        }

        $studentIds = array_column($students, 'id');
        $nisList = array_map(function($s) { return ltrim($s['nis'], '0'); }, $students);

        // 2. Fetch manual records from acad_absensi for date range
        $placeholdersS = implode(',', array_fill(0, count($studentIds), '?'));
        $stmtA = db()->prepare("
            SELECT student_id, tanggal, status 
            FROM acad_absensi 
            WHERE student_id IN ($placeholdersS) 
              AND tanggal BETWEEN ? AND ? 
              AND jam_ke = 0
        ");
        $paramsA = array_merge($studentIds, [$tanggal_awal, $tanggal_akhir]);
        $stmtA->execute($paramsA);
        
        $manualMap = []; // [student_id][tanggal] = status
        while ($row = $stmtA->fetch()) {
            $manualMap[$row['student_id']][$row['tanggal']] = $row['status'];
        }

        // 3. Fetch E-Absen logs for date range
        $placeholdersNis = implode(',', array_fill(0, count($nisList), '?'));
        $stmtL = db()->prepare("
            SELECT TRIM(LEADING '0' FROM mesin_pin) COLLATE utf8mb4_unicode_ci as clean_pin,
                   DATE(waktu_absen) as tgl,
                   MIN(TIME(waktu_absen)) as jam_masuk
            FROM absen_logs
            WHERE TRIM(LEADING '0' FROM mesin_pin) COLLATE utf8mb4_unicode_ci IN ($placeholdersNis)
              AND DATE(waktu_absen) BETWEEN ? AND ?
            GROUP BY clean_pin, tgl
        ");
        $paramsL = array_merge($nisList, [$tanggal_awal, $tanggal_akhir]);
        $stmtL->execute($paramsL);

        $eAbsenMap = []; // [clean_pin][tgl] = jam_masuk
        while ($l = $stmtL->fetch()) {
            $eAbsenMap[$l['clean_pin']][$l['tgl']] = $l['jam_masuk'];
        }

        // 4. Calculate attendance per student
        $rekap = [];
        foreach ($students as $s) {
            $sid = $s['id'];
            $cleanNis = ltrim($s['nis'], '0');

            $countH = 0; // Hadir tepat waktu
            $countT = 0; // Terlambat
            $countS = 0; // Sakit
            $countI = 0; // Izin
            $countA = 0; // Alpha

            // Collect all dates where student has records (either in acad_absensi or E-Absen)
            $dates = [];
            if (isset($manualMap[$sid])) {
                foreach (array_keys($manualMap[$sid]) as $d) $dates[$d] = true;
            }
            if (isset($eAbsenMap[$cleanNis])) {
                foreach (array_keys($eAbsenMap[$cleanNis]) as $d) $dates[$d] = true;
            }

            foreach (array_keys($dates) as $d) {
                // Manual record takes precedence if set
                if (isset($manualMap[$sid][$d])) {
                    $st = $manualMap[$sid][$d];
                    if ($st === 'H') $countH++;
                    else if ($st === 'T') $countT++;
                    else if ($st === 'S') $countS++;
                    else if ($st === 'I') $countI++;
                    else if ($st === 'A') $countA++;
                } else if (isset($eAbsenMap[$cleanNis][$d])) {
                    $jamMasuk = $eAbsenMap[$cleanNis][$d];
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
                'student_id' => $sid,
                'nis' => $s['nis'],
                'nama' => $s['nama'],
                'kelas' => $s['kelas'],
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

        json_response(200, true, 'Rekap absensi dimuat.', [
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
 * Get setting waktu terlambat
 */
function getSettings($user) {
    $waktu_terlambat = get_setting('waktu_terlambat_siswa', '07:15:00');
    json_response(200, true, 'Setting dimuat.', [
        'waktu_terlambat' => substr($waktu_terlambat, 0, 5)
    ]);
}

/**
 * Save setting waktu terlambat
 */
function saveSettings($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    if ($user['role'] !== 'superadmin' && $user['role'] !== 'admin') {
        json_response(403, false, 'Hanya admin yang dapat mengedit setting.');
    }

    $input = get_input();
    $waktu = isset($input['waktu_terlambat']) ? trim($input['waktu_terlambat']) : '07:15';

    // Format HH:MM:SS
    if (strlen($waktu) === 5) {
        $waktu .= ':00';
    }

    upsert_setting('waktu_terlambat_siswa', $waktu, 'text', 'Batas jam terlambat absensi siswa');
    json_response(200, true, 'Setting jam terlambat berhasil disimpan.');
}
