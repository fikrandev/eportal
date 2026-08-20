<?php
/**
 * E-Portal Data Reset API
 */
require_once __DIR__ . '/config.php';

// Auth check
$user = auth_check();
if (!$user || $user['role'] !== 'superadmin') {
    json_response(403, false, 'Akses ditolak. Hanya superadmin yang dapat melakukan reset data.');
}

$action = $_GET['action'] ?? '';

if ($action === 'reset') {
    $categories = $_POST['categories'] ?? [];
    if (empty($categories)) {
        json_response(400, false, 'Pilih minimal satu kategori data untuk direset.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $results = [];

        // 1. Data Siswa
        if (in_array('students', $categories)) {
            $pdo->exec("DELETE FROM xam_exam_students");
            $pdo->exec("DELETE FROM grad_student_sessions");
            $pdo->exec("DELETE FROM grad_student_accounts");
            $pdo->exec("DELETE FROM grad_student_letters");
            $pdo->exec("DELETE FROM grad_student_scores");
            $pdo->exec("DELETE FROM students");
            $results[] = 'Data Siswa & Relasi';
        }

        // 2. Data Guru
        if (in_array('teachers', $categories)) {
            $pdo->exec("DELETE FROM xam_teacher_access");
            $pdo->exec("DELETE FROM grad_teacher_access");
            $pdo->exec("DELETE FROM sarpras_pj");
            $pdo->exec("DELETE FROM users WHERE role = 'guru'");
            $results[] = 'Data Guru & Akses Modul';
        }

        // 3. Data Sarpras
        if (in_array('sarpras', $categories)) {
            $pdo->exec("DELETE FROM sarpras_peminjaman");
            $pdo->exec("DELETE FROM sarpras_perbaikan");
            $pdo->exec("DELETE FROM sarpras_berita_acara");
            $pdo->exec("DELETE FROM sarpras_periodik");
            $pdo->exec("DELETE FROM sarpras_foto");
            $pdo->exec("DELETE FROM sarpras");
            $pdo->exec("DELETE FROM ruang");
            $pdo->exec("DELETE FROM bangunan");
            $pdo->exec("DELETE FROM tanah");
            $pdo->exec("DELETE FROM kategori_sarpras");
            $results[] = 'Data Sarpras Inventaris';
        }

        // 4. Data E-Schedule
        if (in_array('schedule', $categories)) {
            $pdo->exec("DELETE FROM sch_jadwal");
            $pdo->exec("DELETE FROM sch_distribusi");
            $pdo->exec("DELETE FROM sch_guru");
            $pdo->exec("DELETE FROM sch_kelas");
            $pdo->exec("DELETE FROM sch_mapel");
            $pdo->exec("DELETE FROM sch_jam_belajar");
            $results[] = 'Data E-Schedule';
        }

        // 5. Data E-Xam Card
        if (in_array('exam', $categories)) {
            $pdo->exec("DELETE FROM xam_exam_students");
            $pdo->exec("DELETE FROM xam_exam_classes");
            $pdo->exec("DELETE FROM xam_exam_settings");
            $pdo->exec("DELETE FROM xam_exams");
            $results[] = 'Data E-Xam Card';
        }

        // 6. Data E-Graduation
        if (in_array('graduation', $categories)) {
            $pdo->exec("DELETE FROM grad_student_scores");
            $pdo->exec("DELETE FROM grad_student_letters");
            $pdo->exec("DELETE FROM grad_letter_settings");
            $pdo->exec("DELETE FROM grad_subjects");
            $pdo->exec("DELETE FROM grad_subject_groups");
            $results[] = 'Data E-Graduation';
        }

        // 7. Data Tahun Ajaran
        if (in_array('academic', $categories)) {
            // Check if any students exist first if not reset students
            if (!in_array('students', $categories)) {
                $check = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
                if ($check > 0) {
                    throw new Exception("Tidak dapat mereset Tahun Ajaran karena masih ada data Siswa yang terikat. Reset Data Siswa terlebih dahulu.");
                }
            }
            $pdo->exec("DELETE FROM academic_years");
            $results[] = 'Data Tahun Ajaran';
        }

        $pdo->commit();
        json_response(200, true, 'Berhasil mereset: ' . implode(', ', $results));

    } catch (Exception $e) {
        $pdo->rollBack();
        json_response(500, false, 'Gagal mereset data: ' . $e->getMessage());
    }
}

json_response(400, false, 'Action tidak valid.');
