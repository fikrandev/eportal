<?php
/**
 * E-Curriculum — Manajemen Dokumen Guru (RPP, Modul, dll)
 */
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$active_year = get_active_academic_year();
$academic_year_id = !empty($active_year['id']) ? (int)$active_year['id'] : null;
$isAdmin = in_array($user['role'], ['superadmin']) 
    || in_array('dokumen_manage', $user['permissions'] ?? [])
    || in_array($user['acad_role'] ?? '', ['admin_kurikulum', 'operator_kurikulum']);

function get_documents_input() {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) return $json;
    }
    return !empty($_POST) ? $_POST : [];
}

switch ($action) {
    case 'list':
        listDocuments($user, $isAdmin);
        break;
    case 'list_types':
        listDocumentTypes();
        break;
    case 'save_type':
        if (!$isAdmin) json_response(403, false, 'Akses ditolak.');
        saveDocumentType();
        break;
    case 'delete_type':
        if (!$isAdmin) json_response(403, false, 'Akses ditolak.');
        deleteDocumentType();
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
            if ($academic_year_id) {
                $stmt = db()->prepare("
                    SELECT d.*, u.nama_lengkap as nama_guru 
                    FROM acad_documents d 
                    JOIN users u ON d.user_id = u.id 
                    WHERE d.academic_year_id = ? OR d.academic_year_id IS NULL
                    ORDER BY d.created_at DESC
                ");
                $stmt->execute([$academic_year_id]);
            } else {
                $stmt = db()->prepare("
                    SELECT d.*, u.nama_lengkap as nama_guru 
                    FROM acad_documents d 
                    JOIN users u ON d.user_id = u.id 
                    ORDER BY d.created_at DESC
                ");
                $stmt->execute();
            }
        } else {
            if ($academic_year_id) {
                $stmt = db()->prepare("
                    SELECT d.*, u.nama_lengkap as nama_guru 
                    FROM acad_documents d 
                    JOIN users u ON d.user_id = u.id 
                    WHERE (d.academic_year_id = ? OR d.academic_year_id IS NULL) AND d.user_id = ?
                    ORDER BY d.created_at DESC
                ");
                $stmt->execute([$academic_year_id, $user['user_id']]);
            } else {
                $stmt = db()->prepare("
                    SELECT d.*, u.nama_lengkap as nama_guru 
                    FROM acad_documents d 
                    JOIN users u ON d.user_id = u.id 
                    WHERE d.user_id = ?
                    ORDER BY d.created_at DESC
                ");
                $stmt->execute([$user['user_id']]);
            }
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
    
    $baseUploads = dirname(dirname(dirname(__DIR__))) . '/uploads';
    if (!is_dir($baseUploads)) {
        @mkdir($baseUploads, 0777, true);
        @chmod($baseUploads, 0777);
    }

    $uploadDir = $baseUploads . '/curriculum/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
        @chmod($uploadDir, 0777);
    }
    
    $cleanName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($file['name']));
    $filename = time() . '_' . rand(1000, 9999) . '_' . $cleanName;
    $destination = $uploadDir . $filename;
    
    $moved = false;
    if (is_uploaded_file($file['tmp_name'])) {
        $moved = @move_uploaded_file($file['tmp_name'], $destination);
    }
    if (!$moved) {
        $moved = @copy($file['tmp_name'], $destination);
        if ($moved && file_exists($file['tmp_name'])) {
            @unlink($file['tmp_name']);
        }
    }

    if ($moved) {
        @chmod($destination, 0664);
        $dbPath = 'uploads/curriculum/' . $filename;
        try {
            $stmt = db()->prepare("
                INSERT INTO acad_documents (user_id, academic_year_id, judul, tipe_dokumen, file_path, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$user['user_id'], $academic_year_id, $judul, $tipe, $dbPath]);
            json_response(201, true, 'Dokumen berhasil diunggah dan menunggu persetujuan.');
        } catch (PDOException $e) {
            @unlink($destination); // rollback file
            json_response(500, false, 'Gagal menyimpan data: ' . $e->getMessage());
        }
    } else {
        $lastError = error_get_last();
        $errMsg = '';
        if (!is_dir($uploadDir)) {
            $errMsg = ' (Folder uploads/curriculum belum ada atau gagal dibuat secara otomatis).';
        } elseif (!is_writable($uploadDir)) {
            $errMsg = ' (Folder uploads/curriculum tidak memiliki izin tulis/write permission. Ubah permission folder menjadi 777/755 di server).';
        } elseif ($lastError && isset($lastError['message'])) {
            $errMsg = ' (' . $lastError['message'] . ')';
        }
        json_response(500, false, 'Gagal memindahkan file yang diunggah' . $errMsg);
    }
}

function approveDocument() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $input = get_documents_input();
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
    $input = get_documents_input();
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
    $input = get_documents_input();
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

// ==================== MASTER JENIS PERANGKAT ====================
function listDocumentTypes() {
    try {
        $stmt = db()->query("SELECT * FROM acad_document_types ORDER BY urutan ASC, id ASC");
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        json_response(200, true, 'Daftar jenis dokumen', $types);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function saveDocumentType() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $input = get_documents_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $nama_tipe = isset($input['nama_tipe']) ? trim($input['nama_tipe']) : '';
    $deskripsi = isset($input['deskripsi']) ? trim($input['deskripsi']) : '';
    $urutan = isset($input['urutan']) ? (int)$input['urutan'] : 0;

    if (empty($nama_tipe)) {
        json_response(400, false, 'Nama jenis perangkat wajib diisi.');
    }

    try {
        if ($id > 0) {
            // Check unique name on other records
            $chk = db()->prepare("SELECT id FROM acad_document_types WHERE nama_tipe = ? AND id != ?");
            $chk->execute([$nama_tipe, $id]);
            if ($chk->fetchColumn()) {
                json_response(400, false, 'Jenis perangkat dengan nama ini sudah ada.');
            }

            $stmt = db()->prepare("UPDATE acad_document_types SET nama_tipe = ?, deskripsi = ?, urutan = ? WHERE id = ?");
            $stmt->execute([$nama_tipe, $deskripsi, $urutan, $id]);
            json_response(200, true, 'Jenis perangkat berhasil diperbarui.');
        } else {
            // Check unique name
            $chk = db()->prepare("SELECT id FROM acad_document_types WHERE nama_tipe = ?");
            $chk->execute([$nama_tipe]);
            if ($chk->fetchColumn()) {
                json_response(400, false, 'Jenis perangkat dengan nama ini sudah ada.');
            }

            $stmt = db()->prepare("INSERT INTO acad_document_types (nama_tipe, deskripsi, urutan) VALUES (?, ?, ?)");
            $stmt->execute([$nama_tipe, $deskripsi, $urutan]);
            json_response(201, true, 'Jenis perangkat berhasil ditambahkan.');
        }
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteDocumentType() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    $input = get_documents_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        $stmt = db()->prepare("DELETE FROM acad_document_types WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Jenis perangkat berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
