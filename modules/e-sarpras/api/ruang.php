<?php
/**
 * E-Sarpras Ruang API
 */
require_once __DIR__ . '/../../../api/config.php';

require_once __DIR__ . '/auth_helper.php';

$action = $_GET['action'] ?? '';
switch ($action) {
    case 'list': listRuang(); break;
    case 'get': getRuang(); break;
    case 'create': createRuang(); break;
    case 'update': updateRuang(); break;
    case 'delete': deleteRuang(); break;
    case 'upload-foto': uploadFoto(); break;
    case 'delete-foto': deleteFoto(); break;
    case 'all': allRuang(); break;
    default: json_response(400, false, 'Invalid action');
}

function listRuang() {
    $user = sp_auth();
    $bangunan_id = (int)($_GET['bangunan_id'] ?? 0);
    $pj_id = (int)($_GET['pj_id'] ?? 0);
    $where = "WHERE 1=1";
    if ($bangunan_id > 0) $where .= " AND r.bangunan_id = $bangunan_id";
    if ($pj_id > 0) $where .= " AND r.pj_id = $pj_id";
    
    // Enforcement for PJ
    $where .= sp_scope_where($user, 'r', 'id');

    $search = $_GET['search'] ?? '';
    $params = [];
    if ($search) { $where .= " AND (r.nama LIKE ? OR r.kode_ruang LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $jenis = $_GET['jenis_ruang'] ?? '';
    if ($jenis) { $where .= " AND r.jenis_ruang = ?"; $params[] = $jenis; }
    
    $stmt = db()->prepare("SELECT r.*, p.nama as pj_nama, p.nip as pj_nip, b.nama as bangunan_nama, b.id as bangunan_id, t.nama as tanah_nama, t.id as tanah_id,
        (SELECT COUNT(*) FROM sarpras WHERE ruang_id=r.id) as jumlah_sarpras,
        (SELECT COALESCE(SUM(jumlah),0) FROM sarpras WHERE ruang_id=r.id) as total_unit
        FROM ruang r 
        LEFT JOIN sarpras_pj p ON r.pj_id=p.id
        JOIN bangunan b ON r.bangunan_id=b.id 
        JOIN tanah t ON b.tanah_id=t.id $where ORDER BY r.nama");
    $stmt->execute($params);
    json_response(200, true, 'OK', $stmt->fetchAll());
}

function allRuang() {
    $user = sp_auth();
    $where = "WHERE 1=1" . sp_scope_where($user, 'r', 'id');
    $stmt = db()->query("SELECT r.id, r.nama, r.kode_ruang, b.nama as bangunan_nama, t.nama as tanah_nama FROM ruang r JOIN bangunan b ON r.bangunan_id=b.id JOIN tanah t ON b.tanah_id=t.id $where ORDER BY t.nama, b.nama, r.nama");
    json_response(200, true, 'OK', $stmt->fetchAll());
}

function getRuang() {
    $user = sp_auth();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_response(400, false, 'ID tidak valid');
    
    // Scoping check for PJ
    if (!empty($user['scoped_ruang_ids']) && !in_array($id, $user['scoped_ruang_ids'])) {
        json_response(403, false, 'Anda tidak memiliki akses ke ruangan ini');
    }

    $stmt = db()->prepare("SELECT r.*, p.nama as pj_nama, b.nama as bangunan_nama, b.id as bangunan_id, t.nama as tanah_nama, t.id as t_id FROM ruang r LEFT JOIN sarpras_pj p ON r.pj_id=p.id JOIN bangunan b ON r.bangunan_id=b.id JOIN tanah t ON b.tanah_id=t.id WHERE r.id=?");
    $stmt->execute([$id]); $data = $stmt->fetch();
    if (!$data) json_response(404, false, 'Tidak ditemukan');
    $f = db()->prepare("SELECT * FROM ruang_foto WHERE ruang_id=? ORDER BY urutan"); $f->execute([$id]);
    $data['fotos'] = $f->fetchAll();
    json_response(200, true, 'OK', $data);
}

function createRuang() {
    $user = sp_auth(); sp_require_any($user, ['ruang_manage'], 'Akses ditolak');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $d = get_input();
    $bangunan_id = (int)($d['bangunan_id'] ?? 0);
    $pj_id = !empty($d['pj_id']) ? (int)$d['pj_id'] : null;
    $nama = sanitize($d['nama'] ?? '');
    $kode = sanitize($d['kode_ruang'] ?? '');
    if (!$bangunan_id || !$nama || !$kode) json_response(400, false, 'Wajib isi Bangunan, Nama, dan Kode Ruang');
    
    try {
        $stmt = db()->prepare("INSERT INTO ruang (bangunan_id,pj_id,nama,kode_ruang,panjang_m,lebar_m,lantai,jenis_ruang,kapasitas,kondisi,keterangan) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$bangunan_id, $pj_id, $nama, $kode, $d['panjang_m']??null, $d['lebar_m']??null, (int)($d['lantai']??1), sanitize($d['jenis_ruang']??'Ruang Kelas'), (int)($d['kapasitas']??0), sanitize($d['kondisi']??'Baik'), sanitize($d['keterangan']??'')]);
        json_response(201, true, 'Ruang berhasil ditambahkan', ['id' => db()->lastInsertId()]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'uk_kode_ruang') !== false) json_response(400, false, 'Kode ruang sudah digunakan');
        json_response(500, false, 'Error: '.$e->getMessage());
    }
}

function updateRuang() {
    $user = sp_auth(); sp_require_any($user, ['ruang_manage'], 'Akses ditolak');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $d = get_input(); $id = (int)($d['id'] ?? 0);
    if ($id <= 0) json_response(400, false, 'ID tidak valid');
    
    $pj_id = !empty($d['pj_id']) ? (int)$d['pj_id'] : null;
    $bangunan_id = (int)($d['bangunan_id'] ?? 0);
    
    try {
        $stmt = db()->prepare("UPDATE ruang SET bangunan_id=?,pj_id=?,nama=?,kode_ruang=?,panjang_m=?,lebar_m=?,lantai=?,jenis_ruang=?,kapasitas=?,kondisi=?,keterangan=? WHERE id=?");
        $stmt->execute([$bangunan_id, $pj_id, sanitize($d['nama']??''), sanitize($d['kode_ruang']??''), $d['panjang_m']??null, $d['lebar_m']??null, (int)($d['lantai']??1), sanitize($d['jenis_ruang']??''), (int)($d['kapasitas']??0), sanitize($d['kondisi']??'Baik'), sanitize($d['keterangan']??''), $id]);
        json_response(200, true, 'Ruang berhasil diperbarui');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'uk_kode_ruang') !== false) json_response(400, false, 'Kode ruang sudah digunakan');
        json_response(500, false, 'Error: '.$e->getMessage());
    }
}

