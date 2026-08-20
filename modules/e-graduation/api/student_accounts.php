<?php
/**
 * Admin API for E-Graduation student accounts.
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/simple_pdf.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'meta':
        grad_require_manage();
        accountMeta();
        break;
    case 'candidates':
        grad_require_manage();
        accountCandidates();
        break;
    case 'generate':
        grad_require_manage();
        importAccountsFromPortal(false);
        break;
    case 'import':
        grad_require_manage();
        importAccountsFromPortal(true);
        break;
    case 'settings-save':
        grad_require_manage();
        saveAnnouncementSettings();
        break;
    case 'download-pdf':
        grad_require_manage();
        downloadAccountsPdf();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function accountMeta()
{
    try {
        $yearId = grad_active_year_id();
        json_response(200, true, 'Meta akun siswa berhasil dimuat.', [
            'classes' => classesForYear($yearId),
            'account_classes' => classesForActiveAccounts($yearId),
            'total_students' => countActiveAccountStudents($yearId),
            'total_accounts' => countAccounts($yearId),
            'settings' => getAnnouncementSettings($yearId),
            'student_portal_url' => absoluteBaseUrlAccount() . 'modules/e-graduation/student.php'
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function accountCandidates()
{
    try {
        $yearId = grad_active_year_id();
        $params = [$yearId];
        $where = "WHERE s.academic_year_id = ? AND s.nis <> ''";

        $kelas = sanitize($_GET['kelas'] ?? '');
        if ($kelas !== '') {
            $where .= " AND s.kelas = ?";
            $params[] = $kelas;
        }

        $search = sanitize($_GET['search'] ?? '');
        if ($search !== '') {
            $where .= " AND (s.nis LIKE ? OR s.nisn LIKE ? OR s.nama LIKE ? OR s.kelas LIKE ?)";
            $like = "%{$search}%";
            array_push($params, $like, $like, $like, $like);
        }

        $stmt = db()->prepare("
            SELECT s.id, s.no_urut, s.nis, s.nisn, s.nama, s.kelas,
                   a.id AS account_id, a.status AS account_status, a.username AS account_username,
                   u.id AS portal_user_id, u.username AS portal_username
            FROM students s
            LEFT JOIN grad_student_accounts a ON a.student_id = s.id AND a.academic_year_id = s.academic_year_id
            LEFT JOIN users u ON u.username = s.nis AND u.status = 1 AND u.role <> 'guru'
            {$where}
            ORDER BY s.kelas ASC, s.no_urut ASC, s.nama ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $students = [];
        $readyPortal = 0;
        foreach ($rows as $row) {
            $portalAvailable = !empty($row['portal_user_id']);
            $accountReady = !empty($row['account_id']) && (int) ($row['account_status'] ?? 0) === 1;
            if ($portalAvailable) {
                $readyPortal++;
            }
            $students[] = [
                'id' => (int) $row['id'],
                'no_urut' => (int) ($row['no_urut'] ?? 0),
                'nis' => (string) ($row['nis'] ?? ''),
                'nisn' => (string) ($row['nisn'] ?? ''),
                'nama' => (string) ($row['nama'] ?? ''),
                'kelas' => (string) ($row['kelas'] ?? ''),
                'portal_available' => $portalAvailable,
                'portal_username' => (string) ($row['portal_username'] ?? ''),
                'account_ready' => $accountReady,
                'account_username' => (string) ($row['account_username'] ?? '')
            ];
        }

        json_response(200, true, 'Kandidat siswa untuk import akun berhasil dimuat.', [
            'classes' => classesForYear($yearId),
            'total_students' => count($students),
            'ready_portal' => $readyPortal,
            'students' => $students
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function importAccountsFromPortal($requireClassFilter = false)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    try {
        $yearId = grad_active_year_id();
        $input = get_input();
        $rawClasses = $input['classes'] ?? null;
        if (($rawClasses === null || $rawClasses === '') && !empty($input['classes_json'])) {
            $decoded = json_decode((string) $input['classes_json'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $rawClasses = $decoded;
            }
        }
        $rawStudentIds = $input['student_ids'] ?? null;
        if (($rawStudentIds === null || $rawStudentIds === '') && !empty($input['student_ids_json'])) {
            $decoded = json_decode((string) $input['student_ids_json'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $rawStudentIds = $decoded;
            }
        }

        $classSelectionSent = $requireClassFilter
            || !empty($input['use_class_filter'])
            || array_key_exists('classes', $input)
            || array_key_exists('classes_json', $input);
        $studentSelectionSent = !empty($input['use_student_filter'])
            || array_key_exists('student_ids', $input)
            || array_key_exists('student_ids_json', $input);

        $selectedStudentIds = normalizeAccountStudentIds($rawStudentIds ?? [], $yearId);
        if ($studentSelectionSent && !$selectedStudentIds) {
            json_response(400, false, 'Pilih minimal satu siswa yang valid untuk import akun.');
        }

        $selectedClasses = normalizeAccountClasses($rawClasses ?? [], $yearId);
        if (!$studentSelectionSent && $classSelectionSent && !$selectedClasses) {
            json_response(400, false, 'Pilih minimal satu kelas yang valid untuk import akun.');
        }
        if ($requireClassFilter && !$selectedStudentIds && !$selectedClasses) {
            json_response(400, false, 'Pilih minimal satu kelas untuk import akun.');
        }

        $selectedIdsFromClasses = [];
        if ($selectedClasses && !$selectedStudentIds) {
            $classStudentsStmt = db()->prepare("
                SELECT id
                FROM students
                WHERE academic_year_id = ?
                  AND kelas IN (" . implode(',', array_fill(0, count($selectedClasses), '?')) . ")
            ");
            $classStudentsStmt->execute(array_merge([$yearId], $selectedClasses));
            $selectedIdsFromClasses = array_map('intval', $classStudentsStmt->fetchAll(PDO::FETCH_COLUMN));
        }

        $params = [$yearId];
        $where = 'WHERE s.academic_year_id = ? AND s.nis <> \'\'';
        if ($selectedStudentIds) {
            $where .= ' AND s.id IN (' . implode(',', array_fill(0, count($selectedStudentIds), '?')) . ')';
            $params = array_merge($params, $selectedStudentIds);
        } elseif ($selectedIdsFromClasses) {
            $where .= ' AND s.id IN (' . implode(',', array_fill(0, count($selectedIdsFromClasses), '?')) . ')';
            $params = array_merge($params, $selectedIdsFromClasses);
        } elseif ($selectedClasses) {
            $where .= ' AND 1 = 0';
        }

        $deactivatedOutsideSelection = 0;
        if ($selectedClasses && !$selectedStudentIds) {
            if ($selectedIdsFromClasses) {
                $deactivateSql = "
                    UPDATE grad_student_accounts
                    SET status = 0
                    WHERE academic_year_id = ?
                      AND status <> 0
                      AND student_id NOT IN (" . implode(',', array_fill(0, count($selectedIdsFromClasses), '?')) . ")
                ";
                $deactivateParams = array_merge([$yearId], $selectedIdsFromClasses);
            } else {
                $deactivateSql = "
                    UPDATE grad_student_accounts
                    SET status = 0
                    WHERE academic_year_id = ?
                      AND status <> 0
                ";
                $deactivateParams = [$yearId];
            }
            $deactivateStmt = db()->prepare($deactivateSql);
            $deactivateStmt->execute($deactivateParams);
            $deactivatedOutsideSelection = (int) $deactivateStmt->rowCount();
        }

        $stmt = db()->prepare("
            SELECT s.id, s.nis, s.kelas,
                   a.id AS account_id,
                   a.username AS account_username,
                   a.password_hash AS account_password_hash,
                   u.id AS portal_user_id,
                   u.username AS portal_username,
                   u.password AS portal_password
            FROM students s
            LEFT JOIN grad_student_accounts a ON a.student_id = s.id AND a.academic_year_id = s.academic_year_id
            LEFT JOIN users u ON u.username = s.nis AND u.status = 1 AND u.role <> 'guru'
            {$where}
            ORDER BY s.kelas ASC, s.no_urut ASC, s.nama ASC
        ");
        $stmt->execute($params);
        $students = $stmt->fetchAll();

        $upsert = db()->prepare("
            INSERT INTO grad_student_accounts (academic_year_id, student_id, username, password_hash, status, generated_at)
            VALUES (?, ?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                username = VALUES(username),
                password_hash = VALUES(password_hash),
                status = 1,
                generated_at = NOW()
        ");
        $activateExisting = db()->prepare("
            UPDATE grad_student_accounts
            SET status = 1, generated_at = COALESCE(generated_at, NOW())
            WHERE academic_year_id = ? AND student_id = ?
        ");

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $missingPortal = 0;
        foreach ($students as $student) {
            if (empty($student['portal_user_id']) || empty($student['portal_password'])) {
                if (!empty($student['account_id']) && !empty($student['account_username']) && !empty($student['account_password_hash'])) {
                    $activateExisting->execute([$yearId, $student['id']]);
                    $updated++;
                    continue;
                }
                $missingPortal++;
                continue;
            }

            $passwordHash = (string) $student['portal_password'];
            $passwordInfo = password_get_info($passwordHash);
            if (empty($passwordInfo['algo'])) {
                $passwordHash = password_hash($passwordHash, PASSWORD_DEFAULT);
            }

            $upsert->execute([
                $yearId,
                $student['id'],
                (string) $student['portal_username'],
                $passwordHash
            ]);

            if (!empty($student['account_id'])) {
                $updated++;
            } else {
                $imported++;
            }
        }

        $skipped = $missingPortal;
        json_response(200, true, 'Import akun siswa dari Admin Portal selesai.', [
            'generated' => $imported,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'missing_portal' => $missingPortal,
            'classes' => $selectedClasses,
            'student_ids' => $selectedStudentIds,
            'total_candidates' => count($students),
            'deactivated_outside_selection' => $deactivatedOutsideSelection
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function saveAnnouncementSettings()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $status = sanitize($input['announcement_status'] ?? 'not_set');
    $at = sanitize($input['announcement_at'] ?? '');
    if (!in_array($status, ['not_set', 'scheduled', 'published'], true)) {
        $status = 'not_set';
    }

    try {
        $yearId = grad_active_year_id();
        ensureLetterSetting($yearId);
        $stmt = db()->prepare("
            UPDATE grad_letter_settings
            SET announcement_status = ?, announcement_at = ?
            WHERE academic_year_id = ?
        ");
        $stmt->execute([$status, $at !== '' ? str_replace('T', ' ', $at) . ':00' : null, $yearId]);
        json_response(200, true, 'Pengaturan pengumuman berhasil disimpan.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function downloadAccountsPdf()
{
    try {
        $yearId = grad_active_year_id();
        $scope = sanitize($_GET['scope'] ?? 'class');
        $kelas = sanitize($_GET['kelas'] ?? '');
        $students = studentsForAccounts($yearId, $scope, $kelas);
        if (!$students) {
            http_response_code(404);
            echo 'Data akun siswa tidak ditemukan.';
            exit;
        }

        $school = get_setting('nama_sekolah', 'E-Portal Sekolah');
        $active = get_active_academic_year();
        $pdf = new GraduationSimplePdf();
        renderAccountsPdf($pdf, $students, $school, $active, $scope === 'all' ? 'Semua Kelas' : $kelas);
        $content = $pdf->output();
        $filename = 'Akun-Siswa-' . ($scope === 'all' ? 'Semua' : preg_replace('/[^A-Za-z0-9_-]/', '-', $kelas)) . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo 'Server error: ' . $e->getMessage();
        exit;
    }
}

function renderAccountsPdf($pdf, $students, $school, $active, $scopeLabel)
{
    $m = 28.35;
    $rowH = 22;
    $headers = ['No', 'NIS', 'Nama Siswa', 'Kelas', 'Username', 'Password'];
    $widths = [28, 70, 190, 58, 80, 82];
    $x0 = $m;
    $y = 0;

    $drawHeader = function () use ($pdf, $m, &$y, $school, $active, $scopeLabel, $headers, $widths, $x0, $rowH) {
        $pdf->addPage();
        $y = $m;
        $pdf->text($m, $y, 'DAFTAR AKUN SISWA E-GRADUATION', 16, 'F2', 'center', $pdf->width() - ($m * 2));
        $y += 20;
        $pdf->text($m, $y, $school, 13, 'F1', 'center', $pdf->width() - ($m * 2));
        $y += 17;
        $pdf->text($m, $y, 'Tahun Pelajaran ' . ($active['tahun_ajaran'] ?? '-') . ' | ' . $scopeLabel, 12, 'F1', 'center', $pdf->width() - ($m * 2));
        $y += 24;

        $x = $x0;
        foreach ($headers as $idx => $header) {
            $pdf->rect($x, $y, $widths[$idx], $rowH);
            $pdf->text($x, $y + 14, $header, 11, 'F2', 'center', $widths[$idx]);
            $x += $widths[$idx];
        }
        $y += $rowH;
    };

    $drawHeader();
    foreach ($students as $idx => $student) {
        if ($y > $pdf->height() - $m - $rowH) {
            $drawHeader();
        }
        $password = studentDefaultPassword($student['tanggal_lahir']);
        $username = (string) ($student['account_username'] ?? $student['nis']);
        $values = [$idx + 1, $student['nis'], $student['nama'], $student['kelas'], $username, $password];
        $x = $x0;
        foreach ($values as $col => $value) {
            $pdf->rect($x, $y, $widths[$col], $rowH);
            $align = in_array($col, [0, 1, 3, 4, 5], true) ? 'center' : 'left';
            $textX = $align === 'left' ? $x + 5 : $x;
            $pdf->text($textX, $y + 14, (string) $value, 10.5, 'F1', $align, $widths[$col] - ($align === 'left' ? 8 : 0));
            $x += $widths[$col];
        }
        $y += $rowH;
    }
}

function studentsForAccounts($yearId, $scope, $kelas)
{
    $params = [$yearId];
    $where = 'WHERE s.academic_year_id = ?';
    if ($scope !== 'all') {
        if ($kelas === '') return [];
        $where .= ' AND s.kelas = ?';
        $params[] = $kelas;
    }
    $stmt = db()->prepare("
        SELECT s.*, a.id as account_id, a.username as account_username
        FROM students s
        INNER JOIN grad_student_accounts a ON a.student_id = s.id AND a.academic_year_id = s.academic_year_id AND a.status = 1
        {$where}
        ORDER BY s.kelas ASC, s.no_urut ASC, s.nama ASC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function classesForYear($yearId)
{
    $stmt = db()->prepare("SELECT kelas, COUNT(*) as total_siswa FROM students WHERE academic_year_id = ? AND kelas <> '' GROUP BY kelas ORDER BY kelas ASC");
    $stmt->execute([$yearId]);
    return $stmt->fetchAll();
}

function classesForActiveAccounts($yearId)
{
    $stmt = db()->prepare("
        SELECT s.kelas, COUNT(*) as total_siswa
        FROM grad_student_accounts a
        JOIN students s ON s.id = a.student_id AND s.academic_year_id = a.academic_year_id
        WHERE a.academic_year_id = ? AND a.status = 1 AND s.kelas <> ''
        GROUP BY s.kelas
        ORDER BY s.kelas ASC
    ");
    $stmt->execute([$yearId]);
    return $stmt->fetchAll();
}

function countActiveAccountStudents($yearId)
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM grad_student_accounts WHERE academic_year_id = ? AND status = 1");
    $stmt->execute([$yearId]);
    return (int) $stmt->fetchColumn();
}

function countAccounts($yearId)
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM grad_student_accounts WHERE academic_year_id = ? AND status = 1");
    $stmt->execute([$yearId]);
    return (int) $stmt->fetchColumn();
}

function getAnnouncementSettings($yearId)
{
    ensureLetterSetting($yearId);
    $stmt = db()->prepare("SELECT announcement_status, announcement_at FROM grad_letter_settings WHERE academic_year_id = ?");
    $stmt->execute([$yearId]);
    return $stmt->fetch() ?: ['announcement_status' => 'not_set', 'announcement_at' => null];
}

function ensureLetterSetting($yearId)
{
    $stmt = db()->prepare("
        INSERT INTO grad_letter_settings (academic_year_id, start_number, total, letter_format, headmaster_position)
        VALUES (?, 1, 0, ?, 'Kepala Sekolah')
        ON DUPLICATE KEY UPDATE academic_year_id = academic_year_id
    ");
    $stmt->execute([$yearId, '{nomor} / I04.1/SMA.WH1/V/' . date('Y')]);
}

function studentDefaultPassword($date)
{
    $ts = strtotime((string) $date);
    return $ts ? date('dmY', $ts) : '';
}

function normalizeAccountStudentIds($value, $yearId)
{
    $items = is_array($value) ? $value : [$value];
    $ids = array_values(array_unique(array_filter(array_map(function ($item) {
        if (is_numeric($item)) {
            $id = (int) $item;
            return $id > 0 ? $id : null;
        }
        $clean = trim((string) $item);
        if ($clean !== '' && ctype_digit($clean)) {
            $id = (int) $clean;
            return $id > 0 ? $id : null;
        }
        return null;
    }, $items))));

    if (!$ids) return [];

    $params = array_merge([$yearId], $ids);
    $stmt = db()->prepare("
        SELECT id
        FROM students
        WHERE academic_year_id = ?
          AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
    ");
    $stmt->execute($params);
    $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    return array_values(array_unique($valid));
}

function normalizeAccountClasses($value, $yearId)
{
    $classes = is_array($value) ? $value : [$value];
    $classes = array_values(array_unique(array_filter(array_map(function ($item) {
        return trim((string) sanitize($item));
    }, $classes), function ($item) {
        return $item !== '';
    })));
    if (!$classes) return [];

    $valid = array_flip(array_map(function ($row) {
        return (string) ($row['kelas'] ?? '');
    }, classesForYear($yearId)));

    return array_values(array_filter($classes, function ($kelas) use ($valid) {
        return isset($valid[$kelas]);
    }));
}

function absoluteBaseUrlAccount()
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . BASE_URL;
}
