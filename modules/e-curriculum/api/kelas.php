<?php
/**
 * E-Curriculum Kelas API
 */
require_once __DIR__ . '/auth_helper.php';

// Check auth
$user = acad_auth();

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        listKelas();
        break;
    case 'get':
        getKelas();
        break;
    case 'create':
        acad_require_admin($user);
        createKelas();
        break;
    case 'update':
        acad_require_admin($user);
        updateKelas();
        break;
    case 'delete':
        acad_require_admin($user);
        deleteKelas();
        break;
    case 'teachers':
        listTeachers();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List all classes
 */
function listKelas() {
    try {
        $stmt = db()->query("
            SELECT k.*, u.nama_lengkap as wali_nama 
            FROM acad_kelas k 
            LEFT JOIN users u ON k.wali_id = u.id 
            ORDER BY k.tingkat ASC, k.nama_kelas ASC
        ");
        $data = $stmt->fetchAll();
        json_response(200, true, 'Data kelas berhasil dimuat.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get single class
 */
function getKelas() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        json_response(400, false, 'ID tidak valid.');
    }

    try {
        $stmt = db()->prepare("SELECT * FROM acad_kelas WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        if (!$data) {
            json_response(404, false, 'Data kelas tidak ditemukan.');
        }
        json_response(200, true, 'Data kelas.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Create new class
 */
function createKelas() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $nama_kelas = isset($input['nama_kelas']) ? sanitize($input['nama_kelas']) : '';
    $tingkat = isset($input['tingkat']) ? (int)$input['tingkat'] : 0;
    $wali_id = isset($input['wali_id']) && $input['wali_id'] !== '' ? (int)$input['wali_id'] : null;

    if (empty($nama_kelas) || $tingkat <= 0) {
        json_response(400, false, 'Nama kelas dan tingkat harus diisi.');
    }

    try {
        // Check duplicate name
        $stmt = db()->prepare("SELECT id FROM acad_kelas WHERE nama_kelas = ?");
        $stmt->execute([$nama_kelas]);
        if ($stmt->fetch()) {
            json_response(400, false, 'Nama kelas sudah terdaftar.');
        }

        $stmt = db()->prepare("
            INSERT INTO acad_kelas (nama_kelas, tingkat, wali_id) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$nama_kelas, $tingkat, $wali_id]);

        json_response(201, true, 'Kelas berhasil ditambahkan.', ['id' => db()->lastInsertId()]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Update class
 */
function updateKelas() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $nama_kelas = isset($input['nama_kelas']) ? sanitize($input['nama_kelas']) : '';
    $tingkat = isset($input['tingkat']) ? (int)$input['tingkat'] : 0;
    $wali_id = isset($input['wali_id']) && $input['wali_id'] !== '' ? (int)$input['wali_id'] : null;

    if ($id <= 0 || empty($nama_kelas) || $tingkat <= 0) {
        json_response(400, false, 'Data tidak lengkap atau tidak valid.');
    }

    try {
        // Check duplicate name excluding self
        $stmt = db()->prepare("SELECT id FROM acad_kelas WHERE nama_kelas = ? AND id != ?");
        $stmt->execute([$nama_kelas, $id]);
        if ($stmt->fetch()) {
            json_response(400, false, 'Nama kelas sudah terdaftar.');
        }

        $stmt = db()->prepare("
            UPDATE acad_kelas 
            SET nama_kelas = ?, tingkat = ?, wali_id = ? 
            WHERE id = ?
        ");
        $stmt->execute([$nama_kelas, $tingkat, $wali_id, $id]);

        json_response(200, true, 'Kelas berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Delete class
 */
function deleteKelas() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if ($id <= 0) {
        json_response(400, false, 'ID tidak valid.');
    }

    try {
        $stmt = db()->prepare("DELETE FROM acad_kelas WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Kelas berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * List all available teachers (users table where role = 'guru')
 */
function listTeachers() {
    try {
        $stmt = db()->query("
            SELECT id, nama_lengkap, username 
            FROM users 
            WHERE role = 'guru' AND status = 1 
            ORDER BY nama_lengkap ASC
        ");
        $data = $stmt->fetchAll();
        json_response(200, true, 'Data guru berhasil dimuat.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
