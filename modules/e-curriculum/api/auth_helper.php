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

        // Resolve acad_role & custom permissions
        $user['acad_role'] = '';
        $user['custom_role_nama'] = '';
        $user['permissions'] = [];

        if ($user['role'] === 'superadmin') {
            $user['acad_role'] = 'admin_kurikulum';
            $user['custom_role_nama'] = 'Super Admin';
            $user['permissions'] = ['dashboard_view','jadwal_manage','jurnal_manage','absensi_manage','absensi_guru_manage','piket_manage','ketidakhadiran_manage','buku_penghubung_manage','dokumen_manage','laporan_view','roles_manage'];
        } else {
            // Check acad_roles joined with acad_roles_def
            try {
                $stmtRole = db()->prepare("
                    SELECT ar.role, ar.custom_role_id, rd.nama as role_nama,
                           (SELECT GROUP_CONCAT(permission_key) FROM acad_role_permissions WHERE role_id = ar.custom_role_id) as permissions
                    FROM acad_roles ar
                    LEFT JOIN acad_roles_def rd ON ar.custom_role_id = rd.id
                    WHERE ar.user_id = ?
                    LIMIT 1
                ");
                $stmtRole->execute([$user['user_id']]);
                $roleData = $stmtRole->fetch();

                if ($roleData) {
                    $user['acad_role'] = $roleData['role'] ?: 'operator_kurikulum';
                    $user['custom_role_nama'] = $roleData['role_nama'] ?? '';
                    $rawPerms = (string)($roleData['permissions'] ?? '');
                    $user['permissions'] = array_values(array_filter(array_map('trim', explode(',', $rawPerms))));
                } else {
                    // Fallback legacy acad_users
                    $stmtLegacy = db()->prepare("SELECT role FROM acad_users WHERE user_id = ? LIMIT 1");
                    $stmtLegacy->execute([$user['user_id']]);
                    $acadRole = $stmtLegacy->fetchColumn();
                    if ($acadRole) {
                        $user['acad_role'] = $acadRole;
                        if ($acadRole === 'admin_kurikulum') {
                            $user['custom_role_nama'] = 'Admin Kurikulum';
                            $user['permissions'] = ['dashboard_view','jadwal_manage','jurnal_manage','absensi_manage','absensi_guru_manage','piket_manage','ketidakhadiran_manage','buku_penghubung_manage','dokumen_manage','laporan_view','roles_manage'];
                        } else {
                            $user['custom_role_nama'] = 'Operator Kurikulum';
                            $user['permissions'] = ['dashboard_view','jurnal_manage','absensi_manage','absensi_guru_manage','piket_manage','ketidakhadiran_manage','buku_penghubung_manage','laporan_view'];
                        }
                    }
                }
            } catch (Exception $ex) {}
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
 * Check if user has a specific E-Curriculum permission
 */
function acad_can($user, $permission) {
    if (!$user) return false;
    if (($user['role'] ?? '') === 'superadmin' || ($user['acad_role'] ?? '') === 'admin_kurikulum') {
        return true;
    }
    $perms = $user['permissions'] ?? [];
    if (in_array('*', $perms, true) || in_array($permission, $perms, true)) {
        return true;
    }
    if ($permission === 'kurikulum_manage' && in_array('roles_manage', $perms, true)) {
        return true;
    }
    return false;
}

/**
 * Enforce admin role permission check (strictly superadmin, roles_manage, or admin_kurikulum)
 */
function acad_require_admin($user) {
    $isSuperAdmin = ($user['role'] ?? '') === 'superadmin';
    $hasRoleManage = in_array('roles_manage', $user['permissions'] ?? [], true);
    $isAdminKurikulum = ($user['acad_role'] ?? '') === 'admin_kurikulum';
    if (!$isSuperAdmin && !$hasRoleManage && !$isAdminKurikulum) {
        json_response(403, false, 'Akses ditolak. Anda tidak memiliki hak akses admin untuk modul ini.');
    }
}

/**
 * Legacy no-op hook (all E-Curriculum tables/columns are managed in migration_helper.php v26+)
 */
function acad_run_migrations() {}
