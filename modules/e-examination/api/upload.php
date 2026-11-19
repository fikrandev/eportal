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

    // Handle Delete Media File directly
    if ($action === 'delete') {
        $input = get_input();
        $path = trim($input['path'] ?? '');
        if (!$path) {
            throw new Exception('Path file tidak boleh kosong', 400);
        }

        // Security: Only allow deleting inside modules/e-examination/uploads/images/ or uploads/audio/
        $normalized = str_replace(['\\', '..'], ['/', ''], $path);
        $normalized = ltrim($normalized, '/');

        if (strpos($normalized, 'uploads/images/') !== 0 && strpos($normalized, 'uploads/audio/') !== 0) {
            throw new Exception('Path file tidak valid atau berada di luar folder yang diizinkan', 403);
        }

        $allowedDir = strpos($normalized, 'uploads/audio/') === 0
            ? 'modules/e-examination/uploads/audio'
            : 'modules/e-examination/uploads/images';
        if (!safe_unlink_in_dir('modules/e-examination/' . $normalized, $allowedDir)) {
            throw new Exception('Gagal menghapus file atau file berada di luar direktori yang diizinkan.', 500);
        }

        json_response(200, true, 'File media berhasil dihapus dari server.');
    }

    // 1. Check if payload exceeded post_max_size (which causes PHP to discard $_POST and $_FILES)
    if (empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
        $postMax = ini_get('post_max_size');
        $uploadMax = ini_get('upload_max_filesize');
        throw new Exception("Ukuran file melebihi batas 'post_max_size' server (Limit server: post_max_size={$postMax}, upload_max_filesize={$uploadMax}). Harap naikkan limit PHP di server hosting atau kompres file terlebih dahulu.", 400);
    }

    // 2. Validate file existence and upload errors
    if (!isset($_FILES['file'])) {
        throw new Exception('File tidak ditemukan dalam request upload. Pastikan parameter input bernama "file".', 400);
    }

    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['file']['error'];
        $uploadMax = ini_get('upload_max_filesize');
        $postMax = ini_get('post_max_size');
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => "Ukuran file melebihi batas 'upload_max_filesize' server (Limit PHP server saat ini: {$uploadMax}). Silakan naikkan limit di cPanel/php.ini.",
            UPLOAD_ERR_FORM_SIZE  => "Ukuran file melebihi batas MAX_FILE_SIZE pada form HTML.",
            UPLOAD_ERR_PARTIAL    => "File hanya terunggah sebagian karena koneksi terputus. Silakan upload ulang.",
            UPLOAD_ERR_NO_FILE    => "Tidak ada file yang dipilih untuk diunggah.",
            UPLOAD_ERR_NO_TMP_DIR => "Folder temporary PHP server tidak ditemukan. Hubungi penyedia hosting.",
            UPLOAD_ERR_CANT_WRITE => "Server gagal menulis file ke disk (Izin folder temp atau disk server penuh).",
            UPLOAD_ERR_EXTENSION  => "Proses upload dihentikan oleh ekstensi PHP server.",
        ];
        $msg = $errorMessages[$errCode] ?? ("File upload gagal dengan error code: {$errCode}.");
        throw new Exception($msg, 400);
    }

    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($action === 'audio') {
        // Audio upload for listening
        $allowed = ['mp3', 'ogg', 'wav', 'webm', 'm4a', 'aac', 'flac', 'mp4', 'opus', '3gp'];
        $maxSize = 25 * 1024 * 1024; // 25MB
        $uploadDir = __DIR__ . '/../uploads/audio/';
    } else {
        // Image upload for soal (no SVG to prevent stored XSS)
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        $uploadDir = __DIR__ . '/../uploads/images/';
    }

    if (!in_array($ext, $allowed, true)) {
        throw new Exception('Tipe file .' . $ext . ' tidak diizinkan. Gunakan format: ' . implode(', ', $allowed), 400);
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('Sumber upload tidak valid.', 400);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);

    if ($action === 'audio') {
        $allowedAudioMimes = [
            'audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/vnd.wave',
            'audio/webm', 'video/webm', 'audio/mp4', 'video/mp4', 'audio/m4a', 'audio/x-m4a',
            'audio/aac', 'audio/x-aac', 'audio/flac', 'audio/x-flac', 'audio/opus',
            'audio/3gpp', 'video/3gpp', 'application/ogg'
        ];
        if ($detectedMime && !in_array($detectedMime, $allowedAudioMimes, true) && strpos($detectedMime, 'audio/') !== 0) {
            throw new Exception('Isi file audio tidak valid (' . $detectedMime . ').', 400);
        }
    } else {
        $allowedImageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!$detectedMime || !in_array($detectedMime, $allowedImageMimes, true) || !@getimagesize($file['tmp_name'])) {
            throw new Exception('File bukan gambar valid (JPG, PNG, GIF, WebP).', 400);
        }
    }

    if ($file['size'] > $maxSize) {
        throw new Exception('Ukuran file (' . round($file['size'] / 1024 / 1024, 2) . 'MB) melebihi batas aplikasi. Maksimal: ' . ($maxSize / 1024 / 1024) . 'MB', 400);
    }

    // 3. Ensure destination directory exists and is writable
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0777, true)) {
            $parentDir = dirname($uploadDir);
            throw new Exception("Server gagal membuat folder upload: {$uploadDir}. Izin folder parent ({$parentDir}) tidak writable. Silakan jalankan 'chmod -R 777 modules/e-examination/uploads' di server.", 500);
        }
        @chmod($uploadDir, 0777);
    }

    if (!is_writable($uploadDir)) {
        @chmod($uploadDir, 0777);
        if (!is_writable($uploadDir)) {
            throw new Exception("Folder upload di server tidak dapat ditulis (Permission Denied): {$uploadDir}. Silakan jalankan perintah 'chmod -R 777 modules/e-examination/uploads' atau sesuaikan owner folder ke user web server (chown -R www-data:www-data) di terminal hosting/server.", 500);
        }
    }

    // 4. Generate unique filename and store file
    $filename = uniqid('exam_', true) . '.' . $ext;
    $filepath = $uploadDir . $filename;

    $moved = @move_uploaded_file($file['tmp_name'], $filepath);

    if (!$moved) {
        $lastErr = error_get_last();
        $detail = $lastErr ? ' (' . $lastErr['message'] . ')' : '';
        throw new Exception("Gagal menyimpan file ke disk server{$detail}. Pastikan folder uploads memiliki izin tulis penuh (chmod 777).", 500);
    }

    @chmod($filepath, 0664);

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
