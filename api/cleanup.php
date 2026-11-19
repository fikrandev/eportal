<?php
/**
 * E-Portal Bulk Cleanup API
 * Digunakan untuk menghapus data siswa dan guru secara massal.
 * PERINGATAN: Tindakan ini tidak dapat dibatalkan.
 */
require_once __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'clear-data':
        handleCleanup();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function handleCleanup() {
    require_strict_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $target = isset($input['target']) ? $input['target'] : ''; // 'students', 'teachers', or 'both'
    
    if (!in_array($target, ['students', 'teachers', 'both'], true)) {
        json_response(400, false, 'Target tidak valid. Pilih students, teachers, atau both.');
    }

    $safeDeleteAll = function ($table) {
        $chk = db()->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $chk->execute([$table]);
        if ((int)$chk->fetchColumn() > 0) {
            db()->exec("DELETE FROM `{$table}`");
        }
    };

    try {
        db()->beginTransaction();
        $messages = [];

        // --- CLEANUP SISWA ---
        if ($target === 'students' || $target === 'both') {
            $stmt = db()->query("SELECT foto_path FROM students WHERE foto_path IS NOT NULL AND foto_path != ''");
            while ($row = $stmt->fetch()) {
                safe_unlink_in_dir($row['foto_path'], 'uploads');
            }

            $safeDeleteAll('grad_student_letters');
            $safeDeleteAll('grad_student_scores');
            $safeDeleteAll('grad_student_accounts');
            $safeDeleteAll('grad_student_sessions');
            $safeDeleteAll('xam_exam_students');
            $safeDeleteAll('perf_siswa');
            $safeDeleteAll('exam_cheat_log');
            $safeDeleteAll('exam_jawaban');
            $safeDeleteAll('exam_sesi');
            $safeDeleteAll('exam_student_login');
            
            db()->exec("DELETE FROM students");
            $messages[] = "Data Siswa dan semua data terkait (Nilai, Akun Siswa, Exam) berhasil dibersihkan.";
        }

        // --- CLEANUP GURU ---
        if ($target === 'teachers' || $target === 'both') {
            $stmt = db()->query("SELECT avatar FROM users WHERE role = 'guru' AND avatar IS NOT NULL AND avatar != ''");
            while ($row = $stmt->fetch()) {
                safe_unlink_in_dir($row['avatar'], 'uploads');
            }

            $safeDeleteAll('grad_teacher_access');
            $safeDeleteAll('xam_teacher_access');
            $safeDeleteAll('sch_guru');
            
            db()->exec("DELETE FROM users WHERE role = 'guru'");
            $messages[] = "Data Guru dan akun akses terkait berhasil dibersihkan.";
        }

        db()->commit();
        json_response(200, true, implode("\n", $messages));

    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        json_response(500, false, 'Gagal membersihkan data: ' . $e->getMessage());
    }
}
