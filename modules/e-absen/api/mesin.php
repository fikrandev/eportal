<?php
/**
 * E-Absen Mesin API
 * Mengelola konfigurasi mesin fingerprint (Multi-Mesin) dan tes koneksi
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../../../api/ZKLibrary.php';

$user = acad_auth();
if ($user['role'] !== 'superadmin') {
    json_response(403, false, 'Akses ditolak.');
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        listMesin();
        break;
    case 'get':
        getMesin();
        break;
    case 'save':
        saveMesin();
        break;
    case 'delete':
        deleteMesin();
        break;
    case 'test':
        testConnection();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function listMesin() {
    try {
        $stmt = db()->query("SELECT * FROM absen_mesin ORDER BY id ASC");
        $mesin = $stmt->fetchAll();
        json_response(200, true, 'Data mesin dimuat.', $mesin);
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getMesin() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    try {
        $stmt = db()->prepare("SELECT * FROM absen_mesin WHERE id = ?");
        $stmt->execute([$id]);
        $mesin = $stmt->fetch();
        
        json_response(200, true, 'Data mesin dimuat.', $mesin ?: null);
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
}

function saveMesin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $nama = isset($input['nama_mesin']) ? trim($input['nama_mesin']) : 'Mesin Fingerprint';
    $ip = isset($input['ip_address']) ? trim($input['ip_address']) : '';
    $port = isset($input['port']) ? (int)$input['port'] : 4370;
    $sn = isset($input['sn']) ? trim($input['sn']) : '';
    $key = isset($input['com_key']) ? trim($input['com_key']) : '0';
    $status = isset($input['status']) ? (int)$input['status'] : 1;
    
    // IP bisa kosong untuk mesin yang pakai ADMS
    if (empty($ip) && empty($sn)) {
        json_response(400, false, 'IP Address atau SN wajib diisi (SN untuk mesin ADMS).');
    }
    
    try {
        if ($id > 0) {
            $stmt = db()->prepare("UPDATE absen_mesin SET nama_mesin=?, ip_address=?, port=?, sn=?, com_key=?, status=? WHERE id=?");
            $stmt->execute([$nama, $ip, $port, $sn, $key, $status, $id]);
        } else {
            // Cek limit maksimal 4 mesin
            $count = db()->query("SELECT COUNT(*) FROM absen_mesin")->fetchColumn();
            if ($count >= 4) {
                json_response(400, false, 'Maksimal hanya dapat mendaftarkan 4 mesin fingerprint.');
            }

            $stmt = db()->prepare("INSERT INTO absen_mesin (nama_mesin, ip_address, port, sn, com_key, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nama, $ip, $port, $sn, $key, $status]);
        }
        json_response(200, true, 'Pengaturan mesin berhasil disimpan.');
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
}

function deleteMesin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    
    try {
        $stmt = db()->prepare("DELETE FROM absen_mesin WHERE id=?");
        $stmt->execute([$id]);
        json_response(200, true, 'Mesin berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
}

function testConnection() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    
    $input = get_input();
    $ip = isset($input['ip_address']) ? trim($input['ip_address']) : '';
    $port = isset($input['port']) ? (int)$input['port'] : 4370;
    
    if (empty($ip)) {
        json_response(400, false, 'IP Address wajib diisi.');
    }
    
    // Test Socket / Ping
    $zk = new ZKLibrary($ip, $port);
    $connected = $zk->connect();
    
    if ($connected) {
        $zk->disconnect();
        json_response(200, true, 'Koneksi ke mesin berhasil! Mesin dapat diakses.');
    } else {
        json_response(400, false, 'Gagal terhubung. Pastikan mesin aktif dan berada di jaringan (WiFi/LAN) yang sama dengan server.');
    }
}
