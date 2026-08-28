<?php
/**
 * E-Performance — Deskripsi API
 * Endpoints for managing score descriptions per Tupoksi
 */
require_once __DIR__ . '/config_perf.php';

$auth = perf_auth_check();
$role = $auth['role'];

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$db = db();

try {
    switch ($action) {
        case 'list':
            if ($method !== 'GET') throw new Exception('Method not allowed', 405);
            $stmt = $db->query("SELECT * FROM perf_deskripsi ORDER BY tupoksi ASC, min_nilai ASC");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            json_response(200, true, 'Data deskripsi', $data);
            break;

        case 'create':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            if ($role !== 'admin' && $role !== 'superadmin') throw new Exception('Akses ditolak', 403);
            
            $input = get_input();
            
            $tupoksi = trim($input['tupoksi'] ?? '');
            $min_nilai = isset($input['min_nilai']) && $input['min_nilai'] !== '' ? (float)str_replace(',', '.', $input['min_nilai']) : null;
            $max_nilai = isset($input['max_nilai']) && $input['max_nilai'] !== '' ? (float)str_replace(',', '.', $input['max_nilai']) : null;
            $deskripsi = trim($input['deskripsi'] ?? '');
            
            if (!$tupoksi) throw new Exception('Tupoksi harus dipilih', 400);
            if ($min_nilai === null || $max_nilai === null) throw new Exception('Batas nilai minimal dan maksimal harus diisi', 400);
            if ($min_nilai > $max_nilai) throw new Exception('Nilai minimal tidak boleh lebih besar dari maksimal', 400);
            if (!$deskripsi) throw new Exception('Deskripsi harus diisi', 400);
            
            $stmt = $db->prepare("INSERT INTO perf_deskripsi (tupoksi, min_nilai, max_nilai, deskripsi) VALUES (?, ?, ?, ?)");
            $stmt->execute([$tupoksi, $min_nilai, $max_nilai, $deskripsi]);
            
            json_response(200, true, 'Deskripsi berhasil disimpan');
            break;

        case 'delete':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            if ($role !== 'admin' && $role !== 'superadmin') throw new Exception('Akses ditolak', 403);
            
            $input = get_input();
            $id = $input['id'] ?? 0;
            
            $stmt = $db->prepare("DELETE FROM perf_deskripsi WHERE id = ?");
            $stmt->execute([$id]);
            
            json_response(200, true, 'Deskripsi berhasil dihapus');
            break;

        default:
            throw new Exception('Aksi tidak valid', 400);
    }
} catch (Exception $e) {
    $code = (is_numeric($e->getCode()) && $e->getCode() >= 400) ? $e->getCode() : 500;
    json_response($code, false, $e->getMessage());
}
