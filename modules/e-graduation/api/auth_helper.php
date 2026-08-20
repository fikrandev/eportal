<?php
/**
 * E-Graduation authentication helper.
 */

function grad_auth()
{
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
        json_response(401, false, 'Token required');
    }

    $stmt = db()->prepare("
        SELECT u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar
        FROM sessions s
        JOIN users u ON s.user_id = u.id
        WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        json_response(401, false, 'Session expired or user inactive');
    }

    $user['can_manage_graduation'] = $user['role'] === 'superadmin';
    $user['graduation_role'] = $user['can_manage_graduation'] ? 'admin' : 'viewer';
    $user['scoped_classes'] = [];

    if (!$user['can_manage_graduation']) {
        $stmt = db()->prepare("
            SELECT access_role, kelas
            FROM grad_teacher_access
            WHERE user_id = ? AND status = 1
        ");
        $stmt->execute([$user['user_id']]);
        $access = $stmt->fetchAll();
        if ($access) {
            $user['graduation_role'] = $access[0]['access_role'];
            $user['scoped_classes'] = array_values(array_unique(array_map(function ($row) {
                return $row['kelas'];
            }, $access)));
        } else {
            json_response(403, false, 'Akses ditolak. Anda belum terdaftar dalam Akses Modul E-Graduation.');
        }
    }
    return $user;
}

function grad_require_manage()
{
    $user = grad_auth();
    if (empty($user['can_manage_graduation'])) {
        json_response(403, false, 'Akses ditolak. Hanya admin yang dapat mengubah data.');
    }
    return $user;
}

function grad_active_year_id()
{
    $active = get_active_academic_year();
    $id = (int) ($active['id'] ?? 0);
    if ($id <= 0) {
        json_response(400, false, 'Aktifkan tahun ajaran terlebih dahulu.');
    }
    return $id;
}

function grad_can_access_class($user, $kelas)
{
    if (!empty($user['can_manage_graduation'])) {
        return true;
    }
    return in_array($kelas, $user['scoped_classes'] ?? [], true);
}

function grad_require_class_access($user, $kelas)
{
    if (!grad_can_access_class($user, $kelas)) {
        json_response(403, false, 'Akses kelas ditolak.');
    }
}
