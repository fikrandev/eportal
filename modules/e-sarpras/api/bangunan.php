<?php
/**
 * E-Sarpras Bangunan API
 */
require_once __DIR__ . '/../../../api/config.php';

require_once __DIR__ . '/auth_helper.php';

$action = $_GET['action'] ?? '';
switch ($action) {
    case 'list': listBangunan(); break;
    case 'get': getBangunan(); break;
    case 'create': createBangunan(); break;
    case 'update': updateBangunan(); break;
    case 'delete': deleteBangunan(); break;
    case 'upload-foto': uploadFoto(); break;
    case 'delete-foto': deleteFoto(); break;
    default: json_response(400, false, 'Invalid action');
}

function listBangunan() {
    sp_auth();
    $tanahId = isset($_GET['tanah_id']) ? (int)$_GET['tanah_id'] : 0;
    $search = $_GET['search'] ?? '';
    
    $where = $tanahId > 0 ? "WHERE b.tanah_id = $tanahId" : "WHERE 1=1";
    $params = [];
    if ($search) { $where .= " AND (b.nama LIKE ? OR t.nama LIKE ?)"; $params = ["%$search%","%$search%"]; }
    
    $stmt = db()->prepare("SELECT b.*, t.nama as tanah_nama,
        (SELECT COUNT(*) FROM ruang WHERE bangunan_id=b.id) as jumlah_ruang,
        (SELECT COUNT(*) FROM bangunan_foto WHERE bangunan_id=b.id) as jumlah_foto
        FROM bangunan b JOIN tanah t ON b.tanah_id=t.id $where ORDER BY b.nama ASC");
    $stmt->execute($params);
    json_response(200, true, 'OK', $stmt->fetchAll());
}

function getBangunan() {
    sp_auth();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_response(400, false, 'ID tidak valid');
    $stmt = db()->prepare("SELECT b.*, t.nama as tanah_nama FROM bangunan b JOIN tanah t ON b.tanah_id=t.id WHERE b.id=?");
    $stmt->execute([$id]);
    $data = $stmt->fetch();
    if (!$data) json_response(404, false, 'Tidak ditemukan');
    $f = db()->prepare("SELECT * FROM bangunan_foto WHERE bangunan_id=? ORDER BY urutan");
    $f->execute([$id]);
    $data['fotos'] = $f->fetchAll();
    json_response(200, true, 'OK', $data);
}

function createBangunan() {
    $user = sp_auth(); sp_require_any($user, ['bangunan_manage'], 'Akses ditolak');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $d = get_input();
    $tanah_id = (int)($d['tanah_id'] ?? 0);
    $nama = sanitize($d['nama'] ?? '');
    if (!$tanah_id || !$nama) json_response(400, false, 'Tanah dan nama wajib diisi');

    // Auto-generate kode_bangunan if not supplied
    $kode = sanitize($d['kode_bangunan'] ?? '');
    if (empty($kode)) {
        $count = db()->query("SELECT COUNT(*) FROM bangunan")->fetchColumn();
        $kode = 'BGN-' . date('Y') . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    try {
        $stmt = db()->prepare("INSERT INTO bangunan (kode_bangunan,tanah_id,nama,luas_m2,panjang_m,lebar_m,jumlah_lantai,kapasitas,tahun_dibangun,harga_perolehan,asal_anggaran,kondisi,keterangan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $kode, $tanah_id, $nama, floatval($d['luas_m2']??0),
            $d['panjang_m']??null, $d['lebar_m']??null,
            (int)($d['jumlah_lantai']??1), isset($d['kapasitas']) && $d['kapasitas'] !== '' ? (int)$d['kapasitas'] : null,
            (int)($d['tahun_dibangun']??date('Y')), floatval($d['harga_perolehan']??0),
            sanitize($d['asal_anggaran']??''), sanitize($d['kondisi']??'Baik'), sanitize($d['keterangan']??'')
        ]);
        json_response(201, true, 'Bangunan berhasil ditambahkan', ['id' => db()->lastInsertId(), 'kode_bangunan' => $kode]);
    } catch (PDOException $e) { json_response(500, false, 'Error: '.$e->getMessage()); }
}

function updateBangunan() {
    $user = sp_auth(); sp_require_any($user, ['bangunan_manage'], 'Akses ditolak');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $d = get_input();
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) json_response(400, false, 'ID tidak valid');
    $kode = sanitize($d['kode_bangunan'] ?? '');

    try {
        $stmt = db()->prepare("UPDATE bangunan SET kode_bangunan=?,tanah_id=?,nama=?,luas_m2=?,panjang_m=?,lebar_m=?,jumlah_lantai=?,kapasitas=?,tahun_dibangun=?,harga_perolehan=?,asal_anggaran=?,kondisi=?,keterangan=? WHERE id=?");
        $stmt->execute([
            $kode, (int)($d['tanah_id']??0), sanitize($d['nama']??''), floatval($d['luas_m2']??0),
            $d['panjang_m']??null, $d['lebar_m']??null,
            (int)($d['jumlah_lantai']??1), isset($d['kapasitas']) && $d['kapasitas'] !== '' ? (int)$d['kapasitas'] : null,
            (int)($d['tahun_dibangun']??date('Y')), floatval($d['harga_perolehan']??0),
            sanitize($d['asal_anggaran']??''), sanitize($d['kondisi']??'Baik'), sanitize($d['keterangan']??''),
            $id
        ]);
        json_response(200, true, 'Bangunan berhasil diperbarui');
    } catch (PDOException $e) { json_response(500, false, 'Error: '.$e->getMessage()); }
}

