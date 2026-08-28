<?php
/**
 * Guru App — Jadwal Mengajar API
 * Returns schedule filtered by logged-in teacher
 */
require_once __DIR__ . '/../../api/config.php';

// Auth check
$token = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
} elseif (function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) $token = str_replace('Bearer ', '', $headers['Authorization']);
    elseif (isset($headers['authorization'])) $token = str_replace('Bearer ', '', $headers['authorization']);
}
if (empty($token) && isset($_GET['token'])) $token = $_GET['token'];

if (empty($token)) json_response(401, false, 'Token tidak ditemukan.');

$stmt = db()->prepare("
    SELECT u.id as user_id, u.username, u.nama_lengkap, u.role
    FROM sessions s
    JOIN users u ON s.user_id = u.id
    WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1 AND u.role = 'guru'
");
$stmt->execute([trim($token)]);
$user = $stmt->fetch();
if (!$user) json_response(401, false, 'Sesi tidak valid.');

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'today':
        getJadwalToday($user);
        break;
    case 'weekly':
        getJadwalWeekly($user);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * Get today's schedule for the logged-in teacher
 */
function getJadwalToday($user) {
    try {
        // Map PHP day of week to Indonesian day names
        $dayMap = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu'
        ];
        
        // Allow overriding the day via query parameter (for testing or viewing other days)
        $hariParam = isset($_GET['hari']) ? $_GET['hari'] : '';
        if (!empty($hariParam) && in_array($hariParam, $dayMap)) {
            $hariIni = $hariParam;
        } else {
            $dayOfWeek = (int)date('N'); // 1=Monday, 7=Sunday
            $hariIni = $dayMap[$dayOfWeek] ?? 'Senin';
        }

        // Find sch_guru.id by matching kode_guru = users.username
        $stmtGuru = db()->prepare("SELECT id FROM sch_guru WHERE kode_guru = ?");
        $stmtGuru->execute([$user['username']]);
        $guru = $stmtGuru->fetch();

        if (!$guru) {
            json_response(200, true, 'Data jadwal dimuat.', [
                'hari' => $hariIni,
                'jadwal' => [],
                'message' => 'Data guru belum terdaftar di modul jadwal.'
            ]);
            return;
        }

        $stmt = db()->prepare("
            SELECT j.id,
                   k.id as kelas_id, k.nama_kelas, k.rombel,
                   jb.hari, jb.jam_ke, jb.tipe, jb.nama_jam,
                   m.id as mapel_id, m.nama_mapel, m.kode_mapel,
                   d.jp
            FROM sch_jadwal j
            JOIN sch_kelas k ON j.kelas_id = k.id
            JOIN sch_jam_belajar jb ON j.jam_belajar_id = jb.id
            JOIN sch_distribusi d ON j.distribusi_id = d.id
            JOIN sch_mapel m ON d.mapel_id = m.id
            WHERE d.guru_id = ? AND jb.hari = ?
            ORDER BY jb.jam_ke ASC
        ");
        $stmt->execute([$guru['id'], $hariIni]);
        $jadwal = $stmt->fetchAll();

        // Check which slots already have jurnal entries for today
        $today = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $activeYear = get_active_academic_year();
        $yearId = $activeYear['id'] ?? 0;

        $stmtJurnal = db()->prepare("
            SELECT jam_ke, kelas_id, mapel_id, id as jurnal_id
            FROM acad_jurnal
            WHERE guru_id = ? AND tanggal = ? AND academic_year_id = ?
        ");
        $stmtJurnal->execute([$user['user_id'], $today, $yearId]);
        $jurnalList = $stmtJurnal->fetchAll();

        // Create a lookup map for filled journals
        $jurnalMap = [];
        foreach ($jurnalList as $jr) {
            $key = $jr['kelas_id'] . '-' . $jr['mapel_id'] . '-' . $jr['jam_ke'];
            $jurnalMap[$key] = $jr['jurnal_id'];
        }

        // Annotate jadwal with jurnal status
        foreach ($jadwal as &$j) {
            $key = $j['kelas_id'] . '-' . $j['mapel_id'] . '-' . $j['jam_ke'];
            $j['jurnal_id'] = $jurnalMap[$key] ?? null;
            $j['jurnal_filled'] = isset($jurnalMap[$key]);
        }
        unset($j);

        json_response(200, true, 'Data jadwal dimuat.', [
            'hari' => $hariIni,
            'tanggal' => $today,
            'jadwal' => $jadwal
        ]);

    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get weekly schedule for the logged-in teacher
 */
function getJadwalWeekly($user) {
    try {
        // Find sch_guru.id
        $stmtGuru = db()->prepare("SELECT id FROM sch_guru WHERE kode_guru = ?");
        $stmtGuru->execute([$user['username']]);
        $guru = $stmtGuru->fetch();

        if (!$guru) {
            json_response(200, true, 'Data jadwal dimuat.', [
                'jadwal' => [],
                'message' => 'Data guru belum terdaftar di modul jadwal.'
            ]);
            return;
        }

        $stmt = db()->prepare("
            SELECT j.id,
                   k.id as kelas_id, k.nama_kelas, k.rombel,
                   jb.hari, jb.jam_ke, jb.tipe, jb.nama_jam,
                   m.id as mapel_id, m.nama_mapel, m.kode_mapel,
                   d.jp
            FROM sch_jadwal j
            JOIN sch_kelas k ON j.kelas_id = k.id
            JOIN sch_jam_belajar jb ON j.jam_belajar_id = jb.id
            JOIN sch_distribusi d ON j.distribusi_id = d.id
            JOIN sch_mapel m ON d.mapel_id = m.id
            WHERE d.guru_id = ?
            ORDER BY 
                CASE jb.hari
                    WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3
                    WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 ELSE 7
                END,
                jb.jam_ke ASC
        ");
        $stmt->execute([$guru['id']]);
        $allJadwal = $stmt->fetchAll();

        // Group by hari
        $grouped = [];
        $hariOrder = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        foreach ($hariOrder as $h) {
            $grouped[$h] = [];
        }
        foreach ($allJadwal as $j) {
            $grouped[$j['hari']][] = $j;
        }

        // Get total JP
        $totalJP = 0;
        foreach ($allJadwal as $j) {
            $totalJP++;
        }

        json_response(200, true, 'Data jadwal mingguan dimuat.', [
            'jadwal' => $grouped,
            'total_jp' => $totalJP,
            'total_hari_mengajar' => count(array_filter($grouped, fn($v) => !empty($v)))
        ]);

    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
