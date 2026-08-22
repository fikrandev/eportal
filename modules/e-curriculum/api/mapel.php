<?php
/**
 * E-Curriculum Mapel (Subjects) API
 */
require_once __DIR__ . '/auth_helper.php';

// Check auth
$user = acad_auth();

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        listMapel();
        break;
    case 'get':
        getMapel();
        break;
    case 'create':
        acad_require_admin($user);
        createMapel();
        break;
    case 'update':
        acad_require_admin($user);
        updateMapel();
        break;
    case 'delete':
        acad_require_admin($user);
        deleteMapel();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List all subjects
 */
function listMapel() {
    try {
        $stmt = db()->query("SELECT * FROM acad_mapel ORDER BY kelompok ASC, nama_mapel ASC");
        $data = $stmt->fetchAll();
        json_response(200, true, 'Data mata pelajaran berhasil dimuat.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get single subject
 */
function getMapel() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        json_response(400, false, 'ID tidak valid.');
    }

    try {
        $stmt = db()->prepare("SELECT * FROM acad_mapel WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        if (!$data) {
            json_response(404, false, 'Mata pelajaran tidak ditemukan.');
        }
        json_response(200, true, 'Data mata pelajaran.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Create subject
 */
function createMapel() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $kode_mapel = isset($input['kode_mapel']) ? sanitize($input['kode_mapel']) : '';
    $nama_mapel = isset($input['nama_mapel']) ? sanitize($input['nama_mapel']) : '';
    $kelompok = isset($input['kelompok']) ? sanitize($input['kelompok']) : 'Kelompok A';
    $kkm = isset($input['kkm']) ? (int)$input['kkm'] : 75;
    $status = isset($input['status']) ? (int)$input['status'] : 1;

    if (empty($kode_mapel) || empty($nama_mapel)) {
        json_response(400, false, 'Kode mapel dan nama mapel harus diisi.');
    }

    try {
        // Check duplicate code
        $stmt = db()->prepare("SELECT id FROM acad_mapel WHERE kode_mapel = ?");
        $stmt->execute([$kode_mapel]);
        if ($stmt->fetch()) {
            json_response(400, false, 'Kode mapel sudah terdaftar.');
        }

        $stmt = db()->prepare("
            INSERT INTO acad_mapel (kode_mapel, nama_mapel, kelompok, kkm, status) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$kode_mapel, $nama_mapel, $kelompok, $kkm, $status]);

        json_response(201, true, 'Mata pelajaran berhasil ditambahkan.', ['id' => db()->lastInsertId()]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Update subject
 */
function updateMapel() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $kode_mapel = isset($input['kode_mapel']) ? sanitize($input['kode_mapel']) : '';
    $nama_mapel = isset($input['nama_mapel']) ? sanitize($input['nama_mapel']) : '';
    $kelompok = isset($input['kelompok']) ? sanitize($input['kelompok']) : 'Kelompok A';
    $kkm = isset($input['kkm']) ? (int)$input['kkm'] : 75;
    $status = isset($input['status']) ? (int)$input['status'] : 1;

    if ($id <= 0 || empty($kode_mapel) || empty($nama_mapel)) {
        json_response(400, false, 'Data tidak lengkap atau tidak valid.');
    }

    try {
        // Check duplicate code excluding self
        $stmt = db()->prepare("SELECT id FROM acad_mapel WHERE kode_mapel = ? AND id != ?");
        $stmt->execute([$kode_mapel, $id]);
        if ($stmt->fetch()) {
            json_response(400, false, 'Kode mapel sudah terdaftar.');
        }

        $stmt = db()->prepare("
            UPDATE acad_mapel 
            SET kode_mapel = ?, nama_mapel = ?, kelompok = ?, kkm = ?, status = ? 
            WHERE id = ?
        ");
        $stmt->execute([$kode_mapel, $nama_mapel, $kelompok, $kkm, $status, $id]);

        json_response(200, true, 'Mata pelajaran berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Delete subject
 */
function deleteMapel() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if ($id <= 0) {
        json_response(400, false, 'ID tidak valid.');
    }

    try {
        $stmt = db()->prepare("DELETE FROM acad_mapel WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Mata pelajaran berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
