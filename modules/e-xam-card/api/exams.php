<?php
/**
 * E-Xam Card exams API.
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/xam_helper.php';

xam_auth();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'meta':
        meta();
        break;
    case 'list':
        listExams();
        break;
    case 'get':
        getExam();
        break;
    case 'save':
        saveExam();
        break;
    case 'delete':
        deleteExam();
        break;
    case 'upload-template':
        uploadTemplate();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function uploadTemplate()
{
    xam_require_manage();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        json_response(400, false, 'ID ujian tidak valid.');
    }

    if (!isset($_FILES['template'])) {
        json_response(400, false, 'Permintaan upload tidak valid (File tidak dikirim).');
    }

    if ($_FILES['template']['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES['template']['error'];
        $msg = 'Gagal mengunggah file. Kode error: ' . $err;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            $msg = 'Ukuran file melebihi batas maksimal server (' . ini_get('upload_max_filesize') . ').';
        } elseif ($err === UPLOAD_ERR_NO_TMP_DIR) {
            $msg = 'Server kehilangan folder temporary (Hubungi pihak hosting).';
        } elseif ($err === UPLOAD_ERR_CANT_WRITE) {
            $msg = 'Server gagal menulis file ke disk (Penyimpanan penuh atau izin bermasalah).';
        }
        json_response(400, false, $msg);
    }

    $file = $_FILES['template'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png'];
    
    if (!in_array($ext, $allowedExts)) {
        json_response(400, false, 'Tipe file tidak didukung. Gunakan ekstensi JPG, JPEG, atau PNG.');
    }

    $filename = 'template_' . $id . '_' . time() . '.' . $ext;
    
    // Use absolute path for server-side operations
    $moduleDir = dirname(__DIR__);
    $uploadDir = $moduleDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            json_response(500, false, 'Gagal membuat folder upload. Pastikan folder ' . $moduleDir . ' memiliki izin tulis.');
        }
    }

    $targetPath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Relative path for database
        $dbPath = 'modules/e-xam-card/uploads/templates/' . $filename;
        
        try {
            $pdo = db();
            // Get old template to delete if exists
            $stmt = $pdo->prepare("SELECT card_template FROM xam_exams WHERE id = ?");
            $stmt->execute([$id]);
            $oldTemplate = $stmt->fetchColumn();
            
            if ($oldTemplate) {
                // Root directory is 3 levels up from this API file
                $rootDir = dirname(dirname(dirname(__DIR__)));
                $oldFile = $rootDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $oldTemplate);
                if (file_exists($oldFile)) {
                    @unlink($oldFile);
                }
            }

            $stmt = $pdo->prepare("UPDATE xam_exams SET card_template = ? WHERE id = ?");
            $stmt->execute([$dbPath, $id]);
            
            json_response(200, true, 'Template berhasil diunggah.', ['path' => $dbPath]);
        } catch (PDOException $e) {
            json_response(500, false, 'Server database error: ' . $e->getMessage());
        }
    } else {
        $errorDetails = error_get_last();
        $detailMsg = $errorDetails ? $errorDetails['message'] : 'Pastikan izin folder benar.';
        json_response(500, false, 'Gagal memindahkan file ke folder uploads/templates. ' . $detailMsg);
    }
}

function meta()
{
    $year = xam_active_year();
    json_response(200, true, 'Meta berhasil dimuat.', ['academic_year' => $year]);
}

function listExams()
{
    $yearId = xam_active_year_id();
    $stmt = db()->prepare("\n        SELECT e.*,\n               (SELECT COUNT(*) FROM xam_exam_classes c WHERE c.exam_id = e.id) as total_kelas,\n               (SELECT COUNT(*) FROM xam_exam_students xs WHERE xs.exam_id = e.id) as total_siswa\n        FROM xam_exams e\n        WHERE e.academic_year_id = ?\n        ORDER BY e.exam_start_date DESC, e.id DESC\n    ");
    $stmt->execute([$yearId]);
    json_response(200, true, 'Data ujian berhasil dimuat.', $stmt->fetchAll());
}

function getExam()
{
    $examId = (int) ($_GET['id'] ?? 0);
    if ($examId <= 0) {
        json_response(400, false, 'ID ujian tidak valid.');
    }

    $stmt = db()->prepare("SELECT * FROM xam_exams WHERE id = ? LIMIT 1");
    $stmt->execute([$examId]);
    $exam = $stmt->fetch();
    if (!$exam) {
        json_response(404, false, 'Ujian tidak ditemukan.');
    }

    json_response(200, true, 'Detail ujian.', $exam);
}

function saveExam()
{
    xam_require_manage();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = (int) ($input['id'] ?? 0);
    $yearId = xam_active_year_id();
    $name = sanitize($input['exam_name'] ?? '');
    $start = sanitize($input['exam_start_date'] ?? '');
    $end = sanitize($input['exam_end_date'] ?? '');

    if ($name === '' || $start === '' || $end === '') {
        json_response(400, false, 'Nama ujian, tanggal mulai, dan tanggal selesai wajib diisi.');
    }

    if (strtotime($end) < strtotime($start)) {
        json_response(400, false, 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
    }

    try {
        if ($id > 0) {
            $stmt = db()->prepare("\n                UPDATE xam_exams\n                SET exam_name = ?, exam_start_date = ?, exam_end_date = ?\n                WHERE id = ? AND academic_year_id = ?\n            ");
            $stmt->execute([$name, $start, $end, $id, $yearId]);
            json_response(200, true, 'Ujian berhasil diperbarui.', ['id' => $id]);
        }

        $stmt = db()->prepare("\n            INSERT INTO xam_exams (academic_year_id, exam_name, exam_start_date, exam_end_date)\n            VALUES (?, ?, ?, ?)\n        ");
        $stmt->execute([$yearId, $name, $start, $end]);
        $examId = (int) db()->lastInsertId();

        db()->prepare("\n            INSERT INTO xam_exam_settings (exam_id, letter_code)\n            VALUES (?, 'I04.1/SMA.WH1')\n        ")->execute([$examId]);

        json_response(201, true, 'Ujian berhasil ditambahkan.', ['id' => $examId]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteExam()
{
    xam_require_manage();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0) {
        json_response(400, false, 'ID ujian tidak valid.');
    }

    try {
        db()->prepare('DELETE FROM xam_exams WHERE id = ?')->execute([$id]);
        json_response(200, true, 'Ujian berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
