<?php
/**
 * Guru App — Jurnal Mengajar API
 * CRUD operations for teaching journal
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
    case 'list':
        listJurnal($user);
        break;
    case 'get':
        getJurnal($user);
        break;
    case 'create':
        createJurnal($user);
        break;
    case 'update':
        updateJurnal($user);
        break;
    case 'delete':
        deleteJurnal($user);
        break;
    case 'meta':
        getJurnalMeta($user);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List jurnal entries for teacher
 */
function listJurnal($user) {
    try {
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';

        $where = "j.academic_year_id = ? AND j.guru_id = ?";
        $params = [$year_id, $user['user_id']];

        if (!empty($tanggal_akhir)) {
            $where .= " AND j.tanggal BETWEEN ? AND ?";
            $params[] = $tanggal;
            $params[] = $tanggal_akhir;
        } else {
            $where .= " AND j.tanggal = ?";
            $params[] = $tanggal;
        }

        $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
        if ($kelas_id > 0) {
            $where .= " AND j.kelas_id = ?";
            $params[] = $kelas_id;
        }

        $stmt = db()->prepare("
            SELECT j.*, k.nama_kelas, m.nama_mapel
            FROM acad_jurnal j
            JOIN sch_kelas k ON j.kelas_id = k.id
            JOIN sch_mapel m ON j.mapel_id = m.id
            WHERE $where
            ORDER BY j.tanggal DESC, j.jam_ke ASC
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        json_response(200, true, 'Data jurnal dimuat.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get single jurnal by ID
 */
function getJurnal($user) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        $stmt = db()->prepare("
            SELECT j.*, k.nama_kelas, m.nama_mapel
            FROM acad_jurnal j
            JOIN sch_kelas k ON j.kelas_id = k.id
            JOIN sch_mapel m ON j.mapel_id = m.id
            WHERE j.id = ? AND j.guru_id = ?
        ");
        $stmt->execute([$id, $user['user_id']]);
        $data = $stmt->fetch();

        if (!$data) json_response(404, false, 'Jurnal tidak ditemukan.');
        json_response(200, true, 'Data jurnal.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Create new jurnal entry
 */
function createJurnal($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $kelas_id = isset($input['kelas_id']) ? (int)$input['kelas_id'] : 0;
    $mapel_id = isset($input['mapel_id']) ? (int)$input['mapel_id'] : 0;
    $tanggal = isset($input['tanggal']) ? $input['tanggal'] : date('Y-m-d');
    
    // Strict restriction: Can only fill journal for today
    if ($tanggal !== date('Y-m-d')) {
        json_response(400, false, 'Jurnal hanya bisa diisi pada hari ini.');
    }

    $jam_ke = isset($input['jam_ke']) ? trim($input['jam_ke']) : '';
    $tp = isset($input['tujuan_pembelajaran']) ? trim($input['tujuan_pembelajaran']) : '';
    $iptp = isset($input['indikator_tp']) ? trim($input['indikator_tp']) : '';
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';
    $siswa_tidak_hadir = isset($input['siswa_tidak_hadir']) ? $input['siswa_tidak_hadir'] : '';

    $active_year = get_active_academic_year();
    $year_id = $active_year['id'] ?? 0;

    if ($kelas_id <= 0 || $mapel_id <= 0 || empty($jam_ke)) {
        json_response(400, false, 'Kelas, Mata Pelajaran, dan Jam wajib diisi.');
    }

    try {
        // Check for duplicate
        $stmtCheck = db()->prepare("
            SELECT id FROM acad_jurnal 
            WHERE guru_id = ? AND kelas_id = ? AND mapel_id = ? AND tanggal = ? AND jam_ke = ? AND academic_year_id = ?
        ");
        $stmtCheck->execute([$user['user_id'], $kelas_id, $mapel_id, $tanggal, $jam_ke, $year_id]);
        if ($stmtCheck->fetch()) {
            json_response(409, false, 'Jurnal untuk jadwal ini sudah pernah diisi.');
        }

        $stmt = db()->prepare("
            INSERT INTO acad_jurnal (guru_id, kelas_id, mapel_id, academic_year_id, tanggal, jam_ke, tujuan_pembelajaran, indikator_tp, catatan, siswa_tidak_hadir)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['user_id'], $kelas_id, $mapel_id, $year_id, $tanggal, $jam_ke, $tp, $iptp, $catatan,
            is_array($siswa_tidak_hadir) ? json_encode($siswa_tidak_hadir) : $siswa_tidak_hadir
        ]);

        json_response(201, true, 'Jurnal mengajar berhasil disimpan.', ['id' => db()->lastInsertId()]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Update jurnal entry
 */
function updateJurnal($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    // Strict restriction: Can only edit today's journal
    try {
        $stmtCheckDate = db()->prepare("SELECT tanggal FROM acad_jurnal WHERE id = ? AND guru_id = ?");
        $stmtCheckDate->execute([$id, $user['user_id']]);
        $existingJurnal = $stmtCheckDate->fetch();
        if (!$existingJurnal) {
            json_response(404, false, 'Jurnal tidak ditemukan.');
        }
        if ($existingJurnal['tanggal'] !== date('Y-m-d')) {
            json_response(400, false, 'Hanya jurnal hari ini yang dapat diubah.');
        }
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }

    $tp = isset($input['tujuan_pembelajaran']) ? trim($input['tujuan_pembelajaran']) : '';
    $iptp = isset($input['indikator_tp']) ? trim($input['indikator_tp']) : '';
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';
    $siswa_tidak_hadir = isset($input['siswa_tidak_hadir']) ? $input['siswa_tidak_hadir'] : '';

    try {
        $stmt = db()->prepare("
            UPDATE acad_jurnal SET tujuan_pembelajaran = ?, indikator_tp = ?, catatan = ?, siswa_tidak_hadir = ?
            WHERE id = ? AND guru_id = ?
        ");
        $stmt->execute([
            $tp, $iptp, $catatan,
            is_array($siswa_tidak_hadir) ? json_encode($siswa_tidak_hadir) : $siswa_tidak_hadir,
            $id, $user['user_id']
        ]);

        json_response(200, true, 'Jurnal berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Delete jurnal entry
 */
function deleteJurnal($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        // Enforce restriction: Can only delete today's journal
        $stmtCheckDate = db()->prepare("SELECT tanggal FROM acad_jurnal WHERE id = ? AND guru_id = ?");
        $stmtCheckDate->execute([$id, $user['user_id']]);
        $existingJurnal = $stmtCheckDate->fetch();
        if (!$existingJurnal) {
            json_response(404, false, 'Jurnal tidak ditemukan.');
        }
        if ($existingJurnal['tanggal'] !== date('Y-m-d')) {
            json_response(400, false, 'Hanya jurnal hari ini yang dapat dihapus.');
        }

        $stmt = db()->prepare("DELETE FROM acad_jurnal WHERE id = ? AND guru_id = ?");
        $stmt->execute([$id, $user['user_id']]);
        json_response(200, true, 'Jurnal berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get metadata for jurnal form (classes, subjects, students)
 */
function getJurnalMeta($user) {
    try {
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        // Get classes this teacher teaches
        $stmt = db()->prepare("
            SELECT DISTINCT k.id, k.nama_kelas, k.tingkat
            FROM sch_distribusi d
            JOIN sch_kelas k ON d.kelas_id = k.id
            JOIN sch_guru g ON d.guru_id = g.id
            WHERE g.kode_guru = ?
            ORDER BY k.tingkat, k.nama_kelas
        ");
        $stmt->execute([$user['username']]);
        $classes = $stmt->fetchAll();

        // Get subjects this teacher teaches
        $stmt = db()->prepare("
            SELECT DISTINCT mp.id, mp.kode_mapel, mp.nama_mapel
            FROM sch_distribusi d
            JOIN sch_mapel mp ON d.mapel_id = mp.id
            JOIN sch_guru g ON d.guru_id = g.id
            WHERE g.kode_guru = ?
            ORDER BY mp.nama_mapel
        ");
        $stmt->execute([$user['username']]);
        $subjects = $stmt->fetchAll();

        // Get students for the classes
        $students = [];
        if (count($classes) > 0) {
            $classNames = [];
            foreach ($classes as $c) $classNames[$c['id']] = $c['nama_kelas'];

            $placeholders = implode(',', array_fill(0, count($classNames), '?'));
            $stmt = db()->prepare("SELECT id, nis, nama, kelas FROM students WHERE kelas IN ($placeholders) AND status = 1 ORDER BY kelas, nama");
            $stmt->execute(array_values($classNames));
            $students = $stmt->fetchAll();
        }

        json_response(200, true, 'Metadata jurnal.', [
            'classes' => $classes,
            'subjects' => $subjects,
            'students' => $students
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
