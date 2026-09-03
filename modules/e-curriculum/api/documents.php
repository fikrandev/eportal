<?php
/**
 * E-Curriculum — Manajemen Dokumen Guru (RPP, Modul, dll)
 */
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$academic_year_id = get_active_academic_year_id();

$isAdmin = in_array($user['role'], ['superadmin']) || in_array($user['acad_role'] ?? '', ['admin_kurikulum', 'operator_kurikulum']);

switch ($action) {
    case 'list':
        listDocuments($user, $isAdmin);
        break;
    case 'upload':
        uploadDocument($user, $academic_year_id);
        break;
    case 'approve':
        if (!$isAdmin) json_response(403, false, 'Akses ditolak.');
        approveDocument();
        break;
    case 'reject':
        if (!$isAdmin) json_response(403, false, 'Akses ditolak.');
        rejectDocument();
        break;
    case 'delete':
        deleteDocument($user, $isAdmin);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function listDocuments($user, $isAdmin) {
    global $academic_year_id;
    try {
        if ($isAdmin) {
            $stmt = db()->prepare("
                SELECT d.*, u.nama_lengkap as nama_guru 
                FROM acad_documents d 
                JOIN users u ON d.user_id = u.id 
                WHERE d.academic_year_id = ?
                ORDER BY d.created_at DESC
            ");
            $stmt->execute([$academic_year_id]);
        } else {
            $stmt = db()->prepare("
                SELECT d.*, u.nama_lengkap as nama_guru 
                FROM acad_documents d 
                JOIN users u ON d.user_id = u.id 
                WHERE d.academic_year_id = ? AND d.user_id = ?
                ORDER BY d.created_at DESC
            ");
            $stmt->execute([$academic_year_id, $user['user_id']]);
        }
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_response(200, true, 'Daftar dokumen', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function uploadDocument($user, $academic_year_id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    
    $judul = isset($_POST['judul']) ? trim($_POST['judul']) : '';
    $tipe = isset($_POST['tipe']) ? trim($_POST['tipe']) : '';
    
    if (empty($judul) || empty($tipe)) {
        json_response(400, false, 'Judul dan tipe dokumen wajib diisi.');
    }
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_response(400, false, 'Pilih file yang valid untuk diunggah.');
    }
    
    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate extension
    $allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip'];
    if (!in_array($ext, $allowed_exts)) {
        json_response(400, false, 'Format file tidak diizinkan. Gunakan PDF/Word/Excel/PowerPoint/ZIP.');
    }
    
    // Validate size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        json_response(400, false, 'Ukuran file maksimal 10MB.');
    }
    
    $uploadDir = __DIR__ . '/../../../uploads/curriculum/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filename = time() . '_' . rand(1000, 9999) . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '', basename($file['name']));
    $destination = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $dbPath = 'uploads/curriculum/' . $filename;
        try {
            $stmt = db()->prepare("
                INSERT INTO acad_documents (user_id, academic_year_id, judul, tipe_dokumen, file_path, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$user['user_id'], $academic_year_id, $judul, $tipe, $dbPath]);
            json_response(201, true, 'Dokumen berhasil diunggah dan menunggu persetujuan.');
        } catch (PDOException $e) {
            unlink($destination); // rollback file
            json_response(500, false, 'Gagal menyimpan data: ' . $e->getMessage());
        }
    } else {
        json_response(500, false, 'Gagal memindahkan file yang diunggah.');
    }
}

function approveDocument() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');
    
    try {
        $stmt = db()->prepare("UPDATE acad_documents SET status = 'approved', catatan_admin = NULL WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Dokumen berhasil disetujui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function rejectDocument() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';
    
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');
    if (empty($catatan)) json_response(400, false, 'Catatan alasan penolakan wajib diisi.');
    
    try {
        $stmt = db()->prepare("UPDATE acad_documents SET status = 'rejected', catatan_admin = ? WHERE id = ?");
        $stmt->execute([$catatan, $id]);
        json_response(200, true, 'Dokumen berhasil ditolak.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteDocument($user, $isAdmin) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');
    
    try {
        $stmt = db()->prepare("SELECT * FROM acad_documents WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$doc) json_response(404, false, 'Dokumen tidak ditemukan.');
        
        // Authorization
        if (!$isAdmin) {
            if ($doc['user_id'] != $user['user_id']) {
                json_response(403, false, 'Akses ditolak.');
            }
            if ($doc['status'] === 'approved') {
                json_response(400, false, 'Tidak dapat menghapus dokumen yang sudah disetujui.');
            }
        }
        
        // Delete file
        $filePath = __DIR__ . '/../../../' . $doc['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete row
        $stmtDel = db()->prepare("DELETE FROM acad_documents WHERE id = ?");
        $stmtDel->execute([$id]);
        
        json_response(200, true, 'Dokumen berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
