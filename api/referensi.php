<?php
/**
 * E-Portal Referensi API
 * CRUD for sarpras_referensi table (Shared with e-sarpras)
 */
require_once __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list': listRef(); break;
    case 'create': createRef(); break;
    case 'update': updateRef(); break;
    case 'delete': deleteRef(); break;
    case 'get': getRef(); break;
    default: json_response(400, false, 'Action tidak valid.');
}

function listRef() {
    $kategori = isset($_GET['kategori']) ? sanitize($_GET['kategori']) : '';
    $where = "WHERE 1=1";
    $params = [];
    if ($kategori) {
        $where .= " AND kategori = ?";
        $params[] = $kategori;
    }
    $stmt = db()->prepare("SELECT * FROM sarpras_referensi $where ORDER BY kategori ASC, nama ASC");
    $stmt->execute($params);
    json_response(200, true, 'Data referensi berhasil dimuat.', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getRef() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $stmt = db()->prepare("SELECT * FROM sarpras_referensi WHERE id = ?");
    $stmt->execute([$id]);
    json_response(200, true, 'OK', $stmt->fetch(PDO::FETCH_ASSOC));
}

function createRef() {
    require_superadmin();
    $input = get_input();
    $kategori = sanitize($input['kategori'] ?? '');
    $nama = sanitize($input['nama'] ?? '');
    if (!$kategori || !$nama) json_response(400, false, 'Kategori dan nama wajib diisi.');
    
    $stmt = db()->prepare("INSERT INTO sarpras_referensi (kategori, nama, keterangan) VALUES (?, ?, ?)");
    $stmt->execute([$kategori, $nama, sanitize($input['keterangan'] ?? '')]);
    json_response(201, true, 'Referensi berhasil ditambahkan.');
}

function updateRef() {
    require_superadmin();
    $input = get_input();
    $id = (int)($input['id'] ?? 0);
    $kategori = sanitize($input['kategori'] ?? '');
    $nama = sanitize($input['nama'] ?? '');
    if ($id <= 0 || !$kategori || !$nama) json_response(400, false, 'Data tidak valid.');
    
    $stmt = db()->prepare("UPDATE sarpras_referensi SET kategori=?, nama=?, keterangan=? WHERE id=?");
    $stmt->execute([$kategori, $nama, sanitize($input['keterangan'] ?? ''), $id]);
    json_response(200, true, 'Referensi berhasil diubah.');
}

function deleteRef() {
    require_superadmin();
    $input = get_input();
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');
    
    $stmt = db()->prepare("DELETE FROM sarpras_referensi WHERE id=?");
    $stmt->execute([$id]);
    json_response(200, true, 'Referensi berhasil dihapus.');
}
