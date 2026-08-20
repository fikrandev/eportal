<?php
/**
 * E-Graduation letter number API.
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/auth_helper.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get':
        grad_auth();
        getLetterSetup();
        break;
    case 'teachers':
        grad_auth();
        listTeachers();
        break;
    case 'list':
        grad_auth();
        listStudentLetters();
        break;
    case 'save-generate':
        grad_require_manage();
        saveAndGenerate();
        break;
    case 'save-skl-settings':
        grad_require_manage();
        saveSklSettings();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function defaultLetterFormat()
{
    return '{nomor} / I04.1/SMA.WH1/V/' . date('Y');
}

function getLetterSetup()
{
    try {
        $academicYearId = grad_active_year_id();
        $setting = getSetting($academicYearId);
        $totalStudents = countActiveStudents($academicYearId);
        $generatedTotal = countGeneratedLetters($academicYearId);
        $teachers = getTeachersData();

        if (!$setting) {
            $suggested = findSuggestedHeadmaster($teachers);
            $centralHeadmaster = get_setting('kepala_sekolah', '');
            $setting = [
                'id' => null,
                'academic_year_id' => $academicYearId,
                'start_number' => 1,
                'total' => $totalStudents,
                'letter_format' => defaultLetterFormat(),
                'graduation_date' => null,
                'signing_date' => null,
                'headmaster_user_id' => $suggested['id'] ?? null,
                'headmaster_name' => $centralHeadmaster ?: ($suggested['nama_lengkap'] ?? ''),
                'headmaster_niy' => $suggested['username'] ?? '',
                'headmaster_position' => 'Kepala Sekolah',
                'kop_image' => get_setting('kop_surat', ''),
                'decision_number' => '',
                'decision_date' => null,
                'decision_about' => '',
                'skl_city' => '',
                'skl_npsn' => get_setting('npsn_sekolah', get_setting('npsn', '')),
                'skl_province' => get_setting('provinsi_sekolah', get_setting('provinsi', ''))
            ];
        } else {
            $setting['skl_npsn'] = get_setting('npsn_sekolah', get_setting('npsn', ''));
            $setting['skl_province'] = get_setting('provinsi_sekolah', get_setting('provinsi', ''));
        }

        json_response(200, true, 'Pengaturan nomor surat berhasil dimuat.', [
            'setting' => $setting,
            'total_students' => $totalStudents,
            'generated_total' => $generatedTotal,
            'teachers' => $teachers
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function listTeachers()
{
    try {
        json_response(200, true, 'Data guru berhasil dimuat.', getTeachersData());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function listStudentLetters()
{
    try {
        $academicYearId = grad_active_year_id();
        $stmt = db()->prepare("
            SELECT
                s.id as student_id,
                s.no_urut,
                s.nis,
                s.nisn,
                s.nama,
                s.jenis_kelamin,
                s.kelas,
                l.id as letter_id,
                l.sequence_no,
                l.letter_number,
                l.graduation_date,
                l.signing_date,
                l.headmaster_name,
                l.headmaster_niy,
                l.headmaster_position
            FROM grad_student_accounts a
            JOIN students s ON s.id = a.student_id
            LEFT JOIN grad_student_letters l ON l.student_id = s.id AND l.academic_year_id = s.academic_year_id
            WHERE a.academic_year_id = ? AND a.status = 1
            ORDER BY s.kelas ASC, s.no_urut ASC, s.nama ASC
        ");
        $stmt->execute([$academicYearId]);
        json_response(200, true, 'Data nomor surat berhasil dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function saveAndGenerate()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $academicYearId = grad_active_year_id();
    $startNumber = isset($input['start_number']) ? (int) $input['start_number'] : 1;
    $total = isset($input['total']) ? (int) $input['total'] : 0;
    $letterFormat = sanitize($input['letter_format'] ?? defaultLetterFormat());
    $graduationDate = sanitize($input['graduation_date'] ?? '');
    $signingDate = sanitize($input['signing_date'] ?? '');
    $headmasterUserId = isset($input['headmaster_user_id']) ? (int) $input['headmaster_user_id'] : 0;
    $headmasterPosition = sanitize($input['headmaster_position'] ?? 'Kepala Sekolah');

    if ($startNumber <= 0) {
        json_response(400, false, 'Nomor awal harus lebih dari 0.');
    }
    if ($total <= 0) {
        json_response(400, false, 'Total nomor surat harus lebih dari 0.');
    }
    if ($letterFormat === '') {
        json_response(400, false, 'Format nomor surat wajib diisi.');
    }
    if ($graduationDate === '' || $signingDate === '') {
        json_response(400, false, 'Tanggal kelulusan dan tanggal tanda tangan wajib diisi.');
    }
    if ($headmasterUserId <= 0) {
        json_response(400, false, 'Pilih kepala sekolah dari Data Guru.');
    }

    try {
        $totalStudents = countActiveStudents($academicYearId);
        if ($totalStudents <= 0) {
            json_response(400, false, 'Belum ada Data Siswa yang di-import pada tahun ajaran aktif.');
        }
        if ($total > $totalStudents) {
            json_response(400, false, 'Total nomor tidak boleh melebihi jumlah siswa yang terdaftar (' . $totalStudents . ').');
        }

        $teacher = getTeacherById($headmasterUserId);
        if (!$teacher) {
            json_response(404, false, 'Data kepala sekolah tidak ditemukan di Data Guru.');
        }

        if ($headmasterPosition === '') {
            $headmasterPosition = 'Kepala Sekolah';
        }

        db()->beginTransaction();

        $stmt = db()->prepare("
            INSERT INTO grad_letter_settings (
                academic_year_id, start_number, total, letter_format, graduation_date,
                signing_date, headmaster_user_id, headmaster_name, headmaster_niy, headmaster_position
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                start_number = VALUES(start_number),
                total = VALUES(total),
                letter_format = VALUES(letter_format),
                graduation_date = VALUES(graduation_date),
                signing_date = VALUES(signing_date),
                headmaster_user_id = VALUES(headmaster_user_id),
                headmaster_name = VALUES(headmaster_name),
                headmaster_niy = VALUES(headmaster_niy),
                headmaster_position = VALUES(headmaster_position)
        ");
        $stmt->execute([
            $academicYearId,
            $startNumber,
            $total,
            $letterFormat,
            $graduationDate,
            $signingDate,
            $teacher['id'],
            $teacher['nama_lengkap'],
            $teacher['username'],
            $headmasterPosition
        ]);

        $stmt = db()->prepare("DELETE FROM grad_student_letters WHERE academic_year_id = ?");
        $stmt->execute([$academicYearId]);

        $stmt = db()->prepare("
            SELECT s.id
            FROM grad_student_accounts a
            JOIN students s ON s.id = a.student_id
            WHERE a.academic_year_id = ? AND a.status = 1
            ORDER BY s.kelas ASC, s.no_urut ASC, s.nama ASC
            LIMIT {$total}
        ");
        $stmt->execute([$academicYearId]);
        $students = $stmt->fetchAll();

        $insert = db()->prepare("
            INSERT INTO grad_student_letters (
                academic_year_id, student_id, sequence_no, letter_number, graduation_date,
                signing_date, headmaster_name, headmaster_niy, headmaster_position
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($students as $index => $student) {
            $sequence = $startNumber + $index;
            $letterNumber = formatLetterNumber($letterFormat, $sequence);
            $insert->execute([
                $academicYearId,
                $student['id'],
                $sequence,
                $letterNumber,
                $graduationDate,
                $signingDate,
                $teacher['nama_lengkap'],
                $teacher['username'],
                $headmasterPosition
            ]);
        }

        db()->commit();
        json_response(200, true, 'Nomor surat berhasil disimpan dan digenerate.', [
            'generated_total' => count($students)
        ]);
    } catch (PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function saveSklSettings()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $academicYearId = grad_active_year_id();
    $decisionNumber = sanitize($_POST['decision_number'] ?? '');
    $decisionDate = sanitize($_POST['decision_date'] ?? '');
    $decisionAbout = sanitize($_POST['decision_about'] ?? '');
    $sklCity = sanitize($_POST['skl_city'] ?? '');
    $sklNpsn = sanitize($_POST['skl_npsn'] ?? '');
    $sklProvince = sanitize($_POST['skl_province'] ?? '');
    $kopImage = null;

    try {
        $current = getSetting($academicYearId);
        if (isset($_FILES['kop_image']) && $_FILES['kop_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = handle_upload($_FILES['kop_image'], 'letterheads/', ['jpg', 'jpeg', 'png', 'webp']);
            if (!$upload['success']) {
                json_response(400, false, $upload['message']);
            }
            $kopImage = $upload['path'];
            upsert_setting('kop_surat', $kopImage, 'file', 'Path kop surat global untuk semua modul');
        } else {
            $kopImage = $current['kop_image'] ?? get_setting('kop_surat', '');
        }

        $stmt = db()->prepare("
            INSERT INTO grad_letter_settings (
                academic_year_id, start_number, total, letter_format, graduation_date,
                signing_date, headmaster_user_id, headmaster_name, headmaster_niy, headmaster_position,
                kop_image, decision_number, decision_date, decision_about, skl_city
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                kop_image = VALUES(kop_image),
                decision_number = VALUES(decision_number),
                decision_date = VALUES(decision_date),
                decision_about = VALUES(decision_about),
                skl_city = VALUES(skl_city)
        ");
        $stmt->execute([
            $academicYearId,
            (int) ($current['start_number'] ?? 1),
            (int) ($current['total'] ?? countActiveStudents($academicYearId)),
            $current['letter_format'] ?? defaultLetterFormat(),
            $current['graduation_date'] ?? null,
            $current['signing_date'] ?? null,
            $current['headmaster_user_id'] ?? null,
            $current['headmaster_name'] ?? null,
            $current['headmaster_niy'] ?? null,
            $current['headmaster_position'] ?? 'Kepala Sekolah',
            $kopImage,
            $decisionNumber,
            $decisionDate ?: null,
            $decisionAbout,
            $sklCity
        ]);

        upsert_setting('npsn_sekolah', $sklNpsn, 'text', 'NPSN sekolah untuk format SKL');
        upsert_setting('provinsi_sekolah', $sklProvince, 'text', 'Provinsi sekolah untuk format SKL');

        json_response(200, true, 'Pengaturan format SKL berhasil disimpan.', [
            'kop_image' => $kopImage,
            'skl_npsn' => $sklNpsn,
            'skl_province' => $sklProvince
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function getSetting($academicYearId)
{
    $stmt = db()->prepare("SELECT * FROM grad_letter_settings WHERE academic_year_id = ?");
    $stmt->execute([$academicYearId]);
    $setting = $stmt->fetch();
    if (!$setting) return $setting;
    $centralKop = get_setting('kop_surat', '');
    if ($centralKop !== '') {
        $setting['kop_image'] = $centralKop;
    }
    $centralHeadmaster = get_setting('kepala_sekolah', '');
    if ($centralHeadmaster !== '') {
        $setting['headmaster_name'] = $centralHeadmaster;
    }
    return $setting;
}

function countActiveStudents($academicYearId)
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM grad_student_accounts WHERE academic_year_id = ? AND status = 1");
    $stmt->execute([$academicYearId]);
    return (int) $stmt->fetchColumn();
}

function countGeneratedLetters($academicYearId)
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM grad_student_letters WHERE academic_year_id = ?");
    $stmt->execute([$academicYearId]);
    return (int) $stmt->fetchColumn();
}

function getTeachersData()
{
    $stmt = db()->query("
        SELECT id, username, nama_lengkap, jabatan
        FROM users
        WHERE role = 'guru' AND status = 1
        ORDER BY
            CASE
                WHEN LOWER(jabatan) LIKE '%kepala%' THEN 0
                WHEN LOWER(nama_lengkap) LIKE '%kepala%' THEN 1
                ELSE 2
            END,
            nama_lengkap ASC
    ");
    return $stmt->fetchAll();
}

function getTeacherById($id)
{
    $stmt = db()->prepare("
        SELECT id, username, nama_lengkap, jabatan
        FROM users
        WHERE id = ? AND role = 'guru' AND status = 1
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function findSuggestedHeadmaster($teachers)
{
    foreach ($teachers as $teacher) {
        $needle = strtolower(($teacher['jabatan'] ?? '') . ' ' . ($teacher['nama_lengkap'] ?? ''));
        if (strpos($needle, 'kepala') !== false) {
            return $teacher;
        }
    }
    return $teachers[0] ?? null;
}

function formatLetterNumber($format, $number)
{
    $paddedNumber = str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    if (strpos($format, '{nomor}') !== false) {
        return str_replace('{nomor}', $paddedNumber, $format);
    }
    if (stripos($format, 'Nomor') !== false) {
        return str_ireplace('Nomor', $paddedNumber, $format);
    }
    return $paddedNumber . ' / ' . ltrim($format, '/ ');
}
