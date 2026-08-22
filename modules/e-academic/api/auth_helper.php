<?php
/**
 * E-Academic Authentication Helper
 * Centralized logic for session resolution and authentication checks
 */

require_once __DIR__ . '/../../../api/config.php';

/**
 * Extract token from Authorization header or query parameter
 */
function acad_extract_token() {
    $token = '';
    
    // Check via $_SERVER HTTP headers
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    } 
    // Fallback: getallheaders
    elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $token = str_replace('Bearer ', '', $headers['Authorization']);
        } elseif (isset($headers['authorization'])) {
            $token = str_replace('Bearer ', '', $headers['authorization']);
        }
    }
    
    // Final fallback: query parameters
    if ($token === '' && isset($_GET['token'])) {
        $token = (string)$_GET['token'];
    }
    
    return trim($token);
}

/**
 * Resolve user by session token
 */
function acad_resolve_user_by_token($token, $jsonOnFail = true) {
    $token = trim((string)$token);
    if ($token === '') {
        if ($jsonOnFail) json_response(401, false, 'Token required');
        return null;
    }

    try {
        $stmt = db()->prepare("
            SELECT u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            if ($jsonOnFail) json_response(401, false, 'Sesi tidak valid atau telah berakhir.');
            return null;
        }

        return $user;
    } catch (PDOException $e) {
        if ($jsonOnFail) json_response(500, false, 'Database error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Enforce authentication and return the user object
 */
function acad_auth() {
    return acad_resolve_user_by_token(acad_extract_token(), true);
}

/**
 * Enforce admin role permission check
 */
function acad_require_admin($user) {
    if (!in_array($user['role'], ['superadmin'])) {
        json_response(403, false, 'Akses ditolak. Anda tidak memiliki hak akses admin untuk modul ini.');
    }
}
