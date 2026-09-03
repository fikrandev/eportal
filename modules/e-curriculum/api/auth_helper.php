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

        // Resolve acad_role
        $user['acad_role'] = '';
        if ($user['role'] === 'superadmin') {
            $user['acad_role'] = 'admin_kurikulum';
        } else {
            $stmtRole = db()->prepare("SELECT role FROM acad_users WHERE user_id = ? LIMIT 1");
            $stmtRole->execute([$user['user_id']]);
            $acadRole = $stmtRole->fetchColumn();
            if ($acadRole) {
                $user['acad_role'] = $acadRole;
            }
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
    if (!isset($user['acad_role']) || !in_array($user['acad_role'], ['admin_kurikulum', 'operator_kurikulum'])) {
        json_response(403, false, 'Akses ditolak. Anda tidak memiliki hak akses admin untuk modul ini.');
    }
}

/**
 * Auto-migrate tables for E-Curriculum
 */
function acad_run_migrations() {
    try {
        // Table acad_users
        db()->exec("CREATE TABLE IF NOT EXISTS acad_users (
            id int(11) unsigned NOT NULL AUTO_INCREMENT,
            user_id int(11) unsigned NOT NULL,
            role enum('admin_kurikulum','operator_kurikulum') NOT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Table acad_documents
        db()->exec("CREATE TABLE IF NOT EXISTS acad_documents (
            id int(11) unsigned NOT NULL AUTO_INCREMENT, 
            user_id int(11) unsigned NOT NULL, 
            academic_year_id int(11) unsigned NOT NULL,
            judul varchar(255) NOT NULL,
            tipe_dokumen varchar(100) NOT NULL,
            file_path varchar(255) NOT NULL,
            status enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            catatan_admin text DEFAULT NULL,
            created_at timestamp NOT NULL DEFAULT current_timestamp(), 
            updated_at timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(), 
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY academic_year_id (academic_year_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
    } catch (Exception $e) {
        // Ignore errors to not break the API if migration fails
    }
}

// Run migrations silently on API boot
acad_run_migrations();
