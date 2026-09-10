<?php
/**
 * Siswa App - Kehadiran & Izin API
 */
require_once __DIR__ . '/auth_helper.php';
$siswa = siswa_auth();

// Ensure acad_izin_siswa table and its columns exist
function ensure_izin_table() {
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS `acad_izin_siswa` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `student_id` int(11) unsigned NOT NULL,
            `academic_year_id` int(11) unsigned DEFAULT NULL,
            `tanggal` date NOT NULL,
            `jenis` enum('Sakit','Izin','Lainnya') NOT NULL DEFAULT 'Izin',
            `keterangan` text DEFAULT NULL,
            `lampiran` varchar(255) DEFAULT NULL,
            `status` enum('Pending','Disetujui','Ditolak','Approved','Rejected') NOT NULL DEFAULT 'Pending',
            `disetujui_oleh` int(11) unsigned DEFAULT NULL,
            `alasan_tolak` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_izin_siswa_tanggal` (`tanggal`),
            KEY `idx_izin_siswa_student` (`student_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $cols = db()->query("SHOW COLUMNS FROM acad_izin_siswa")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('academic_year_id', $cols)) {
            db()->exec("ALTER TABLE `acad_izin_siswa` ADD COLUMN `academic_year_id` INT(11) UNSIGNED DEFAULT NULL AFTER `student_id`");
        }
        if (!in_array('lampiran', $cols)) {
            db()->exec("ALTER TABLE `acad_izin_siswa` ADD COLUMN `lampiran` VARCHAR(255) DEFAULT NULL AFTER `keterangan`");
        }
        if (!in_array('disetujui_oleh', $cols)) {
            db()->exec("ALTER TABLE `acad_izin_siswa` ADD COLUMN `disetujui_oleh` INT(11) UNSIGNED DEFAULT NULL AFTER `status`");
        }
        if (!in_array('alasan_tolak', $cols)) {
            db()->exec("ALTER TABLE `acad_izin_siswa` ADD COLUMN `alasan_tolak` TEXT DEFAULT NULL AFTER `disetujui_oleh`");
        }
        
        // Also ensure ENUM includes Disetujui/Ditolak
        db()->exec("ALTER TABLE `acad_izin_siswa` MODIFY COLUMN `status` ENUM('Pending','Disetujui','Ditolak','Approved','Rejected') NOT NULL DEFAULT 'Pending'");
    } catch (Exception $e) {
        // Continue
    }
}

ensure_izin_table();

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if ($action === 'rekap') {
    // 1. Data Mesin Absen (Log harian)
    $today = date('Y-m-d');
    
    // Convert NIS to format that matches PIN (machine PIN without leading zeros)
    $pin = ltrim($siswa['nis'], '0');
    
    $logs = [];
    try {
        $stmtLogs = db()->prepare("
            SELECT waktu_absen as waktu, status_absen 
            FROM absen_logs 
            WHERE (mesin_pin = ? OR mesin_pin = ?) AND DATE(waktu_absen) = ? 
            ORDER BY waktu_absen ASC
        ");
        $stmtLogs->execute([$pin, $siswa['nis'], $today]);
        $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        // Format time only
        foreach ($logs as &$log) {
            $log['waktu'] = date('H:i', strtotime($log['waktu']));
        }
    } catch (Exception $e) {
        $logs = [];
    }

    // 2. Data Absen Kelas (Dari Guru Mapel / Kelas)
    $active_year = get_active_academic_year();
    $year_id = $active_year ? (int)$active_year['id'] : 0;
    
    $kelas = [];
    try {
        $stmtKelas = db()->prepare("
            SELECT jam_ke, status
            FROM acad_absensi
            WHERE (student_id = ? OR student_id = ?) AND tanggal = ? AND jam_ke > 0
            ORDER BY jam_ke ASC
        ");
        $stmtKelas->execute([$siswa['id'], $siswa['nis'], $today]);
        $kelas = $stmtKelas->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $kelas = [];
    }

    json_response(200, true, 'Rekap kehadiran berhasil dimuat.', [
        'logs' => $logs,
        'kelas' => $kelas
    ]);

} else if ($action === 'list_izin') {
    try {
        $stmt = db()->prepare("
            SELECT * FROM acad_izin_siswa 
            WHERE student_id = ? OR student_id = ? OR student_id IN (SELECT id FROM students WHERE nis = ?)
            ORDER BY tanggal DESC, id DESC LIMIT 50
        ");
        $stmt->execute([$siswa['id'], $siswa['nis'], $siswa['nis']]);
        $izinList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format tanggal indonesia & normalize status text
        foreach ($izinList as &$i) {
            $i['tanggal_indo'] = format_tanggal($i['tanggal']);
            if ($i['status'] === 'Approved') $i['status'] = 'Disetujui';
            if ($i['status'] === 'Rejected') $i['status'] = 'Ditolak';
        }

        json_response(200, true, 'Daftar izin berhasil dimuat.', $izinList);
    } catch (PDOException $e) {
        json_response(500, false, 'Gagal memuat izin: ' . $e->getMessage());
    }

} else if ($action === 'submit_izin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: $_POST;
    
    $tanggal = isset($input['tanggal']) ? trim($input['tanggal']) : '';
    $jenis = isset($input['jenis']) ? trim($input['jenis']) : 'Izin';
    $keterangan = isset($input['keterangan']) ? trim($input['keterangan']) : '';

    if (empty($tanggal) || empty($jenis) || empty($keterangan)) {
        json_response(400, false, 'Semua kolom formulir wajib diisi.');
    }

    // Validasi format tanggal YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $ts = strtotime($tanggal);
        if ($ts) {
            $tanggal = date('Y-m-d', $ts);
        } else {
            json_response(400, false, 'Format tanggal tidak valid.');
        }
    }

    if (!in_array($jenis, ['Sakit', 'Izin', 'Lainnya'])) {
        $jenis = 'Izin';
    }

    $active_year = get_active_academic_year();
    $year_id = !empty($active_year['id']) ? (int)$active_year['id'] : null;

    // Check if already applied for this date
    try {
        $stmtCheck = db()->prepare("
            SELECT id FROM acad_izin_siswa 
            WHERE (student_id = ? OR student_id = ?) AND tanggal = ? LIMIT 1
        ");
        $stmtCheck->execute([$siswa['id'], $siswa['nis'], $tanggal]);
        if ($stmtCheck->fetch()) {
            json_response(400, false, 'Anda sudah pernah mengajukan permohonan izin untuk tanggal tersebut.');
        }

        $stmt = db()->prepare("
            INSERT INTO acad_izin_siswa (student_id, academic_year_id, tanggal, jenis, keterangan, status) 
            VALUES (?, ?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$siswa['id'], $year_id, $tanggal, $jenis, $keterangan]);
        json_response(200, true, 'Pengajuan permohonan izin berhasil dikirim.');
    } catch (PDOException $e) {
        json_response(500, false, 'Gagal menyimpan pengajuan izin: ' . $e->getMessage());
    }
} else {
    json_response(400, false, 'Aksi tidak valid.');
}
