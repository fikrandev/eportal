<?php
/**
 * E-Examination — Upload API
 * Upload gambar soal & audio listening
 */
require_once __DIR__ . '/config_exam.php';

header('Content-Type: application/json; charset=UTF-8');

$user = exam_require_admin_or_guru();
$action = $_GET['action'] ?? 'image';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload gagal. Error code: ' . ($_FILES['file']['error'] ?? 'none'), 400);
    }

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($action === 'audio') {
        // Audio upload for listening
        $allowed = ['mp3', 'ogg', 'wav', 'webm', 'm4a'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        $uploadDir = __DIR__ . '/../uploads/audio/';
    } else {
        // Image upload for soal
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        $uploadDir = __DIR__ . '/../uploads/images/';
    }

    if (!in_array($ext, $allowed)) {
        throw new Exception('Tipe file tidak diizinkan. Gunakan: ' . implode(', ', $allowed), 400);
    }

    if ($file['size'] > $maxSize) {
        throw new Exception('Ukuran file terlalu besar. Maksimal ' . ($maxSize / 1024 / 1024) . 'MB', 400);
    }

    // Create directory if not exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = uniqid('exam_') . '_' . time() . '.' . $ext;
    $filepath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Gagal menyimpan file', 500);
    }

    // Return relative path from module root
    $relativePath = ($action === 'audio' ? 'uploads/audio/' : 'uploads/images/') . $filename;

    json_response(200, true, 'File berhasil diupload', [
        'filename' => $filename,
        'path' => $relativePath,
        'url' => BASE_URL . 'modules/e-examination/' . $relativePath,
        'size' => $file['size'],
        'ext' => $ext
    ]);

} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    if ($code < 100 || $code >= 600) $code = 500;
    json_response($code, false, $e->getMessage());
}
