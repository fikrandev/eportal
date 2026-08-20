<?php
/**
 * E-Xam Card student status API.
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/xam_helper.php';

xam_auth();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'classes':
        examClasses();
        break;
    case 'list':
        classStudents();
        break;
    case 'save':
        saveStudentStatus();
        break;
    case 'bulk-generate':
        bulkGenerate();
        break;
    case 'bulk-status':
        bulkStatus();
        break;
    case 'import':
        importAccounts();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function examClasses()
{
    $user = xam_auth();
    $examId = (int) ($_GET['exam_id'] ?? 0);
    if ($examId <= 0) {
        json_response(400, false, 'Pilih ujian terlebih dahulu.');
    }
    $classes = xam_exam_classes($examId);
    if (!$classes) {
        json_response(200, true, 'Belum ada kelas terpilih untuk ujian ini.', []);
    }

    $sql = "
        SELECT s.kelas,
               COUNT(*) as total_siswa,
               SUM(CASE WHEN xs.status = 'OKE' THEN 1 ELSE 0 END) as total_oke,
               SUM(CASE WHEN xs.status = 'DITANGGUHKAN' THEN 1 ELSE 0 END) as total_tangguh
        FROM xam_exam_students xs
        JOIN students s ON s.id = xs.student_id
        WHERE xs.exam_id = ?
    ";
    $params = [$examId];

    if ($user['is_teacher'] && $user['managed_class']) {
        $sql .= " AND s.kelas = ?";
        $params[] = $user['managed_class'];
    }

    $sql .= " GROUP BY s.kelas ORDER BY s.kelas ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_response(200, true, 'Data kelas ujian berhasil dimuat.', $stmt->fetchAll());
}

function classStudents()
{
    $user = xam_auth();
    $examId = (int) ($_GET['exam_id'] ?? 0);
    $kelas = sanitize($_GET['kelas'] ?? '');
    
    if ($examId <= 0 || $kelas === '') {
        json_response(400, false, 'Exam dan kelas wajib dipilih.');
    }

    // Force teacher to their managed class
    if ($user['is_teacher']) {
        if (!$user['managed_class']) {
            json_response(403, false, 'Akses ditolak. Anda tidak memiliki kelas yang dikelola.');
        }
        $kelas = $user['managed_class'];
    }

    $sql = "
        SELECT xs.id as exam_student_id, xs.exam_id, xs.student_id, xs.ruang_ujian, xs.username, xs.password_plain, xs.status, xs.suspension_note,
               s.nis, s.nisn, s.nama, s.kelas, s.no_urut
        FROM xam_exam_students xs
        JOIN students s ON s.id = xs.student_id
        WHERE xs.exam_id = ?
    ";
    
    $params = [$examId];
    if ($kelas !== 'SEMUA') {
        $sql .= " AND s.kelas = ?";
        $params[] = $kelas;
    }
    
    $sql .= " ORDER BY s.kelas ASC, s.no_urut ASC, s.nama ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'exam_student_id' => (int) $row['exam_student_id'],
            'student_id' => (int) $row['student_id'],
            'nis' => $row['nis'],
            'nisn' => $row['nisn'],
            'nama' => $row['nama'],
            'kelas' => $row['kelas'],
            'ruang_ujian' => $row['ruang_ujian'] ?? '',
            'username' => $row['username'] ?? '',
            'password' => ($row['status'] === 'OKE') ? ($row['password_plain'] ?? '') : '',
            'password_raw' => $row['password_plain'] ?? '',
            'status' => $row['status'] ?: 'DITANGGUHKAN',
            'suspension_note' => $row['suspension_note'] ?: 'Silakan hubungi Wali Kelas / Waka. Kesiswaan'
        ];
    }

    json_response(200, true, 'Data status siswa berhasil dimuat.', [
        'kelas' => $kelas,
        'items' => $rows
    ]);
}

function saveStudentStatus()
{
    xam_require_manage();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = (int) ($input['exam_student_id'] ?? 0);
    if ($id <= 0) {
        json_response(400, false, 'Data siswa ujian tidak valid.');
    }

    $status = strtoupper(trim((string) ($input['status'] ?? 'DITANGGUHKAN')));
    if (!in_array($status, ['OKE', 'DITANGGUHKAN'], true)) {
        $status = 'DITANGGUHKAN';
    }

    $ruang = sanitize($input['ruang_ujian'] ?? '');
    $username = sanitize($input['username'] ?? '');
    $password = trim((string) ($input['password'] ?? ''));
    $note = sanitize($input['suspension_note'] ?? 'Silakan hubungi Wali Kelas / Waka. Kesiswaan');

    if ($username === '') {
        json_response(400, false, 'Username wajib diisi.');
    }

    if ($status === 'OKE' && $password === '') {
        json_response(400, false, 'Password wajib diisi untuk status OKE.');
    }

    $sql = "UPDATE xam_exam_students SET ruang_ujian = ?, username = ?, status = ?, suspension_note = ?, updated_at = NOW()";
    $params = [$ruang, $username, $status, $note];

    if ($password !== '') {
        $sql .= ', password_plain = ?, password_hash = ?';
        $params[] = $password;
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sql .= ' WHERE id = ?';
    $params[] = $id;

    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        json_response(200, true, 'Status siswa berhasil disimpan.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function bulkGenerate()
{
    xam_require_manage();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $examId = (int) ($input['exam_id'] ?? 0);
    $kelas = sanitize($input['kelas'] ?? '');
    if ($examId <= 0 || $kelas === '') {
        json_response(400, false, 'Exam dan kelas wajib dipilih.');
    }

    try {
        $sql = "
            SELECT xs.id, s.id as student_id, s.nis, s.nisn
            FROM xam_exam_students xs
            JOIN students s ON s.id = xs.student_id
            WHERE xs.exam_id = ?
        ";
        $params = [$examId];
        
        if ($kelas !== 'SEMUA') {
            $sql .= " AND s.kelas = ?";
            $params[] = $kelas;
        }
        
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            json_response(404, false, 'Data siswa ujian tidak ditemukan pada kelas ini.');
        }

        $upd = db()->prepare("\n            UPDATE xam_exam_students\n            SET username = ?, password_plain = ?, password_hash = ?, status = 'DITANGGUHKAN', updated_at = NOW()\n            WHERE id = ?\n        ");

        foreach ($rows as $row) {
            $username = xam_default_username($examId, $row);
            $pass = xam_default_password($row);
            $upd->execute([$username, $pass, password_hash($pass, PASSWORD_DEFAULT), $row['id']]);
        }

        json_response(200, true, 'Username & password default berhasil digenerate ulang untuk kelas ini.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function bulkStatus()
{
    xam_require_manage();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $examId = (int) ($input['exam_id'] ?? 0);
    $kelas = sanitize($input['kelas'] ?? '');
    $status = strtoupper(sanitize($input['status'] ?? ''));
    
    if ($examId <= 0 || $kelas === '') {
        json_response(400, false, 'Exam dan kelas wajib dipilih.');
    }
    
    if (!in_array($status, ['OKE', 'DITANGGUHKAN'], true)) {
        json_response(400, false, 'Status tidak valid.');
    }

    try {
        $sql = "
            UPDATE xam_exam_students xs
            JOIN students s ON s.id = xs.student_id
            SET xs.status = ?, xs.updated_at = NOW()
            WHERE xs.exam_id = ?
        ";
        $params = [$status, $examId];
        
        if ($kelas !== 'SEMUA') {
            $sql .= " AND s.kelas = ?";
            $params[] = $kelas;
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        json_response(200, true, 'Status massal berhasil diubah.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function importAccounts()
{
    xam_require_manage();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $examId = (int) ($input['exam_id'] ?? 0);
    $data = $input['data'] ?? [];

    if ($examId <= 0 || empty($data)) {
        json_response(400, false, 'Data import tidak lengkap.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $count = 0;
        $stmt = $pdo->prepare("
            UPDATE xam_exam_students xs
            JOIN students s ON s.id = xs.student_id
            SET xs.ruang_ujian = ?, 
                xs.username = ?, 
                xs.password_plain = ?, 
                xs.password_hash = ?, 
                xs.updated_at = NOW()
            WHERE xs.exam_id = ? AND s.nis = ?
        ");

        foreach ($data as $row) {
            $nis = trim((string) ($row['nis'] ?? ''));
            $ruang = sanitize($row['ruang'] ?? '');
            $username = sanitize($row['username'] ?? '');
            $password = trim((string) ($row['password'] ?? ''));

            if ($nis === '' || $username === '') continue;

            $passHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
            
            $stmt->execute([
                $ruang,
                $username,
                $password,
                $passHash,
                $examId,
                $nis
            ]);
            
            if ($stmt->rowCount() > 0) {
                $count++;
            }
        }

        $pdo->commit();
        json_response(200, true, "Berhasil mengupdate $count data akun siswa.");
    } catch (Exception $e) {
        $pdo->rollBack();
        json_response(500, false, 'Gagal mengimport data: ' . $e->getMessage());
    }
}
