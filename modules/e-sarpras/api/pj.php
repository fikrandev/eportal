<?php
/**
 * E-Sarpras Penanggung Jawab (PJ) API
 */
require_once __DIR__ . '/../../../api/config.php';

require_once __DIR__ . '/auth_helper.php';

$action = $_GET['action'] ?? '';
switch ($action) {
    case 'list': listPj(); break;
    case 'get': getPj(); break;
    case 'create': createPj(); break;
    case 'update': updatePj(); break;
    case 'delete': deletePj(); break;
    case 'guru_list': getGuruList(); break;
    case 'my_rooms': getMyRooms(); break;
    default: json_response(400, false, 'Invalid action');
}

function getMyRooms() {
    $user = sp_auth();
    if (empty($user['scoped_ruang_ids'])) {
        json_response(200, true, 'OK', []);
    }
    
    $ids = implode(',', array_map('intval', $user['scoped_ruang_ids']));
    $stmt = db()->query("SELECT r.id, r.nama, r.kode_ruang, b.nama as bangunan_nama FROM ruang r JOIN bangunan b ON r.bangunan_id=b.id WHERE r.id IN ($ids) ORDER BY r.nama ASC");
    json_response(200, true, 'OK', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getGuruList() {
    sp_auth();
    $stmt = db()->query("SELECT id, username as nip, nama_lengkap, jabatan FROM users WHERE role NOT IN ('siswa','orangtua') AND status=1 ORDER BY nama_lengkap ASC");
    json_response(200, true, 'OK', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function listPj() {
    sp_auth();
    $bangunan_id = (int)($_GET['bangunan_id'] ?? 0);
    $where = $bangunan_id > 0 ? "WHERE p.bangunan_id = $bangunan_id" : "WHERE 1=1";
    
    $stmt = db()->query("
        SELECT p.*, b.nama as bangunan_nama, t.nama as tanah_nama, t.id as tanah_id,
        (SELECT COUNT(*) FROM ruang WHERE pj_id=p.id) as jumlah_ruang
        FROM sarpras_pj p 
        LEFT JOIN bangunan b ON p.bangunan_id=b.id 
        LEFT JOIN tanah t ON b.tanah_id=t.id 
        $where ORDER BY p.nama
    ");
    json_response(200, true, 'OK', $stmt->fetchAll());
}

function getPj() {
    sp_auth();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_response(400, false, 'ID tidak valid');
    $stmt = db()->prepare("SELECT p.*, b.nama as bangunan_nama, t.nama as tanah_nama, t.id as tanah_id,
        (SELECT id FROM ruang WHERE pj_id=p.id LIMIT 1) as ruang_id,
        (SELECT nama FROM ruang WHERE pj_id=p.id LIMIT 1) as ruang_nama
        FROM sarpras_pj p LEFT JOIN bangunan b ON p.bangunan_id=b.id LEFT JOIN tanah t ON b.tanah_id=t.id WHERE p.id=?");
    $stmt->execute([$id]); $data = $stmt->fetch();
    if (!$data) json_response(404, false, 'Tidak ditemukan');
    json_response(200, true, 'OK', $data);
}

function createPj() {
    $user = sp_auth(); sp_require_any($user, ['ruang_manage', 'referensi_manage'], 'Akses ditolak');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $d = get_input();
    $bangunan_id = !empty($d['bangunan_id']) ? (int)$d['bangunan_id'] : null;
    $nama = sanitize($d['nama'] ?? '');
    
    if (!$nama) json_response(400, false, 'Wajib isi nama PJ');
    try {
        db()->beginTransaction();
        $stmt = db()->prepare("INSERT INTO sarpras_pj (bangunan_id, nama, nip, keterangan, user_id) VALUES (?,?,?,?,?)");
        $stmt->execute([$bangunan_id, $nama, sanitize($d['nip']??''), sanitize($d['keterangan']??''), !empty($d['user_id']) ? (int)$d['user_id'] : null]);
        $pj_id = db()->lastInsertId();
        
        $ruang_id = !empty($d['ruang_id']) ? (int)$d['ruang_id'] : null;
        if ($ruang_id > 0) {
            db()->prepare("UPDATE ruang SET pj_id=? WHERE id=?")->execute([$pj_id, $ruang_id]);
        }
        
        db()->commit();
        json_response(201, true, 'Penanggung Jawab berhasil ditambahkan', ['id' => $pj_id]);
    } catch (PDOException $e) {
        if(db()->inTransaction()) db()->rollBack();
        json_response(500, false, 'Error: '.$e->getMessage());
    }
}

function updatePj() {
    $user = sp_auth(); sp_require_any($user, ['ruang_manage', 'referensi_manage'], 'Akses ditolak');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $d = get_input(); $id = (int)($d['id'] ?? 0);
    if ($id <= 0) json_response(400, false, 'ID tidak valid');
    
    try {
        db()->beginTransaction();
        $stmt = db()->prepare("UPDATE sarpras_pj SET bangunan_id=?, nama=?, nip=?, keterangan=? WHERE id=?");
        $stmt->execute([
            !empty($d['bangunan_id']) ? (int)$d['bangunan_id'] : null,
            sanitize($d['nama'] ?? ''),
            sanitize($d['nip'] ?? ''),
            sanitize($d['keterangan'] ?? ''),
            $id
        ]);
        
        // Handle ruang assignment
        if (isset($d['ruang_id'])) {
            $ruang_id = (int)$d['ruang_id'];
            // Clear existing assignment for this PJ
            db()->prepare("UPDATE ruang SET pj_id=NULL WHERE pj_id=?")->execute([$id]);
            // Assign new room if selected
            if ($ruang_id > 0) {
                db()->prepare("UPDATE ruang SET pj_id=? WHERE id=?")->execute([$id, $ruang_id]);
            }
        }
        
        db()->commit();
        json_response(200, true, 'Penanggung Jawab berhasil diperbarui');
    } catch (PDOException $e) {
        if(db()->inTransaction()) db()->rollBack();
        json_response(500, false, 'Error: '.$e->getMessage());
    }
}

function deletePj() {
    $user = sp_auth(); sp_require_any($user, ['ruang_manage', 'referensi_manage'], 'Akses ditolak');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $d = get_input(); $id = (int)($d['id'] ?? 0);
    try { db()->prepare("DELETE FROM sarpras_pj WHERE id=?")->execute([$id]); json_response(200, true, 'Penanggung Jawab dihapus'); }
    catch (PDOException $e) { json_response(500, false, 'Error: '.$e->getMessage()); }
}
