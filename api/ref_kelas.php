<?php
/**
 * E-Portal API untuk Data Referensi Kelas
 * CRUD for ref_kelas table
 */
require_once __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list': listRefKelas(); break;
    case 'create': createRefKelas(); break;
    case 'update': updateRefKelas(); break;
    case 'delete': deleteRefKelas(); break;
    case 'get': getRefKelas(); break;
    default: json_response(400, false, 'Action tidak valid.');
}

function listRefKelas() {
    $stmt = db()->prepare("SELECT * FROM ref_kelas ORDER BY tingkat ASC, nama_kelas ASC");
    $stmt->execute();
    json_response(200, true, 'Data kelas berhasil dimuat.', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getRefKelas() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $stmt = db()->prepare("SELECT * FROM ref_kelas WHERE id = ?");
    $stmt->execute([$id]);
    json_response(200, true, 'OK', $stmt->fetch(PDO::FETCH_ASSOC));
}

function createRefKelas() {
    require_superadmin();
    $input = get_input();
    $tingkat = sanitize($input['tingkat'] ?? '');
    $nama_kelas = sanitize($input['nama_kelas'] ?? '');
    $keterangan = sanitize($input['keterangan'] ?? '');
    
    if (!$tingkat || !$nama_kelas) json_response(400, false, 'Tingkat dan Nama Kelas wajib diisi.');
    
    $stmt = db()->prepare("INSERT INTO ref_kelas (tingkat, nama_kelas, keterangan) VALUES (?, ?, ?)");
    $stmt->execute([$tingkat, $nama_kelas, $keterangan]);
    json_response(201, true, 'Data Kelas berhasil ditambahkan.');
}

function updateRefKelas() {
    require_superadmin();
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $tingkat = sanitize($input['tingkat'] ?? '');
    $nama_kelas = sanitize($input['nama_kelas'] ?? '');
    $keterangan = sanitize($input['keterangan'] ?? '');
    
    if (!$id || !$tingkat || !$nama_kelas) json_response(400, false, 'ID, Tingkat dan Nama Kelas wajib diisi.');
    
    $stmt = db()->prepare("UPDATE ref_kelas SET tingkat=?, nama_kelas=?, keterangan=? WHERE id=?");
    $stmt->execute([$tingkat, $nama_kelas, $keterangan, $id]);
    json_response(200, true, 'Data Kelas berhasil diperbarui.');
}

function deleteRefKelas() {
    require_superadmin();
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if (!$id) json_response(400, false, 'ID tidak valid.');
    
    $stmt = db()->prepare("DELETE FROM ref_kelas WHERE id=?");
    $stmt->execute([$id]);
    json_response(200, true, 'Data Kelas berhasil dihapus.');
}
