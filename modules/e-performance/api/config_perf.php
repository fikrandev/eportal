<?php
/**
 * E-Performance — API Configuration & Auth Helpers
 * Reuses eportal core config, adds performance-specific auth
 */
require_once __DIR__ . '/../../../api/config.php';

// Performance module constants
define('PERF_SESSION_DURATION', 86400); // 24 hours

/**
 * Map jenis_ptk / tupoksi & jabatan into valid perf_users.role slug.
 * Ensures Wakil Kepala Sekolah, Kepala TU, Kepala Perpustakaan, Kepala Lab
 * are NEVER mapped to 'kepsek'.
 */
function perf_map_ptk_to_user_role($jenisPtk, $jabatan = '') {
    $t = strtolower(trim((string)$jenisPtk));
    $j = strtolower(trim((string)$jabatan));

    if (strpos($t, 'wakil') !== false || strpos($t, 'waka') !== false || strpos($j, 'wakil') !== false || strpos($j, 'waka') !== false) {
        return 'guru';
    }
    if (in_array($t, ['kepala sekolah', 'plt. kepala sekolah', 'plt kepala sekolah', 'kepsek'], true)) {
        return 'kepsek';
    }
    if (strpos($t, 'tu') !== false || strpos($t, 'tata') !== false || strpos($t, 'administrasi') !== false) {
        return 'tu';
    }
    if (strpos($t, 'it') !== false || strpos($t, 'teknologi') !== false) {
        return 'it';
    }
    if (strpos($t, 'pustaka') !== false || strpos($t, 'perpus') !== false) {
        return 'pustakawan';
    }
    if (in_array($t, ['guru', 'tu', 'it', 'pustakawan', 'siswa'], true)) {
        return $t;
    }
    return 'guru';
}

/**
 * Strict check whether the authenticated user is truly Kepala Sekolah (and NOT Wakil Kepala Sekolah / Kepala TU / etc.).
 */
function perf_is_kepsek_rater(array $auth, $actualTupoksi = '') {
    $tup = strtolower(trim($actualTupoksi !== '' ? (string)$actualTupoksi : (string)($auth['tupoksi'] ?? '')));
    if ($tup !== '') {
        if (strpos($tup, 'wakil') !== false || strpos($tup, 'waka') !== false) {
            return false;
        }
        return in_array($tup, ['kepala sekolah', 'plt. kepala sekolah', 'plt kepala sekolah', 'kepsek'], true);
    }
    return empty($auth['ptk_id']) && strtolower(trim((string)($auth['role'] ?? ''))) === 'kepsek';
}

/**
 * Authenticate request for E-Performance
 * Supports both eportal token (admin/superadmin) and perf_sessions token
 * Returns: ['type' => 'eportal'|'perf', 'user' => [...], 'role' => '...']
 */
function perf_auth_check() {
    $token = get_bearer_token(true);

    if (empty($token)) {
        json_response(401, false, 'Token tidak ditemukan.');
    }

    // Try eportal session first
    try {
        $stmt = db()->prepare("
            SELECT s.*, u.id as user_id, u.username, u.nama_lengkap, u.role, u.tupoksi, u.jabatan as u_jabatan, u.avatar, u.status,
                   pu.id as perf_user_id, pu.role as pu_role, pu.perf_ptk_id,
                   COALESCE(r_pu.permissions, r_u.permissions) as permissions,
                   pp.id as resolved_ptk_id, pp.jenis_ptk as pp_jenis_ptk, pp.jabatan as pp_jabatan
            FROM sessions s 
            JOIN users u ON s.user_id = u.id 
            LEFT JOIN perf_users pu ON pu.username COLLATE utf8mb4_unicode_ci = u.username COLLATE utf8mb4_unicode_ci AND pu.status = 1
            LEFT JOIN perf_roles r_pu ON pu.role COLLATE utf8mb4_unicode_ci = r_pu.role_slug COLLATE utf8mb4_unicode_ci
            LEFT JOIN perf_roles r_u ON u.role COLLATE utf8mb4_unicode_ci = r_u.role_slug COLLATE utf8mb4_unicode_ci
            LEFT JOIN perf_ptk pp ON pp.niy COLLATE utf8mb4_unicode_ci = u.username COLLATE utf8mb4_unicode_ci AND pp.status = 1
            WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if ($session) {
            $permissions = json_decode($session['permissions'] ?: '[]', true);
            if (!is_array($permissions)) $permissions = [];
            $ptkId = !empty($session['perf_ptk_id']) ? (int)$session['perf_ptk_id'] : (!empty($session['resolved_ptk_id']) ? (int)$session['resolved_ptk_id'] : null);
            $resolvedTupoksi = !empty($session['tupoksi']) ? $session['tupoksi'] : ($session['pp_jenis_ptk'] ?? '');
            $resolvedJabatan = !empty($session['u_jabatan']) ? $session['u_jabatan'] : ($session['pp_jabatan'] ?? '');

            $effectiveRole = !empty($session['pu_role']) ? $session['pu_role'] : $session['role'];
            if ($effectiveRole === 'kepsek' && !perf_is_kepsek_rater(['tupoksi' => $resolvedTupoksi, 'ptk_id' => $ptkId, 'role' => $effectiveRole])) {
                $effectiveRole = perf_map_ptk_to_user_role($resolvedTupoksi ?: 'guru', $resolvedJabatan);
            }
            $perfRole = $effectiveRole;

            if ($session['role'] === 'superadmin' || $effectiveRole === 'admin' || in_array('akses_modul', $permissions, true)) {
                $perfRole = 'admin';
            } elseif ($session['role'] === 'user' && empty($session['pu_role'])) {
                if (portal_has_permission($session, 'module_e-performance') || portal_has_permission($session, '*')) {
                    $perfRole = 'admin';
                } else {
                    json_response(403, false, 'Anda tidak memiliki izin akses modul E-Performance.');
                }
            }

            return [
                'type' => 'eportal',
                'user_id' => $session['user_id'],
                'perf_user_id' => !empty($session['perf_user_id']) ? (int)$session['perf_user_id'] : null,
                'ptk_id' => $ptkId,
                'username' => $session['username'],
                'nama_lengkap' => $session['nama_lengkap'],
                'role' => $perfRole,
                'tupoksi' => $resolvedTupoksi,
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
                   pu.perf_ptk_id, pu.status, pr.permissions,
                   pp.jenis_ptk as pp_jenis_ptk, pp.jabatan as pp_jabatan
            FROM perf_sessions ps
            JOIN perf_users pu ON ps.perf_user_id = pu.id
            LEFT JOIN perf_roles pr ON pu.role = pr.role_slug
            LEFT JOIN perf_ptk pp ON pu.perf_ptk_id = pp.id
            WHERE ps.token = ? AND ps.expired_at > NOW() AND pu.status = 1
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch();

        if ($session) {
            $resolvedTupoksi = $session['pp_jenis_ptk'] ?? '';
            $resolvedRole = $session['role'];
            if ($resolvedRole === 'kepsek' && !perf_is_kepsek_rater(['tupoksi' => $resolvedTupoksi, 'ptk_id' => $session['perf_ptk_id'], 'role' => $resolvedRole])) {
                $resolvedRole = perf_map_ptk_to_user_role($resolvedTupoksi ?: 'guru', $session['pp_jabatan'] ?? '');
            }

            return [
                'type' => 'perf',
                'user_id' => $session['perf_user_id'],
                'username' => $session['username'],
                'nama_lengkap' => $session['nama_lengkap'],
                'role' => $resolvedRole,
                'tupoksi' => $resolvedTupoksi,
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

