<?php
/**
 * E-Xam Card helper utilities.
 */

function xam_auth()
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

    if ($token === '' && isset($_GET['token'])) {
        $token = (string) $_GET['token'];
    }

    if ($token === '') {
        json_response(401, false, 'Token required');
    }

    $stmt = db()->prepare("
        SELECT u.id as user_id, u.username, u.nama_lengkap, u.role, u.avatar, u.status
        FROM sessions s
        JOIN users u ON s.user_id = u.id
        WHERE s.token = ? AND s.expired_at > NOW() AND u.status = 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) {
        json_response(401, false, 'Session expired or invalid.');
    }

    $user['is_admin'] = in_array(strtolower($user['role']), ['superadmin', 'user'], true);
    $user['is_teacher'] = (strtolower($user['role']) === 'guru');
    $user['managed_class'] = null;
    $user['has_access'] = $user['is_admin'];

    if ($user['is_teacher']) {
        $stmt = db()->prepare("SELECT kelas FROM xam_teacher_access WHERE user_id = ? AND status = 1");
        $stmt->execute([$user['user_id']]);
        $access = $stmt->fetch();
        if ($access) {
            $user['managed_class'] = $access['kelas'];
            $user['has_access'] = true;
        }
    }

    $user['can_manage_xam'] = $user['has_access']; // Alias for legacy support
    return $user;
}

function xam_require_manage()
{
    $user = xam_auth();
    if (!$user['has_access']) {
        json_response(403, false, 'Akses ditolak. Anda tidak memiliki izin untuk mengakses modul ini.');
    }
    return $user;
}

function xam_active_year()
{
    $active = get_active_academic_year();
    $id = (int) ($active['id'] ?? 0);
    if ($id <= 0) {
        json_response(400, false, 'Tahun ajaran aktif belum diatur di admin E-Portal.');
    }
    return $active;
}

function xam_active_year_id()
{
    $active = xam_active_year();
    return (int) $active['id'];
}

function xam_exam_setting($examId)
{
    $stmt = db()->prepare("\n        SELECT s.*, u.nama_lengkap as headmaster_user_name\n        FROM xam_exam_settings s\n        LEFT JOIN users u ON u.id = s.headmaster_user_id\n        WHERE s.exam_id = ?\n    ");
    $stmt->execute([(int) $examId]);
    $setting = $stmt->fetch();
    return $setting ?: [];
}

function xam_exam_classes($examId)
{
    $stmt = db()->prepare("SELECT kelas FROM xam_exam_classes WHERE exam_id = ? ORDER BY kelas ASC");
    $stmt->execute([(int) $examId]);
    return array_values(array_unique(array_filter(array_map('trim', $stmt->fetchAll(PDO::FETCH_COLUMN)))));
}

function xam_all_classes($yearId)
{
    $stmt = db()->prepare("\n        SELECT kelas, COUNT(*) as total_siswa\n        FROM students\n        WHERE academic_year_id = ? AND status = 1 AND kelas <> ''\n        GROUP BY kelas\n        ORDER BY kelas ASC\n    ");
    $stmt->execute([(int) $yearId]);
    return $stmt->fetchAll();
}

function xam_students_by_class($yearId, $kelas)
{
    $stmt = db()->prepare("\n        SELECT id, nis, nisn, nama, kelas, no_urut\n        FROM students\n        WHERE academic_year_id = ? AND status = 1 AND kelas = ?\n        ORDER BY no_urut ASC, nama ASC\n    ");
    $stmt->execute([(int) $yearId, $kelas]);
    return $stmt->fetchAll();
}

function xam_roman_month($month)
{
    $map = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    $month = (int) $month;
    return $map[$month] ?? 'I';
}

function xam_compose_letter_number($manualNo, $letterCode, $letterDate)
{
    $manualNo = trim((string) $manualNo);
    $letterCode = trim((string) $letterCode);
    if ($manualNo === '' || $letterCode === '') {
        return '';
    }

    $ts = strtotime((string) $letterDate);
    if (!$ts) {
        $ts = time();
    }

    return $manualNo . '/' . $letterCode . '/' . xam_roman_month(date('n', $ts)) . '/' . date('Y', $ts);
}

function xam_default_username($examId, $student)
{
    return (string) random_int(100000, 999999);
}

function xam_default_password($student)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, 4);
}

function xam_h($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function xam_indo_date($date)
{
    if (!$date) return '-';
    $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts = strtotime((string) $date);
    if (!$ts) return '-';
    return date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/**
 * Generate/Verify a security signature for card verification.
 */
function xam_verify_signature($studentId, $examId, $letterNumber)
{
    $secret = DB_NAME . '|' . DB_USER . '|' . DB_PASS;
    return substr(hash_hmac('sha256', (int)$studentId . '|' . (int)$examId . '|' . (string)$letterNumber, $secret), 0, 16);
}

function absoluteBaseUrl()
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . $host . BASE_URL;
}
