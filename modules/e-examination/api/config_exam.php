<?php
/**
 * E-Examination — API Configuration & Auth Helpers
 * Reuses eportal core config, adds examination-specific auth
 */
require_once __DIR__ . '/../../../api/config.php';

/**
 * Authenticate request for E-Examination (Admin/Guru)
 * Uses eportal session token
 */
function exam_auth() {
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

    if (empty($token)) {
        json_response(401, false, 'Token tidak ditemukan.');
    }

    try {
        $stmt = db()->prepare("
            SELECT s.*, u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar, u.status
            FROM sessions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if (!$session) {
            json_response(401, false, 'Sesi tidak valid atau telah berakhir.');
        }

        $user = [
            'user_id'       => $session['user_id'],
            'username'      => $session['username'],
            'nama_lengkap'  => $session['nama_lengkap'],
            'role'          => $session['role'],
            'avatar'        => $session['avatar'],
            'is_admin'      => in_array($session['role'], ['superadmin', 'user']),
            'is_guru'       => ($session['role'] === 'guru'),
        ];

        return $user;
    } catch (PDOException $e) {
        json_response(500, false, 'Server error.');
    }
}

/**
 * Require admin access
 */
function exam_require_admin() {
    $user = exam_auth();
    if (!$user['is_admin']) {
        json_response(403, false, 'Akses ditolak. Hanya admin yang dapat mengakses.');
    }
    return $user;
}

/**
 * Require admin or guru access
 */
function exam_require_admin_or_guru() {
    $user = exam_auth();
    if (!$user['is_admin'] && !$user['is_guru']) {
        json_response(403, false, 'Akses ditolak.');
    }
    return $user;
}

/**
 * Get active academic year helper
 */
function exam_active_year() {
    $active = get_active_academic_year();
    $id = (int)($active['id'] ?? 0);
    if ($id <= 0) {
        json_response(400, false, 'Tahun ajaran aktif belum diatur di admin E-Portal.');
    }
    return $active;
}

/**
 * Generate random exam token (6 chars uppercase)
 */
function exam_generate_token($length = 6) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Exclude confusing chars (0,O,1,I)
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $token;
}

/**
 * Get all classes from students table for active year
 */
function exam_get_classes() {
    $active = get_active_academic_year();
    $yearId = (int)($active['id'] ?? 0);
    if ($yearId <= 0) return [];

    $stmt = db()->prepare("
        SELECT kelas, COUNT(*) as total_siswa
        FROM students
        WHERE academic_year_id = ? AND status = 1 AND kelas <> ''
        GROUP BY kelas
        ORDER BY kelas ASC
    ");
    $stmt->execute([$yearId]);
    return $stmt->fetchAll();
}
