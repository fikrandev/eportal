<?php
/**
 * E-Sarpras Authentication Helper
 * Centralized logic for role-based access control and scoping.
 */

function sp_permission_aliases() {
    return [
        'dashboard_view' => ['dashboard_view'],
        'tanah_manage' => ['tanah_manage'],
        'bangunan_manage' => ['bangunan_manage'],
        'ruang_manage' => ['ruang_manage'],
        'sarpras_manage' => ['sarpras_manage', 'sarana_manage', 'ahp_manage'],
        'peminjaman_manage' => ['peminjaman_manage'],
        'perbaikan_manage' => ['perbaikan_manage'],
        'penghapusan_manage' => ['penghapusan_manage'],
        'report_view' => ['report_view', 'laporan_view'],
        'referensi_manage' => ['referensi_manage'],
        'roles_manage' => ['roles_manage'],
        'settings_manage' => ['settings_manage'],
    ];
}

function sp_all_permissions() {
    return array_keys(sp_permission_aliases());
}

function sp_normalize_permissions($permissions) {
    $normalized = [];
    $aliases = sp_permission_aliases();

    foreach ((array) $permissions as $permission) {
        $permission = trim((string) $permission);
        if ($permission === '') continue;

        $matched = false;
        foreach ($aliases as $canonical => $knownKeys) {
            if (in_array($permission, $knownKeys, true)) {
                $normalized[] = $canonical;
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            $normalized[] = $permission;
        }
    }

    $normalized = array_values(array_unique($normalized));
    if (!in_array('dashboard_view', $normalized, true)) {
        $normalized[] = 'dashboard_view';
    }

    return $normalized;
}

function sp_default_permissions_for_role($sarprasRole) {
    switch ((string) $sarprasRole) {
        case 'admin_sarpras':
            return sp_all_permissions();
        case 'operator_sarpras':
            return [
                'dashboard_view',
                'tanah_manage',
                'bangunan_manage',
                'ruang_manage',
                'sarpras_manage',
                'peminjaman_manage',
                'perbaikan_manage',
                'penghapusan_manage',
                'report_view',
            ];
        default:
            return ['dashboard_view'];
    }
}

function sp_extract_token() {
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
    if ($token === '' && isset($_GET['token'])) {
        $token = (string) $_GET['token'];
    }
    return trim($token);
}

function sp_resolve_user_by_token($token, $jsonOnFail = true) {
    $token = trim((string) $token);
    if ($token === '') {
        if ($jsonOnFail) json_response(401, false, 'Token required');
        return null;
    }

    $stmt = db()->prepare("SELECT u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar FROM sessions s JOIN users u ON s.user_id=u.id WHERE s.token=? AND s.expired_at>NOW() AND u.status=1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        if ($jsonOnFail) json_response(401, false, 'Session expired or user inactive');
        return null;
    }

    $user['sarpras_role'] = 'viewer_sarpras';
    $user['permissions'] = ['dashboard_view'];
    $user['scoped_ruang_ids'] = [];
    $user['custom_role_id'] = null;
    $user['custom_role_name'] = '';

    if ($user['role'] === 'superadmin') {
        $user['sarpras_role'] = 'admin_sarpras';
        $user['custom_role_name'] = 'Superadmin';
        $user['permissions'] = sp_all_permissions();
        return $user;
    }

    $stmtRole = db()->prepare("
        SELECT sr.role, sr.custom_role_id, rd.nama as role_name
        FROM sarpras_roles sr
        LEFT JOIN sarpras_roles_def rd ON sr.custom_role_id = rd.id
        WHERE sr.user_id = ?
        LIMIT 1
    ");
    $stmtRole->execute([$user['user_id']]);
    $roleData = $stmtRole->fetch();

    if (!$roleData) {
        if ($jsonOnFail) json_response(403, false, 'Akses ditolak. Anda belum terdaftar dalam Akses Modul E-Sarpras.');
        return null;
    }

    $user['sarpras_role'] = $roleData['role'] ?: 'viewer_sarpras';
    $user['custom_role_id'] = $roleData['custom_role_id'] ? (int) $roleData['custom_role_id'] : null;
    $user['custom_role_name'] = trim((string) ($roleData['role_name'] ?? ''));

    if ($user['custom_role_id']) {
        $stmtPerm = db()->prepare("SELECT permission_key FROM sarpras_role_permissions WHERE role_id = ?");
        $stmtPerm->execute([$user['custom_role_id']]);
        $user['permissions'] = sp_normalize_permissions($stmtPerm->fetchAll(PDO::FETCH_COLUMN));

        if ($user['custom_role_name'] === 'Penanggung Jawab Ruangan') {
            // Find PJ by user_id OR nip (matching username)
            $pjStmt = db()->prepare("SELECT id FROM sarpras_pj WHERE user_id = ? OR nip = ? LIMIT 1");
            $pjStmt->execute([$user['user_id'], $user['username']]);
            $pjId = $pjStmt->fetchColumn();

            if ($pjId) {
                $ruangStmt = db()->prepare("SELECT id FROM ruang WHERE pj_id = ?");
                $ruangStmt->execute([$pjId]);
                $user['scoped_ruang_ids'] = $ruangStmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }
    } else {
        $user['permissions'] = sp_default_permissions_for_role($user['sarpras_role']);
    }

    return $user;
}

function sp_auth() {
    return sp_resolve_user_by_token(sp_extract_token(), true);
}

function sp_has($user, $permission) {
    $permission = trim((string) $permission);
    if ($permission === '') return false;

    $owned = sp_normalize_permissions($user['permissions'] ?? []);
    $aliases = sp_permission_aliases();
    $candidates = $aliases[$permission] ?? [$permission];

    foreach ($candidates as $candidate) {
        $canonical = sp_normalize_permissions([$candidate]);
        if (array_intersect($owned, $canonical)) {
            return true;
        }
    }

    return false;
}

function sp_has_any($user, $permissions) {
    foreach ((array) $permissions as $permission) {
        if (sp_has($user, $permission)) return true;
    }
    return false;
}

function sp_can_edit($user, $permissions = []) {
    if (empty($permissions)) {
        $permissions = [
            'tanah_manage',
            'bangunan_manage',
            'ruang_manage',
            'sarpras_manage',
            'peminjaman_manage',
            'perbaikan_manage',
            'penghapusan_manage',
            'referensi_manage',
            'roles_manage',
            'settings_manage',
        ];
    }
    return sp_has_any($user, $permissions);
}

function sp_require_any($user, $permissions, $message = 'Akses ditolak') {
    if (!sp_has_any($user, $permissions)) {
        json_response(403, false, $message);
    }
}

function sp_scope_where($user, $table_alias = 's', $column = 'ruang_id', $allow_null = false) {
    if (!empty($user['scoped_ruang_ids'])) {
        $ids = implode(',', array_map('intval', $user['scoped_ruang_ids']));
        if ($allow_null) {
            return " AND ($table_alias.$column IN ($ids) OR $table_alias.$column IS NULL) ";
        }
        return " AND $table_alias.$column IN ($ids) ";
    }
    return "";
}
