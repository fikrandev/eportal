<?php
/**
 * E-Absen Rekap API
 * Mengkalkulasi jam masuk dan jam pulang berdasarkan absen_logs
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
$isAdmin = $user['role'] === 'superadmin';

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'generate') {
    if (!$isAdmin) json_response(403, false, 'Akses ditolak.');
    
    // Tarik raw logs untuk tanggal tertentu, lalu proses ke absen_rekap
    try {
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        
        // Ambil jadwal kerja untuk hari ini
        $hari_indonesia = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
        ];
        $nama_hari = $hari_indonesia[date('l', strtotime($tanggal))];
        
        $stmtJadwal = db()->prepare("SELECT * FROM absen_jadwal_kerja WHERE hari = ?");
        $stmtJadwal->execute([$nama_hari]);
        $jadwal = $stmtJadwal->fetch();
        
        if (!$jadwal) {
            $batas_masuk = '07:15:00'; // Fallback
        } else {
            $batas_masuk = $jadwal['batas_masuk'];
        }
        
        // Ambil semua mapping pegawai
        $stmtMap = db()->query("SELECT user_id, mesin_pin FROM absen_user_map");
        $mappings = [];
        while ($row = $stmtMap->fetch()) {
            $mappings[$row['mesin_pin']] = $row['user_id'];
        }
        
        // Ambil logs hari ini
        $stmtLogs = db()->prepare("
            SELECT mesin_pin, TIME(waktu_absen) as jam, status_absen 
            FROM absen_logs 
            WHERE DATE(waktu_absen) = ? 
            ORDER BY waktu_absen ASC
        ");
        $stmtLogs->execute([$tanggal]);
        
        $kehadiran = [];
        while ($row = $stmtLogs->fetch()) {
            $pin = $row['mesin_pin'];
            if (!isset($mappings[$pin])) continue; // Skip unmapped PIN
            
            $uid = $mappings[$pin];
            if (!isset($kehadiran[$uid])) {
                $kehadiran[$uid] = ['masuk' => null, 'pulang' => null];
            }
            
            // Asumsi sederhana: Log pertama adalah masuk, log terakhir adalah pulang
            if ($kehadiran[$uid]['masuk'] === null) {
                $kehadiran[$uid]['masuk'] = $row['jam'];
            }
            $kehadiran[$uid]['pulang'] = $row['jam'];
        }
        
        // Simpan ke absen_rekap
        db()->beginTransaction();
        
        $stmtIns = db()->prepare("
            INSERT INTO absen_rekap (user_id, tanggal, jam_masuk, jam_pulang, status)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE jam_masuk=VALUES(jam_masuk), jam_pulang=VALUES(jam_pulang), status=VALUES(status)
        ");
        
        foreach ($kehadiran as $uid => $data) {
            // Jika jam pulang sama dengan jam masuk (hanya absen 1 kali), set pulang null
            if ($data['masuk'] === $data['pulang']) {
                $data['pulang'] = null;
            }
            
            $status = 'Hadir';
            if ($data['masuk'] !== null && $data['masuk'] > $batas_masuk) {
                $status = 'Terlambat';
            }
            
            $stmtIns->execute([$uid, $tanggal, $data['masuk'], $data['pulang'], $status]);
        }
        
        db()->commit();
        json_response(200, true, 'Rekapitulasi berhasil dibuat.');
    } catch (PDOException $e) {
        if (db()->inTransaction()) db()->rollBack();
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
} else if ($action === 'report') {
    try {
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        
        $where = "r.tanggal = ?";
        $params = [$tanggal];
        
        if (!$isAdmin) {
            $where .= " AND r.user_id = ?";
            $params[] = $user['user_id'];
        }
        
        $stmt = db()->prepare("
            SELECT u.nama_lengkap, u.username as nik, u.jabatan, r.*
            FROM absen_rekap r
            JOIN users u ON r.user_id = u.id
            WHERE $where
            ORDER BY u.nama_lengkap ASC
        ");
        $stmt->execute($params);
        
        json_response(200, true, 'Data rekap dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
} else if ($action === 'dashboard_stats') {
    try {
        $today = date('Y-m-d');
        $stmt = db()->prepare("SELECT status, COUNT(*) as cnt FROM absen_rekap WHERE tanggal = ? GROUP BY status");
        $stmt->execute([$today]);
        
        $stats = ['Hadir' => 0, 'Terlambat' => 0, 'Alpha' => 0, 'Izin' => 0, 'Sakit' => 0];
        while ($row = $stmt->fetch()) {
            $stats[$row['status']] = (int)$row['cnt'];
        }
        
        json_response(200, true, 'Stats', $stats);
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
} else {
    json_response(400, false, 'Action tidak valid.');
}
