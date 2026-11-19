<?php
/**
 * E-Curriculum Authentication Helper
 * Centralized logic for session resolution and authentication checks
 */

require_once __DIR__ . '/../../../api/config.php';

/**
 * Extract token from Authorization header or query parameter
 */
function acad_extract_token() {
    return get_bearer_token(true);
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
    if (($user['role'] ?? '') !== 'superadmin' && !portal_has_permission($user, 'module_e-absen') && !portal_has_permission($user, '*')) {
        json_response(403, false, 'Akses ditolak. Anda tidak memiliki hak akses admin untuk modul ini.');
    }
}
