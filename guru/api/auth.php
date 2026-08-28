<?php
/**
 * Guru App — Authentication API
 * Login with permanent session (10 years expiry)
 * Only allows role 'guru'
 */
require_once __DIR__ . '/../../api/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'login':
        handleGuruLogin();
        break;
    case 'check':
        handleGuruCheck();
        break;
    case 'logout':
        handleGuruLogout();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * Handle Guru Login — session expires in 10 years
 */
function handleGuruLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $username = isset($input['username']) ? sanitize($input['username']) : '';
    $password = isset($input['password']) ? $input['password'] : '';

    if (empty($username) || empty($password)) {
        json_response(400, false, 'Username dan password harus diisi.');
    }

    try {
        $stmt = db()->prepare("SELECT * FROM users WHERE username = ? AND status = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            json_response(401, false, 'Username atau password salah.');
        }

        // Only allow guru role
        if ($user['role'] !== 'guru') {
            json_response(403, false, 'Akses ditolak. Hanya guru yang dapat menggunakan aplikasi ini.');
        }

        // Generate token with 10-year expiry for "permanent" session
        $token = generate_token();
        $expiredAt = date('Y-m-d H:i:s', time() + (10 * 365 * 24 * 60 * 60));

        // Clean only expired sessions
        db()->prepare("DELETE FROM sessions WHERE expired_at < NOW()")->execute();

        // Create new session
        $stmt = db()->prepare("
            INSERT INTO sessions (user_id, token, ip_address, user_agent, expired_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $token,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expiredAt
        ]);

        // Update last login
        db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

        // Get school settings
        $namaSekolah = get_setting('nama_sekolah', 'E-Portal Sekolah');
        $iconSekolah = get_setting('icon_sekolah', '');
        $activeAcademicYear = get_active_academic_year();

        // Check if teacher is a wali kelas
        $stmtWali = db()->prepare("SELECT id, tingkat, nama_kelas FROM ref_kelas WHERE wali_kelas_id = ? LIMIT 1");
        $stmtWali->execute([$user['id']]);
        $waliKelas = $stmtWali->fetch(PDO::FETCH_ASSOC) ?: null;

        json_response(200, true, 'Login berhasil!', [
            'token' => $token,
            'user' => [
                'id'            => $user['id'],
                'username'      => $user['username'],
                'nama_lengkap'  => $user['nama_lengkap'],
                'role'          => $user['role'],
                'avatar'        => $user['avatar'],
                'wali_kelas'    => $waliKelas
            ],
            'school' => [
                'nama' => $namaSekolah,
                'icon' => $iconSekolah
            ],
            'academic_year' => $activeAcademicYear
        ]);

    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Handle session check
 */
function handleGuruCheck() {
    $token = extractGuruToken();
    
    if (empty($token)) {
        json_response(401, false, 'Token tidak ditemukan.');
    }

    try {
        $stmt = db()->prepare("
            SELECT u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1 AND u.role = 'guru'
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            json_response(401, false, 'Sesi tidak valid atau telah berakhir.');
        }

        // Check if teacher is a wali kelas
        $stmtWali = db()->prepare("SELECT id, tingkat, nama_kelas FROM ref_kelas WHERE wali_kelas_id = ? LIMIT 1");
        $stmtWali->execute([$user['user_id']]);
        $waliKelas = $stmtWali->fetch(PDO::FETCH_ASSOC) ?: null;
        $user['wali_kelas'] = $waliKelas;

        // Get school settings
        $namaSekolah = get_setting('nama_sekolah', 'E-Portal Sekolah');
        $iconSekolah = get_setting('icon_sekolah', '');
        $activeAcademicYear = get_active_academic_year();

        json_response(200, true, 'Sesi aktif.', [
            'user' => $user,
            'school' => [
                'nama' => $namaSekolah,
                'icon' => $iconSekolah
            ],
            'academic_year' => $activeAcademicYear
        ]);

    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Handle logout
 */
function handleGuruLogout() {
    $token = extractGuruToken();

    if (!empty($token)) {
        try {
            db()->prepare("DELETE FROM sessions WHERE token = ?")->execute([$token]);
        } catch (PDOException $e) {
            // Silent fail
        }
    }

    json_response(200, true, 'Logout berhasil.');
}

/**
 * Extract token from Authorization header or query parameter
 */
function extractGuruToken() {
    $token = '';

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        } elseif (isset($headers['authorization'])) {
            $token = str_replace('Bearer ', '', $headers['authorization']);
        }
    }

    if (empty($token) && isset($_GET['token'])) {
        $token = $_GET['token'];
    }

    return trim($token);
}