function deleteBangunan() {
    $user = sp_auth(); sp_require_any($user, ['bangunan_manage'], 'Akses ditolak');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $d = get_input(); $id = (int)($d['id'] ?? 0);
    if ($id <= 0) json_response(400, false, 'ID tidak valid');
    try {
        db()->prepare("DELETE FROM bangunan WHERE id=?")->execute([$id]);
        json_response(200, true, 'Bangunan berhasil dihapus');
    } catch (PDOException $e) { json_response(500, false, 'Error: '.$e->getMessage()); }
}

function uploadFoto() {
    $user = sp_auth(); sp_require_any($user, ['bangunan_manage'], 'Akses ditolak');
    $id = (int)($_POST['bangunan_id'] ?? 0);
    $cnt = db()->prepare("SELECT COUNT(*) as t FROM bangunan_foto WHERE bangunan_id=?"); $cnt->execute([$id]);
    if ($cnt->fetch()['t'] >= 5) json_response(400, false, 'Maksimal 5 foto');
    if (!isset($_FILES['foto'])) json_response(400, false, 'File required');
    $r = handle_upload($_FILES['foto'], 'sarpras/bangunan/');
    if (!$r['success']) json_response(400, false, $r['message']);
    if (filesize($r['full_path']) > 500*1024) compress_image($r['full_path'], $r['full_path'], 500);
    $stmt = db()->prepare("INSERT INTO bangunan_foto (bangunan_id,foto_path,keterangan,urutan) VALUES (?,?,?,(SELECT COALESCE(MAX(x.urutan),0)+1 FROM bangunan_foto x WHERE x.bangunan_id=?))");
    $stmt->execute([$id, $r['path'], sanitize($_POST['keterangan']??''), $id]);
    json_response(201, true, 'Foto berhasil diupload', ['path' => $r['path']]);
}

function deleteFoto() {
    $user = sp_auth(); sp_require_any($user, ['bangunan_manage'], 'Akses ditolak');
    $d = get_input(); $fid = (int)($d['foto_id'] ?? 0);
    $f = db()->prepare("SELECT foto_path FROM bangunan_foto WHERE id=?"); $f->execute([$fid]); $fd=$f->fetch();
    if ($fd) { $p = UPLOAD_DIR.'../'.$fd['foto_path']; if(file_exists($p)) unlink($p); }
    db()->prepare("DELETE FROM bangunan_foto WHERE id=?")->execute([$fid]);
    json_response(200, true, 'Foto dihapus');
}
