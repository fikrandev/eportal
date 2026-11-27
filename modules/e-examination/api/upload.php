<?php
/**
 * E-Examination — Upload API
 * Upload gambar soal & audio listening (mendukung single & chunked upload)
 */
require_once __DIR__ . '/config_exam.php';

header('Content-Type: application/json; charset=UTF-8');

$user = exam_require_admin_or_guru();
$action = $_GET['action'] ?? 'image';

/**
 * Daftar ekstensi & batas ukuran berdasarkan tipe media.
 */
function exam_media_spec($mediaType) {
    if ($mediaType === 'audio') {
        return [
            'allowed'   => ['mp3', 'ogg', 'oga', 'wav', 'webm', 'weba', 'm4a', 'aac', 'flac', 'mp4', 'opus', '3gp', 'mpeg', 'mpga', 'amr'],
            'max_size'  => 25 * 1024 * 1024, // 25 MB
            'dir'       => __DIR__ . '/../uploads/audio/',
            'rel_dir'   => 'uploads/audio/'
        ];
    }
    return [
        'allowed'   => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'max_size'  => 10 * 1024 * 1024, // 10 MB
        'dir'       => __DIR__ . '/../uploads/images/',
        'rel_dir'   => 'uploads/images/'
    ];
}

/**
 * Pastikan direktori tujuan ada dan dapat ditulis.
 */
function exam_ensure_writable_dir($dir) {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true)) {
            $parentDir = dirname($dir);
            throw new Exception("Server gagal membuat folder upload: {$dir}. Izin folder parent ({$parentDir}) tidak writable. Silakan jalankan 'chmod -R 777 modules/e-examination/uploads' di server.", 500);
        }
        @chmod($dir, 0777);
    }
    if (!is_writable($dir)) {
        @chmod($dir, 0777);
        if (!is_writable($dir)) {
            throw new Exception("Folder upload di server tidak dapat ditulis (Permission Denied): {$dir}. Silakan jalankan perintah 'chmod -R 777 modules/e-examination/uploads' atau sesuaikan owner folder ke user web server.", 500);
        }
    }
}

/**
 * Validasi keamanan isi file audio / gambar.
 */
