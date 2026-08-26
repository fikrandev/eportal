<?php
/**
 * E-Examination — API Akses Modul & Manajemen Peran (Admin, Guru, Proktor)
 */
require_once __DIR__ . '/config_exam.php';

header('Content-Type: application/json; charset=UTF-8');

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {

        // ==========================================
        // LIST AKSES & PENGGUNA PORTAL
        // ==========================================
        case 'list':
            exam_require_admin();

            // 1. Data akses yang sudah ditugaskan di exam_roles
            $stmtAccess = db()->query("
                SELECT er.id as access_id, er.user_id, er.role as exam_role, er.status as access_status, er.created_at,
                       u.username, u.nama_lengkap, u.nik, u.jabatan, u.tupoksi, u.role as portal_role, u.avatar
                FROM exam_roles er
                JOIN users u ON u.id = er.user_id
                ORDER BY er.created_at DESC, u.nama_lengkap ASC
            ");
            $accesses = $stmtAccess->fetchAll(PDO::FETCH_ASSOC);

            // 2. Data seluruh pengguna aktif dari E-Portal untuk Dropdown Search
            $stmtUsers = db()->query("
                SELECT u.id, u.username, u.nama_lengkap, u.nik, u.jabatan, u.tupoksi, u.role as portal_role,
                       er.role as current_exam_role, er.id as has_access
                FROM users u
                LEFT JOIN exam_roles er ON er.user_id = u.id
                WHERE u.status = 1
                ORDER BY u.nama_lengkap ASC
            ");
            $portalUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

            json_response(200, true, 'Data akses modul berhasil dimuat', [
                'accesses' => $accesses,
                'portal_users' => $portalUsers
            ]);
            break;

        // ==========================================
        // SIMPAN / UPDATE AKSES PENGGUNA
        // ==========================================
        case 'save':
            exam_require_admin();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);

            $data = get_input();
            $userId = (int)($data['user_id'] ?? 0);
            $role = trim($data['role'] ?? 'guru');
            $status = isset($data['status']) ? (int)$data['status'] : 1;

            if ($userId <= 0) {
                throw new Exception('Pilih pengguna dari E-Portal terlebih dahulu.', 400);
            }

            if (!in_array($role, ['admin', 'guru', 'proktor'])) {
                throw new Exception('Tugas / peran tidak valid. Pilih Admin, Guru, atau Proktor.', 400);
            }

            // Verify user exists in users table
            $stmtCheck = db()->prepare("SELECT id, username, nama_lengkap FROM users WHERE id = ? AND status = 1");
            $stmtCheck->execute([$userId]);
            $u = $stmtCheck->fetch();
            if (!$u) {
                throw new Exception('Akun pengguna tidak ditemukan atau tidak aktif di E-Portal.', 404);
            }

            $stmtUpsert = db()->prepare("
                INSERT INTO exam_roles (user_id, role, status)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    role = VALUES(role),
                    status = VALUES(status),
                    updated_at = NOW()
            ");
            $stmtUpsert->execute([$userId, $role, $status]);

            json_response(200, true, "Hak akses untuk {$u['nama_lengkap']} berhasil disimpan sebagai " . strtoupper($role));
            break;

        // ==========================================
        // CABUT / HAPUS AKSES PENGGUNA
        // ==========================================
        case 'delete':
            exam_require_admin();
            if ($method !== 'POST') throw new Exception('Method not allowed', 405);

            $data = get_input();
            $userId = (int)($data['user_id'] ?? 0);
            if ($userId <= 0) throw new Exception('User ID tidak valid', 400);

            // Check if user is superadmin
            $stmtU = db()->prepare("SELECT role, nama_lengkap FROM users WHERE id = ?");
            $stmtU->execute([$userId]);
            $u = $stmtU->fetch();

            if ($u && $u['role'] === 'superadmin') {
                throw new Exception('Akses Superadmin tidak dapat dicabut.', 400);
            }

            $stmtDel = db()->prepare("DELETE FROM exam_roles WHERE user_id = ?");
            $stmtDel->execute([$userId]);

            json_response(200, true, 'Akses modul berhasil dicabut.');
            break;

        default:
            throw new Exception('Action tidak valid', 400);
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    if ($code < 100 || $code >= 600) $code = 500;
    json_response($code, false, $e->getMessage());
}
