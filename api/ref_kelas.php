<?php
/**
 * E-Portal API untuk Data Referensi Kelas
 * CRUD for ref_kelas table
 */
require_once __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list': listRefKelas(); break;
    case 'create': createRefKelas(); break;
    case 'update': updateRefKelas(); break;
    case 'delete': deleteRefKelas(); break;
    case 'get': getRefKelas(); break;
    case 'list_teachers': listTeachers(); break;
    default: json_response(400, false, 'Action tidak valid.');
}

function syncRefKelasFromStudents() {
    try {
        $pdo = db();
        
        // Fetch all distinct classes from students
        $stmt = $pdo->query("SELECT DISTINCT kelas FROM students WHERE kelas IS NOT NULL AND kelas != ''");
        $studentClasses = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($studentClasses)) {
            return;
        }
        
        // Fetch existing classes in ref_kelas
        $stmtExisting = $pdo->query("SELECT DISTINCT nama_kelas FROM ref_kelas");
        $existingClasses = $stmtExisting->fetchAll(PDO::FETCH_COLUMN);
        $existingClassesLower = array_map('strtolower', $existingClasses);
        
        $stmtInsert = $pdo->prepare("INSERT INTO ref_kelas (tingkat, nama_kelas, keterangan) VALUES (?, ?, ?)");
        
        foreach ($studentClasses as $rawKelas) {
            $rawKelasTrimmed = trim($rawKelas);
            if (in_array(strtolower($rawKelasTrimmed), $existingClassesLower)) {
                continue; // Already exists
            }
            
            // Parse tingkat from raw kelas (e.g. "10.1" -> tingkat = "10")
            if (preg_match('/^([a-zA-Z0-9]+)[\s\.\-](.+)$/', $rawKelasTrimmed, $matches)) {
                $tingkat = $matches[1];
                $nama_kelas = $rawKelasTrimmed;
            } else {
                $tingkat = $rawKelasTrimmed;
                $nama_kelas = $rawKelasTrimmed;
            }
            
            $stmtInsert->execute([$tingkat, $nama_kelas, 'Sinkronisasi Otomatis dari Data Siswa']);
        }
    } catch (Exception $e) {
        // Silent catch to prevent API failure
    }
}

function listRefKelas() {
    // Auto-sync classes from students table
    syncRefKelasFromStudents();

    $stmt = db()->prepare("
        SELECT r.*, u.nama_lengkap as wali_kelas_nama 
        FROM ref_kelas r 
        LEFT JOIN users u ON r.wali_kelas_id = u.id 
        ORDER BY r.tingkat ASC, r.nama_kelas ASC
    ");
    $stmt->execute();
    json_response(200, true, 'Data kelas berhasil dimuat.', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getRefKelas() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $stmt = db()->prepare("SELECT * FROM ref_kelas WHERE id = ?");
    $stmt->execute([$id]);
    json_response(200, true, 'OK', $stmt->fetch(PDO::FETCH_ASSOC));
}

function createRefKelas() {
    require_superadmin();
    $input = get_input();
    $tingkat = sanitize($input['tingkat'] ?? '');
    $nama_kelas = sanitize($input['nama_kelas'] ?? '');
    $keterangan = sanitize($input['keterangan'] ?? '');
    $wali_kelas_id = isset($input['wali_kelas_id']) && (int)$input['wali_kelas_id'] > 0 ? (int)$input['wali_kelas_id'] : null;
    
    if (!$tingkat || !$nama_kelas) json_response(400, false, 'Tingkat dan Nama Kelas wajib diisi.');
    
    $stmt = db()->prepare("INSERT INTO ref_kelas (tingkat, nama_kelas, wali_kelas_id, keterangan) VALUES (?, ?, ?, ?)");
    $stmt->execute([$tingkat, $nama_kelas, $wali_kelas_id, $keterangan]);
    json_response(201, true, 'Data Kelas berhasil ditambahkan.');
}

function updateRefKelas() {
    require_superadmin();
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $tingkat = sanitize($input['tingkat'] ?? '');
    $nama_kelas = sanitize($input['nama_kelas'] ?? '');
    $keterangan = sanitize($input['keterangan'] ?? '');
    $wali_kelas_id = isset($input['wali_kelas_id']) && (int)$input['wali_kelas_id'] > 0 ? (int)$input['wali_kelas_id'] : null;
    
    if (!$id || !$tingkat || !$nama_kelas) json_response(400, false, 'ID, Tingkat dan Nama Kelas wajib diisi.');
    
    $stmt = db()->prepare("UPDATE ref_kelas SET tingkat=?, nama_kelas=?, wali_kelas_id=?, keterangan=? WHERE id=?");
    $stmt->execute([$tingkat, $nama_kelas, $wali_kelas_id, $keterangan, $id]);
    json_response(200, true, 'Data Kelas berhasil diperbarui.');
}

function deleteRefKelas() {
    require_superadmin();
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if (!$id) json_response(400, false, 'ID tidak valid.');
    
    $stmt = db()->prepare("DELETE FROM ref_kelas WHERE id=?");
    $stmt->execute([$id]);
    json_response(200, true, 'Data Kelas berhasil dihapus.');
}

function listTeachers() {
    require_superadmin();
    $stmt = db()->prepare("SELECT id, username, nama_lengkap FROM users WHERE role = 'guru' AND status = 1 ORDER BY nama_lengkap ASC");
    $stmt->execute();
    json_response(200, true, 'Daftar guru.', $stmt->fetchAll(PDO::FETCH_ASSOC));
}
