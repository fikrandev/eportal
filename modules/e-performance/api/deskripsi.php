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

        case 'get':
            if ($method !== 'GET') throw new Exception('Method not allowed', 405);
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM perf_deskripsi WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) throw new Exception('Data deskripsi tidak ditemukan', 404);
            json_response(200, true, 'Detail deskripsi', $item);
            break;

        case 'create':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            if ($role !== 'admin' && $role !== 'superadmin') throw new Exception('Akses ditolak', 403);
            
            $input = get_input();
            
            $tupoksi = trim($input['tupoksi'] ?? '');
            $predikat = trim($input['predikat'] ?? '');
            $min_nilai = isset($input['min_nilai']) && $input['min_nilai'] !== '' ? (float)str_replace(',', '.', $input['min_nilai']) : null;
            $max_nilai = isset($input['max_nilai']) && $input['max_nilai'] !== '' ? (float)str_replace(',', '.', $input['max_nilai']) : null;
            $deskripsi = trim($input['deskripsi'] ?? '');
            
            if (!$tupoksi) throw new Exception('Tupoksi harus dipilih', 400);
            if ($min_nilai === null || $max_nilai === null) throw new Exception('Batas nilai minimal dan maksimal harus diisi', 400);
            if ($min_nilai > $max_nilai) throw new Exception('Nilai minimal tidak boleh lebih besar dari maksimal', 400);
            if (!$deskripsi) throw new Exception('Deskripsi harus diisi', 400);
            
            $stmt = $db->prepare("INSERT INTO perf_deskripsi (tupoksi, predikat, min_nilai, max_nilai, deskripsi) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$tupoksi, $predikat ?: null, $min_nilai, $max_nilai, $deskripsi]);
            
            json_response(200, true, 'Deskripsi berhasil disimpan');
            break;

        case 'update':
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);
            if ($role !== 'admin' && $role !== 'superadmin') throw new Exception('Akses ditolak', 403);
            
            $input = get_input();
            $id = (int)($input['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid', 400);
            
            $tupoksi = trim($input['tupoksi'] ?? '');
            $predikat = trim($input['predikat'] ?? '');
            $min_nilai = isset($input['min_nilai']) && $input['min_nilai'] !== '' ? (float)str_replace(',', '.', $input['min_nilai']) : null;
            $max_nilai = isset($input['max_nilai']) && $input['max_nilai'] !== '' ? (float)str_replace(',', '.', $input['max_nilai']) : null;
            $deskripsi = trim($input['deskripsi'] ?? '');
            
            if (!$tupoksi) throw new Exception('Tupoksi harus dipilih', 400);
            if ($min_nilai === null || $max_nilai === null) throw new Exception('Batas nilai minimal dan maksimal harus diisi', 400);
            if ($min_nilai > $max_nilai) throw new Exception('Nilai minimal tidak boleh lebih besar dari maksimal', 400);
            if (!$deskripsi) throw new Exception('Deskripsi harus diisi', 400);
            
            $stmt = $db->prepare("UPDATE perf_deskripsi SET tupoksi = ?, predikat = ?, min_nilai = ?, max_nilai = ?, deskripsi = ? WHERE id = ?");
            $stmt->execute([$tupoksi, $predikat ?: null, $min_nilai, $max_nilai, $deskripsi, $id]);
            
            json_response(200, true, 'Deskripsi berhasil diperbarui');
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
