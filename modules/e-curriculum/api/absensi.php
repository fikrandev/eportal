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
 * List absensi for a class on a given date (merging E-Absen logs and acad_absensi for 3 sessions)
 */
function listAbsensi($user) {
    try {
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
        $jam_ke = isset($_GET['jam_ke']) ? (int)$_GET['jam_ke'] : 1;
        if ($jam_ke <= 0) $jam_ke = 1;

        $waktu_terlambat = get_setting('waktu_terlambat_siswa', '06:30:00');
        $waktu_istirahat_mulai = get_setting('waktu_istirahat_siswa_mulai', '09:30:00');
        $waktu_istirahat_selesai = get_setting('waktu_istirahat_siswa_selesai', '10:15:00');
        $waktu_pulang = get_setting('waktu_pulang_siswa', '15:30:00');
        $waktu_pulang_mulai = get_setting('waktu_pulang_siswa_mulai', '13:30:00');

        if (strlen($waktu_terlambat) === 5) $waktu_terlambat .= ':00';
        if (strlen($waktu_istirahat_mulai) === 5) $waktu_istirahat_mulai .= ':00';
        if (strlen($waktu_istirahat_selesai) === 5) $waktu_istirahat_selesai .= ':00';
        if (strlen($waktu_pulang) === 5) $waktu_pulang .= ':00';
        if (strlen($waktu_pulang_mulai) === 5) $waktu_pulang_mulai .= ':00';

        if ($kelas_id <= 0) json_response(400, false, 'Kelas wajib dipilih.');

        // Get class name mapping
        $stmtK = db()->prepare("SELECT nama_kelas FROM sch_kelas WHERE id = ?");
        $stmtK->execute([$kelas_id]);
        $kelas = $stmtK->fetch();
        if (!$kelas) json_response(404, false, 'Kelas tidak ditemukan.');

        // Get students in this class (filter active academic year & active status)
        $active_year = get_active_academic_year();
        $year_id = (int)($active_year['id'] ?? 0);

        if ($year_id > 0) {
            $stmtS = db()->prepare("SELECT id, nis, nama FROM students WHERE kelas = ? AND academic_year_id = ? AND status = 1 AND (status_siswa = 'Aktif' OR status_siswa IS NULL OR status_siswa = '') ORDER BY nama");
            $stmtS->execute([$kelas['nama_kelas'], $year_id]);
        } else {
            $stmtS = db()->prepare("SELECT id, nis, nama FROM students WHERE kelas = ? AND status = 1 AND (status_siswa = 'Aktif' OR status_siswa IS NULL OR status_siswa = '') ORDER BY nama");
            $stmtS->execute([$kelas['nama_kelas']]);
        }
        $students = $stmtS->fetchAll();

        // Get existing manual absensi from acad_absensi
        $stmtA = db()->prepare("
            SELECT student_id, status, keterangan 
            FROM acad_absensi 
            WHERE tanggal = ? AND kelas_id = ? AND (jam_ke = ? " . ($jam_ke == 1 ? "OR jam_ke = 0" : "") . ")
            ORDER BY jam_ke DESC
        ");
        $stmtA->execute([$tanggal, $kelas_id, $jam_ke]);
        $existingManual = [];
        while ($row = $stmtA->fetch()) {
            if (!isset($existingManual[$row['student_id']])) {
                $existingManual[$row['student_id']] = $row;
            }
        }

        // Get E-Absen logs for this date partitioned into 3 sessions
        $stmtLogs = db()->prepare("
            SELECT TRIM(LEADING '0' FROM mesin_pin) COLLATE utf8mb4_unicode_ci as clean_pin, 
                   MIN(CASE WHEN TIME(waktu_absen) < ? THEN TIME(waktu_absen) END) as jam_masuk,
                   MIN(CASE WHEN TIME(waktu_absen) >= ? AND TIME(waktu_absen) < ? THEN TIME(waktu_absen) END) as jam_istirahat,
                   MAX(CASE WHEN TIME(waktu_absen) >= ? THEN TIME(waktu_absen) END) as jam_pulang
            FROM absen_logs 
            WHERE DATE(waktu_absen) = ? 
            GROUP BY clean_pin
        ");
        $stmtLogs->execute([$waktu_istirahat_mulai, $waktu_istirahat_mulai, $waktu_pulang_mulai, $waktu_pulang_mulai, $tanggal]);
        $eAbsenLogs = [];
        while ($l = $stmtLogs->fetch()) {
            $eAbsenLogs[$l['clean_pin']] = $l;
        }

        $waktu_terlambat_short = substr($waktu_terlambat, 0, 5);

        // Merge student list with E-Absen log and manual override based on active session (jam_ke)
        $result = [];
        foreach ($students as $s) {
            $cleanNis = ltrim($s['nis'], '0');
            $log = isset($eAbsenLogs[$cleanNis]) ? $eAbsenLogs[$cleanNis] : null;
            $jamMasuk = ($log && !empty($log['jam_masuk'])) ? $log['jam_masuk'] : null;
            $jamIstirahat = ($log && !empty($log['jam_istirahat'])) ? $log['jam_istirahat'] : null;
            $jamPulang = ($log && !empty($log['jam_pulang'])) ? $log['jam_pulang'] : null;

            $calculatedStatus = 'A'; // Default jika belum scan adalah Alpha
            $scanInfo = 'Belum Scan Mesin';
            $jamScanSesi = null;

            if ($jam_ke == 1) { // 1. Absen Masuk
                $jamScanSesi = $jamMasuk;
                if ($jamMasuk !== null) {
                    $masukShort = substr($jamMasuk, 0, 5);
                    if ($masukShort <= $waktu_terlambat_short) {
                        $calculatedStatus = 'H';
                        $scanInfo = "Hadir ({$masukShort})";
                    } else {
                        $calculatedStatus = 'T';
                        $scanInfo = "Terlambat ({$masukShort})";
                    }
                } else {
                    $calculatedStatus = 'A';
                    $scanInfo = 'Belum Scan Mesin';
                }
            } else if ($jam_ke == 2) { // 2. Absen Istirahat (tampilan jam saja)
                $jamScanSesi = $jamIstirahat;
                if ($jamIstirahat !== null) {
                    $calculatedStatus = 'H';
                    $scanInfo = substr($jamIstirahat, 0, 5);
                } else {
                    $calculatedStatus = 'A';
                    $scanInfo = 'Belum Scan';
                }
            } else if ($jam_ke == 3) { // 3. Absen Pulang (tampilan jam saja)
                $jamScanSesi = $jamPulang;
                if ($jamPulang !== null) {
                    $calculatedStatus = 'H';
                    $scanInfo = substr($jamPulang, 0, 5);
                } else {
                    $calculatedStatus = 'A';
                    $scanInfo = 'Belum Scan';
                }
            }

            // Manual override takes precedence if recorded in acad_absensi for this session
            $finalStatus = isset($existingManual[$s['id']]) ? $existingManual[$s['id']]['status'] : $calculatedStatus;
            $keterangan = isset($existingManual[$s['id']]) ? $existingManual[$s['id']]['keterangan'] : ($jamScanSesi !== null ? "Fingerprint: $jamScanSesi" : '');

            $result[] = [
                'student_id' => $s['id'],
                'nis' => $s['nis'],
                'nama' => $s['nama'],
                'jam_scan' => $jamScanSesi,
                'jam_masuk' => $jamMasuk,
                'jam_istirahat' => $jamIstirahat,
                'jam_pulang' => $jamPulang,
                'scan_info' => $scanInfo,
                'status' => $finalStatus,
                'keterangan' => $keterangan
            ];
        }

        json_response(200, true, 'Data absensi dimuat.', [
            'jam_ke' => $jam_ke,
            'waktu_terlambat' => substr($waktu_terlambat, 0, 5),
            'waktu_istirahat_mulai' => substr($waktu_istirahat_mulai, 0, 5),
            'waktu_istirahat_selesai' => substr($waktu_istirahat_selesai, 0, 5),
            'waktu_pulang' => substr($waktu_pulang, 0, 5),
            'waktu_pulang_mulai' => substr($waktu_pulang_mulai, 0, 5),
            'students' => $result
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Save/update absensi for a class on a date per session
 */
function saveAbsensi($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $tanggal = isset($input['tanggal']) ? $input['tanggal'] : date('Y-m-d');
    $kelas_id = isset($input['kelas_id']) ? (int)$input['kelas_id'] : 0;
    $jam_ke = isset($input['jam_ke']) ? (int)$input['jam_ke'] : 1;
    if ($jam_ke <= 0) $jam_ke = 1;
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

        $sesiLabels = [
            1 => 'Masuk',
            2 => 'Istirahat',
            3 => 'Pulang'
        ];
        $sesiText = $sesiLabels[$jam_ke] ?? 'Harian';

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
                        ['{nama}', '{status_absen}', '{waktu}', '{sesi}'], 
                        [$siswa['nama'], $statusText, $tanggal, $sesiText], 
                        $waTemplate
                    );
                    triggerWAGateway($siswa['no_hp_ortu'], $msg);
                }
            }
        }

        db()->commit();
        json_response(200, true, "Absensi {$sesiText} berhasil disimpan.");
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

        $active_year = get_active_academic_year();
        $year_id = (int)($active_year['id'] ?? 0);

        if ($year_id > 0) {
            $stmt = db()->prepare("SELECT id, nis, nisn, nama, jenis_kelamin FROM students WHERE kelas = ? AND academic_year_id = ? AND status = 1 AND (status_siswa = 'Aktif' OR status_siswa IS NULL OR status_siswa = '') ORDER BY nama");
            $stmt->execute([$kelas['nama_kelas'], $year_id]);
        } else {
            $stmt = db()->prepare("SELECT id, nis, nisn, nama, jenis_kelamin FROM students WHERE kelas = ? AND status = 1 AND (status_siswa = 'Aktif' OR status_siswa IS NULL OR status_siswa = '') ORDER BY nama");
            $stmt->execute([$kelas['nama_kelas']]);
        }
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
        $year_id = (int)($active_year['id'] ?? 0);

        // Get class name filter if specific class selected
        $nama_kelas_filter = null;
        if ($kelas_id > 0) {
            $stmtK = db()->prepare("SELECT nama_kelas FROM sch_kelas WHERE id = ?");
            $stmtK->execute([$kelas_id]);
            $nama_kelas_filter = $stmtK->fetchColumn();
        }

        // 1. Get all relevant students (filter active academic year & not graduated)
        if ($nama_kelas_filter) {
            if ($year_id > 0) {
                $stmtS = db()->prepare("SELECT id, nis, nama, kelas FROM students WHERE kelas = ? AND academic_year_id = ? AND status = 1 AND (status_siswa = 'Aktif' OR status_siswa IS NULL OR status_siswa = '') ORDER BY kelas, nama");
                $stmtS->execute([$nama_kelas_filter, $year_id]);
            } else {
                $stmtS = db()->prepare("SELECT id, nis, nama, kelas FROM students WHERE kelas = ? AND status = 1 AND (status_siswa = 'Aktif' OR status_siswa IS NULL OR status_siswa = '') ORDER BY kelas, nama");
                $stmtS->execute([$nama_kelas_filter]);
            }
        } else {
            if ($year_id > 0) {
                $stmtS = db()->prepare("SELECT id, nis, nama, kelas FROM students WHERE academic_year_id = ? AND status = 1 AND (status_siswa = 'Aktif' OR status_siswa IS NULL OR status_siswa = '') ORDER BY kelas, nama");
                $stmtS->execute([$year_id]);
            } else {
                $stmtS = db()->query("SELECT id, nis, nama, kelas FROM students WHERE status = 1 AND (status_siswa = 'Aktif' OR status_siswa IS NULL OR status_siswa = '') ORDER BY kelas, nama");
            }
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
 * Get setting waktu absensi siswa
 */
function getSettings($user) {
    json_response(200, true, 'Setting dimuat.', [
        'waktu_terlambat' => substr(get_setting('waktu_terlambat_siswa', '06:30:00'), 0, 5),
        'waktu_istirahat_mulai' => substr(get_setting('waktu_istirahat_siswa_mulai', '09:30:00'), 0, 5),
        'waktu_istirahat_selesai' => substr(get_setting('waktu_istirahat_siswa_selesai', '10:15:00'), 0, 5),
        'waktu_pulang' => substr(get_setting('waktu_pulang_siswa', '15:30:00'), 0, 5),
        'waktu_pulang_mulai' => substr(get_setting('waktu_pulang_siswa_mulai', '13:30:00'), 0, 5)
    ]);
}

/**
 * Save setting waktu absensi siswa
 */
function saveSettings($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    if ($user['role'] !== 'superadmin' && $user['role'] !== 'admin') {
        json_response(403, false, 'Hanya admin yang dapat mengedit setting.');
    }

    $input = get_input();
    
    // Check if body was passed as raw JSON
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
        $w = trim($input['waktu_terlambat']);
        if (strlen($w) === 5) $w .= ':00';
        upsert_setting('waktu_terlambat_siswa', $w, 'text', 'Batas jam masuk / terlambat absensi siswa');
    }
    if (isset($input['waktu_istirahat_mulai'])) {
        $w = trim($input['waktu_istirahat_mulai']);
        if (strlen($w) === 5) $w .= ':00';
        upsert_setting('waktu_istirahat_siswa_mulai', $w, 'text', 'Jam mulai istirahat siswa');
    }
    if (isset($input['waktu_istirahat_selesai'])) {
        $w = trim($input['waktu_istirahat_selesai']);
        if (strlen($w) === 5) $w .= ':00';
        upsert_setting('waktu_istirahat_siswa_selesai', $w, 'text', 'Jam selesai istirahat siswa');
    }
    if (isset($input['waktu_pulang'])) {
        $w = trim($input['waktu_pulang']);
        if (strlen($w) === 5) $w .= ':00';
        upsert_setting('waktu_pulang_siswa', $w, 'text', 'Jam batas pulang siswa');
    }
    if (isset($input['waktu_pulang_mulai'])) {
        $w = trim($input['waktu_pulang_mulai']);
        if (strlen($w) === 5) $w .= ':00';
        upsert_setting('waktu_pulang_siswa_mulai', $w, 'text', 'Jam mulai tap mesin untuk absen pulang siswa');
    }

    json_response(200, true, 'Pengaturan jam absensi siswa berhasil disimpan.', [
        'waktu_terlambat' => substr(get_setting('waktu_terlambat_siswa', '06:30:00'), 0, 5),
        'waktu_istirahat_mulai' => substr(get_setting('waktu_istirahat_siswa_mulai', '09:30:00'), 0, 5),
        'waktu_istirahat_selesai' => substr(get_setting('waktu_istirahat_siswa_selesai', '10:15:00'), 0, 5),
        'waktu_pulang' => substr(get_setting('waktu_pulang_siswa', '15:30:00'), 0, 5),
        'waktu_pulang_mulai' => substr(get_setting('waktu_pulang_siswa_mulai', '13:30:00'), 0, 5)
    ]);
}