function deleteRuang() {
    $user = sp_auth(); sp_require_any($user, ['ruang_manage'], 'Akses ditolak');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $d = get_input(); $id = (int)($d['id'] ?? 0);
    try { db()->prepare("DELETE FROM ruang WHERE id=?")->execute([$id]); json_response(200, true, 'Ruang dihapus'); }
    catch (PDOException $e) { json_response(500, false, 'Error: '.$e->getMessage()); }
}

function uploadFoto() {
    $user = sp_auth(); sp_require_any($user, ['ruang_manage'], 'Akses ditolak');
    $id = (int)($_POST['ruang_id'] ?? 0);
    $cnt = db()->prepare("SELECT COUNT(*) as t FROM ruang_foto WHERE ruang_id=?"); $cnt->execute([$id]);
    if ($cnt->fetch()['t'] >= 5) json_response(400, false, 'Maksimal 5 foto');
    if (!isset($_FILES['foto'])) json_response(400, false, 'File required');
    $r = handle_upload($_FILES['foto'], 'sarpras/ruang/');
    if (!$r['success']) json_response(400, false, $r['message']);
    if (filesize($r['full_path']) > 500*1024) compress_image($r['full_path'], $r['full_path'], 500);
    db()->prepare("INSERT INTO ruang_foto (ruang_id,foto_path,keterangan,urutan) VALUES (?,?,?,(SELECT COALESCE(MAX(x.urutan),0)+1 FROM ruang_foto x WHERE x.ruang_id=?))")->execute([$id, $r['path'], sanitize($_POST['keterangan']??''), $id]);
    json_response(201, true, 'Foto uploaded', ['path' => $r['path']]);
}

function deleteFoto() {
    $user = sp_auth(); sp_require_any($user, ['ruang_manage'], 'Akses ditolak');
    $d = get_input(); $fid = (int)($d['foto_id'] ?? 0);
    $f = db()->prepare("SELECT foto_path FROM ruang_foto WHERE id=?"); $f->execute([$fid]); $fd=$f->fetch();
    if ($fd) { $p=UPLOAD_DIR.'../'.$fd['foto_path']; if(file_exists($p)) unlink($p); }
    db()->prepare("DELETE FROM ruang_foto WHERE id=?")->execute([$fid]);
    json_response(200, true, 'Foto dihapus');
}
