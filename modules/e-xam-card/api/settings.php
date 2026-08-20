<?php
/**
 * E-Xam Card exam settings API.
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/xam_helper.php';

xam_auth();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'meta':
        settingsMeta();
        break;
    case 'get':
        getSettings();
        break;
    case 'save':
        saveSettings();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function settingsMeta()
{
    $yearId = xam_active_year_id();
    $teachers = teacherOptions();
    $classes = xam_all_classes($yearId);
    $exams = examOptions($yearId);

    json_response(200, true, 'Meta pengaturan berhasil dimuat.', [
        'exams' => $exams,
        'classes' => $classes,
        'teachers' => $teachers,
        'academic_year' => xam_active_year()
    ]);
}

function getSettings()
{
    $examId = (int) ($_GET['exam_id'] ?? 0);
    if ($examId <= 0) {
        json_response(400, false, 'Pilih nama ujian terlebih dahulu.');
    }

    $exam = findExam($examId);
    if (!$exam) {
        json_response(404, false, 'Ujian tidak ditemukan.');
    }

    $setting = xam_exam_setting($examId);
    $classes = xam_exam_classes($examId);
    $headmasterName = trim((string) ($setting['headmaster_name'] ?? ''));
    if ($headmasterName === '' && !empty($setting['headmaster_user_name'])) {
        $headmasterName = $setting['headmaster_user_name'];
    }

    $letterDate = $setting['letter_date'] ?? date('Y-m-d');
    $letterNumber = xam_compose_letter_number(
        $setting['letter_manual_no'] ?? '',
        $setting['letter_code'] ?? 'I04.1/SMA.WH1',
        $letterDate
    );

    json_response(200, true, 'Pengaturan ujian berhasil dimuat.', [
        'exam' => $exam,
        'setting' => [
            'letter_manual_no' => $setting['letter_manual_no'] ?? '',
            'letter_code' => $setting['letter_code'] ?? 'I04.1/SMA.WH1',
            'letter_number_preview' => $letterNumber,
            'letter_date' => $letterDate,
            'sign_date' => $setting['sign_date'] ?? date('Y-m-d'),
            'headmaster_user_id' => isset($setting['headmaster_user_id']) ? (int) $setting['headmaster_user_id'] : 0,
            'headmaster_name' => $headmasterName
        ],
        'selected_classes' => $classes
    ]);
}

function saveSettings()
{
    xam_require_manage();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $examId = (int) ($input['exam_id'] ?? 0);
    if ($examId <= 0) {
        json_response(400, false, 'Nama ujian wajib dipilih.');
    }

    $exam = findExam($examId);
    if (!$exam) {
        json_response(404, false, 'Ujian tidak ditemukan.');
    }

    $selectedClasses = isset($input['classes']) && is_array($input['classes']) ? array_values(array_unique(array_filter(array_map('trim', $input['classes'])))) : [];
    if (!$selectedClasses) {
        json_response(400, false, 'Pilih minimal satu kelas peserta ujian.');
    }

    $letterManualNo = sanitize($input['letter_manual_no'] ?? '');
    $letterCode = sanitize($input['letter_code'] ?? 'I04.1/SMA.WH1');
    $letterDate = sanitize($input['letter_date'] ?? date('Y-m-d'));
    $signDate = sanitize($input['sign_date'] ?? date('Y-m-d'));
    $headmasterUserId = (int) ($input['headmaster_user_id'] ?? 0);
    $headmasterName = sanitize($input['headmaster_name'] ?? '');

    if ($letterManualNo === '') {
        json_response(400, false, 'Nomor surat awal wajib diisi (contoh: 112).');
    }

    if ($headmasterUserId > 0) {
        $stmt = db()->prepare("SELECT nama_lengkap FROM users WHERE id = ? AND status = 1 LIMIT 1");
        $stmt->execute([$headmasterUserId]);
        $row = $stmt->fetch();
        if ($row) {
            $headmasterName = $row['nama_lengkap'];
        }
    }

    if ($headmasterName === '') {
        json_response(400, false, 'Data kepala sekolah wajib dipilih.');
    }

    try {
        db()->prepare("\n            INSERT INTO xam_exam_settings (exam_id, letter_manual_no, letter_code, letter_date, sign_date, headmaster_user_id, headmaster_name)\n            VALUES (?, ?, ?, ?, ?, ?, ?)\n            ON DUPLICATE KEY UPDATE\n                letter_manual_no = VALUES(letter_manual_no),\n                letter_code = VALUES(letter_code),\n                letter_date = VALUES(letter_date),\n                sign_date = VALUES(sign_date),\n                headmaster_user_id = VALUES(headmaster_user_id),\n                headmaster_name = VALUES(headmaster_name)\n        ")->execute([
            $examId,
            $letterManualNo,
            $letterCode,
            $letterDate,
            $signDate,
            $headmasterUserId > 0 ? $headmasterUserId : null,
            $headmasterName
        ]);

        db()->prepare("DELETE FROM xam_exam_classes WHERE exam_id = ?")->execute([$examId]);
        $insClass = db()->prepare("INSERT INTO xam_exam_classes (exam_id, kelas) VALUES (?, ?)");
        foreach ($selectedClasses as $kelas) {
            $insClass->execute([$examId, $kelas]);
        }

        syncExamStudents($examId, (int) $exam['academic_year_id'], $selectedClasses);

        json_response(200, true, 'Pengaturan ujian berhasil disimpan.', [
            'letter_number_preview' => xam_compose_letter_number($letterManualNo, $letterCode, $letterDate)
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function syncExamStudents($examId, $yearId, $classes)
{
    if (!$classes) return;

    // Extend timeout for large datasets (500+ students)
    @set_time_limit(120);
    @ini_set('max_execution_time', '120');

    $placeholders = implode(',', array_fill(0, count($classes), '?'));
    $params = array_merge([$yearId], $classes);

    $stmt = db()->prepare("
        SELECT id, nis, nisn
        FROM students
        WHERE academic_year_id = ? AND status = 1 AND kelas IN ($placeholders)
    ");
    $stmt->execute($params);
    $students = $stmt->fetchAll();

    // Get existing student IDs for this exam (to skip re-hashing passwords)
    $existingStmt = db()->prepare("SELECT student_id FROM xam_exam_students WHERE exam_id = ?");
    $existingStmt->execute([$examId]);
    $existingIds = $existingStmt->fetchAll(PDO::FETCH_COLUMN);
    $existingMap = array_flip($existingIds);

    $studentIds = [];

    db()->beginTransaction();
    try {
        $insertNew = db()->prepare("
            INSERT INTO xam_exam_students (exam_id, student_id, username, password_hash, password_plain, status)
            VALUES (?, ?, ?, ?, ?, 'DITANGGUHKAN')
            ON DUPLICATE KEY UPDATE
                username = COALESCE(NULLIF(xam_exam_students.username, ''), VALUES(username)),
                password_hash = COALESCE(NULLIF(xam_exam_students.password_hash, ''), VALUES(password_hash)),
                password_plain = COALESCE(NULLIF(xam_exam_students.password_plain, ''), VALUES(password_plain))
        ");

        foreach ($students as $student) {
            $sid = (int) $student['id'];
            $studentIds[] = $sid;

            // Only generate credentials for NEW students
            // Existing students keep their current username/password
            if (!isset($existingMap[$sid])) {
                $username = xam_default_username($examId, $student);
                $plain = xam_default_password($student);
                $hash = password_hash($plain, PASSWORD_DEFAULT);
                $insertNew->execute([$examId, $sid, $username, $hash, $plain]);
            }
        }

        // Remove students no longer in selected classes
        if ($studentIds) {
            $idList = implode(',', array_map('intval', $studentIds));
            db()->exec("DELETE FROM xam_exam_students WHERE exam_id = " . (int) $examId . " AND student_id NOT IN ($idList)");
        } else {
            db()->prepare("DELETE FROM xam_exam_students WHERE exam_id = ?")->execute([$examId]);
        }

        db()->commit();
    } catch (PDOException $e) {
        db()->rollBack();
        throw $e;
    }
}

function teacherOptions()
{
    $search = trim((string) ($_GET['q'] ?? ''));
    $sql = "SELECT id, nama_lengkap, username, role FROM users WHERE status = 1 AND role = 'guru'";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (nama_lengkap LIKE ? OR username LIKE ? OR nik LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    $sql .= ' ORDER BY nama_lengkap ASC LIMIT 100';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function examOptions($yearId)
{
    $stmt = db()->prepare("SELECT id, exam_name, exam_start_date, exam_end_date FROM xam_exams WHERE academic_year_id = ? ORDER BY exam_start_date DESC, id DESC");
    $stmt->execute([(int) $yearId]);
    return $stmt->fetchAll();
}

function findExam($examId)
{
    $stmt = db()->prepare('SELECT * FROM xam_exams WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $examId]);
    return $stmt->fetch();
}
