<?php
/**
 * E-Curriculum Absensi Siswa API
 * Manages student attendance
 */
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
$action = isset($_GET['action']) ? $_GET['action'] : '';

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
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List absensi for a class on a given date
 */
function listAbsensi($user) {
    try {
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
        $jam_ke = isset($_GET['jam_ke']) ? (int)$_GET['jam_ke'] : 0;

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

        // Get existing absensi
        $stmtA = db()->prepare("
            SELECT student_id, status, keterangan 
            FROM acad_absensi 
            WHERE tanggal = ? AND kelas_id = ? AND jam_ke = ?
        ");
        $stmtA->execute([$tanggal, $kelas_id, $jam_ke]);
        $existing = [];
        while ($row = $stmtA->fetch()) {
            $existing[$row['student_id']] = $row;
        }

        // Merge
        $result = [];
        foreach ($students as $s) {
            $result[] = [
                'student_id' => $s['id'],
                'nis' => $s['nis'],
                'nama' => $s['nama'],
                'status' => isset($existing[$s['id']]) ? $existing[$s['id']]['status'] : 'H',
                'keterangan' => isset($existing[$s['id']]) ? $existing[$s['id']]['keterangan'] : ''
            ];
        }

        json_response(200, true, 'Data absensi dimuat.', $result);
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

        foreach ($absensi as $a) {
            $student_id = (int)$a['student_id'];
            $status = in_array($a['status'], ['H','S','I','A']) ? $a['status'] : 'H';
            $keterangan = isset($a['keterangan']) ? trim($a['keterangan']) : '';

            $stmt = db()->prepare("
                INSERT INTO acad_absensi (student_id, kelas_id, academic_year_id, tanggal, jam_ke, status, keterangan, dicatat_oleh)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), keterangan = VALUES(keterangan), dicatat_oleh = VALUES(dicatat_oleh)
            ");
            $stmt->execute([$student_id, $kelas_id, $year_id, $tanggal, $jam_ke, $status, $keterangan, $user['user_id']]);
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
 * Rekapitulasi absensi
 */
function rekapAbsensi($user) {
    try {
        $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-01');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        $where = "a.academic_year_id = ? AND a.tanggal BETWEEN ? AND ?";
        $params = [$year_id, $tanggal, $tanggal_akhir];

        if ($kelas_id > 0) {
            $where .= " AND a.kelas_id = ?";
            $params[] = $kelas_id;
        }

        $stmt = db()->prepare("
            SELECT s.nama, s.nis, s.kelas,
                   SUM(CASE WHEN a.status = 'H' THEN 1 ELSE 0 END) as hadir,
                   SUM(CASE WHEN a.status = 'S' THEN 1 ELSE 0 END) as sakit,
                   SUM(CASE WHEN a.status = 'I' THEN 1 ELSE 0 END) as izin,
                   SUM(CASE WHEN a.status = 'A' THEN 1 ELSE 0 END) as alpha,
                   COUNT(*) as total
            FROM acad_absensi a
            JOIN students s ON a.student_id = s.id
            WHERE $where AND a.jam_ke = 0
            GROUP BY s.id, s.nama, s.nis, s.kelas
            ORDER BY s.kelas, s.nama
        ");
        $stmt->execute($params);
        json_response(200, true, 'Rekap absensi dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
