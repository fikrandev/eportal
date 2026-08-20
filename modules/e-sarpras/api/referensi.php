<?php
/**
 * E-Sarpras Reference API
 * Managing lookup data like Funding Sources (Asal Dana)
 */
require_once __DIR__ . '/../../../api/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Validation from tanah.php (sp_auth) is similar, let's keep it consistent
require_once __DIR__ . '/auth_helper.php';

switch ($action) {
    case 'list': listRef(); break;
    case 'create': createRef(); break;
    case 'update': updateRef(); break;
    case 'delete': deleteRef(); break;
    default: json_response(400, false, 'Invalid action');
}

function listRef() {
    sp_auth();
    $cat = isset($_GET['kategori']) ? $_GET['kategori'] : '';
    $where = $cat ? "WHERE kategori = ?" : "";
    $params = $cat ? [$cat] : [];
    
    $stmt = db()->prepare("SELECT * FROM sarpras_referensi $where ORDER BY kategori ASC, nama ASC");
    $stmt->execute($params);
    json_response(200, true, 'OK', $stmt->fetchAll());
}

function createRef() {
    $user = sp_auth();
    sp_require_any($user, ['referensi_manage'], 'Akses ditolak');
    
    $input = get_input();
    $cat = sanitize($input['kategori'] ?? '');
    $nama = sanitize($input['nama'] ?? '');
    $kode = sanitize($input['kode'] ?? '');
    $ket = sanitize($input['keterangan'] ?? '');
    
    if (empty($cat) || empty($nama)) json_response(400, false, 'Kategori dan nama wajib diisi');
    
    try {
        db()->prepare("INSERT INTO sarpras_referensi (kategori, kode, nama, keterangan) VALUES (?,?,?,?)")->execute([$cat, $kode, $nama, $ket]);
        json_response(201, true, 'Referensi berhasil ditambah');
    } catch (PDOException $e) {
        json_response(500, false, $e->getMessage());
    }
}

function updateRef() {
    $user = sp_auth();
    sp_require_any($user, ['referensi_manage'], 'Akses ditolak');
    
    $input = get_input();
    $id = intval($input['id'] ?? 0);
    $nama = sanitize($input['nama'] ?? '');
    $kode = sanitize($input['kode'] ?? '');
    $ket = sanitize($input['keterangan'] ?? '');
    
    if ($id <= 0 || empty($nama)) json_response(400, false, 'Data tidak lengkap');
    
    // Check lock
    $stmt = db()->prepare("SELECT kategori, nama FROM sarpras_referensi WHERE id=?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if ($old && $old['kategori'] === 'jenis_ruang' && in_array($old['nama'], ['Ruang Kelas', 'Perpustakaan'])) {
        json_response(400, false, 'Tipe ruangan ini dikunci oleh sistem dan tidak dapat diubah');
    }
    
    try {
        db()->prepare("UPDATE sarpras_referensi SET nama=?, keterangan=?, kode=? WHERE id=?")->execute([$nama, $ket, $kode, $id]);
        json_response(200, true, 'Referensi berhasil diupdate');
    } catch (PDOException $e) {
        json_response(500, false, $e->getMessage());
    }
}

function deleteRef() {
    $user = sp_auth();
    sp_require_any($user, ['referensi_manage'], 'Akses ditolak');
    
    $input = get_input();
    $id = intval($input['id'] ?? 0);
    
    // Check lock
    $stmt = db()->prepare("SELECT kategori, nama FROM sarpras_referensi WHERE id=?");
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if ($old && $old['kategori'] === 'jenis_ruang' && in_array($old['nama'], ['Ruang Kelas', 'Perpustakaan'])) {
        json_response(400, false, 'Tipe ruangan ini dikunci oleh sistem dan tidak dapat dihapus');
    }
    
    try {
        db()->prepare("DELETE FROM sarpras_referensi WHERE id=?")->execute([$id]);
        json_response(200, true, 'Referensi berhasil dihapus');
    } catch (PDOException $e) {
        json_response(500, false, $e->getMessage());
    }
}
