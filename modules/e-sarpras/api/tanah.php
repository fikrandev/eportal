<?php
/**
 * E-Sarpras Tanah API
 * CRUD + Photo management for land records
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/auth_helper.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list': listTanah(); break;
    case 'get': getTanah(); break;
    case 'create': createTanah(); break;
    case 'update': updateTanah(); break;
    case 'delete': deleteTanah(); break;
    case 'upload-foto': uploadFoto(); break;
    case 'delete-foto': deleteFoto(); break;
    case 'get-foto': getFoto(); break;
    default: json_response(400, false, 'Invalid action');
}

function listTanah() {
    $user = sp_auth();
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $page = isset($_GET['page']) ? max(1,(int)$_GET['page']) : 1;
    $perPage = isset($_GET['per_page']) ? min(50,max(1,(int)$_GET['per_page'])) : 10;
    
    $where = '';
    $params = [];
    if ($search) {
        $where = " WHERE (t.nama LIKE ? OR t.lokasi LIKE ? OR t.no_sertifikat LIKE ?)";
        $params = ["%$search%", "%$search%", "%$search%"];
    }
    
    $countStmt = db()->prepare("SELECT COUNT(*) as total FROM tanah t $where");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    $offset = ($page - 1) * $perPage;
    $stmt = db()->prepare("
        SELECT t.*, 
            (SELECT COUNT(*) FROM bangunan WHERE tanah_id=t.id) as jumlah_bangunan,
            (SELECT COUNT(*) FROM tanah_foto WHERE tanah_id=t.id) as jumlah_foto
        FROM tanah t $where 
        ORDER BY t.created_at DESC 
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    
    json_response(200, true, 'OK', [
        'data' => $stmt->fetchAll(),
        'total' => (int)$total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => ceil($total / $perPage)
    ]);
}

function getTanah() {
    $user = sp_auth();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid');
    
    $stmt = db()->prepare("SELECT * FROM tanah WHERE id=?");
    $stmt->execute([$id]);
    $tanah = $stmt->fetch();
    if (!$tanah) json_response(404, false, 'Data tidak ditemukan');
    
    // Get photos
    $fotoStmt = db()->prepare("SELECT * FROM tanah_foto WHERE tanah_id=? ORDER BY urutan ASC");
    $fotoStmt->execute([$id]);
    $tanah['fotos'] = $fotoStmt->fetchAll();
    
    // Get bangunan count
    $bgStmt = db()->prepare("SELECT COUNT(*) as total FROM bangunan WHERE tanah_id=?");
    $bgStmt->execute([$id]);
    $tanah['jumlah_bangunan'] = $bgStmt->fetch()['total'];
    
    json_response(200, true, 'OK', $tanah);
}

function createTanah() {
    $user = sp_auth();
    sp_require_any($user, ['tanah_manage'], 'Akses ditolak');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    
    $input = get_input();
    $nama = sanitize($input['nama'] ?? '');
    $lokasi = sanitize($input['lokasi'] ?? '');
    $kode = sanitize($input['kode_tanah'] ?? '');
    $luas = floatval($input['luas_m2'] ?? 0);
    $panjang = isset($input['panjang_m']) ? floatval($input['panjang_m']) : null;
    $lebar = isset($input['lebar_m']) ? floatval($input['lebar_m']) : null;
    $harga = floatval($input['harga_perolehan'] ?? 0);
    $tahun = intval($input['tahun_perolehan'] ?? date('Y'));
    $status_kep = sanitize($input['status_kepemilikan'] ?? 'Milik Sendiri');
    $no_sert = sanitize($input['no_sertifikat'] ?? '');
    $asal = sanitize($input['asal_anggaran'] ?? '');
    $ket = sanitize($input['keterangan'] ?? '');
    $lintang = sanitize($input['lintang'] ?? '');
    $bujur = sanitize($input['bujur'] ?? '');
    
    if (empty($nama) || empty($lokasi) || $luas <= 0) {
        json_response(400, false, 'Nama, lokasi, dan luas wajib diisi');
    }

    // Auto-generate kode if empty
    if (empty($kode)) {
        $count = db()->query("SELECT COUNT(*) FROM tanah")->fetchColumn();
        $kode = 'TNH-' . date('Y') . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }
    
    try {
        $stmt = db()->prepare("INSERT INTO tanah (nama, kode_tanah, lokasi, lintang, bujur, luas_m2, panjang_m, lebar_m, harga_perolehan, tahun_perolehan, status_kepemilikan, no_sertifikat, asal_anggaran, keterangan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$nama, $kode, $lokasi, $lintang, $bujur, $luas, $panjang, $lebar, $harga, $tahun, $status_kep, $no_sert, $asal, $ket]);
        json_response(201, true, 'Data tanah berhasil ditambahkan', ['id' => db()->lastInsertId(), 'kode_tanah' => $kode]);
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function updateTanah() {
    $user = sp_auth();
    sp_require_any($user, ['tanah_manage'], 'Akses ditolak');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    
    $input = get_input();
    $id = intval($input['id'] ?? 0);
    if ($id <= 0) json_response(400, false, 'ID tidak valid');
    
    $nama = sanitize($input['nama'] ?? '');
    $kode = sanitize($input['kode_tanah'] ?? '');
    $lokasi = sanitize($input['lokasi'] ?? '');
    $luas = floatval($input['luas_m2'] ?? 0);
    $panjang = isset($input['panjang_m']) ? floatval($input['panjang_m']) : null;
    $lebar = isset($input['lebar_m']) ? floatval($input['lebar_m']) : null;
    $harga = floatval($input['harga_perolehan'] ?? 0);
    $tahun = intval($input['tahun_perolehan'] ?? date('Y'));
    $status_kep = sanitize($input['status_kepemilikan'] ?? 'Milik Sendiri');
    $no_sert = sanitize($input['no_sertifikat'] ?? '');
    $asal = sanitize($input['asal_anggaran'] ?? '');
    $ket = sanitize($input['keterangan'] ?? '');
    $lintang = sanitize($input['lintang'] ?? '');
    $bujur = sanitize($input['bujur'] ?? '');
    
    if (empty($nama)) json_response(400, false, 'Nama wajib diisi');
    
    try {
        $stmt = db()->prepare("UPDATE tanah SET nama=?, kode_tanah=?, lokasi=?, lintang=?, bujur=?, luas_m2=?, panjang_m=?, lebar_m=?, harga_perolehan=?, tahun_perolehan=?, status_kepemilikan=?, no_sertifikat=?, asal_anggaran=?, keterangan=? WHERE id=?");
        $stmt->execute([$nama, $kode, $lokasi, $lintang, $bujur, $luas, $panjang, $lebar, $harga, $tahun, $status_kep, $no_sert, $asal, $ket, $id]);
        json_response(200, true, 'Data tanah berhasil diperbarui');
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function deleteTanah() {
    $user = sp_auth();
    sp_require_any($user, ['tanah_manage'], 'Akses ditolak');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    
    $input = get_input();
    $id = intval($input['id'] ?? 0);
    if ($id <= 0) json_response(400, false, 'ID tidak valid');
    
    try {
        // Delete associated photos files
        $fotos = db()->prepare("SELECT foto_path FROM tanah_foto WHERE tanah_id=?");
        $fotos->execute([$id]);
        foreach ($fotos->fetchAll() as $f) {
            $path = UPLOAD_DIR . '../' . $f['foto_path'];
            if (file_exists($path)) unlink($path);
        }
        
        db()->prepare("DELETE FROM tanah WHERE id=?")->execute([$id]);
        json_response(200, true, 'Data tanah berhasil dihapus');
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function uploadFoto() {
    $user = sp_auth();
    sp_require_any($user, ['tanah_manage'], 'Akses ditolak');
    
    $id = intval($_POST['tanah_id'] ?? 0);
    if ($id <= 0) json_response(400, false, 'ID tidak valid');
    
    // Check max 5 photos
    $cnt = db()->prepare("SELECT COUNT(*) as total FROM tanah_foto WHERE tanah_id=?");
    $cnt->execute([$id]);
    if ($cnt->fetch()['total'] >= 5) json_response(400, false, 'Maksimal 5 foto');
    
    if (!isset($_FILES['foto'])) json_response(400, false, 'File foto required');
    
    $result = handle_upload($_FILES['foto'], 'sarpras/tanah/');
    if (!$result['success']) json_response(400, false, $result['message']);
    
    // Compress if > 500KB
    $fullPath = $result['full_path'];
    if (filesize($fullPath) > 500 * 1024) {
        compress_image($fullPath, $fullPath, 500); // 500 target KB
    }
    
    $ket = sanitize($_POST['keterangan'] ?? '');
    
    // Get next urutan safely
    $uStmt = db()->prepare("SELECT COALESCE(MAX(urutan), 0) + 1 FROM tanah_foto WHERE tanah_id=?");
    $uStmt->execute([$id]);
    $nextU = $uStmt->fetchColumn();
    
    $stmt = db()->prepare("INSERT INTO tanah_foto (tanah_id, foto_path, keterangan, urutan) VALUES (?, ?, ?, ?)");
    $stmt->execute([$id, $result['path'], $ket, $nextU]);
    
    json_response(201, true, 'Foto berhasil diupload', ['id' => db()->lastInsertId(), 'path' => $result['path']]);
}

function deleteFoto() {
    $user = sp_auth();
    sp_require_any($user, ['tanah_manage'], 'Akses ditolak');
    
    $input = get_input();
    $fotoId = intval($input['foto_id'] ?? 0);
    if ($fotoId <= 0) json_response(400, false, 'ID foto tidak valid');
    
    $foto = db()->prepare("SELECT foto_path FROM tanah_foto WHERE id=?");
    $foto->execute([$fotoId]);
    $f = $foto->fetch();
    if ($f) {
        $path = UPLOAD_DIR . '../' . $f['foto_path'];
        if (file_exists($path)) unlink($path);
    }
    
    db()->prepare("DELETE FROM tanah_foto WHERE id=?")->execute([$fotoId]);
    json_response(200, true, 'Foto berhasil dihapus');
}

function getFoto() {
    sp_auth();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $stmt = db()->prepare("SELECT * FROM tanah_foto WHERE tanah_id=? ORDER BY urutan ASC");
    $stmt->execute([$id]);
    json_response(200, true, 'OK', $stmt->fetchAll());
}
