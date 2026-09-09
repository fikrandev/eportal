<?php
/**
 * Guru App — Dokumen Perangkat API
 * Upload & list teaching documents (RPP, Modul Ajar, Silabus, dll)
 * Integrated with E-Curriculum verification & approval
 */
require_once __DIR__ . '/../../api/config.php';

// Auth check
$token = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
} elseif (function_exists('getallheaders')) {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) $token = str_replace('Bearer ', '', $headers['Authorization']);
    elseif (isset($headers['authorization'])) $token = str_replace('Bearer ', '', $headers['authorization']);
}
if (empty($token) && isset($_GET['token'])) $token = $_GET['token'];

if (empty($token)) {
    json_response(401, false, 'Token tidak ditemukan.');
}

$stmt = db()->prepare("
    SELECT u.id as user_id, u.username, u.nama_lengkap, u.role
    FROM sessions s
    JOIN users u ON s.user_id = u.id
    WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1 AND u.role = 'guru'
");
$stmt->execute([trim($token)]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    json_response(401, false, 'Sesi tidak valid atau telah berakhir.');
}

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

switch ($action) {
    case 'list':
        listTeacherDocuments($user);
        break;
    case 'types':
    case 'list_types':
        listDocumentTypesGuru();
        break;
    case 'upload':
        uploadTeacherDocument($user);
        break;
    case 'delete':
        deleteTeacherDocument($user);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List master document / perangkat types
 */
function listDocumentTypesGuru() {
    try {
        $stmt = db()->query("SELECT id, nama_tipe, deskripsi, urutan FROM acad_document_types ORDER BY urutan ASC, id ASC");
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fallback default if empty
        if (empty($types)) {
            $defaultTypes = [
                ['nama_tipe' => 'RPP', 'deskripsi' => 'Rencana Pelaksanaan Pembelajaran', 'urutan' => 1],
                ['nama_tipe' => 'Modul Ajar', 'deskripsi' => 'Modul Ajar Kurikulum Merdeka', 'urutan' => 2],
                ['nama_tipe' => 'Silabus', 'deskripsi' => 'Silabus Pembelajaran', 'urutan' => 3],
                ['nama_tipe' => 'Prota / Promes', 'deskripsi' => 'Program Tahunan & Semester', 'urutan' => 4],
                ['nama_tipe' => 'ATP', 'deskripsi' => 'Alur Tujuan Pembelajaran', 'urutan' => 5],
                ['nama_tipe' => 'CP', 'deskripsi' => 'Capaian Pembelajaran', 'urutan' => 6],
                ['nama_tipe' => 'Bahan Ajar', 'deskripsi' => 'Bahan Ajar / Materi Pembelajaran', 'urutan' => 7],
                ['nama_tipe' => 'Lainnya', 'deskripsi' => 'Dokumen Lainnya', 'urutan' => 8],
            ];
            $stmtInsert = db()->prepare("INSERT IGNORE INTO acad_document_types (nama_tipe, deskripsi, urutan) VALUES (?, ?, ?)");
            foreach ($defaultTypes as $dt) {
                $stmtInsert->execute([$dt['nama_tipe'], $dt['deskripsi'], $dt['urutan']]);
            }
            $stmt = db()->query("SELECT id, nama_tipe, deskripsi, urutan FROM acad_document_types ORDER BY urutan ASC, id ASC");
            $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        json_response(200, true, 'Daftar jenis dokumen berhasil dimuat.', $types);
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
}

/**
 * List documents uploaded by the authenticated teacher
 */
function listTeacherDocuments($user) {
    try {
        $stmt = db()->prepare("
            SELECT d.id, d.user_id, d.academic_year_id, d.judul, d.tipe_dokumen, 
                   d.file_path, d.status, d.catatan_admin, d.created_at, d.updated_at
            FROM acad_documents d
            WHERE d.user_id = ?
            ORDER BY d.created_at DESC
        ");
        $stmt->execute([$user['user_id']]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add file size & exists meta
        foreach ($documents as &$doc) {
            $fullPath = __DIR__ . '/../../' . $doc['file_path'];
            $doc['file_exists'] = file_exists($fullPath);
            $doc['file_size'] = $doc['file_exists'] ? filesize($fullPath) : 0;
            $doc['file_ext'] = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
        }

        // Summary counters
        $summary = [
            'total' => count($documents),
            'pending' => count(array_filter($documents, fn($d) => $d['status'] === 'pending')),
            'approved' => count(array_filter($documents, fn($d) => $d['status'] === 'approved')),
            'rejected' => count(array_filter($documents, fn($d) => $d['status'] === 'rejected')),
        ];

        json_response(200, true, 'Daftar dokumen berhasil dimuat.', [
            'documents' => $documents,
            'summary' => $summary
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
}

/**
 * Handle document upload by teacher
 */
function uploadTeacherDocument($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $judul = isset($_POST['judul']) ? trim($_POST['judul']) : '';
    $tipe = isset($_POST['tipe_dokumen']) ? trim($_POST['tipe_dokumen']) : (isset($_POST['tipe']) ? trim($_POST['tipe']) : '');

    if (empty($judul)) {
        json_response(400, false, 'Judul dokumen wajib diisi.');
    }
    if (empty($tipe)) {
        json_response(400, false, 'Tipe dokumen wajib dipilih.');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES['file']['error'] ?? 'FILE_MISSING';
        json_response(400, false, 'Silakan pilih berkas file yang ingin diunggah (Error code: ' . $err . ').');
    }

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Validated file extensions
    $allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip'];
    if (!in_array($ext, $allowed_exts)) {
        json_response(400, false, 'Format file tidak diizinkan. Gunakan format PDF, Word (DOC/DOCX), Excel (XLS/XLSX), PowerPoint (PPT/PPTX), atau ZIP.');
    }

    // Max 15MB
    if ($file['size'] > 15 * 1024 * 1024) {
        json_response(400, false, 'Ukuran file terlalu besar. Maksimal ukuran file adalah 15MB.');
    }

    $baseUploads = dirname(dirname(__DIR__)) . '/uploads';
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
    $newFilename = time() . '_' . rand(1000, 9999) . '_' . $cleanName;
    $destination = $uploadDir . $newFilename;

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
        $dbPath = 'uploads/curriculum/' . $newFilename;

        // Get active academic year
        $activeYear = get_active_academic_year();
        $academicYearId = (!empty($activeYear) && !empty($activeYear['id'])) ? (int)$activeYear['id'] : 0;

        try {
            $stmt = db()->prepare("
                INSERT INTO acad_documents (user_id, academic_year_id, judul, tipe_dokumen, file_path, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$user['user_id'], $academicYearId, $judul, $tipe, $dbPath]);
            $insertedId = db()->lastInsertId();

            json_response(201, true, 'Dokumen berhasil diunggah dan sedang menunggu persetujuan Kurikulum.', [
                'id' => $insertedId,
                'judul' => $judul,
                'status' => 'pending'
            ]);
        } catch (PDOException $e) {
            if (file_exists($destination)) {
                @unlink($destination); // rollback file
            }
            json_response(500, false, 'Gagal menyimpan database: ' . $e->getMessage());
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
        json_response(500, false, 'Gagal menyimpan file ke direktori server' . $errMsg);
    }
}

/**
 * Handle document deletion by teacher (only allowed if not approved yet)
 */
function deleteTeacherDocument($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if ($id <= 0) {
        json_response(400, false, 'ID dokumen tidak valid.');
    }

    try {
        $stmt = db()->prepare("SELECT * FROM acad_documents WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) {
            json_response(404, false, 'Dokumen tidak ditemukan.');
        }

        if ($doc['user_id'] != $user['user_id']) {
            json_response(403, false, 'Anda tidak memiliki hak akses untuk menghapus dokumen ini.');
        }

        if ($doc['status'] === 'approved') {
            json_response(400, false, 'Dokumen yang sudah disetujui tidak dapat dihapus.');
        }

        // Delete local file if exists
        $filePath = __DIR__ . '/../../' . $doc['file_path'];
        if (file_exists($filePath) && is_file($filePath)) {
            unlink($filePath);
        }

        // Delete from database
        $delStmt = db()->prepare("DELETE FROM acad_documents WHERE id = ?");
        $delStmt->execute([$id]);

        json_response(200, true, 'Dokumen berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Database error: ' . $e->getMessage());
    }
}
