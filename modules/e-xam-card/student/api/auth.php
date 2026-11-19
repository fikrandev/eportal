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

    // 1. Find all student records by NIS (ordered from newest to oldest)
    $stmt = db()->prepare("
        SELECT id, nama, kelas, academic_year_id, tanggal_lahir, foto_path 
        FROM students 
        WHERE nis = ? AND status = 1
        ORDER BY academic_year_id DESC, id DESC
    ");
    $stmt->execute([$nis]);
    $studentRecords = $stmt->fetchAll();

    if (empty($studentRecords)) {
        json_response(404, false, 'Data siswa tidak ditemukan. Periksa kembali NIS Anda.');
    }

    // 2. Validate password against student's DOB (dmY format, Ymd format, or raw)
    $inputClean = preg_replace('/[^0-9]/', '', $dob);
    $passwordValid = false;
    foreach ($studentRecords as $rec) {
        $dbDob = $rec['tanggal_lahir'] ?? '';
        if (!$dbDob || $dbDob === '0000-00-00') continue;
        $ts = strtotime($dbDob);
        if (!$ts || $ts <= 0) continue;
        $dmy = date('dmY', $ts);
        $ymd = date('Ymd', $ts);
        if ($inputClean !== '' && ($inputClean === $dmy || $inputClean === $ymd || $dob === $dbDob)) {
            $passwordValid = true;
            break;
        }
    }

    if (!$passwordValid) {
        json_response(401, false, 'Password salah. Gunakan tanggal lahir Anda (contoh: 12052005).');
    }

    // 3. Resolve active academic year
    $activeYear = get_active_academic_year();
    $activeYearId = (int) ($activeYear['id'] ?? 0);

    // 4. Find exam where student is registered in xam_exam_students
    // Prioritize active exams in the active academic year, then latest active exams
    $stmt = db()->prepare("
        SELECT e.id as exam_id, e.exam_name, e.academic_year_id as exam_year_id,
               xs.id as xam_student_id, xs.student_id, xs.status, xs.suspension_note,
               s.nama, s.kelas, s.foto_path
        FROM xam_exam_students xs
        JOIN xam_exams e ON e.id = xs.exam_id
        JOIN students s ON s.id = xs.student_id
        WHERE s.nis = ? AND e.status = 1
        ORDER BY (e.academic_year_id = ?) DESC, e.academic_year_id DESC, e.id DESC
        LIMIT 1
    ");
    $stmt->execute([$nis, $activeYearId]);
    $examInfo = $stmt->fetch();

    // Fallback: If not found with e.status = 1, check any exam where student is registered
    if (!$examInfo) {
        $stmt = db()->prepare("
            SELECT e.id as exam_id, e.exam_name, e.academic_year_id as exam_year_id,
                   xs.id as xam_student_id, xs.student_id, xs.status, xs.suspension_note,
                   s.nama, s.kelas, s.foto_path
            FROM xam_exam_students xs
            JOIN xam_exams e ON e.id = xs.exam_id
            JOIN students s ON s.id = xs.student_id
            WHERE s.nis = ?
            ORDER BY (e.academic_year_id = ?) DESC, e.academic_year_id DESC, e.id DESC
            LIMIT 1
        ");
        $stmt->execute([$nis, $activeYearId]);
        $examInfo = $stmt->fetch();
    }

    // Auto-enroll: If student is valid but not yet enrolled in active exam, enroll automatically
    if (!$examInfo) {
        $stmtActiveExam = db()->prepare("
            SELECT e.id as exam_id, e.exam_name, e.academic_year_id as exam_year_id
            FROM xam_exams e
            WHERE e.status = 1
            ORDER BY (e.academic_year_id = ?) DESC, e.academic_year_id DESC, e.id DESC
            LIMIT 1
        ");
        $stmtActiveExam->execute([$activeYearId]);
        $activeExam = $stmtActiveExam->fetch();

        if ($activeExam) {
            $latestStudent = xam_get_latest_student_info($nis);
            if ($latestStudent) {
                $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                $plain = substr(str_shuffle($chars), 0, 4);
                $hash = password_hash($plain, PASSWORD_BCRYPT, ['cost' => 4]);
                $uname = 'U' . str_pad($latestStudent['nis'], 6, '0', STR_PAD_LEFT);

                try {
                    $stmtIns = db()->prepare("
                        INSERT INTO xam_exam_students (exam_id, student_id, username, password_hash, password_plain, status)
                        VALUES (?, ?, ?, ?, ?, 'OKE')
                    ");
                    $stmtIns->execute([(int)$activeExam['exam_id'], (int)$latestStudent['id'], $uname, $hash, $plain]);
                    $newXamStudentId = (int) db()->lastInsertId();

                    $examInfo = [
                        'exam_id' => $activeExam['exam_id'],
                        'exam_name' => $activeExam['exam_name'],
                        'exam_year_id' => $activeExam['exam_year_id'],
                        'xam_student_id' => $newXamStudentId,
                        'student_id' => $latestStudent['id'],
                        'status' => 'OKE',
                        'suspension_note' => 'Silakan hubungi Wali Kelas / Waka. Kesiswaan',
                        'nama' => $latestStudent['nama'],
                        'kelas' => $latestStudent['kelas'],
                        'foto_path' => $latestStudent['foto_path']
                    ];
                } catch (Exception $e) {}
            }
        }
    }

    if (!$examInfo) {
        json_response(404, false, 'Belum ada jadwal kartu ujian aktif untuk data Anda. Silakan hubungi admin sekolah.');
    }

    // 5. Ensure student record is synchronized with the student's latest active class & info
    $latestStudent = xam_get_latest_student_info($nis);
    $finalStudentId = (int) $examInfo['student_id'];

    if ($latestStudent && (int)$latestStudent['id'] !== $finalStudentId) {
        try {
            // Update xam_exam_students to point to latest student record
            db()->prepare("UPDATE xam_exam_students SET student_id = ? WHERE id = ?")
                ->execute([$latestStudent['id'], $examInfo['xam_student_id']]);
            $finalStudentId = (int) $latestStudent['id'];
        } catch (Exception $e) {
            $finalStudentId = (int) $latestStudent['id'];
        }
    }

    // 6. Generate temporary token for card viewing
    $tokenData = [
        'student_id' => $finalStudentId,
        'exam_id' => (int) $examInfo['exam_id'],
        'exp' => time() + 3600 // 1 hour
    ];
    $token = base64_encode(json_encode($tokenData)) . '.' . hash_hmac('sha256', json_encode($tokenData), app_secret('xam_card'));

    json_response(200, true, 'Auth success', [
        'status' => $examInfo['status'],
        'suspension_note' => $examInfo['suspension_note'],
        'token' => $token
    ]);

} catch (Exception $e) {
    json_response(500, false, 'Internal Server Error: ' . $e->getMessage());
}
