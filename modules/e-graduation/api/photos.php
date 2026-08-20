<?php
/**
 * Photo management API for E-Graduation.
 * Handles single file upload, ZIP batch upload, listing, and deletion.
 * Photos are matched to students by NIS filename.
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/auth_helper.php';

$user = grad_require_manage();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        listPhotos();
        break;
    case 'upload':
        uploadPhoto();
        break;
    case 'upload-zip':
        uploadZip();
        break;
    case 'delete':
        deletePhoto();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List all graduation students with photo status.
 */
function listPhotos()
{
    $active = get_active_academic_year();
    $yearId = (int) ($active['id'] ?? 0);
    if ($yearId <= 0) {
        json_response(400, false, 'Tahun ajaran aktif belum diatur.');
    }

    $stmt = db()->prepare("
        SELECT s.id, s.nis, s.nisn, s.nama, s.kelas, s.foto_path
        FROM students s
        INNER JOIN grad_student_accounts a ON a.student_id = s.id AND a.academic_year_id = ? AND a.status = 1
        ORDER BY s.kelas ASC, s.nama ASC
    ");
    $stmt->execute([$yearId]);
    $students = $stmt->fetchAll();

    $baseUrl = defined('BASE_URL') ? BASE_URL : '/';
    $items = [];
    foreach ($students as $s) {
        $items[] = [
            'id'        => (int) $s['id'],
            'nis'       => $s['nis'],
            'nisn'      => $s['nisn'],
            'nama'      => $s['nama'],
            'kelas'     => $s['kelas'],
            'foto_path' => $s['foto_path'] ?: '',
            'foto_url'  => !empty($s['foto_path']) ? $baseUrl . $s['foto_path'] : '',
            'has_photo'  => !empty($s['foto_path']) && file_exists(realpath(__DIR__ . '/../../../') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($s['foto_path'], '/\\')))
        ];
    }

    $totalWithPhoto = count(array_filter($items, fn($i) => $i['has_photo']));
    json_response(200, true, 'OK', [
        'items' => $items,
        'total' => count($items),
        'total_with_photo' => $totalWithPhoto
    ]);
}

/**
 * Upload a single photo. File name must be the student's NIS (e.g. 10001.jpg).
 */
function uploadPhoto()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        json_response(400, false, 'File foto tidak ditemukan atau gagal diupload.');
    }

    $file = $_FILES['photo'];
    $result = processPhotoFile($file['name'], $file['tmp_name']);

    if ($result['success']) {
        json_response(200, true, "Foto berhasil diupload untuk {$result['nama']} (NIS: {$result['nis']}).", $result);
    } else {
        json_response(400, false, $result['message']);
    }
}

/**
 * Upload a ZIP file containing multiple student photos.
 */
function uploadZip()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    if (empty($_FILES['zipfile']) || $_FILES['zipfile']['error'] !== UPLOAD_ERR_OK) {
        json_response(400, false, 'File ZIP tidak ditemukan atau gagal diupload.');
    }

    $file = $_FILES['zipfile'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'zip') {
        json_response(400, false, 'File harus berformat ZIP.');
    }

    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true) {
        json_response(400, false, 'Gagal membuka file ZIP.');
    }

    $root = realpath(__DIR__ . '/../../../');
    $tempDir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'temp_zip_' . uniqid();
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    $zip->extractTo($tempDir);
    $zip->close();

    $results = ['success' => 0, 'failed' => 0, 'skipped' => 0, 'details' => []];
    $allowedExt = ['jpg', 'jpeg', 'png'];

    // Scan all files including subdirectories
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDir()) continue;
        $fileName = $fileInfo->getBasename();
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedExt)) {
            $results['skipped']++;
            continue;
        }

        $result = processPhotoFile($fileName, $fileInfo->getPathname());
        if ($result['success']) {
            $results['success']++;
            $results['details'][] = "✓ {$result['nis']} - {$result['nama']}";
        } else {
            $results['failed']++;
            $results['details'][] = "✗ {$fileName}: {$result['message']}";
        }
    }

    // Clean up temp directory
    deleteDirectory($tempDir);

    $msg = "Import selesai: {$results['success']} berhasil, {$results['failed']} gagal, {$results['skipped']} dilewati.";
    json_response(200, true, $msg, $results);
}

