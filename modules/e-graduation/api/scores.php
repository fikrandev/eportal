<?php
/**
 * E-Graduation score input API.
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/auth_helper.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'meta':
        $user = grad_auth();
        scoreMeta($user);
        break;
    case 'save':
        $user = grad_auth();
        saveScores($user);
        break;
    case 'import-csv':
        $user = grad_auth();
        importScoresCsv($user);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function scoreMeta($user)
{
    $kelas = sanitize($_GET['kelas'] ?? '');
    if ($kelas === '') {
        $classes = classesForUser($user);
        $kelas = $classes[0]['kelas'] ?? '';
    }
    if ($kelas === '') {
        json_response(400, false, 'Belum ada kelas yang bisa diakses.');
    }
    grad_require_class_access($user, $kelas);

    try {
        $yearId = grad_active_year_id();
        json_response(200, true, 'Data input nilai berhasil dimuat.', [
            'kelas' => $kelas,
            'classes' => classesForUser($user),
            'subjects' => subjectsForClass($yearId, $kelas),
            'students' => studentsForClass($yearId, $kelas),
            'scores' => scoresForClass($yearId, $kelas)
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function saveScores($user)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $kelas = sanitize($input['kelas'] ?? '');
    $scores = $input['scores'] ?? [];
    if ($kelas === '') {
        json_response(400, false, 'Kelas wajib dipilih.');
    }
    grad_require_class_access($user, $kelas);

    try {
        $yearId = grad_active_year_id();
        $validStudents = array_flip(array_map('strval', array_column(studentsForClass($yearId, $kelas), 'id')));
        $validSubjects = array_flip(array_map('strval', array_column(subjectsForClass($yearId, $kelas), 'id')));

        $stmt = db()->prepare("
            INSERT INTO grad_student_scores (academic_year_id, student_id, subject_id, nilai_akhir, predikat)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE nilai_akhir = VALUES(nilai_akhir), predikat = VALUES(predikat)
        ");

        $saved = 0;
        foreach ($scores as $studentId => $studentScores) {
            if (!isset($validStudents[(string) $studentId]) || !is_array($studentScores)) {
                continue;
            }
            foreach ($studentScores as $subjectId => $value) {
                if (!isset($validSubjects[(string) $subjectId])) {
                    continue;
                }
                $value = trim((string) $value);
                $score = $value === '' ? null : (float) str_replace(',', '.', $value);
                $stmt->execute([$yearId, (int) $studentId, (int) $subjectId, $score, predicateForScore($score)]);
                $saved++;
            }
        }

        // Save manual averages
        $averages = $input['averages'] ?? [];
        if (!empty($averages)) {
            $stmtAvg = db()->prepare("UPDATE students SET rata_rata = ? WHERE id = ? AND academic_year_id = ?");
            foreach ($averages as $studentId => $avg) {
                if (!isset($validStudents[(string) $studentId])) {
                    continue;
                }
                $avg = trim((string) $avg);
                $val = $avg === '' ? null : (float) str_replace(',', '.', $avg);
                $stmtAvg->execute([$val, (int) $studentId, $yearId]);
            }
        }

        json_response(200, true, 'Nilai berhasil disimpan.', ['saved' => $saved]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function importScoresCsv($user)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }
    $kelas = sanitize($_POST['kelas'] ?? '');
    if ($kelas === '') {
        json_response(400, false, 'Kelas wajib dipilih.');
    }
    grad_require_class_access($user, $kelas);
    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        json_response(400, false, 'File CSV wajib diupload.');
    }

    try {
        $yearId = grad_active_year_id();
        $students = studentsForClass($yearId, $kelas);
        $subjects = subjectsForClass($yearId, $kelas);
        $studentByNis = [];
        foreach ($students as $student) {
            $studentByNis[$student['nis']] = $student['id'];
        }
        $subjectByCode = [];
        foreach ($subjects as $subject) {
            $subjectByCode[strtoupper($subject['kode_mapel'] ?: $subject['nama_mapel'])] = $subject['id'];
        }

        $handle = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$handle) {
            json_response(400, false, 'File CSV tidak bisa dibaca.');
        }
        $header = fgetcsv($handle);
        if (!$header) {
            json_response(400, false, 'Header CSV kosong.');
        }

        $columns = [];
        foreach ($header as $idx => $name) {
            $key = strtoupper(trim($name));
            if (isset($subjectByCode[$key])) {
                $columns[$idx] = $subjectByCode[$key];
            }
        }
        if (!$columns) {
            json_response(400, false, 'Tidak ada kolom mapel yang cocok dengan kode mapel.');
        }

        $stmt = db()->prepare("
            INSERT INTO grad_student_scores (academic_year_id, student_id, subject_id, nilai_akhir, predikat)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE nilai_akhir = VALUES(nilai_akhir), predikat = VALUES(predikat)
        ");

        $saved = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $nis = trim($row[0] ?? '');
            if ($nis === '' || !isset($studentByNis[$nis])) {
                continue;
            }
            foreach ($columns as $idx => $subjectId) {
                $value = trim($row[$idx] ?? '');
                if ($value === '') {
                    continue;
                }
                $score = (float) str_replace(',', '.', $value);
                $stmt->execute([$yearId, $studentByNis[$nis], $subjectId, $score, predicateForScore($score)]);
                $saved++;
            }
        }
        fclose($handle);

        json_response(200, true, 'Import nilai berhasil.', ['saved' => $saved]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function classesForUser($user)
{
    $yearId = grad_active_year_id();
    $params = [$yearId];
    $where = "academic_year_id = ? AND kelas <> ''";
    if (empty($user['can_manage_graduation'])) {
        if (empty($user['scoped_classes'])) return [];
        $placeholders = implode(',', array_fill(0, count($user['scoped_classes']), '?'));
        $where .= " AND kelas IN ($placeholders)";
        $params = array_merge($params, $user['scoped_classes']);
    }

    $stmt = db()->prepare("SELECT kelas, COUNT(*) as total_siswa FROM students WHERE {$where} GROUP BY kelas ORDER BY kelas ASC");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function studentsForClass($yearId, $kelas)
{
    $stmt = db()->prepare("SELECT id, nis, nisn, nama, kelas, rata_rata FROM students WHERE academic_year_id = ? AND kelas = ? ORDER BY no_urut ASC, nama ASC");
    $stmt->execute([$yearId, $kelas]);
    return $stmt->fetchAll();
}

function subjectsForClass($yearId, $kelas)
{
    $stmt = db()->prepare("
        SELECT s.*, g.kode as group_kode, g.nama as group_nama, g.tipe as group_tipe
        FROM grad_subjects s
        JOIN grad_subject_groups g ON g.id = s.group_id
        WHERE s.academic_year_id = ?
          AND (g.tipe <> 'pilihan' OR FIND_IN_SET(REPLACE(?, ' ', ''), REPLACE(COALESCE(s.kelas, ''), ' ', '')) > 0)
        ORDER BY g.urutan ASC, g.kode ASC, s.urutan ASC, s.nama_mapel ASC
    ");
    $stmt->execute([$yearId, $kelas]);
    return $stmt->fetchAll();
}

function scoresForClass($yearId, $kelas)
{
    $stmt = db()->prepare("
        SELECT sc.student_id, sc.subject_id, sc.nilai_akhir
        FROM grad_student_scores sc
        JOIN students s ON s.id = sc.student_id
        WHERE sc.academic_year_id = ? AND s.kelas = ?
    ");
    $stmt->execute([$yearId, $kelas]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['student_id'] . ':' . $row['subject_id']] = $row['nilai_akhir'];
    }
    return $map;
}

function predicateForScore($score)
{
    if ($score === null) return null;
    if ($score >= 90) return 'A';
    if ($score >= 80) return 'B';
    if ($score >= 70) return 'C';
    return 'D';
}
