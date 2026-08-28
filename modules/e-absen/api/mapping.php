<?php
/**
 * E-Absen Mapping API
 * Memetakan Data Guru/Karyawan di Web dengan PIN Mesin
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
if ($user['role'] !== 'superadmin') {
    json_response(403, false, 'Akses ditolak.');
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'list') {
    try {
        $stmt = db()->query("
            SELECT u.id as user_id, u.nama_lengkap, u.username as nik, u.role, u.jabatan, m.mesin_pin 
            FROM users u
            LEFT JOIN absen_user_map m ON u.id = m.user_id
            WHERE u.status = 1 AND u.role IN ('guru', 'superadmin', 'user')
            ORDER BY u.nama_lengkap ASC
        ");
        json_response(200, true, 'Data pegawai dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
} else if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    
    $input = get_input();
    $mappings = isset($input['mappings']) ? $input['mappings'] : [];
    
    if (empty($mappings)) {
        json_response(400, false, 'Tidak ada data untuk disimpan.');
    }
    
    try {
        db()->beginTransaction();
        
        $stmtDel = db()->prepare("DELETE FROM absen_user_map WHERE user_id = ?");
        $stmtIns = db()->prepare("INSERT INTO absen_user_map (user_id, mesin_pin) VALUES (?, ?)");
        
        foreach ($mappings as $m) {
            $user_id = (int)$m['user_id'];
            $pin = trim($m['mesin_pin']);
            
            // Hapus mapping lama untuk user ini
            $stmtDel->execute([$user_id]);
            
            // Jika PIN diisi, simpan mapping baru
            if (!empty($pin)) {
                $stmtIns->execute([$user_id, $pin]);
            }
        }
        
        db()->commit();
        json_response(200, true, 'Mapping PIN Mesin berhasil disimpan.');
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
} else {
    json_response(400, false, 'Action tidak valid.');
}
