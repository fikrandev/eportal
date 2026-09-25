<?php
/**
 * Siswa App - Exam Card API
 * Integrates E-Xam Card directly into Portal Siswa
 */
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/../../modules/e-xam-card/api/xam_helper.php';

header('Content-Type: application/json');

try {
    $student = siswa_auth();
    $nis = $student['nis'];

    // 1. Resolve active academic year
    $activeYear = get_active_academic_year();
    $activeYearId = (int) ($activeYear['id'] ?? 0);

    // 2. Find exam where student is registered in xam_exam_students
    $stmt = db()->prepare("
        SELECT e.id as exam_id, e.exam_name, e.academic_year_id as exam_year_id,
               xs.id as xam_student_id, xs.student_id, xs.status, xs.suspension_note,
               xs.username as exam_username, xs.password_plain as exam_password, xs.ruang_ujian,
               COALESCE(ay.tahun_ajaran, ?) as tahun_ajaran
        FROM xam_exam_students xs
        JOIN xam_exams e ON e.id = xs.exam_id
        JOIN students s ON s.id = xs.student_id
        LEFT JOIN academic_years ay ON ay.id = e.academic_year_id
        WHERE s.nis = ? AND e.status = 1
        ORDER BY (e.academic_year_id = ?) DESC, e.academic_year_id DESC, e.id DESC
        LIMIT 1
    ");
    $stmt->execute([$activeYear['tahun_ajaran'] ?? '2026/2027', $nis, $activeYearId]);
    $examInfo = $stmt->fetch();

    // Fallback: Check any exam where student is registered if no active (status=1) found
    if (!$examInfo) {
        $stmt = db()->prepare("
            SELECT e.id as exam_id, e.exam_name, e.academic_year_id as exam_year_id,
                   xs.id as xam_student_id, xs.student_id, xs.status, xs.suspension_note,
                   xs.username as exam_username, xs.password_plain as exam_password, xs.ruang_ujian,
                   COALESCE(ay.tahun_ajaran, ?) as tahun_ajaran
            FROM xam_exam_students xs
            JOIN xam_exams e ON e.id = xs.exam_id
            JOIN students s ON s.id = xs.student_id
            LEFT JOIN academic_years ay ON ay.id = e.academic_year_id
            WHERE s.nis = ?
            ORDER BY (e.academic_year_id = ?) DESC, e.academic_year_id DESC, e.id DESC
            LIMIT 1
        ");
        $stmt->execute([$activeYear['tahun_ajaran'] ?? '2026/2027', $nis, $activeYearId]);
        $examInfo = $stmt->fetch();
    }

    // Auto-enroll if student is valid but not yet enrolled in active exam
    if (!$examInfo) {
        $stmtActiveExam = db()->prepare("
            SELECT e.id as exam_id, e.exam_name, e.academic_year_id as exam_year_id,
                   COALESCE(ay.tahun_ajaran, ?) as tahun_ajaran
            FROM xam_exams e
            LEFT JOIN academic_years ay ON ay.id = e.academic_year_id
            WHERE e.status = 1
            ORDER BY (e.academic_year_id = ?) DESC, e.academic_year_id DESC, e.id DESC
            LIMIT 1
        ");
        $stmtActiveExam->execute([$activeYear['tahun_ajaran'] ?? '2026/2027', $activeYearId]);
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
                        'tahun_ajaran' => $activeExam['tahun_ajaran'],
                        'xam_student_id' => $newXamStudentId,
                        'student_id' => $latestStudent['id'],
                        'status' => 'OKE',
                        'suspension_note' => 'Silakan hubungi Wali Kelas / Waka. Kesiswaan',
                        'exam_username' => $uname,
                        'exam_password' => $plain,
                        'ruang_ujian' => '-'
                    ];
                } catch (Exception $e) {}
            }
        }
    }

    if (!$examInfo) {
        json_response(200, true, 'Tidak ada ujian aktif', [
            'has_exam' => false,
            'message' => 'Belum ada jadwal kartu ujian aktif saat ini.'
        ]);
    }

    // Ensure student record is synchronized with latest active class & info
    $latestStudent = xam_get_latest_student_info($nis);
    $finalStudentId = (int) $examInfo['student_id'];

    if ($latestStudent && (int)$latestStudent['id'] !== $finalStudentId) {
        try {
            db()->prepare("UPDATE xam_exam_students SET student_id = ? WHERE id = ?")
                ->execute([$latestStudent['id'], $examInfo['xam_student_id']]);
            $finalStudentId = (int) $latestStudent['id'];
        } catch (Exception $e) {
            $finalStudentId = (int) $latestStudent['id'];
        }
    }

    // Generate temporary token for card viewing/downloading (valid 2 hours)
    $tokenData = [
        'student_id' => $finalStudentId,
        'exam_id' => (int) $examInfo['exam_id'],
        'exp' => time() + 7200
    ];
    $token = base64_encode(json_encode($tokenData)) . '.' . hash_hmac('sha256', json_encode($tokenData), DB_NAME);

    $previewUrl = BASE_URL . 'modules/e-xam-card/student/view.php?token=' . urlencode($token);
    $downloadUrl = BASE_URL . 'modules/e-xam-card/api/reports.php?action=download-card&exam_id=' . (int)$examInfo['exam_id'] . '&scope=student&student_id=' . $finalStudentId . '&token=' . urlencode($token);

    json_response(200, true, 'Sukses', [
        'has_exam' => true,
        'exam_id' => (int) $examInfo['exam_id'],
        'exam_name' => $examInfo['exam_name'],
        'tahun_ajaran' => $examInfo['tahun_ajaran'] ?? ($activeYear['tahun_ajaran'] ?? '2026/2027'),
        'status' => $examInfo['status'],
        'suspension_note' => $examInfo['suspension_note'] ?: 'Silakan hubungi Wali Kelas / Waka. Kesiswaan',
        'exam_username' => $examInfo['exam_username'] ?? '-',
        'exam_password' => $examInfo['exam_password'] ?? '-',
        'ruang_ujian' => $examInfo['ruang_ujian'] ?? '-',
        'token' => $token,
        'preview_url' => $previewUrl,
        'download_url' => $downloadUrl
    ]);

} catch (Exception $e) {
    json_response(500, false, 'Internal Server Error: ' . $e->getMessage());
}
