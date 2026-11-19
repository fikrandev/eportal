<?php
/**
 * E-Examination — API Configuration & Auth Helpers
 * Reuses eportal core config, adds examination-specific auth
 */
require_once __DIR__ . '/../../../api/config.php';

/**
 * Legacy no-op hook (schema managed centrally in migration_helper.php v26+)
 */
function exam_ensure_tables() {}

/**
 * Authenticate request for E-Examination (Admin/Guru)
 * Uses eportal session token
 */
function exam_auth() {
    $token = get_bearer_token(true);

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
            if ($rRow) {
                if ((int)$rRow['status'] === 0 && $session['role'] !== 'superadmin') {
                    json_response(403, false, 'Akses modul E-Examination Anda sedang dinonaktifkan.');
                }
                if ((int)$rRow['status'] === 1) {
                    $examRole = $rRow['role'];
                }
            }
        } catch (PDOException $e) {
            $examRole = null;
        }

        $isSuperAdmin = ($session['role'] === 'superadmin');
        $hasModulePerm = $isSuperAdmin || portal_has_permission($session, 'module_e-examination') || portal_has_permission($session, '*');
        $isExamAdmin = $isSuperAdmin || ($examRole === 'admin') || (!$examRole && $session['role'] === 'user' && $hasModulePerm);
        $isProktor = $isExamAdmin || ($examRole === 'proktor');
        $isGuru = $isExamAdmin || ($examRole === 'guru') || (!$examRole && $session['role'] === 'guru');

        if (!$isExamAdmin && !$isProktor && !$isGuru) {
            json_response(403, false, 'Anda tidak memiliki akses ke modul E-Examination.');
        }

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
 * Enforce ownership check on exam_ujian for non-admin/non-proktor gurus
 */
function exam_require_ujian_access($ujian_id, $user = null) {
    if (!$user) $user = exam_require_admin_or_guru();
    $stmt = db()->prepare("SELECT id, created_by FROM exam_ujian WHERE id = ?");
    $stmt->execute([(int)$ujian_id]);
    $ujian = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ujian) {
        json_response(404, false, 'Ujian tidak ditemukan.');
    }
    if (!$user['is_admin'] && !$user['is_proktor'] && (int)$ujian['created_by'] !== (int)$user['user_id']) {
        json_response(403, false, 'Akses ditolak. Anda bukan pembuat ujian ini.');
    }
    return $user;
}

/**
 * Enforce ownership check on exam_sesi for non-admin/non-proktor gurus
 */
function exam_require_sesi_access($session_id, $user = null) {
    if (!$user) $user = exam_require_admin_or_guru();
    $stmt = db()->prepare("SELECT s.id, u.created_by FROM exam_sesi s JOIN exam_ujian u ON s.ujian_id = u.id WHERE s.id = ?");
    $stmt->execute([(int)$session_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        json_response(404, false, 'Sesi ujian tidak ditemukan.');
    }
    if (!$user['is_admin'] && !$user['is_proktor'] && (int)$row['created_by'] !== (int)$user['user_id']) {
        json_response(403, false, 'Akses ditolak. Anda tidak memiliki akses ke sesi ujian ini.');
    }
    return $user;
}

/**
 * Enforce ownership check on exam_bank_soal for non-admin gurus
 */
function exam_require_bank_access($bank_id, $user = null) {
    if (!$user) $user = exam_require_admin_or_guru();
    $stmt = db()->prepare("SELECT id, created_by FROM exam_bank_soal WHERE id = ?");
    $stmt->execute([(int)$bank_id]);
    $bank = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$bank) {
        json_response(404, false, 'Bank soal tidak ditemukan.');
    }
    if (!$user['is_admin'] && (int)$bank['created_by'] !== (int)$user['user_id']) {
        json_response(403, false, 'Akses ditolak. Anda bukan pemilik bank soal ini.');
    }
    return $user;
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

    $sql = "
        SELECT kelas, COUNT(*) as total_siswa
        FROM students
        WHERE status = 1 
          AND (status_siswa = 'Aktif' OR status_siswa IS NULL OR status_siswa = '')
          AND kelas <> ''
    ";
    $params = [];
    if ($yearId > 0) {
        $sql .= " AND academic_year_id = ?";
        $params[] = $yearId;
    }
    $sql .= " GROUP BY kelas ORDER BY kelas ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($res) && $yearId > 0) {
        // Fallback if active year not yet populated
        $stmtFallback = db()->query("
            SELECT kelas, COUNT(*) as total_siswa
            FROM students
            WHERE status = 1 
              AND (status_siswa = 'Aktif' OR status_siswa IS NULL OR status_siswa = '')
              AND kelas <> ''
            GROUP BY kelas
            ORDER BY kelas ASC
        ");
        $res = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
    }

    return $res;
}
