<?php
/**
 * E-Examination — API Configuration & Auth Helpers
 * Reuses eportal core config, adds examination-specific auth
 */
require_once __DIR__ . '/../../../api/config.php';

/**
 * Auto-ensure E-Examination tables exist in database
 */
function exam_ensure_tables() {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        db()->exec("
            CREATE TABLE IF NOT EXISTS `exam_roles` (
              `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id` INT(11) UNSIGNED NOT NULL,
              `role` ENUM('admin', 'guru', 'proktor') NOT NULL DEFAULT 'guru',
              `status` TINYINT(1) NOT NULL DEFAULT 1,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_exam_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `exam_student_login` (
              `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
              `student_id` INT(11) UNSIGNED NOT NULL,
              `status` ENUM('logged_in', 'mengerjakan', 'selesai', 'logged_out') NOT NULL DEFAULT 'logged_in',
              `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
              `lock_reason` VARCHAR(255) DEFAULT NULL,
              `ujian_id` INT(11) UNSIGNED DEFAULT NULL,
              `sesi_id` INT(11) UNSIGNED DEFAULT NULL,
              `ip_address` VARCHAR(45) DEFAULT NULL,
              `user_agent` TEXT DEFAULT NULL,
              `last_heartbeat` DATETIME DEFAULT NULL,
              `login_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_exam_student` (`student_id`),
              KEY `idx_status_locked` (`status`, `is_locked`),
              KEY `idx_ujian_login` (`ujian_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    } catch (Exception $e) {
        // Table creation fallback handled silently
    }
}

// Ensure tables exist on boot
exam_ensure_tables();

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

        // Resolve examination-specific role from exam_roles table
        $examRole = null;
        try {
            $stmtRole = db()->prepare("SELECT role, status FROM exam_roles WHERE user_id = ?");
            $stmtRole->execute([$session['user_id']]);
            $rRow = $stmtRole->fetch();
            if ($rRow && (int)$rRow['status'] === 1) {
                $examRole = $rRow['role'];
            }
        } catch (PDOException $e) {
            // Table might not exist yet before migration
            $examRole = null;
        }

        $isSuperAdmin = ($session['role'] === 'superadmin');
        $isExamAdmin = $isSuperAdmin || ($examRole === 'admin') || (!$examRole && $session['role'] === 'user');
        $isProktor = $isExamAdmin || ($examRole === 'proktor');
        $isGuru = $isExamAdmin || ($examRole === 'guru') || (!$examRole && $session['role'] === 'guru');

        // Determine primary effective exam role
        $effectiveRole = 'guru';
        if ($isExamAdmin) {
            $effectiveRole = 'admin';
        } elseif ($examRole === 'proktor') {
            $effectiveRole = 'proktor';
        } elseif ($isGuru) {
            $effectiveRole = 'guru';
        }

        $user = [
            'user_id'       => (int)$session['user_id'],
            'username'      => $session['username'],
            'nama_lengkap'  => $session['nama_lengkap'],
            'role'          => $session['role'], // portal role
            'exam_role'     => $effectiveRole,   // examination module role: admin, guru, proktor
            'avatar'        => $session['avatar'],
            'is_admin'      => $isExamAdmin,
            'is_proktor'    => $isProktor,
            'is_guru'       => $isGuru,
        ];

        return $user;
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Require admin access (Full superadmin / admin examination)
 */
function exam_require_admin() {
    $user = exam_auth();
    if (!$user['is_admin']) {
        json_response(403, false, 'Akses ditolak. Hanya admin yang dapat mengakses menu ini.');
    }
    return $user;
}

/**
 * Require proktor access (Admin or Proktor)
 */
function exam_require_proktor() {
    $user = exam_auth();
    if (!$user['is_proktor'] && !$user['is_admin']) {
        json_response(403, false, 'Akses ditolak. Hanya Proktor dan Admin yang dapat mengakses menu ini.');
    }
    return $user;
}

/**
 * Require guru access (Admin or Guru)
 */
function exam_require_guru() {
    $user = exam_auth();
    if (!$user['is_guru'] && !$user['is_admin']) {
        json_response(403, false, 'Akses ditolak. Hanya Guru dan Admin yang dapat mengakses menu ini.');
    }
    return $user;
}

/**
 * Require admin or guru access
 */
function exam_require_admin_or_guru() {
    return exam_require_guru();
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
