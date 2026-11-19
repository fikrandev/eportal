<?php
/**
 * E-Portal Data Reset API
 */
require_once __DIR__ . '/config.php';

// Auth check: strict superadmin only
require_strict_superadmin();

$action = $_GET['action'] ?? '';

if ($action === 'reset') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }
    $input = get_input();
    $categories = $input['categories'] ?? ($_POST['categories'] ?? []);
    if (!is_array($categories) || empty($categories)) {
        json_response(400, false, 'Pilih minimal satu kategori data untuk direset.');
    }

    $pdo = db();
    $safeDeleteAll = function ($table) use ($pdo) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $chk->execute([$table]);
        if ((int)$chk->fetchColumn() > 0) {
            $pdo->exec("DELETE FROM `{$table}`");
        }
    };

    $pdo->beginTransaction();

    try {
        $results = [];

        // 1. Data Siswa
        if (in_array('students', $categories, true)) {
            $safeDeleteAll('exam_cheat_log');
            $safeDeleteAll('exam_jawaban');
            $safeDeleteAll('exam_sesi');
            $safeDeleteAll('exam_student_login');
            $safeDeleteAll('xam_exam_students');
            $safeDeleteAll('grad_student_sessions');
            $safeDeleteAll('grad_student_accounts');
            $safeDeleteAll('grad_student_letters');
            $safeDeleteAll('grad_student_scores');
            $safeDeleteAll('acad_absensi');
            $safeDeleteAll('students');
            $results[] = 'Data Siswa & Relasi';
        }

        // 2. Data Guru
        if (in_array('teachers', $categories, true)) {
            $safeDeleteAll('xam_teacher_access');
            $safeDeleteAll('grad_teacher_access');
            $safeDeleteAll('sarpras_pj');
            $pdo->exec("DELETE FROM users WHERE role = 'guru'");
            $results[] = 'Data Guru & Akses Modul';
        }

        // 3. Data Sarpras
        if (in_array('sarpras', $categories, true)) {
            $safeDeleteAll('sarpras_peminjaman');
            $safeDeleteAll('sarpras_perbaikan');
            $safeDeleteAll('sarpras_berita_acara');
            $safeDeleteAll('sarpras_periodik');
            $safeDeleteAll('sarpras_foto');
            $safeDeleteAll('sarpras');
            $safeDeleteAll('ruang');
            $safeDeleteAll('bangunan');
            $safeDeleteAll('tanah');
            $safeDeleteAll('kategori_sarpras');
            $results[] = 'Data Sarpras Inventaris';
        }

        // 4. Data E-Schedule
        if (in_array('schedule', $categories, true)) {
            $safeDeleteAll('sch_jadwal');
            $safeDeleteAll('sch_distribusi');
            $safeDeleteAll('sch_guru');
            $safeDeleteAll('sch_kelas');
            $safeDeleteAll('sch_mapel');
            $safeDeleteAll('sch_jam_belajar');
            $results[] = 'Data E-Schedule';
        }

        // 5. Data E-Xam Card
        if (in_array('exam', $categories, true)) {
            $safeDeleteAll('xam_exam_students');
            $safeDeleteAll('xam_exam_classes');
            $safeDeleteAll('xam_exam_settings');
            $safeDeleteAll('xam_exams');
            $results[] = 'Data E-Xam Card';
        }

        // 6. Data E-Graduation
        if (in_array('graduation', $categories, true)) {
            $safeDeleteAll('grad_student_scores');
            $safeDeleteAll('grad_student_letters');
            $safeDeleteAll('grad_letter_settings');
            $safeDeleteAll('grad_subjects');
            $safeDeleteAll('grad_subject_groups');
            $results[] = 'Data E-Graduation';
        }

        // 7. Data Tahun Ajaran
        if (in_array('academic', $categories, true)) {
            if (!in_array('students', $categories, true)) {
                $check = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
                if ($check > 0) {
                    throw new Exception("Tidak dapat mereset Tahun Ajaran karena masih ada data Siswa yang terikat. Reset Data Siswa terlebih dahulu.");
                }
            }
            $safeDeleteAll('academic_years');
            $results[] = 'Data Tahun Ajaran';
        }

        if (empty($results)) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            json_response(400, false, 'Tidak ada kategori valid yang dipilih.');
        }

        $pdo->commit();
        json_response(200, true, 'Berhasil mereset: ' . implode(', ', $results));

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_response(500, false, 'Gagal mereset data: ' . $e->getMessage());
    }
}

json_response(400, false, 'Action tidak valid.');
