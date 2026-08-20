<?php
/**
 * E-Portal account management API.
 * Generates and resets teacher/student login accounts from master data.
 */
require_once __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'teachers':
        listTeacherAccounts();
        break;
    case 'students':
        listStudentAccounts();
        break;
    case 'generate-teachers':
        generateTeacherAccounts();
        break;
    case 'generate-students':
        generateStudentAccounts();
        break;
    case 'reset-teacher':
        resetTeacherAccount();
        break;
    case 'update-teacher-username':
        updateTeacherUsername();
        break;
    case 'reset-student':
        resetStudentAccount();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function teacher_default_password()
{
    return '1234567';
}

function listTeacherAccounts()
{
    require_superadmin();

    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $where = "WHERE role = 'guru'";
    $params = [];
    if ($search !== '') {
        $where .= " AND (nama_lengkap LIKE ? OR username LIKE ? OR nik LIKE ? OR jabatan LIKE ?)";
        $like = "%{$search}%";
        array_push($params, $like, $like, $like, $like);
    }

    try {
        $stmt = db()->prepare("
            SELECT id, username, nik, nama_lengkap, tempat_lahir, tgl_lahir, jabatan, status_guru, status, last_login
            FROM users
            {$where}
            ORDER BY nama_lengkap ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $missingNik = 0;
        foreach ($rows as &$row) {
            $nik = trim((string) ($row['nik'] ?? ''));
            $row['nik'] = $nik;
            $row['suggested_username'] = $nik;
            $row['default_password'] = teacher_default_password();
            $row['has_generated_account'] = is_generated_teacher_username($row['username'], $nik);
            if ($nik === '') {
                $row['account_status'] = 'NIK belum diisi';
                $missingNik++;
            } else {
                $row['account_status'] = $row['has_generated_account'] ? 'Siap' : 'Perlu Generate';
            }
        }
        unset($row);
        json_response(200, true, 'Akun guru berhasil dimuat.', [
            'default_password' => teacher_default_password(),
            'total' => count($rows),
            'ready' => count(array_filter($rows, fn($r) => (bool) $r['has_generated_account'])),
            'missing_nik' => $missingNik,
            'data' => $rows
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function listStudentAccounts()
{
    require_superadmin();

    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $academicYearId = isset($_GET['academic_year_id']) ? (int) $_GET['academic_year_id'] : 0;
    if ($academicYearId <= 0) {
        $active = get_active_academic_year();
        $academicYearId = (int) ($active['id'] ?? 0);
    }
    if ($academicYearId <= 0) {
        json_response(400, false, 'Aktifkan tahun ajaran terlebih dahulu.');
    }

    $where = "WHERE s.academic_year_id = ?";
    $params = [$academicYearId];
    if ($search !== '') {
        $where .= " AND (s.nis LIKE ? OR s.nisn LIKE ? OR s.nama LIKE ? OR s.kelas LIKE ?)";
        $like = "%{$search}%";
        array_push($params, $like, $like, $like, $like);
    }

    try {
        $stmt = db()->prepare("
            SELECT s.id, s.academic_year_id, s.nis, s.nisn, s.nama, s.tanggal_lahir, s.kelas,
                   a.id AS account_id, a.username, a.status AS account_status, a.generated_at, a.last_login
            FROM students s
            LEFT JOIN grad_student_accounts a ON a.student_id = s.id AND a.academic_year_id = s.academic_year_id
            {$where}
            ORDER BY s.kelas ASC, s.no_urut ASC, s.nama ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['suggested_username'] = $row['nis'];
            $row['default_password'] = student_account_password($row['tanggal_lahir']);
            $row['has_generated_account'] = !empty($row['account_id']) && (string) $row['username'] === (string) $row['nis'];
            $row['account_label'] = !empty($row['account_id'])
                ? ($row['has_generated_account'] ? 'Siap' : 'Perlu Reset')
                : 'Perlu Generate';
        }
        unset($row);

        json_response(200, true, 'Akun siswa berhasil dimuat.', [
            'academic_year_id' => $academicYearId,
            'total' => count($rows),
            'ready' => count(array_filter($rows, fn($r) => (bool) $r['has_generated_account'])),
            'data' => $rows
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function generateTeacherAccounts()
{
    require_superadmin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    try {
        $stmt = db()->query("SELECT id, username, nik, nama_lengkap FROM users WHERE role = 'guru' ORDER BY nama_lengkap ASC");
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $update = db()->prepare("UPDATE users SET username = ?, password = ?, status = 1 WHERE id = ?");

        $generated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        $passwordHash = password_hash(teacher_default_password(), PASSWORD_DEFAULT);
        foreach ($teachers as $teacher) {
            $nik = trim((string) ($teacher['nik'] ?? ''));
            if ($nik === '') {
                $failed++;
                $errors[] = "Guru '{$teacher['nama_lengkap']}' dilewati karena NIK kosong.";
                continue;
            }
            if (is_generated_teacher_username($teacher['username'], $nik)) {
                $skipped++;
                continue;
            }
            if (teacher_username_exists($nik, (int) $teacher['id'])) {
                $failed++;
                $errors[] = "NIK '{$nik}' milik '{$teacher['nama_lengkap']}' tidak bisa dipakai karena username sudah digunakan akun lain.";
                continue;
            }
            $update->execute([$nik, $passwordHash, $teacher['id']]);
            $generated++;
        }

        json_response(200, true, 'Generate akun guru selesai.', [
            'generated' => $generated,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
            'default_password' => teacher_default_password()
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function generateStudentAccounts()
{
    require_superadmin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $academicYearId = isset($input['academic_year_id']) ? (int) $input['academic_year_id'] : 0;
    if ($academicYearId <= 0) {
        $active = get_active_academic_year();
        $academicYearId = (int) ($active['id'] ?? 0);
    }
    if ($academicYearId <= 0) {
        json_response(400, false, 'Aktifkan tahun ajaran terlebih dahulu.');
    }

    try {
        $stmt = db()->prepare("
            SELECT s.id, s.nis, s.tanggal_lahir, a.id AS account_id
            FROM students s
            LEFT JOIN grad_student_accounts a ON a.student_id = s.id AND a.academic_year_id = s.academic_year_id
            WHERE s.academic_year_id = ? AND s.nis <> ''
            ORDER BY s.no_urut ASC, s.nama ASC
        ");
        $stmt->execute([$academicYearId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $insert = db()->prepare("
            INSERT INTO grad_student_accounts (academic_year_id, student_id, username, password_hash, status, generated_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");

        $generated = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($students as $student) {
            if (!empty($student['account_id'])) {
                $skipped++;
                continue;
            }
            $password = student_account_password($student['tanggal_lahir']);
            if ($password === '') {
                $failed++;
                continue;
            }
            $insert->execute([
                $academicYearId,
                $student['id'],
                $student['nis'],
                password_hash($password, PASSWORD_DEFAULT)
            ]);
            $generated++;
        }

        json_response(200, true, 'Generate akun siswa selesai.', [
            'generated' => $generated,
            'skipped' => $skipped,
            'failed' => $failed
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function resetTeacherAccount()
{
    require_superadmin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    if ($id <= 0) {
        json_response(400, false, 'ID guru tidak valid.');
    }

    try {
        $stmt = db()->prepare("SELECT id, username, nik, nama_lengkap FROM users WHERE id = ? AND role = 'guru'");
        $stmt->execute([$id]);
        $teacher = $stmt->fetch();
        if (!$teacher) {
            json_response(404, false, 'Data guru tidak ditemukan.');
        }
        $username = trim((string) ($teacher['nik'] ?? ''));
        if ($username === '') {
            json_response(400, false, 'NIK guru kosong. Isi NIK terlebih dahulu sebelum reset akun.');
        }
        if (teacher_username_exists($username, (int) $teacher['id'])) {
            json_response(400, false, 'Username dari NIK sudah digunakan akun lain.');
        }
        $stmt = db()->prepare("UPDATE users SET username = ?, password = ?, status = 1 WHERE id = ?");
        $stmt->execute([$username, password_hash(teacher_default_password(), PASSWORD_DEFAULT), $id]);

        json_response(200, true, 'Akun guru berhasil direset.', [
            'username' => $username,
            'password' => teacher_default_password()
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function updateTeacherUsername()
{
    require_superadmin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    $username = normalize_teacher_username($input['username'] ?? '');
    if ($id <= 0) {
        json_response(400, false, 'ID guru tidak valid.');
    }
    if ($username === '') {
        json_response(400, false, 'Username wajib diisi.');
    }
    try {
        $stmt = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'guru'");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            json_response(404, false, 'Data guru tidak ditemukan.');
        }
        if (teacher_username_exists($username, $id)) {
            json_response(400, false, 'Username sudah digunakan.');
        }

        $stmt = db()->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->execute([$username, $id]);
        json_response(200, true, 'Username guru berhasil diubah.', ['username' => $username]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function resetStudentAccount()
{
    require_superadmin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $studentId = isset($input['student_id']) ? (int) $input['student_id'] : 0;
    if ($studentId <= 0) {
        json_response(400, false, 'ID siswa tidak valid.');
    }

    try {
        $stmt = db()->prepare("SELECT id, academic_year_id, nis, tanggal_lahir FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        if (!$student) {
            json_response(404, false, 'Data siswa tidak ditemukan.');
        }
        $password = student_account_password($student['tanggal_lahir']);
        if ($student['nis'] === '' || $password === '') {
            json_response(400, false, 'NIS dan tanggal lahir siswa harus lengkap.');
        }

        $stmt = db()->prepare("
            INSERT INTO grad_student_accounts (academic_year_id, student_id, username, password_hash, status, generated_at)
            VALUES (?, ?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                username = VALUES(username),
                password_hash = VALUES(password_hash),
                status = 1,
                generated_at = NOW()
        ");
        $stmt->execute([
            $student['academic_year_id'],
            $student['id'],
            $student['nis'],
            password_hash($password, PASSWORD_DEFAULT)
        ]);

        json_response(200, true, 'Akun siswa berhasil direset.', [
            'username' => $student['nis'],
            'password' => $password
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function is_generated_teacher_username($username, $nik = '')
{
    $username = trim((string) $username);
    $nik = trim((string) $nik);
    if ($username === '' || $nik === '') {
        return false;
    }
    return $username === $nik;
}

function normalize_teacher_username($value)
{
    $value = trim((string) $value);
    $value = preg_replace('/\s+/', '', $value);
    $value = preg_replace('/[^a-zA-Z0-9._@-]/', '', $value);
    if ($value === '') return '';
    if (strlen($value) < 3 || strlen($value) > 50) {
        json_response(400, false, 'Username harus 3-50 karakter.');
    }
    return $value;
}

function teacher_username_exists($username, $ignoreId)
{
    $stmt = db()->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
    $stmt->execute([$username, $ignoreId]);
    return (bool) $stmt->fetch();
}

function student_account_password($date)
{
    $ts = strtotime((string) $date);
    return $ts ? date('dmY', $ts) : '';
}
