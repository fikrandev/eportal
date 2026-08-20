<?php
/**
 * Public student portal API for E-Graduation.
 */
require_once __DIR__ . '/../../../api/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'login':
        studentLogin();
        break;
    case 'me':
        studentMe();
        break;
    case 'scores':
        studentScores();
        break;
    case 'logout':
        studentLogout();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function studentLogin()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }
    $input = get_input();
    $username = sanitize($input['username'] ?? '');
    $password = trim((string) ($input['password'] ?? ''));
    if ($username === '' || $password === '') {
        json_response(400, false, 'Username dan password wajib diisi.');
    }

    try {
        $yearId = activeYearIdForStudent();
        $stmt = db()->prepare("
            SELECT a.*, s.nis, s.nisn, s.nama, s.kelas, s.tanggal_lahir, s.foto_path
            FROM grad_student_accounts a
            JOIN students s ON s.id = a.student_id
            WHERE a.academic_year_id = ? AND a.username = ? AND a.status = 1
        ");
        $stmt->execute([$yearId, $username]);
        $account = $stmt->fetch();
        if (!$account || !password_verify($password, $account['password_hash'])) {
            json_response(401, false, 'Username atau password salah.');
        }

        $token = generate_token();
        $expiredAt = date('Y-m-d H:i:s', time() + SESSION_DURATION);
        db()->prepare("DELETE FROM grad_student_sessions WHERE expired_at < NOW()")->execute();
        $stmt = db()->prepare("
            INSERT INTO grad_student_sessions (account_id, token, ip_address, user_agent, expired_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $account['id'],
            $token,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expiredAt
        ]);
        db()->prepare("UPDATE grad_student_accounts SET last_login = NOW() WHERE id = ?")->execute([$account['id']]);

        json_response(200, true, 'Login siswa berhasil.', [
            'token' => $token,
            'student' => studentPayload($account),
            'announcement' => announcementPayload($yearId, $account)
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function studentMe()
{
    $auth = studentAuth();
    json_response(200, true, 'Sesi siswa aktif.', [
        'student' => studentPayload($auth),
        'announcement' => announcementPayload((int) $auth['academic_year_id'], $auth)
    ]);
}

function studentScores()
{
    $auth = studentAuth();
    $announcement = announcementPayload((int) $auth['academic_year_id'], $auth);
    if ($announcement['mode'] !== 'published') {
        json_response(403, false, 'Nilai belum dapat dilihat sebelum pengumuman kelulusan dibuka.');
    }

    try {
        $subjects = subjectsForStudent((int) $auth['academic_year_id'], $auth['kelas']);
        $scores = scoresForStudent((int) $auth['academic_year_id'], (int) $auth['student_id']);
        $items = [];
        foreach ($subjects as $subject) {
            $items[] = [
                'kode_mapel' => $subject['kode_mapel'],
                'nama_mapel' => $subject['nama_mapel'],
                'group_kode' => $subject['group_kode'],
                'group_nama' => $subject['group_nama'],
                'nilai_akhir' => $scores[$subject['id']] ?? ''
            ];
        }
        json_response(200, true, 'Nilai berhasil dimuat.', ['items' => $items]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function studentLogout()
{
    $token = bearerOrParamToken();
    if ($token !== '') {
        db()->prepare("DELETE FROM grad_student_sessions WHERE token = ?")->execute([$token]);
    }
    json_response(200, true, 'Logout berhasil.');
}

function studentAuth()
{
    $token = bearerOrParamToken();
    if ($token === '') {
        json_response(401, false, 'Token siswa diperlukan.');
    }
    $stmt = db()->prepare("
        SELECT a.id as account_id, a.student_id, a.academic_year_id, a.username,
               s.nis, s.nisn, s.nama, s.kelas, s.tanggal_lahir, s.foto_path
        FROM grad_student_sessions ss
        JOIN grad_student_accounts a ON a.id = ss.account_id
        JOIN students s ON s.id = a.student_id
        WHERE ss.token = ? AND ss.expired_at > NOW() AND a.status = 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) {
        json_response(401, false, 'Sesi siswa telah berakhir.');
    }
    return $row;
}

function announcementPayload($yearId, $student)
{
    $stmt = db()->prepare("SELECT announcement_status, announcement_at FROM grad_letter_settings WHERE academic_year_id = ?");
    $stmt->execute([$yearId]);
    $setting = $stmt->fetch() ?: ['announcement_status' => 'not_set', 'announcement_at' => null];
    $active = get_active_academic_year();
    $status = $setting['announcement_status'] ?? 'not_set';
    $at = $setting['announcement_at'] ?? null;
    $mode = 'not_set';
    $seconds = 0;
    if ($status === 'published') {
        $mode = 'published';
    } elseif ($status === 'scheduled' && $at) {
        $seconds = strtotime($at) - time();
        $mode = $seconds <= 0 ? 'published' : 'countdown';
        $seconds = max(0, $seconds);
    }

    return [
        'mode' => $mode,
        'announcement_at' => $at,
        'seconds_remaining' => $seconds,
        'tahun_ajaran' => $active['tahun_ajaran'] ?? '',
        'student_name' => $student['nama'] ?? '',
        'nis' => $student['nis'] ?? '',
        'kelas' => $student['kelas'] ?? '',
        'foto_url' => !empty($student['foto_path']) ? BASE_URL . $student['foto_path'] : ''
    ];
}

function studentPayload($row)
{
    return [
        'id' => (int) $row['student_id'],
        'nis' => $row['nis'],
        'nisn' => $row['nisn'],
        'nama' => $row['nama'],
        'kelas' => $row['kelas'],
        'tanggal_lahir' => $row['tanggal_lahir'],
        'foto_url' => !empty($row['foto_path']) ? BASE_URL . $row['foto_path'] : ''
    ];
}

function subjectsForStudent($yearId, $kelas)
{
    $stmt = db()->prepare("
        SELECT s.*, g.kode as group_kode, g.nama as group_nama, g.tipe as group_tipe
        FROM grad_subjects s
        JOIN grad_subject_groups g ON g.id = s.group_id
        WHERE s.academic_year_id = ?
          AND (g.tipe <> 'pilihan' OR FIND_IN_SET(REPLACE(?, ' ', ''), REPLACE(COALESCE(s.kelas, ''), ' ', '')) > 0)
        ORDER BY g.urutan ASC, g.kode ASC, s.urutan ASC, s.nama_mapel ASC
    ");
    $stmt->execute([$yearId, $kelas]);
    return $stmt->fetchAll();
}

function scoresForStudent($yearId, $studentId)
{
    $stmt = db()->prepare("SELECT subject_id, nilai_akhir FROM grad_student_scores WHERE academic_year_id = ? AND student_id = ?");
    $stmt->execute([$yearId, $studentId]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['subject_id']] = $row['nilai_akhir'];
    }
    return $map;
}

function activeYearIdForStudent()
{
    $active = get_active_academic_year();
    $id = (int) ($active['id'] ?? 0);
    if ($id <= 0) {
        json_response(400, false, 'Tahun ajaran aktif belum diatur.');
    }
    return $id;
}

function bearerOrParamToken()
{
    $token = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    } elseif (isset($_GET['token'])) {
        $token = $_GET['token'];
    }
    return sanitize($token);
}
