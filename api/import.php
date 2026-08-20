<?php
/**
 * E-Portal Import API
 * Excel import for users with SheetJS (processed client-side)
 */
require_once __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'users':
        importUsers();
        break;
    case 'template':
        downloadTemplate();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * Import users from parsed Excel data
 * Client sends JSON array of user data (parsed by SheetJS)
 */
function importUsers() {
    require_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $users = isset($input['users']) ? $input['users'] : [];
    $importType = isset($input['type']) ? sanitize($input['type']) : 'users';

    if (empty($users) || !is_array($users)) {
        json_response(400, false, 'Data users tidak valid atau kosong.');
    }

    $success = 0;
    $failed = 0;
    $errors = [];

    try {
        $stmtCheck = db()->prepare("SELECT id FROM users WHERE username = ?");
        $stmtInsert = db()->prepare("
            INSERT INTO users (username, password, nama_lengkap, nik, email, tempat_lahir, tgl_lahir, tupoksi, jabatan, mapel, status_guru, tpg, tmt, role)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        db()->beginTransaction();

        foreach ($users as $index => $user) {
            $row = $index + 2; // Excel row (header = row 1)
            
            $username = import_value($user, ['username', 'nip', 'NIP', 'NIP / Username', 'NIP/Username']);
            $password = import_value($user, ['password', 'Password']);
            $namaLengkap = import_value($user, ['nama_lengkap', 'Nama Lengkap', 'nama', 'Nama']);
            $nik = import_value($user, ['nik', 'NIK', 'Nomor Induk Kependudukan']);
            $email = strtolower(import_value($user, ['email', 'Email', 'E-mail']));
            $tempatLahir = import_value($user, ['tempat_lahir', 'Tempat Lahir']);
            $tglLahir = normalize_import_date(import_value($user, ['tgl_lahir', 'Tgl Lahir', 'Tanggal Lahir']));
            $tupoksi = import_value($user, ['tupoksi', 'Tupoksi']);
            $jabatan = import_value($user, ['jabatan', 'Jabatan']);
            $mapel = import_value($user, ['mapel', 'Mapel', 'Mata Pelajaran']);
            $statusGuru = import_value($user, ['status_guru', 'Status Guru', 'status_dropdown', 'Status']);
            $tpg = normalize_import_tpg(import_value($user, ['tpg', 'TPG']));
            $tmt = normalize_import_date(import_value($user, ['tmt', 'TMT']));
            $role = strtolower(import_value($user, ['role', 'Role'], $importType === 'gurus' ? 'guru' : 'user'));

            if ($importType === 'gurus') {
                $role = 'guru';
                if (empty($username) && !empty($nik)) {
                    $username = $nik;
                }
                if (empty($password)) {
                    $password = '1234567';
                }
            }

            // Validate
            if (empty($username)) {
                $errors[] = "Baris {$row}: Username kosong.";
                $failed++;
                continue;
            }

            if (empty($password)) {
                $errors[] = "Baris {$row}: Password kosong.";
                $failed++;
                continue;
            }

            if (empty($namaLengkap)) {
                $errors[] = "Baris {$row}: Nama lengkap kosong.";
                $failed++;
                continue;
            }

            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Baris {$row}: Format email tidak valid.";
                $failed++;
                continue;
            }

            if (!in_array($role, ['superadmin', 'user', 'guru'])) {
                $role = 'user';
            }

            // Check duplicate
            $stmtCheck->execute([$username]);
            if ($stmtCheck->fetch()) {
                $errors[] = "Baris {$row}: Username '{$username}' sudah ada.";
                $failed++;
                continue;
            }

            // Insert
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmtInsert->execute([$username, $hashedPassword, $namaLengkap, $nik ?: null, $email ?: null, $tempatLahir, $tglLahir, $tupoksi, $jabatan, $mapel, $statusGuru, $tpg, $tmt, $role]);
            $success++;
        }

        db()->commit();

        $message = "Import selesai. Berhasil: {$success}, Gagal: {$failed}.";
        json_response(200, true, $message, [
            'success_count' => $success,
            'failed_count'  => $failed,
            'errors'        => $errors
        ]);

    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function import_value($row, $keys, $default = '') {
    foreach ($keys as $key) {
        if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
            return sanitize(trim((string)$row[$key]));
        }
    }
    return $default;
}

function normalize_import_date($value) {
    if ($value === null || $value === '') return null;
    if (is_numeric($value)) {
        $serial = (float)$value;
        if ($serial > 20000 && $serial < 80000) {
            return gmdate('Y-m-d', (int)(($serial - 25569) * 86400));
        }
    }
    $value = trim((string)$value);
    $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date && $date->format($format) === $value) {
            return $date->format('Y-m-d');
        }
    }
    $time = strtotime($value);
    return $time ? date('Y-m-d', $time) : null;
}

function normalize_import_tpg($value) {
    $value = strtolower(trim((string)$value));
    return in_array($value, ['ya', 'y', 'yes', '1', 'true']) ? 'Ya' : 'Tidak';
}

/**
 * Return template format info
 */
function downloadTemplate() {
    require_superadmin();

    json_response(200, true, 'Template format.', [
        'columns' => [
            ['key' => 'username', 'label' => 'Username', 'required' => true],
            ['key' => 'password', 'label' => 'Password', 'required' => true],
            ['key' => 'nama_lengkap', 'label' => 'Nama Lengkap', 'required' => true],
            ['key' => 'nik', 'label' => 'NIK', 'required' => false],
            ['key' => 'email', 'label' => 'Email', 'required' => false],
            ['key' => 'tempat_lahir', 'label' => 'Tempat Lahir', 'required' => false],
            ['key' => 'tgl_lahir', 'label' => 'Tgl Lahir', 'required' => false],
            ['key' => 'tupoksi', 'label' => 'Tupoksi', 'required' => false],
            ['key' => 'jabatan', 'label' => 'Jabatan', 'required' => false],
            ['key' => 'mapel', 'label' => 'Mata Pelajaran', 'required' => false],
            ['key' => 'status_guru', 'label' => 'Status Guru', 'required' => false],
            ['key' => 'tpg', 'label' => 'TPG (Ya/Tidak)', 'required' => false, 'default' => 'Tidak'],
            ['key' => 'tmt', 'label' => 'TMT', 'required' => false],
            ['key' => 'role', 'label' => 'Role (user/superadmin/guru)', 'required' => false, 'default' => 'user']
        ],
        'example' => [
            ['username' => 'guru001', 'password' => 'pass123', 'nama_lengkap' => 'Budi Santoso', 'nik' => '7201010203040001', 'email' => 'budi.santoso@email.com', 'role' => 'user'],
            ['username' => 'guru002', 'password' => 'pass456', 'nama_lengkap' => 'Siti Rahayu', 'nik' => '7201010203040002', 'email' => 'siti.rahayu@email.com', 'role' => 'user']
        ]
    ]);
}
