<?php
/**
 * E-Portal Students API
 * Master student data used by E-Graduation and other modules.
 */
require_once __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        listStudents();
        break;
    case 'get':
        getStudent();
        break;
    case 'create':
        saveStudent(false);
        break;
    case 'update':
        saveStudent(true);
        break;
    case 'delete':
        deleteStudent();
        break;
    case 'delete-bulk':
        deleteBulkStudents();
        break;
    case 'import':
        importRows();
        break;
    case 'import-csv':
        importCsv();
        break;
    case 'template-csv':
        templateCsv();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function listStudents()
{
    require_superadmin();

    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $kelas = isset($_GET['kelas']) ? sanitize($_GET['kelas']) : '';
    $academicYearId = isset($_GET['academic_year_id']) ? (int) $_GET['academic_year_id'] : 0;
    $allYears = isset($_GET['all_years']) ? (int) $_GET['all_years'] : 0;

    if ($academicYearId <= 0 && $allYears !== 1) {
        $active = get_active_academic_year();
        $academicYearId = (int) ($active['id'] ?? 0);
    }

    try {
        $where = "WHERE 1=1";
        $params = [];

        if ($academicYearId > 0) {
            $where .= " AND s.academic_year_id = ?";
            $params[] = $academicYearId;
        }
        if ($search !== '') {
            $where .= " AND (s.nis LIKE ? OR s.nisn LIKE ? OR s.nama LIKE ? OR s.email LIKE ? OR s.tempat_lahir LIKE ? OR s.kelas LIKE ?)";
            $like = "%{$search}%";
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        if ($kelas !== '') {
            $where .= " AND s.kelas = ?";
            $params[] = $kelas;
        }

        $query = "
            SELECT s.*, ay.tahun_ajaran, ay.semester
            FROM students s
            LEFT JOIN academic_years ay ON ay.id = s.academic_year_id
            {$where}
            ORDER BY s.no_urut ASC, s.nama ASC
        ";
        $result = paginate($query, $params, $page, $perPage);
        json_response(200, true, 'Data siswa berhasil dimuat.', $result);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function getStudent()
{
    require_superadmin();

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        json_response(400, false, 'ID siswa tidak valid.');
    }

    try {
        $stmt = db()->prepare("
            SELECT s.*, ay.tahun_ajaran, ay.semester
            FROM students s
            LEFT JOIN academic_years ay ON ay.id = s.academic_year_id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $student = $stmt->fetch();
        if (!$student) {
            json_response(404, false, 'Data siswa tidak ditemukan.');
        }
        json_response(200, true, 'Data siswa.', $student);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function saveStudent($isUpdate)
{
    require_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = $_POST ?: get_input();
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    $academicYearId = isset($input['academic_year_id']) ? (int) $input['academic_year_id'] : 0;
    if ($academicYearId <= 0) {
        $active = get_active_academic_year();
        $academicYearId = (int) ($active['id'] ?? 0);
    }

    $data = normalizeStudentInput($input);

    if ($isUpdate && $id <= 0) {
        json_response(400, false, 'ID siswa tidak valid.');
    }
    if ($academicYearId <= 0) {
        json_response(400, false, 'Aktifkan tahun ajaran terlebih dahulu.');
    }
    validateStudent($data);

    try {
        $photoPath = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $upload = handle_upload($_FILES['foto'], 'students/', ['jpg', 'jpeg', 'png', 'webp']);
            if (!$upload['success']) {
                json_response(400, false, $upload['message']);
            }
            $photoPath = $upload['path'];
        }

        if ($isUpdate) {
            if ($photoPath !== null) {
                $old = db()->prepare("SELECT foto_path FROM students WHERE id = ?");
                $old->execute([$id]);
                $oldPath = $old->fetchColumn();
                if ($oldPath && file_exists(__DIR__ . '/../' . $oldPath)) {
                    unlink(__DIR__ . '/../' . $oldPath);
                }

                $stmt = db()->prepare("
                    UPDATE students SET academic_year_id=?, no_urut=?, nis=?, nisn=?, nama=?, email=?, tempat_lahir=?, jenis_kelamin=?, tanggal_lahir=?, kelas=?, foto_path=?
                    WHERE id=?
                ");
                $stmt->execute([$academicYearId, $data['no_urut'], $data['nis'], $data['nisn'], $data['nama'], $data['email'], $data['tempat_lahir'], $data['jenis_kelamin'], $data['tanggal_lahir'], $data['kelas'], $photoPath, $id]);
            } else {
                $stmt = db()->prepare("
                    UPDATE students SET academic_year_id=?, no_urut=?, nis=?, nisn=?, nama=?, email=?, tempat_lahir=?, jenis_kelamin=?, tanggal_lahir=?, kelas=?
                    WHERE id=?
                ");
                $stmt->execute([$academicYearId, $data['no_urut'], $data['nis'], $data['nisn'], $data['nama'], $data['email'], $data['tempat_lahir'], $data['jenis_kelamin'], $data['tanggal_lahir'], $data['kelas'], $id]);
            }
            json_response(200, true, 'Data siswa berhasil diperbarui.');
        }

        $stmt = db()->prepare("
            INSERT INTO students (academic_year_id, no_urut, nis, nisn, nama, email, tempat_lahir, jenis_kelamin, tanggal_lahir, kelas, foto_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$academicYearId, $data['no_urut'], $data['nis'], $data['nisn'], $data['nama'], $data['email'], $data['tempat_lahir'], $data['jenis_kelamin'], $data['tanggal_lahir'], $data['kelas'], $photoPath]);
        json_response(201, true, 'Data siswa berhasil ditambahkan.', ['id' => db()->lastInsertId()]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'uk_student_nis_year') !== false) {
            json_response(400, false, 'NIS sudah terdaftar pada tahun ajaran ini.');
        }
        if (strpos($e->getMessage(), 'uk_student_nisn_year') !== false) {
            json_response(400, false, 'NISN sudah terdaftar pada tahun ajaran ini.');
        }
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteStudent()
{
    require_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    if ($id <= 0) {
        json_response(400, false, 'ID siswa tidak valid.');
    }

    try {
        $stmt = db()->prepare("SELECT foto_path FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $photo = $stmt->fetchColumn();

        $stmt = db()->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$id]);

        if ($photo && file_exists(__DIR__ . '/../' . $photo)) {
            unlink(__DIR__ . '/../' . $photo);
        }

        json_response(200, true, 'Data siswa berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteBulkStudents()
{
    require_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $ids = isset($input['ids']) && is_array($input['ids']) ? $input['ids'] : [];
    if (empty($ids)) {
        json_response(400, false, 'Tidak ada data siswa yang dipilih.');
    }

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $stmt = db()->prepare("SELECT foto_path FROM students WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $photos = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmtDelete = db()->prepare("DELETE FROM students WHERE id IN ($placeholders)");
        $stmtDelete->execute($ids);

        foreach ($photos as $photo) {
            if ($photo && file_exists(__DIR__ . '/../' . $photo)) {
                unlink(__DIR__ . '/../' . $photo);
            }
        }

        json_response(200, true, count($ids) . ' data siswa berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function importCsv()
{
    require_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }
    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        json_response(400, false, 'File CSV harus diupload.');
    }

    $academicYearId = isset($_POST['academic_year_id']) ? (int) $_POST['academic_year_id'] : 0;
    if ($academicYearId <= 0) {
        $active = get_active_academic_year();
        $academicYearId = (int) ($active['id'] ?? 0);
    }
    if ($academicYearId <= 0) {
        json_response(400, false, 'Aktifkan tahun ajaran terlebih dahulu.');
    }

    $ext = strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        json_response(400, false, 'Gunakan file CSV.');
    }

    $rows = readCsvRows($_FILES['csv']['tmp_name']);
    if (empty($rows)) {
        json_response(400, false, 'Data CSV kosong.');
    }

    $inserted = 0;
    $updated = 0;
    $failed = 0;
    $errors = [];

    try {
        db()->beginTransaction();
        $check = db()->prepare("SELECT id FROM students WHERE academic_year_id = ? AND nis = ?");
        $insert = db()->prepare("
            INSERT INTO students (academic_year_id, no_urut, nis, nisn, nama, email, tempat_lahir, jenis_kelamin, tanggal_lahir, kelas)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $update = db()->prepare("
            UPDATE students SET no_urut=?, nisn=?, nama=?, email=?, tempat_lahir=?, jenis_kelamin=?, tanggal_lahir=?, kelas=?
            WHERE id=?
        ");

        foreach ($rows as $idx => $row) {
            $line = $idx + 2;
            $data = normalizeStudentInput($row);
            $error = validateStudent($data, false);
            if ($error) {
                $failed++;
                $errors[] = "Baris {$line}: {$error}";
                continue;
            }

            $check->execute([$academicYearId, $data['nis']]);
            $existingId = $check->fetchColumn();
            if ($existingId) {
                $update->execute([$data['no_urut'], $data['nisn'], $data['nama'], $data['email'], $data['tempat_lahir'], $data['jenis_kelamin'], $data['tanggal_lahir'], $data['kelas'], $existingId]);
                $updated++;
            } else {
                $insert->execute([$academicYearId, $data['no_urut'], $data['nis'], $data['nisn'], $data['nama'], $data['email'], $data['tempat_lahir'], $data['jenis_kelamin'], $data['tanggal_lahir'], $data['kelas']]);
                $inserted++;
            }
        }

        db()->commit();
        json_response(200, true, "Import selesai. Baru: {$inserted}, diperbarui: {$updated}, gagal: {$failed}.", [
            'inserted' => $inserted,
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors
        ]);
    } catch (PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function importRows()
{
    require_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $rows = isset($input['students']) && is_array($input['students']) ? $input['students'] : [];
    if (empty($rows)) {
        json_response(400, false, 'Data siswa kosong.');
    }

    $academicYearId = isset($input['academic_year_id']) ? (int) $input['academic_year_id'] : 0;
    if ($academicYearId <= 0) {
        $active = get_active_academic_year();
        $academicYearId = (int) ($active['id'] ?? 0);
    }
    if ($academicYearId <= 0) {
        json_response(400, false, 'Aktifkan tahun ajaran terlebih dahulu.');
    }

    $inserted = 0;
    $updated = 0;
    $failed = 0;
    $errors = [];

    try {
        db()->beginTransaction();
        $check = db()->prepare("SELECT id FROM students WHERE academic_year_id = ? AND nis = ?");
        $insert = db()->prepare("
            INSERT INTO students (academic_year_id, no_urut, nis, nisn, nama, email, tempat_lahir, jenis_kelamin, tanggal_lahir, kelas)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $update = db()->prepare("
            UPDATE students SET no_urut=?, nisn=?, nama=?, email=?, tempat_lahir=?, jenis_kelamin=?, tanggal_lahir=?, kelas=?
            WHERE id=?
        ");

        foreach ($rows as $idx => $row) {
            $line = $idx + 2;
            if (!is_array($row)) {
                $failed++;
                $errors[] = "Baris {$line}: Format data tidak valid.";
                continue;
            }

            $data = normalizeStudentInput($row);
            $error = validateStudent($data, false);
            if ($error) {
                $failed++;
                $errors[] = "Baris {$line}: {$error}";
                continue;
            }

            $check->execute([$academicYearId, $data['nis']]);
            $existingId = $check->fetchColumn();
            if ($existingId) {
                $update->execute([$data['no_urut'], $data['nisn'], $data['nama'], $data['email'], $data['tempat_lahir'], $data['jenis_kelamin'], $data['tanggal_lahir'], $data['kelas'], $existingId]);
                $updated++;
            } else {
                $insert->execute([$academicYearId, $data['no_urut'], $data['nis'], $data['nisn'], $data['nama'], $data['email'], $data['tempat_lahir'], $data['jenis_kelamin'], $data['tanggal_lahir'], $data['kelas']]);
                $inserted++;
            }
        }

        db()->commit();
        json_response(200, true, "Import selesai. Baru: {$inserted}, diperbarui: {$updated}, gagal: {$failed}.", [
            'inserted' => $inserted,
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors
        ]);
    } catch (PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function templateCsv()
{
    require_superadmin();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="template_data_siswa.csv"');
    echo "no,nis,nisn,nama,email,tempat_lahir,jenis_kelamin,tanggal_lahir,kelas\n";
    echo "1,12345,0012345678,Ahmad Fikri,ahmad.fikri@email.com,Surabaya,L,2008-05-12,XII IPA 1\n";
    exit;
}

function normalizeStudentInput($input)
{
    $gender = strtoupper(sanitize($input['jenis_kelamin'] ?? $input['lp'] ?? $input['l_p'] ?? ''));
    if ($gender === 'LAKI-LAKI' || $gender === 'L') {
        $gender = 'L';
    } elseif ($gender === 'PEREMPUAN' || $gender === 'P') {
        $gender = 'P';
    }

    return [
        'no_urut' => isset($input['no_urut']) ? (int) $input['no_urut'] : (int) ($input['no'] ?? 0),
        'nis' => trim(strip_tags((string) ($input['nis'] ?? ''))),
        'nisn' => trim(strip_tags((string) ($input['nisn'] ?? ''))) ?: null,
        'nama' => trim(strip_tags((string) ($input['nama'] ?? $input['nama_siswa'] ?? ''))),
        'email' => normalizeStudentEmail($input['email'] ?? $input['email_siswa'] ?? ''),
        'tempat_lahir' => trim(strip_tags((string) ($input['tempat_lahir'] ?? $input['tempat'] ?? ''))) ?: null,
        'jenis_kelamin' => $gender,
        'tanggal_lahir' => normalizeDate($input['tanggal_lahir'] ?? $input['tgl_lahir'] ?? ''),
        'kelas' => trim(strip_tags((string) ($input['kelas'] ?? '')))
    ];
}

function validateStudent($data, $sendResponse = true)
{
    $message = '';
    if (empty($data['nis']) || empty($data['nama']) || empty($data['jenis_kelamin']) || empty($data['tanggal_lahir']) || empty($data['kelas'])) {
        $message = 'NIS, nama, L/P, tanggal lahir, dan kelas wajib diisi.';
    } elseif (!in_array($data['jenis_kelamin'], ['L', 'P'])) {
        $message = 'L/P harus L atau P.';
    } elseif (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $message = 'Format email siswa tidak valid.';
    }

    if ($message && $sendResponse) {
        json_response(400, false, $message);
    }

    return $message;
}

function normalizeDate($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value;
    }
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $value, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
    }

    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : '';
}

function normalizeStudentEmail($value)
{
    $email = strtolower(trim((string) $value));
    return $email !== '' ? $email : null;
}

function readCsvRows($path)
{
    $handle = fopen($path, 'r');
    if (!$handle) {
        return [];
    }

    $firstLine = fgets($handle);
    if ($firstLine === false) {
        fclose($handle);
        return [];
    }
    $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    rewind($handle);

    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) {
        fclose($handle);
        return [];
    }
    $headers = array_map(function ($h) {
        $h = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)));
        $h = str_replace(['.', ' ', '-', '/'], ['_', '_', '_', '_'], $h);
        $h = trim($h, '_');
        if ($h === 'l_p') {
            return 'jenis_kelamin';
        }
        if ($h === 'no') {
            return 'no_urut';
        }
        return $h;
    }, $headers);

    $rows = [];
    while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
        $nonEmpty = array_filter($line, function ($v) {
            return trim((string) $v) !== '';
        });
        if (count($nonEmpty) === 0) {
            continue;
        }
        $row = [];
        foreach ($headers as $idx => $key) {
            $row[$key] = $line[$idx] ?? '';
        }
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}
