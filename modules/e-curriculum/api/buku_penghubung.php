<?php
/**
 * E-Curriculum Buku Penghubung API
 */
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        listBuku($user);
        break;
    case 'create':
        createBuku($user);
        break;
    case 'update':
        updateBuku($user);
        break;
    case 'delete':
        deleteBuku($user);
        break;
    case 'student_detail':
        studentDetail($user);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function listBuku($user) {
    try {
        $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
        $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
        $jenis = isset($_GET['jenis']) ? $_GET['jenis'] : '';
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        $where = "b.academic_year_id = ?";
        $params = [$year_id];

        if ($kelas_id > 0) {
            $where .= " AND b.kelas_id = ?";
            $params[] = $kelas_id;
        }
        if ($student_id > 0) {
            $where .= " AND b.student_id = ?";
            $params[] = $student_id;
        }
        if (!empty($jenis)) {
            $where .= " AND b.jenis = ?";
            $params[] = $jenis;
        }

        // If teacher (not admin), only show for their wali kelas
        if ($user['role'] !== 'superadmin') {
            $stmtW = db()->prepare("SELECT id FROM sch_kelas WHERE wali_id = ?");
            $stmtW->execute([$user['user_id']]);
            $waliKelas = $stmtW->fetchAll(PDO::FETCH_COLUMN);
            if (empty($waliKelas)) {
                json_response(200, true, 'Anda bukan wali kelas.', []);
            }
            $placeholders = implode(',', array_fill(0, count($waliKelas), '?'));
            $where .= " AND b.kelas_id IN ($placeholders)";
            $params = array_merge($params, $waliKelas);
        }

        $stmt = db()->prepare("
            SELECT b.*, s.nama as nama_siswa, s.nis, k.nama_kelas, u.nama_lengkap as dicatat_nama
            FROM acad_buku_penghubung b
            JOIN students s ON b.student_id = s.id
            JOIN sch_kelas k ON b.kelas_id = k.id
            LEFT JOIN users u ON b.dicatat_oleh = u.id
            WHERE $where
            ORDER BY b.tanggal DESC, s.nama ASC
        ");
        $stmt->execute($params);
        json_response(200, true, 'Data buku penghubung dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function createBuku($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $student_id = isset($input['student_id']) ? (int)$input['student_id'] : 0;
    $kelas_id = isset($input['kelas_id']) ? (int)$input['kelas_id'] : 0;
    $jenis = isset($input['jenis']) ? $input['jenis'] : '';
    $tanggal = isset($input['tanggal']) ? $input['tanggal'] : date('Y-m-d');
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';

    $active_year = get_active_academic_year();
    $year_id = $active_year['id'] ?? 0;

    $validJenis = ['Keterlambatan','Pelanggaran','Prestasi','Screening','Konsultasi'];
    if ($student_id <= 0 || $kelas_id <= 0 || !in_array($jenis, $validJenis) || empty($catatan)) {
        json_response(400, false, 'Semua kolom wajib diisi dengan benar.');
    }

    try {
        $stmt = db()->prepare("
            INSERT INTO acad_buku_penghubung (student_id, kelas_id, academic_year_id, jenis, tanggal, catatan, dicatat_oleh)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$student_id, $kelas_id, $year_id, $jenis, $tanggal, $catatan, $user['user_id']]);
        json_response(201, true, 'Catatan berhasil ditambahkan.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function updateBuku($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';
    $jenis = isset($input['jenis']) ? $input['jenis'] : '';

    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        $stmt = db()->prepare("UPDATE acad_buku_penghubung SET catatan = ?, jenis = ? WHERE id = ?");
        $stmt->execute([$catatan, $jenis, $id]);
        json_response(200, true, 'Catatan berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteBuku($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        $stmt = db()->prepare("DELETE FROM acad_buku_penghubung WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Catatan berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function studentDetail($user) {
    $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
    if ($student_id <= 0) json_response(400, false, 'ID siswa tidak valid.');

    try {
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        $stmtS = db()->prepare("SELECT * FROM students WHERE id = ?");
        $stmtS->execute([$student_id]);
        $student = $stmtS->fetch();
        if (!$student) json_response(404, false, 'Siswa tidak ditemukan.');

        // Get absensi summary
        $stmtA = db()->prepare("
            SELECT 
                SUM(CASE WHEN status='S' THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN status='I' THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN status='A' THEN 1 ELSE 0 END) as alpha
            FROM acad_absensi WHERE student_id = ? AND academic_year_id = ? AND jam_ke = 0
        ");
        $stmtA->execute([$student_id, $year_id]);
        $absensi = $stmtA->fetch();

        // Get buku penghubung entries
        $stmtB = db()->prepare("
            SELECT b.*, u.nama_lengkap as dicatat_nama
            FROM acad_buku_penghubung b
            LEFT JOIN users u ON b.dicatat_oleh = u.id
            WHERE b.student_id = ? AND b.academic_year_id = ?
            ORDER BY b.tanggal DESC
        ");
        $stmtB->execute([$student_id, $year_id]);
        $entries = $stmtB->fetchAll();

        json_response(200, true, 'Detail siswa dimuat.', [
            'student' => $student,
            'absensi' => $absensi,
            'entries' => $entries
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
