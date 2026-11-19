<?php
/**
 * Guru App — Absensi API
 * Fetch attendance data for teachers
 */
require_once __DIR__ . '/auth_helper.php';
$user = guru_auth();

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
        $cleanPin = $pin ? ltrim((string)$pin, '0') : '';

        $startDate = $bulan . '-01 00:00:00';
        $endDate = date('Y-m-t 23:59:59', strtotime($startDate));
        $startDateOnly = $bulan . '-01';
        $endDateOnly = date('Y-m-t', strtotime($startDate));

        $byDate = [];

        if ($pin) {
            $stmtLogs = db()->prepare("
                SELECT 
                    DATE(waktu_absen) as tanggal,
                    MIN(TIME(waktu_absen)) as jam_masuk,
                    MAX(TIME(waktu_absen)) as jam_pulang,
                    COUNT(id) as total_scan
                FROM absen_logs 
                WHERE (mesin_pin = ? OR mesin_pin = ?) AND waktu_absen BETWEEN ? AND ?
                GROUP BY DATE(waktu_absen)
                ORDER BY tanggal DESC
            ");
            $stmtLogs->execute([$pin, $cleanPin !== '' ? $cleanPin : $pin, $startDate, $endDate]);
            $rawLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rawLogs as $row) {
                $masuk = date('H:i', strtotime($row['jam_masuk']));
                $pulang = ($row['total_scan'] > 1) ? date('H:i', strtotime($row['jam_pulang'])) : '-';
                $byDate[$row['tanggal']] = [
                    'tanggal' => $row['tanggal'],
                    'jam_masuk' => $masuk,
                    'jam_pulang' => $pulang,
                    'status' => 'H'
                ];
            }
        }

        // Also merge manual PTK attendance from acad_absensi_guru
        try {
            $stmtManual = db()->prepare("
                SELECT tanggal, jam_ke, status, keterangan
                FROM acad_absensi_guru
                WHERE user_id = ? AND tanggal BETWEEN ? AND ?
                ORDER BY tanggal DESC, jam_ke ASC
            ");
            $stmtManual->execute([$user['user_id'], $startDateOnly, $endDateOnly]);
            foreach ($stmtManual->fetchAll(PDO::FETCH_ASSOC) as $m) {
                $tgl = $m['tanggal'];
                if (!isset($byDate[$tgl])) {
                    $byDate[$tgl] = [
                        'tanggal' => $tgl,
                        'jam_masuk' => '-',
                        'jam_pulang' => '-',
                        'status' => $m['status'] ?: 'H'
                    ];
                }
                $jk = (int)$m['jam_ke'];
                $st = $m['status'] ?: 'H';
                $label = ($st === 'H') ? ($m['keterangan'] ?: 'Hadir') : $st;
                if (($jk === 0 || $jk === 1) && $byDate[$tgl]['jam_masuk'] === '-') {
                    $byDate[$tgl]['jam_masuk'] = $label;
                    $byDate[$tgl]['status'] = $st;
                } elseif ($jk === 3 && $byDate[$tgl]['jam_pulang'] === '-') {
                    $byDate[$tgl]['jam_pulang'] = $label;
                }
            }
        } catch (Exception $e) {}

        if (!$pin && empty($byDate)) {
            json_response(200, true, 'Rekapitulasi Absensi', [
                'mapped' => false,
                'logs' => [],
                'stats' => ['hadir' => 0]
            ]);
            return;
        }

        krsort($byDate);
        $logs = array_values($byDate);
        $totalHadir = 0;
        foreach ($logs as $item) {
            if (($item['status'] ?? 'H') === 'H' || ($item['status'] ?? '') === 'DNS') {
                $totalHadir++;
            }
        }

        json_response(200, true, 'Rekapitulasi Absensi', [
            'mapped' => (bool)$pin || !empty($logs),
            'pin' => $pin ?: '-',
            'logs' => $logs,
            'stats' => ['hadir' => $totalHadir]
        ]);

    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
