<?php
/**
 * E-Academic Penugasan Mengajar API
 */
require_once __DIR__ . '/auth_helper.php';

// Check auth
$user = acad_auth();

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        listMengajar();
        break;
    case 'get':
        getMengajar();
        break;
    case 'create':
        acad_require_admin($user);
        createMengajar();
        break;
    case 'update':
        acad_require_admin($user);
        updateMengajar();
        break;
    case 'delete':
        acad_require_admin($user);
        deleteMengajar();
        break;
    case 'meta':
        getFormMeta();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List all teaching assignments for the active academic year
 */
function listMengajar() {
    try {
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        if (!$year_id) {
            json_response(200, true, 'Tahun ajaran aktif belum ditentukan.', []);
        }

        $stmt = db()->prepare("
            SELECT m.*, u.nama_lengkap as guru_nama, u.username as guru_nik, 
                   map.kode_mapel, map.nama_mapel, k.nama_kelas, ay.tahun_ajaran, ay.semester
            FROM acad_mengajar m 
            JOIN users u ON m.guru_id = u.id 
            JOIN acad_mapel map ON m.mapel_id = map.id 
            JOIN acad_kelas k ON m.kelas_id = k.id 
            JOIN academic_years ay ON m.academic_year_id = ay.id
            WHERE m.academic_year_id = ?
            ORDER BY u.nama_lengkap ASC, k.nama_kelas ASC
        ");
        $stmt->execute([$year_id]);
        $data = $stmt->fetchAll();

        json_response(200, true, 'Penugasan mengajar berhasil dimuat.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get single teaching assignment
 */
function getMengajar() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        json_response(400, false, 'ID tidak valid.');
    }

    try {
        $stmt = db()->prepare("SELECT * FROM acad_mengajar WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        if (!$data) {
            json_response(404, false, 'Data penugasan tidak ditemukan.');
        }
        json_response(200, true, 'Data penugasan.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Create teaching assignment
 */
function createMengajar() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $guru_id = isset($input['guru_id']) ? (int)$input['guru_id'] : 0;
    $mapel_id = isset($input['mapel_id']) ? (int)$input['mapel_id'] : 0;
    $kelas_id = isset($input['kelas_id']) ? (int)$input['kelas_id'] : 0;

    $active_year = get_active_academic_year();
    $year_id = $active_year['id'] ?? 0;

    if ($guru_id <= 0 || $mapel_id <= 0 || $kelas_id <= 0 || $year_id <= 0) {
        json_response(400, false, 'Semua kolom formulir harus diisi.');
    }

    try {
        // Check duplicate assignment
        $stmt = db()->prepare("
            SELECT id FROM acad_mengajar 
            WHERE guru_id = ? AND mapel_id = ? AND kelas_id = ? AND academic_year_id = ?
        ");
        $stmt->execute([$guru_id, $mapel_id, $kelas_id, $year_id]);
        if ($stmt->fetch()) {
            json_response(400, false, 'Penugasan mengajar ini sudah terdaftar.');
        }

        $stmt = db()->prepare("
            INSERT INTO acad_mengajar (guru_id, mapel_id, kelas_id, academic_year_id) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$guru_id, $mapel_id, $kelas_id, $year_id]);

        json_response(201, true, 'Penugasan mengajar berhasil ditambahkan.', ['id' => db()->lastInsertId()]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Update teaching assignment
 */
function updateMengajar() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $guru_id = isset($input['guru_id']) ? (int)$input['guru_id'] : 0;
    $mapel_id = isset($input['mapel_id']) ? (int)$input['mapel_id'] : 0;
    $kelas_id = isset($input['kelas_id']) ? (int)$input['kelas_id'] : 0;

    $active_year = get_active_academic_year();
    $year_id = $active_year['id'] ?? 0;

    if ($id <= 0 || $guru_id <= 0 || $mapel_id <= 0 || $kelas_id <= 0 || $year_id <= 0) {
        json_response(400, false, 'Data tidak lengkap atau tidak valid.');
    }

    try {
        // Check duplicate assignment excluding self
        $stmt = db()->prepare("
            SELECT id FROM acad_mengajar 
            WHERE guru_id = ? AND mapel_id = ? AND kelas_id = ? AND academic_year_id = ? AND id != ?
        ");
        $stmt->execute([$guru_id, $mapel_id, $kelas_id, $year_id, $id]);
        if ($stmt->fetch()) {
            json_response(400, false, 'Penugasan mengajar serupa sudah terdaftar.');
        }

        $stmt = db()->prepare("
            UPDATE acad_mengajar 
            SET guru_id = ?, mapel_id = ?, kelas_id = ? 
            WHERE id = ? AND academic_year_id = ?
        ");
        $stmt->execute([$guru_id, $mapel_id, $kelas_id, $id, $year_id]);

        json_response(200, true, 'Penugasan mengajar berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Delete teaching assignment
 */
function deleteMengajar() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if ($id <= 0) {
        json_response(400, false, 'ID tidak valid.');
    }

    try {
        $stmt = db()->prepare("DELETE FROM acad_mengajar WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Penugasan mengajar berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get meta info to populate select elements (teachers, active classes, active subjects)
 */
function getFormMeta() {
    try {
        // 1. Teachers
        $stmt = db()->query("SELECT id, nama_lengkap, username FROM users WHERE role = 'guru' AND status = 1 ORDER BY nama_lengkap ASC");
        $teachers = $stmt->fetchAll();

        // 2. Classes
        $stmt = db()->query("SELECT id, nama_kelas, tingkat FROM acad_kelas ORDER BY tingkat ASC, nama_kelas ASC");
        $classes = $stmt->fetchAll();

        // 3. Active subjects
        $stmt = db()->query("SELECT id, kode_mapel, nama_mapel, kelompok FROM acad_mapel WHERE status = 1 ORDER BY kelompok ASC, nama_mapel ASC");
        $subjects = $stmt->fetchAll();

        json_response(200, true, 'Form metadata.', [
            'teachers' => $teachers,
            'classes' => $classes,
            'subjects' => $subjects
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
