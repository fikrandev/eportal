<?php
/**
 * E-Portal Users API
 * CRUD operations for user management (Superadmin only)
 */
require_once __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        listUsers();
        break;
    case 'get':
        getUser();
        break;
    case 'create':
        createUser();
        break;
    case 'update':
        updateUser();
        break;
    case 'delete':
        deleteUser();
        break;
    case 'update_kode_guru':
        updateKodeGuru();
        break;
    case 'delete-bulk':
        deleteBulkUsers();
        break;
    case 'reset-password':
        resetPassword();
        break;
    case 'stats':
        getStats();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List users with pagination and search
 */
function listUsers() {
    require_superadmin();

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $role = isset($_GET['role']) ? sanitize($_GET['role']) : '';
    $excludeGuru = isset($_GET['exclude_guru']) ? (int)$_GET['exclude_guru'] : 0;

    try {
        $where = "WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $where .= " AND (username LIKE ? OR nama_lengkap LIKE ? OR nik LIKE ? OR email LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        if (!empty($role) && in_array($role, ['superadmin', 'user', 'guru'])) {
            $where .= " AND role = ?";
            $params[] = $role;
        } elseif ($excludeGuru === 1) {
            $where .= " AND role != 'guru'";
        }

        $query = "SELECT id, username, kode_guru, nama_lengkap, nik, email, no_hp, tempat_lahir, tgl_lahir, tupoksi, jabatan, mapel, status_guru, tpg, tmt, role, avatar, status, last_login, created_at FROM users {$where} ORDER BY created_at DESC";
        $result = paginate($query, $params, $page, $perPage);

        json_response(200, true, 'Data user berhasil dimuat.', $result);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Update kode guru
 */
function updateKodeGuru() {
    require_superadmin();
    
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $kode = isset($input['kode_guru']) ? sanitize($input['kode_guru']) : '';

    if ($id <= 0 || empty($kode)) {
        json_response(400, false, 'ID dan Kode Guru harus diisi.');
    }

    try {
        $stmt = db()->prepare("UPDATE users SET kode_guru = ? WHERE id = ? AND role = 'guru'");
        $stmt->execute([strtoupper($kode), $id]);
        json_response(200, true, 'Kode Guru berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get single user
 */
function getUser() {
    require_superadmin();

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        json_response(400, false, 'ID user tidak valid.');
    }

    try {
        $stmt = db()->prepare("SELECT id, username, kode_guru, nama_lengkap, nik, email, no_hp, tempat_lahir, tgl_lahir, tupoksi, jabatan, mapel, status_guru, tpg, tmt, role, avatar, status, last_login, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user) {
            json_response(404, false, 'User tidak ditemukan.');
        }

        json_response(200, true, 'Data user.', $user);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Create new user
 */
function createUser() {
    require_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = $_POST ?: get_input();
    $username = isset($input['username']) ? sanitize($input['username']) : '';
    $password = isset($input['password']) ? $input['password'] : '';
    $namaLengkap = isset($input['nama_lengkap']) ? sanitize($input['nama_lengkap']) : '';
    $nik = isset($input['nik']) ? sanitize($input['nik']) : '';
    $email = isset($input['email']) ? strtolower(sanitize($input['email'])) : '';
    $no_hp = isset($input['no_hp']) ? sanitize($input['no_hp']) : '';
    $tempatLahir = isset($input['tempat_lahir']) ? sanitize($input['tempat_lahir']) : '';
    $tglLahir = normalize_user_date($input['tgl_lahir'] ?? null);
    $tupoksi = isset($input['tupoksi']) ? sanitize($input['tupoksi']) : '';
    $jabatan = isset($input['jabatan']) ? sanitize($input['jabatan']) : '';
    $mapel = isset($input['mapel']) ? sanitize($input['mapel']) : '';
    $statusGuru = isset($input['status_guru']) ? sanitize($input['status_guru']) : '';
    $tpg = normalize_tpg($input['tpg'] ?? 'Tidak');
    $tmt = normalize_user_date($input['tmt'] ?? null);
    $role = isset($input['role']) ? sanitize($input['role']) : 'user';

    if (!in_array($role, ['superadmin', 'user', 'guru'])) {
        $role = 'user';
    }

    $kode_guru = null;
    // Auto-generate username/password for Guru
    if ($role === 'guru') {
        if (empty($username) && !empty($nik)) {
            $username = $nik;
        }
        if (empty($password)) {
            $password = '1234567';
        }
        
        // Auto-generate kode_guru dari nama
        $consonants = preg_replace('/[AEIOUaeiou\s]/', '', strtoupper(trim($namaLengkap)));
        $kode_guru = substr($consonants, 0, 4);
        if (empty($kode_guru)) $kode_guru = substr(strtoupper(trim($namaLengkap)), 0, 4);
    }

    if (empty($username) || empty($password) || empty($namaLengkap)) {
        json_response(400, false, 'Username, password, dan nama lengkap harus diisi.');
    }

    if (strlen($username) < 3) {
        json_response(400, false, 'Username minimal 3 karakter.');
    }

    if (strlen($password) < 5) {
        json_response(400, false, 'Password minimal 5 karakter.');
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(400, false, 'Format email tidak valid.');
    }

    try {
        // Check duplicate
        $stmt = db()->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            json_response(400, false, 'Username sudah digunakan.');
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $avatarPath = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload = handle_upload($_FILES['avatar'], 'avatars/', ['jpg', 'jpeg', 'png', 'webp']);
            if (!$upload['success']) {
                json_response(400, false, $upload['message']);
            }
            $avatarPath = $upload['path'];
        }

        $stmt = db()->prepare("
            INSERT INTO users (username, kode_guru, password, nama_lengkap, nik, email, no_hp, tempat_lahir, tgl_lahir, tupoksi, jabatan, mapel, status_guru, tpg, tmt, role, avatar)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$username, $kode_guru, $hashedPassword, $namaLengkap, $nik ?: null, $email ?: null, $no_hp ?: null, $tempatLahir, $tglLahir, $tupoksi, $jabatan, $mapel, $statusGuru, $tpg, $tmt, $role, $avatarPath]);

        json_response(201, true, 'User berhasil ditambahkan.', [
            'id' => db()->lastInsertId()
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Update user
 */
function updateUser() {
    require_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = $_POST ?: get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    
    if ($id <= 0) {
        json_response(400, false, 'ID user tidak valid.');
    }

    $namaLengkap = isset($input['nama_lengkap']) ? sanitize($input['nama_lengkap']) : '';
    $nik = isset($input['nik']) ? sanitize($input['nik']) : '';
    $email = isset($input['email']) ? strtolower(sanitize($input['email'])) : '';
    $no_hp = isset($input['no_hp']) ? sanitize($input['no_hp']) : '';
    $tempatLahir = isset($input['tempat_lahir']) ? sanitize($input['tempat_lahir']) : '';
    $tglLahir = normalize_user_date($input['tgl_lahir'] ?? null);
    $tupoksi = isset($input['tupoksi']) ? sanitize($input['tupoksi']) : '';
    $jabatan = isset($input['jabatan']) ? sanitize($input['jabatan']) : '';
    $mapel = isset($input['mapel']) ? sanitize($input['mapel']) : '';
    $statusGuru = isset($input['status_guru']) ? sanitize($input['status_guru']) : '';
    $tpg = normalize_tpg($input['tpg'] ?? 'Tidak');
    $tmt = normalize_user_date($input['tmt'] ?? null);
    $role = isset($input['role']) ? sanitize($input['role']) : 'user';
    $status = isset($input['status']) ? (int)$input['status'] : 1;

    if (empty($namaLengkap)) {
        json_response(400, false, 'Nama lengkap harus diisi.');
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(400, false, 'Format email tidak valid.');
    }

    if (!empty($input['password'])) {
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
    } elseif ($role === 'guru' && !isset($input['password'])) {
        // If password is not sent or explicitly ignored in guru form, we preserve the existing one.
        // We do nothing here so the existing hash stays.
    }

    try {
        $avatarPath = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload = handle_upload($_FILES['avatar'], 'avatars/', ['jpg', 'jpeg', 'png', 'webp']);
            if (!$upload['success']) {
                json_response(400, false, $upload['message']);
            }
            $avatarPath = $upload['path'];
            
            // Delete old avatar
            $old = db()->prepare("SELECT avatar FROM users WHERE id = ?");
            $old->execute([$id]);
            $oldPath = $old->fetchColumn();
            if ($oldPath && file_exists(__DIR__ . '/../' . $oldPath)) {
                unlink(__DIR__ . '/../' . $oldPath);
            }
        }

        if (!empty($input['password'])) {
            if ($avatarPath !== null) {
                $stmt = db()->prepare("
                    UPDATE users SET nama_lengkap = ?, nik = ?, email = ?, no_hp = ?, tempat_lahir = ?, tgl_lahir = ?, tupoksi = ?, jabatan = ?, mapel = ?, status_guru = ?, tpg = ?, tmt = ?, role = ?, status = ?, password = ?, avatar = ?
                    WHERE id = ?
                ");
                $stmt->execute([$namaLengkap, $nik ?: null, $email ?: null, $no_hp ?: null, $tempatLahir, $tglLahir, $tupoksi, $jabatan, $mapel, $statusGuru, $tpg, $tmt, $role, $status, $hashedPassword, $avatarPath, $id]);
            } else {
                $stmt = db()->prepare("
                    UPDATE users SET nama_lengkap = ?, nik = ?, email = ?, no_hp = ?, tempat_lahir = ?, tgl_lahir = ?, tupoksi = ?, jabatan = ?, mapel = ?, status_guru = ?, tpg = ?, tmt = ?, role = ?, status = ?, password = ?
                    WHERE id = ?
                ");
                $stmt->execute([$namaLengkap, $nik ?: null, $email ?: null, $no_hp ?: null, $tempatLahir, $tglLahir, $tupoksi, $jabatan, $mapel, $statusGuru, $tpg, $tmt, $role, $status, $hashedPassword, $id]);
            }
        } else {
            if ($avatarPath !== null) {
                $stmt = db()->prepare("
                    UPDATE users SET nama_lengkap = ?, nik = ?, email = ?, no_hp = ?, tempat_lahir = ?, tgl_lahir = ?, tupoksi = ?, jabatan = ?, mapel = ?, status_guru = ?, tpg = ?, tmt = ?, role = ?, status = ?, avatar = ?
                    WHERE id = ?
                ");
                $stmt->execute([$namaLengkap, $nik ?: null, $email ?: null, $no_hp ?: null, $tempatLahir, $tglLahir, $tupoksi, $jabatan, $mapel, $statusGuru, $tpg, $tmt, $role, $status, $avatarPath, $id]);
            } else {
                $stmt = db()->prepare("
                    UPDATE users SET nama_lengkap = ?, nik = ?, email = ?, no_hp = ?, tempat_lahir = ?, tgl_lahir = ?, tupoksi = ?, jabatan = ?, mapel = ?, status_guru = ?, tpg = ?, tmt = ?, role = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$namaLengkap, $nik ?: null, $email ?: null, $no_hp ?: null, $tempatLahir, $tglLahir, $tupoksi, $jabatan, $mapel, $statusGuru, $tpg, $tmt, $role, $status, $id]);
            }
        }

        json_response(200, true, 'User berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function normalize_user_date($value) {
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

function normalize_tpg($value) {
    $value = strtolower(trim((string)$value));
    return in_array($value, ['ya', 'y', 'yes', '1', 'true']) ? 'Ya' : 'Tidak';
}

/**
 * Delete user
 */
function deleteUser() {
    require_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if ($id <= 0) {
        json_response(400, false, 'ID user tidak valid.');
    }

    // Prevent deleting self
    $currentUser = auth_check();
    if ($currentUser['user_id'] == $id) {
        json_response(400, false, 'Tidak dapat menghapus akun sendiri.');
    }

    try {
        $stmt = db()->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        json_response(200, true, 'User berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Bulk delete users
 */
function deleteBulkUsers() {
    require_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $ids = isset($input['ids']) && is_array($input['ids']) ? $input['ids'] : [];

    if (empty($ids)) {
        json_response(400, false, 'Tidak ada user yang dipilih.');
    }

    // Prevent deleting self
    $currentUser = auth_check();
    if (in_array($currentUser['user_id'], $ids)) {
        json_response(400, false, 'Tidak dapat menghapus akun sendiri.');
    }

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare("DELETE FROM users WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        json_response(200, true, count($ids) . ' user berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Reset user password
 */
function resetPassword() {
    require_superadmin();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $newPassword = isset($input['password']) ? $input['password'] : '';

    if ($id <= 0 || empty($newPassword)) {
        json_response(400, false, 'ID user dan password baru harus diisi.');
    }

    if (strlen($newPassword) < 5) {
        json_response(400, false, 'Password minimal 5 karakter.');
    }

    try {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = db()->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $id]);

        json_response(200, true, 'Password berhasil direset.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get stats for admin dashboard
 */
function getStats() {
    require_superadmin();

    try {
        $stats = [];

        // Total users
        $stmt = db()->query("SELECT COUNT(*) as total FROM users");
        $stats['total_users'] = (int)$stmt->fetch()['total'];

        // Active users
        $stmt = db()->query("SELECT COUNT(*) as total FROM users WHERE status = 1");
        $stats['active_users'] = (int)$stmt->fetch()['total'];

        // Total modules
        $stmt = db()->query("SELECT COUNT(*) as total FROM modules WHERE status = 1");
        $stats['total_modules'] = (int)$stmt->fetch()['total'];

        json_response(200, true, 'Statistik berhasil dimuat.', $stats);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