/**
 * Delete a student's photo.
 */
function deletePhoto()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $studentId = (int) ($input['student_id'] ?? 0);
    if ($studentId <= 0) {
        json_response(400, false, 'ID siswa tidak valid.');
    }

    $stmt = db()->prepare("SELECT foto_path FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
    if (!$student) {
        json_response(404, false, 'Siswa tidak ditemukan.');
    }

    // Delete physical file
    if (!empty($student['foto_path'])) {
        $root = realpath(__DIR__ . '/../../../');
        $fullPath = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($student['foto_path'], '/\\'));
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    // Clear database
    db()->prepare("UPDATE students SET foto_path = '' WHERE id = ?")->execute([$studentId]);

    json_response(200, true, 'Foto berhasil dihapus.');
}

/**
 * Process a single photo file: validate, match NIS, save, update DB.
 * If the student already has a photo, the old one is REPLACED.
 */
function processPhotoFile($originalName, $tmpPath)
{
    $allowedExt = ['jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $baseName = pathinfo($originalName, PATHINFO_FILENAME);

    if (!in_array($ext, $allowedExt)) {
        return ['success' => false, 'message' => 'Format file harus JPG atau PNG.'];
    }

    // NIS is the filename (without extension)
    $nis = trim($baseName);
    if ($nis === '') {
        return ['success' => false, 'message' => 'Nama file kosong.'];
    }

    // Find student by NIS in the active graduation year
    $active = get_active_academic_year();
    $yearId = (int) ($active['id'] ?? 0);

    $stmt = db()->prepare("
        SELECT s.id, s.nis, s.nama, s.foto_path
        FROM students s
        INNER JOIN grad_student_accounts a ON a.student_id = s.id AND a.academic_year_id = ? AND a.status = 1
        WHERE s.nis = ?
        LIMIT 1
    ");
    $stmt->execute([$yearId, $nis]);
    $student = $stmt->fetch();

    if (!$student) {
        return ['success' => false, 'message' => "NIS '{$nis}' tidak ditemukan di data siswa kelulusan."];
    }

    // Prepare upload directory
    $root = realpath(__DIR__ . '/../../../');
    $uploadDir = 'uploads' . DIRECTORY_SEPARATOR . 'students' . DIRECTORY_SEPARATOR . 'graduation';
    $fullDir = $root . DIRECTORY_SEPARATOR . $uploadDir;
    if (!is_dir($fullDir)) {
        mkdir($fullDir, 0755, true);
    }

    // Delete old photo if exists (REPLACE behavior)
    if (!empty($student['foto_path'])) {
        $oldPath = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($student['foto_path'], '/\\'));
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
    }

    // Save new photo — always save as jpg for consistency
    $targetExt = ($ext === 'png') ? 'png' : 'jpg';
    $targetName = $nis . '.' . $targetExt;
    $targetPath = $fullDir . DIRECTORY_SEPARATOR . $targetName;
    $relativePath = 'uploads/students/graduation/' . $targetName;

    if (!copy($tmpPath, $targetPath)) {
        return ['success' => false, 'message' => 'Gagal menyimpan file.'];
    }

    // Update database
    db()->prepare("UPDATE students SET foto_path = ? WHERE id = ?")->execute([$relativePath, $student['id']]);

    return [
        'success' => true,
        'nis' => $student['nis'],
        'nama' => $student['nama'],
        'foto_path' => $relativePath
    ];
}

/**
 * Recursively delete a directory.
 */
function deleteDirectory($dir)
{
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}
