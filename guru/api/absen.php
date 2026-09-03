<?php
/**
 * Guru App — Absensi API
 * Fetch attendance data for teachers
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
    case 'rekap_bulanan':
        getRekapBulanan($user);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * Get monthly attendance recap for the logged-in teacher
 */
function getRekapBulanan($user) {
    $bulan = isset($_GET['bulan']) ? trim($_GET['bulan']) : date('Y-m'); // Format YYYY-MM
    
    if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {
        json_response(400, false, 'Format bulan tidak valid. Gunakan YYYY-MM.');
    }

    try {
        // Find mapped mesin_pin for this user
        $stmtMap = db()->prepare("SELECT mesin_pin FROM absen_user_map WHERE user_id = ? LIMIT 1");
        $stmtMap->execute([$user['user_id']]);
        $pin = $stmtMap->fetchColumn();

        if (!$pin) {
            json_response(200, true, 'Rekapitulasi Absensi', [
                'mapped' => false,
                'logs' => [],
                'stats' => ['hadir' => 0]
            ]);
            return;
        }

        // Fetch logs for the specified month
        $startDate = $bulan . '-01 00:00:00';
        $endDate = date('Y-m-t 23:59:59', strtotime($startDate));

        $stmtLogs = db()->prepare("
            SELECT 
                DATE(waktu_absen) as tanggal,
                MIN(TIME(waktu_absen)) as jam_masuk,
                MAX(TIME(waktu_absen)) as jam_pulang,
                COUNT(id) as total_scan
            FROM absen_logs 
            WHERE mesin_pin = ? AND waktu_absen BETWEEN ? AND ?
            GROUP BY DATE(waktu_absen)
            ORDER BY tanggal DESC
        ");
        
        $stmtLogs->execute([$pin, $startDate, $endDate]);
        $rawLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        $logs = [];
        $totalHadir = count($rawLogs);

        foreach ($rawLogs as $row) {
            $masuk = date('H:i', strtotime($row['jam_masuk']));
            $pulang = ($row['total_scan'] > 1) ? date('H:i', strtotime($row['jam_pulang'])) : '-';

            $logs[] = [
                'tanggal' => $row['tanggal'],
                'jam_masuk' => $masuk,
                'jam_pulang' => $pulang
            ];
        }

        json_response(200, true, 'Rekapitulasi Absensi', [
            'mapped' => true,
            'pin' => $pin,
            'logs' => $logs,
            'stats' => ['hadir' => $totalHadir]
        ]);

    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
