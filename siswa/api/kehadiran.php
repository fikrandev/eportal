<?php
/**
 * Siswa App - Kehadiran & Izin API
 */
require_once __DIR__ . '/auth_helper.php';
$siswa = siswa_auth();

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'rekap') {
    // 1. Data Mesin Absen (Log harian)
    $today = date('Y-m-d');
    
    // Convert NIS to format that matches PIN (usually without leading zeros in machine)
    $pin = ltrim($siswa['nis'], '0');
    
    $stmtLogs = db()->prepare("
        SELECT waktu_absen as waktu, status_absen 
        FROM absen_logs 
        WHERE mesin_pin = ? AND DATE(waktu_absen) = ? 
        ORDER BY waktu_absen ASC
    ");
    $stmtLogs->execute([$pin, $today]);
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    // Format time only
    foreach ($logs as &$log) {
        $log['waktu'] = date('H:i', strtotime($log['waktu']));
    }

    // 2. Data Absen Kelas (Dari Guru)
    $active_year = get_active_academic_year();
    $year_id = $active_year ? $active_year['id'] : 0;
    
    $stmtKelas = db()->prepare("
        SELECT jam_ke, status
        FROM acad_absensi
        WHERE student_id = ? AND academic_year_id = ? AND tanggal = ? AND jam_ke > 0
        ORDER BY jam_ke ASC
    ");
    $stmtKelas->execute([$siswa['id'], $year_id, $today]);
    $kelas = $stmtKelas->fetchAll(PDO::FETCH_ASSOC);

    json_response(200, true, 'Rekap loaded', [
        'logs' => $logs,
        'kelas' => $kelas
    ]);

} else if ($action === 'list_izin') {
    $stmt = db()->prepare("
        SELECT * FROM acad_izin_siswa 
        WHERE student_id = ? 
        ORDER BY tanggal DESC LIMIT 30
    ");
    $stmt->execute([$siswa['id']]);
    $izinList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format tanggal indonesia
    foreach($izinList as &$i) {
        $i['tanggal_indo'] = format_tanggal($i['tanggal']);
    }

    json_response(200, true, 'Izin loaded', $izinList);

} else if ($action === 'submit_izin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $tanggal = $input['tanggal'] ?? '';
    $jenis = $input['jenis'] ?? '';
    $keterangan = $input['keterangan'] ?? '';

    if (empty($tanggal) || empty($jenis) || empty($keterangan)) {
        json_response(400, false, 'Semua field wajib diisi');
    }

    // Check if already applied for this date
    $stmtCheck = db()->prepare("SELECT id FROM acad_izin_siswa WHERE student_id = ? AND tanggal = ?");
    $stmtCheck->execute([$siswa['id'], $tanggal]);
    if ($stmtCheck->fetch()) {
        json_response(400, false, 'Anda sudah mengajukan izin untuk tanggal ini');
    }

    try {
        $stmt = db()->prepare("
            INSERT INTO acad_izin_siswa (student_id, tanggal, jenis, keterangan, status) 
            VALUES (?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$siswa['id'], $tanggal, $jenis, $keterangan]);
        json_response(200, true, 'Pengajuan izin berhasil dikirim');
    } catch (PDOException $e) {
        json_response(500, false, 'Database Error: ' . $e->getMessage());
    }
} else {
    json_response(400, false, 'Invalid action');
}
