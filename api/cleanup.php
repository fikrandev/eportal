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
    require_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $target = isset($input['target']) ? $input['target'] : ''; // 'students', 'teachers', or 'both'
    
    if (!in_array($target, ['students', 'teachers', 'both'])) {
        json_response(400, false, 'Target tidak valid. Pilih students, teachers, atau both.');
    }

    try {
        db()->beginTransaction();
        $messages = [];

        // --- CLEANUP SISWA ---
        if ($target === 'students' || $target === 'both') {
            // 1. Hapus file foto siswa
            $stmt = db()->query("SELECT foto_path FROM students WHERE foto_path IS NOT NULL AND foto_path != ''");
            while ($row = $stmt->fetch()) {
                $filePath = __DIR__ . '/../' . $row['foto_path'];
                if (file_exists($filePath) && is_file($filePath)) {
                    @unlink($filePath);
                }
            }

            // 2. Hapus data di tabel-tabel terkait (Dependencies)
            // Urutan penghapusan: tabel anak dulu baru tabel induk
            db()->exec("DELETE FROM grad_student_letters");
            db()->exec("DELETE FROM grad_student_scores");
            db()->exec("DELETE FROM grad_student_accounts");
            db()->exec("DELETE FROM grad_student_sessions");
            db()->exec("DELETE FROM xam_exam_students");
            db()->exec("DELETE FROM perf_siswa");
            
            // 3. Hapus tabel induk siswa
            db()->exec("DELETE FROM students");
            
            $messages[] = "Data Siswa dan semua data terkait (Nilai, Akun Siswa, Exam) berhasil dibersihkan.";
        }

        // --- CLEANUP GURU ---
        if ($target === 'teachers' || $target === 'both') {
            // 1. Hapus avatar guru di tabel users
            $stmt = db()->query("SELECT avatar FROM users WHERE role = 'guru' AND avatar IS NOT NULL AND avatar != ''");
            while ($row = $stmt->fetch()) {
                $filePath = __DIR__ . '/../' . $row['avatar'];
                if (file_exists($filePath) && is_file($filePath)) {
                    @unlink($filePath);
                }
            }

            // 2. Hapus data terkait guru di modul-modul
            db()->exec("DELETE FROM grad_teacher_access");
            db()->exec("DELETE FROM xam_teacher_access");
            db()->exec("DELETE FROM sch_guru"); // Tabel jadwal/referensi guru
            
            // 3. Hapus akun guru di tabel users
            db()->exec("DELETE FROM users WHERE role = 'guru'");
            
            $messages[] = "Data Guru dan akun akses terkait berhasil dibersihkan.";
        }

        db()->commit();
        json_response(200, true, implode("\n", $messages));

    } catch (PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        json_response(500, false, 'Gagal membersihkan data: ' . $e->getMessage());
    }
}
