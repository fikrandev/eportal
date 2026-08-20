<?php
/**
 * E-Performance — API Configuration & Auth Helpers
 * Reuses eportal core config, adds performance-specific auth
 */
require_once __DIR__ . '/../../../api/config.php';

// Performance module constants
define('PERF_SESSION_DURATION', 86400); // 24 hours

/**
 * Authenticate request for E-Performance
 * Supports both eportal token (admin/superadmin) and perf_sessions token
 * Returns: ['type' => 'eportal'|'perf', 'user' => [...], 'role' => '...']
 */
function perf_auth_check() {
    $token = '';
    
    // Check via $_SERVER first (most reliable for CGI/FastCGI hosting)
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }
    // Fallback: getallheaders (works on Apache mod_php)
    elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        } elseif (isset($headers['authorization'])) {
            $token = str_replace('Bearer ', '', $headers['authorization']);
        }
    }
    // Final fallback: query param
    if (empty($token) && isset($_GET['token'])) {
        $token = $_GET['token'];
    }

    if (empty($token)) {
        json_response(401, false, 'Token tidak ditemukan.');
    }

    // Try eportal session first
    try {
        $stmt = db()->prepare("
            SELECT s.*, u.id as user_id, u.username, u.nama_lengkap, u.role, u.tupoksi, u.avatar, u.status, r.permissions
            FROM sessions s 
            JOIN users u ON s.user_id = u.id 
            LEFT JOIN perf_roles r ON u.role COLLATE utf8mb4_unicode_ci = r.role_slug COLLATE utf8mb4_unicode_ci
            WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if ($session) {
            $permissions = json_decode($session['permissions'] ?: '[]', true);
            $perfRole = $session['role'];
            if ($session['role'] === 'superadmin' || !empty($permissions)) {
                $perfRole = 'admin';
            }
            return [
                'type' => 'eportal',
                'user_id' => $session['user_id'],
                'username' => $session['username'],
                'nama_lengkap' => $session['nama_lengkap'],
                'role' => $perfRole,
                'tupoksi' => $session['tupoksi'] ?? '',
                'eportal_role' => $session['role'],
                'avatar' => $session['avatar'],
                'permissions' => $permissions
            ];
        }
    } catch (PDOException $e) {
        // Continue to perf session check
    }

    // Try perf_sessions
    try {
        $stmt = db()->prepare("
            SELECT ps.*, pu.id as perf_user_id, pu.username, pu.nama_lengkap, pu.role, 
                   pu.perf_ptk_id, pu.status, pr.permissions
            FROM perf_sessions ps
            JOIN perf_users pu ON ps.perf_user_id = pu.id
            LEFT JOIN perf_roles pr ON pu.role = pr.role_slug
            WHERE ps.token = ? AND ps.expired_at > NOW() AND pu.status = 1
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if ($session) {
            return [
                'type' => 'perf',
                'user_id' => $session['perf_user_id'],
                'username' => $session['username'],
                'nama_lengkap' => $session['nama_lengkap'],
                'role' => $session['role'],
                'ptk_id' => $session['perf_ptk_id'],
                'avatar' => null,
                'permissions' => $session['permissions'] ? json_decode($session['permissions'], true) : []
            ];
        }
    } catch (PDOException $e) {
        // Fall through
    }

    json_response(401, false, 'Sesi tidak valid atau telah berakhir.');
}

/**
 * Require specific roles
 */
function perf_require_role($allowed_roles) {
    $auth = perf_auth_check();
    if (!in_array($auth['role'], (array)$allowed_roles)) {
        json_response(403, false, 'Akses ditolak untuk role Anda.');
    }
    return $auth;
}

/**
 * Require specific permission
 */
function perf_require_permission($required_perm) {
    $auth = perf_auth_check();
    $perms = isset($auth['permissions']) ? $auth['permissions'] : [];
    if (!in_array($required_perm, $perms) && $auth['role'] !== 'admin' && $auth['role'] !== 'superadmin') {
        json_response(403, false, 'Akses ditolak. Anda tidak memiliki izin ('.$required_perm.').');
    }
    return $auth;
}

/**
 * Require admin access
 */
function perf_require_admin() {
    return perf_require_role(['admin']);
}

/**
 * Generate perf session token
 */
function perf_generate_token($perf_user_id) {
    $token = bin2hex(random_bytes(32));
    $expired = date('Y-m-d H:i:s', time() + PERF_SESSION_DURATION);
    
    // Remove old sessions for this user
    $stmt = db()->prepare("DELETE FROM perf_sessions WHERE perf_user_id = ?");
    $stmt->execute([$perf_user_id]);
    
    // Create new session
    $stmt = db()->prepare("INSERT INTO perf_sessions (perf_user_id, token, expired_at) VALUES (?, ?, ?)");
    $stmt->execute([$perf_user_id, $token, $expired]);
    
    return $token;
}

// Auto-migrate: Add tipe_jawaban, skor_ya, skor_tidak column to perf_instrumen if they don't exist
try {
    $stmt = db()->query("SHOW COLUMNS FROM perf_instrumen LIKE 'tipe_jawaban'");
    if ($stmt->rowCount() == 0) {
        db()->query("ALTER TABLE perf_instrumen ADD COLUMN tipe_jawaban VARCHAR(20) DEFAULT 'angka' AFTER is_manual");
        db()->query("ALTER TABLE perf_instrumen ADD COLUMN skor_ya DECIMAL(5,2) DEFAULT 100 AFTER tipe_jawaban");
        db()->query("ALTER TABLE perf_instrumen ADD COLUMN skor_tidak DECIMAL(5,2) DEFAULT 0 AFTER skor_ya");
    } else {
        // Also check if skor_ya exists independently in case they ran partial migration
        $stmtYa = db()->query("SHOW COLUMNS FROM perf_instrumen LIKE 'skor_ya'");
        if ($stmtYa->rowCount() == 0) {
            db()->query("ALTER TABLE perf_instrumen ADD COLUMN skor_ya DECIMAL(5,2) DEFAULT 100 AFTER tipe_jawaban");
            db()->query("ALTER TABLE perf_instrumen ADD COLUMN skor_tidak DECIMAL(5,2) DEFAULT 0 AFTER skor_ya");
        }
    }
    
    // Auto-migrate: Add is_released to perf_periode
    $stmtPeriode = db()->query("SHOW COLUMNS FROM perf_periode LIKE 'is_released'");
    if ($stmtPeriode->rowCount() == 0) {
        db()->query("ALTER TABLE perf_periode ADD COLUMN is_released TINYINT(1) DEFAULT 0 AFTER status");
    }
} catch (PDOException $e) {
    // Ignore, table might not exist yet during fresh install
}
