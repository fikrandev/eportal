<?php
/**
 * Reset data API for E-Graduation.
 * Allows admin to selectively reset graduation-specific data.
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/auth_helper.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'info':
        grad_require_manage();
        resetInfo();
        break;
    case 'execute':
        grad_require_manage();
        executeReset();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function resetInfo()
{
    try {
        $yearId = grad_active_year_id();

        $counts = [];
        $tables = [
            'grad_student_scores'  => ['label' => 'Nilai Siswa', 'where' => 'academic_year_id = ?'],
            'grad_student_letters' => ['label' => 'Nomor Surat', 'where' => 'academic_year_id = ?'],
            'grad_subjects'        => ['label' => 'Mata Pelajaran', 'where' => 'academic_year_id = ?'],
            'grad_subject_groups'  => ['label' => 'Kelompok Mapel', 'where' => 'academic_year_id = ?'],
            'grad_student_accounts'=> ['label' => 'Akun Siswa', 'where' => 'academic_year_id = ?'],
            'grad_student_sessions'=> ['label' => 'Sesi Login Siswa', 'where' => 'account_id IN (SELECT id FROM grad_student_accounts WHERE academic_year_id = ?)'],
            'grad_teacher_access'  => ['label' => 'Akses Guru', 'where' => '1=1'],
            'grad_letter_settings' => ['label' => 'Pengaturan Surat', 'where' => 'academic_year_id = ?'],
        ];

        // Also count photos
        $photoStmt = db()->prepare("SELECT COUNT(*) FROM students WHERE academic_year_id = ? AND foto_path <> '' AND foto_path IS NOT NULL");
        $photoStmt->execute([$yearId]);
        $photoCount = (int) $photoStmt->fetchColumn();

        $items = [];
        foreach ($tables as $table => $meta) {
            $params = ($meta['where'] !== '1=1') ? [$yearId] : [];
            $stmt = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$meta['where']}");
            $stmt->execute($params);
            $count = (int) $stmt->fetchColumn();
            $items[] = [
                'key'   => $table,
                'label' => $meta['label'],
                'count' => $count
            ];
        }

        $items[] = [
            'key'   => 'photos',
            'label' => 'Foto Siswa',
            'count' => $photoCount
        ];

        json_response(200, true, 'OK', ['items' => $items, 'year_id' => $yearId]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function executeReset()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $targets = $input['targets'] ?? [];
    if (!is_array($targets) || empty($targets)) {
        json_response(400, false, 'Pilih minimal satu data yang ingin di-reset.');
    }

    $confirm = trim($input['confirm'] ?? '');
    if ($confirm !== 'RESET') {
        json_response(400, false, 'Ketik RESET untuk mengkonfirmasi.');
    }

    try {
        $yearId = grad_active_year_id();
        $results = [];

        // Order matters — delete dependents first
        $deleteOrder = [
            'grad_student_sessions',
            'grad_student_scores',
            'grad_student_letters',
            'grad_student_accounts',
            'grad_subjects',
            'grad_subject_groups',
            'grad_teacher_access',
            'grad_letter_settings',
            'photos'
        ];

        foreach ($deleteOrder as $key) {
            if (!in_array($key, $targets)) continue;

            $deleted = 0;
            switch ($key) {
                case 'grad_student_sessions':
                    $stmt = db()->prepare("DELETE ss FROM grad_student_sessions ss INNER JOIN grad_student_accounts a ON a.id = ss.account_id WHERE a.academic_year_id = ?");
                    $stmt->execute([$yearId]);
                    $deleted = $stmt->rowCount();
                    break;

                case 'grad_student_scores':
                    $stmt = db()->prepare("DELETE FROM grad_student_scores WHERE academic_year_id = ?");
                    $stmt->execute([$yearId]);
                    $deleted = $stmt->rowCount();
                    break;

                case 'grad_student_letters':
                    $stmt = db()->prepare("DELETE FROM grad_student_letters WHERE academic_year_id = ?");
                    $stmt->execute([$yearId]);
                    $deleted = $stmt->rowCount();
                    break;

                case 'grad_student_accounts':
                    // Delete sessions first if not already targeted
                    if (!in_array('grad_student_sessions', $targets)) {
                        db()->prepare("DELETE ss FROM grad_student_sessions ss INNER JOIN grad_student_accounts a ON a.id = ss.account_id WHERE a.academic_year_id = ?")->execute([$yearId]);
                    }
                    $stmt = db()->prepare("DELETE FROM grad_student_accounts WHERE academic_year_id = ?");
                    $stmt->execute([$yearId]);
                    $deleted = $stmt->rowCount();
                    break;

                case 'grad_subjects':
                    $stmt = db()->prepare("DELETE FROM grad_subjects WHERE academic_year_id = ?");
                    $stmt->execute([$yearId]);
                    $deleted = $stmt->rowCount();
                    break;

                case 'grad_subject_groups':
                    // Delete subjects first if not already targeted
                    if (!in_array('grad_subjects', $targets)) {
                        db()->prepare("DELETE FROM grad_subjects WHERE academic_year_id = ?")->execute([$yearId]);
                    }
                    $stmt = db()->prepare("DELETE FROM grad_subject_groups WHERE academic_year_id = ?");
                    $stmt->execute([$yearId]);
                    $deleted = $stmt->rowCount();
                    break;

                case 'grad_teacher_access':
                    $stmt = db()->prepare("DELETE FROM grad_teacher_access");
                    $stmt->execute();
                    $deleted = $stmt->rowCount();
                    break;

                case 'grad_letter_settings':
                    $stmt = db()->prepare("DELETE FROM grad_letter_settings WHERE academic_year_id = ?");
                    $stmt->execute([$yearId]);
                    $deleted = $stmt->rowCount();
                    break;

                case 'photos':
                    $root = realpath(__DIR__ . '/../../../');
                    $stmtFoto = db()->prepare("SELECT id, foto_path FROM students WHERE academic_year_id = ? AND foto_path <> '' AND foto_path IS NOT NULL");
                    $stmtFoto->execute([$yearId]);
                    $photos = $stmtFoto->fetchAll();
                    foreach ($photos as $photo) {
                        $fullPath = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($photo['foto_path'], '/\\'));
                        if (file_exists($fullPath)) {
                            @unlink($fullPath);
                        }
                    }
                    $stmtUpdate = db()->prepare("UPDATE students SET foto_path = '' WHERE academic_year_id = ? AND foto_path <> ''");
                    $stmtUpdate->execute([$yearId]);
                    $deleted = count($photos);
                    break;
            }

            $results[$key] = $deleted;
        }

        json_response(200, true, 'Reset data berhasil dilakukan.', ['deleted' => $results]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
