<?php
/**
 * Student Auth API - E-Xam Card
 */
require_once __DIR__ . '/../../../../api/config.php';
require_once __DIR__ . '/../../api/xam_helper.php';

header('Content-Type: application/json');

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (empty($input)) {
        $input = $_POST ?: [];
    }

    $nis = sanitize($input['nis'] ?? '');
    $dob = sanitize($input['dob'] ?? '');

    if (!$nis || !$dob) {
        json_response(400, false, 'NIS dan Password wajib diisi.');
    }

    // 1. Find student by NIS
    $stmt = db()->prepare("
        SELECT id, nama, kelas, academic_year_id, tanggal_lahir 
        FROM students 
        WHERE nis = ? AND status = 1
        LIMIT 1
    ");
    $stmt->execute([$nis]);
    $student = $stmt->fetch();

    if (!$student) {
        json_response(404, false, 'Data siswa tidak ditemukan. Periksa kembali NIS Anda.');
    }

    // 2. Validate password (DOB in dmY format)
    $dbDob = $student['tanggal_lahir'] ?? '';
    $expectedPassword = '';
    if ($dbDob) {
        $ts = strtotime($dbDob);
        $expectedPassword = $ts ? date('dmY', $ts) : '';
    }

    if ($dob !== $expectedPassword) {
        json_response(401, false, 'Password salah. Gunakan tanggal lahir Anda (contoh: 12052005).');
    }

    // 3. Find latest active exam for this student's class
    $stmt = db()->prepare("
        SELECT e.id as exam_id, e.exam_name, xs.status, xs.suspension_note
        FROM xam_exams e
        JOIN xam_exam_classes ec ON ec.exam_id = e.id
        JOIN xam_exam_students xs ON xs.exam_id = e.id AND xs.student_id = ?
        WHERE e.academic_year_id = ? AND e.status = 1 AND ec.kelas = ?
        ORDER BY e.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$student['id'], $student['academic_year_id'], $student['kelas']]);
    $examInfo = $stmt->fetch();

    if (!$examInfo) {
        json_response(404, false, 'Belum ada jadwal ujian aktif untuk kelas Anda.');
    }

    // 4. Generate temporary token for card viewing
    $tokenData = [
        'student_id' => $student['id'],
        'exam_id' => $examInfo['exam_id'],
        'exp' => time() + 3600 // 1 hour
    ];
    $token = base64_encode(json_encode($tokenData)) . '.' . hash_hmac('sha256', json_encode($tokenData), DB_NAME);

    json_response(200, true, 'Auth success', [
        'status' => $examInfo['status'],
        'suspension_note' => $examInfo['suspension_note'],
        'token' => $token
    ]);

} catch (Exception $e) {
    json_response(500, false, 'Internal Server Error: ' . $e->getMessage());
}
