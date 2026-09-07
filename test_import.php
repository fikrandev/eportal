<?php
require_once 'api/config.php';

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
        'no_hp_ortu' => trim(strip_tags((string) ($input['no_hp_ortu'] ?? ''))) ?: null,
        'no_hp_siswa' => trim(strip_tags((string) ($input['no_hp_siswa'] ?? ''))) ?: null,
        'guru_wali' => trim(strip_tags((string) ($input['guru_wali'] ?? ''))) ?: null,
        'tempat_lahir' => trim(strip_tags((string) ($input['tempat_lahir'] ?? $input['tempat'] ?? ''))) ?: null,
        'jenis_kelamin' => $gender ?: null,
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

$rows = [
    [
        'no_urut' => 1,
        'nis' => '123456',
        'nisn' => '0012345678',
        'nama' => 'Test Siswa',
        'email' => 'test@siswa.com',
        'tempat_lahir' => 'Jakarta',
        'jenis_kelamin' => 'L',
        'tanggal_lahir' => '2010-01-01',
        'kelas' => 'X MIPA 1'
    ]
];
$academicYearId = 1;

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
        
        echo "Data: " . print_r($data, true) . "\n";
        
        if ($existingId) {
            $update->execute([$data['no_urut'], $data['nisn'], $data['nama'], $data['email'], $data['tempat_lahir'], $data['jenis_kelamin'], $data['tanggal_lahir'], $data['kelas'], $existingId]);
            $updated++;
        } else {
            $insert->execute([$academicYearId, $data['no_urut'], $data['nis'], $data['nisn'], $data['nama'], $data['email'], $data['tempat_lahir'], $data['jenis_kelamin'], $data['tanggal_lahir'], $data['kelas']]);
            $inserted++;
        }
    }

    db()->commit();
    echo "Success! Inserted: $inserted, Updated: $updated, Failed: $failed\n";
    if (!empty($errors)) {
        print_r($errors);
    }
} catch (PDOException $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    echo "PDO Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