function exam_validate_media_file($filePath, $mediaType) {
    $size = @filesize($filePath);
    if ($size === false || $size <= 0) {
        throw new Exception('File kosong atau gagal dibaca di server.', 400);
    }

    $head = (string)@file_get_contents($filePath, false, null, 0, 4096);
    if (preg_match('/<\?(?:php|=)|\s*<!doctype\s+html|<\s*(?:html|script|iframe|body)\b/i', $head)) {
        throw new Exception('File ditolak karena mengandung konten skrip/HTML tidak aman.', 400);
    }

    $detectedMime = '';
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detectedMime = (string)@finfo_file($finfo, $filePath);
            @finfo_close($finfo);
        }
    } elseif (function_exists('mime_content_type')) {
        $detectedMime = (string)@mime_content_type($filePath);
    }

    if ($mediaType === 'audio') {
        // File MP3 dengan tag ID3v2 (termasuk cover album) selalu diawali header "ID3"
        if ( strncmp($head, 'ID3', 3) === 0 ) {
            return;
        }
        $allowedAudioMimes = [
            'audio/mpeg', 'audio/mp3', 'audio/x-mpeg', 'audio/mpeg3', 'audio/x-mpeg-3',
            'audio/ogg', 'application/ogg', 'application/x-ogg',
            'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave',
            'audio/webm', 'video/webm',
            'audio/mp4', 'video/mp4', 'audio/m4a', 'audio/x-m4a', 'audio/mp4a-latm',
            'audio/aac', 'audio/aacp', 'audio/x-aac', 'audio/x-hx-aac-adts',
            'audio/flac', 'audio/x-flac', 'audio/opus', 'audio/amr',
            'audio/3gpp', 'video/3gpp', 'audio/3gpp2', 'video/3gpp2',
            'video/quicktime', 'video/mp2t', 'video/mpeg',
            'application/octet-stream'
        ];
        if ($detectedMime !== '' && !in_array($detectedMime, $allowedAudioMimes, true) && strpos($detectedMime, 'audio/') !== 0) {
            throw new Exception('Isi file audio tidak valid (' . $detectedMime . '). Pastikan file benar-benar file musik/audio.', 400);
        }
    } else {
        $allowedImageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!$detectedMime || !in_array($detectedMime, $allowedImageMimes, true) || !@getimagesize($filePath)) {
            throw new Exception('File bukan gambar valid (JPG, PNG, GIF, WebP).', 400);
        }
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    // ==========================================
    // 1. DELETE MEDIA
    // ==========================================
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

    // Cek apakah payload terpotong karena post_max_size server
    if (empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
        $postMax = ini_get('post_max_size');
        $uploadMax = ini_get('upload_max_filesize');
        throw new Exception("Ukuran request melebihi batas server (post_max_size={$postMax}, upload_max_filesize={$uploadMax}).", 400);
    }

    // ==========================================
    // 2. CHUNKED UPLOAD (Bebas kendala upload_max_filesize 2MB)
    // ==========================================
    if ($action === 'chunk') {
        $mediaType = ($_POST['media_type'] ?? 'audio') === 'audio' ? 'audio' : 'image';
        $uploadId = trim((string)($_POST['upload_id'] ?? ''));
        $chunkIndex = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : -1;
        $totalChunks = isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : 0;
        $ext = strtolower(trim((string)($_POST['ext'] ?? '')));

        if (!preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $uploadId)) {
            throw new Exception('ID sesi upload bertahap tidak valid.', 400);
        }
        if ($chunkIndex < 0 || $totalChunks < 1 || $totalChunks > 100 || $chunkIndex >= $totalChunks) {
            throw new Exception('Parameter urutan chunk upload tidak valid.', 400);
        }

        $spec = exam_media_spec($mediaType);
        if (!in_array($ext, $spec['allowed'], true)) {
            throw new Exception('Tipe file .' . $ext . ' tidak diizinkan. Gunakan format: ' . implode(', ', $spec['allowed']), 400);
        }

        if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['chunk']['error'] ?? 'none';
            $uploadMax = ini_get('upload_max_filesize');
            throw new Exception("Gagal mengunggah bagian file ke-" . ($chunkIndex + 1) . " (kode: {$errCode}, limit PHP: {$uploadMax}).", 400);
        }
        if (!is_uploaded_file($_FILES['chunk']['tmp_name'])) {
            throw new Exception('Sumber bagian upload tidak valid.', 400);
        }

        exam_ensure_writable_dir($spec['dir']);
        $chunkDir = $spec['dir'] . '.chunks/';
        exam_ensure_writable_dir($chunkDir);

        // Bersihkan file sementara lama (> 2 jam)
        foreach (glob($chunkDir . '*.part') ?: [] as $oldPart) {
            if (@filemtime($oldPart) < time() - 7200) {
                @unlink($oldPart);
            }
        }

        $userId = (int)($user['id'] ?? 0);
        $partFile = $chunkDir . 'u' . $userId . '_' . $uploadId . '.part';

        $chunkData = @file_get_contents($_FILES['chunk']['tmp_name']);
        if ($chunkData === false) {
            throw new Exception('Gagal membaca data chunk sementara di server.', 500);
        }

        if ($chunkIndex === 0) {
            if (@file_put_contents($partFile, $chunkData, LOCK_EX) === false) {
                throw new Exception('Gagal menulis bagian awal file ke disk server.', 500);
            }
        } else {
            if (!is_file($partFile)) {
                throw new Exception('Sesi upload terputus atau kadaluarsa. Silakan ulangi simpan soal.', 400);
            }
            if (@file_put_contents($partFile, $chunkData, FILE_APPEND | LOCK_EX) === false) {
                @unlink($partFile);
                throw new Exception('Gagal menggabungkan bagian file di disk server.', 500);
            }
        }

        clearstatcache(true, $partFile);
        $currentSize = (int)@filesize($partFile);
        if ($currentSize > $spec['max_size']) {
            @unlink($partFile);
            throw new Exception('Ukuran file melebihi batas maksimal (' . ($spec['max_size'] / 1024 / 1024) . 'MB).', 400);
        }

        // Jika belum chunk terakhir, kembalikan status sukses parsial
        if ($chunkIndex + 1 < $totalChunks) {
            json_response(200, true, 'Chunk diterima', [
                'chunk_index'  => $chunkIndex,
                'total_chunks' => $totalChunks,
                'received'     => $currentSize
            ]);
        }

        // Chunk terakhir selesai digabungkan: validasi isi file utuh
        try {
            exam_validate_media_file($partFile, $mediaType);
        } catch (Exception $ve) {
            @unlink($partFile);
            throw $ve;
        }

        $filename = uniqid('exam_', true) . '.' . $ext;
        $filepath = $spec['dir'] . $filename;
        if (!@rename($partFile, $filepath)) {
            if (!@copy($partFile, $filepath)) {
                @unlink($partFile);
                throw new Exception('Gagal memindahkan file hasil gabungan ke folder uploads.', 500);
            }
            @unlink($partFile);
        }
        @chmod($filepath, 0664);

        $relativePath = $spec['rel_dir'] . $filename;
        json_response(200, true, 'File berhasil diupload', [
            'filename' => $filename,
            'path'     => $relativePath,
            'url'      => BASE_URL . 'modules/e-examination/' . $relativePath,
            'size'     => $currentSize,
            'ext'      => $ext
        ]);
    }

    // ==========================================
    // 3. SINGLE FILE UPLOAD (image / audio)
    // ==========================================
    if (!isset($_FILES['file'])) {
        throw new Exception('File tidak ditemukan dalam request upload. Pastikan parameter input bernama "file".', 400);
    }

    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['file']['error'];
        $uploadMax = ini_get('upload_max_filesize');
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => "Ukuran file melebihi batas 'upload_max_filesize' server (Limit PHP server saat ini: {$uploadMax}).",
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
    $mediaType = ($action === 'audio') ? 'audio' : 'image';
    $spec = exam_media_spec($mediaType);

    if (!in_array($ext, $spec['allowed'], true)) {
        throw new Exception('Tipe file .' . $ext . ' tidak diizinkan. Gunakan format: ' . implode(', ', $spec['allowed']), 400);
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('Sumber upload tidak valid.', 400);
    }

    if ($file['size'] > $spec['max_size']) {
        throw new Exception('Ukuran file (' . round($file['size'] / 1024 / 1024, 2) . 'MB) melebihi batas aplikasi. Maksimal: ' . ($spec['max_size'] / 1024 / 1024) . 'MB', 400);
    }

    exam_validate_media_file($file['tmp_name'], $mediaType);
    exam_ensure_writable_dir($spec['dir']);

    $filename = uniqid('exam_', true) . '.' . $ext;
    $filepath = $spec['dir'] . $filename;

    $moved = @move_uploaded_file($file['tmp_name'], $filepath);
    if (!$moved) {
        $lastErr = error_get_last();
        $detail = $lastErr ? ' (' . $lastErr['message'] . ')' : '';
        throw new Exception("Gagal menyimpan file ke disk server{$detail}. Pastikan folder uploads memiliki izin tulis penuh (chmod 777).", 500);
    }

    @chmod($filepath, 0664);

    $relativePath = $spec['rel_dir'] . $filename;
    json_response(200, true, 'File berhasil diupload', [
        'filename' => $filename,
        'path'     => $relativePath,
        'url'      => BASE_URL . 'modules/e-examination/' . $relativePath,
        'size'     => $file['size'],
        'ext'      => $ext
    ]);

} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    if ($code < 100 || $code >= 600) $code = 500;
    json_response($code, false, $e->getMessage());
}
