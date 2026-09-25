<?php
/**
 * E-Portal Roles & Permissions API
 * Manage roles, module access, and admin permissions
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list_roles':
        listRoles();
        break;
    case 'get_role':
        getRole();
        break;
    case 'save_role':
        saveRole();
        break;
    case 'delete_role':
        deleteRole();
        break;
    case 'accounts_list':
        accountsList();
        break;
    case 'assign_account':
        assignAccount();
        break;
    case 'get_permissions_def':
        getPermissionsDef();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List all defined roles with their permissions
 */
function listRoles() {
    $user = auth_check();
    if (!portal_has_permission($user, 'roles_manage')) {
        json_response(403, false, 'Akses ditolak.');
    }

    try {
        $stmt = db()->query("
            SELECT r.*, 
                   (SELECT GROUP_CONCAT(permission_key) FROM portal_role_permissions WHERE role_id = r.id) as permissions,
                   (SELECT COUNT(*) FROM portal_user_roles WHERE role_id = r.id) as user_count
            FROM portal_roles_def r 
            ORDER BY r.is_locked DESC, r.id ASC
        ");
        $roles = $stmt->fetchAll();

        json_response(200, true, 'Data role berhasil dimuat.', $roles);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Get single role detail
 */
function getRole() {
    $user = auth_check();
    if (!portal_has_permission($user, 'roles_manage')) {
        json_response(403, false, 'Akses ditolak.');
    }

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_response(400, false, 'ID role tidak valid.');
    }

    try {
        $stmt = db()->prepare("SELECT * FROM portal_roles_def WHERE id = ?");
        $stmt->execute([$id]);
        $role = $stmt->fetch();

        if (!$role) {
            json_response(404, false, 'Role tidak ditemukan.');
        }

        $stmtP = db()->prepare("SELECT permission_key FROM portal_role_permissions WHERE role_id = ?");
        $stmtP->execute([$id]);
        $role['permissions'] = $stmtP->fetchAll(PDO::FETCH_COLUMN) ?: [];

        json_response(200, true, 'Detail role.', $role);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Save / Update Role
 */
function saveRole() {
    $user = auth_check();
    if (!portal_has_permission($user, 'roles_manage')) {
        json_response(403, false, 'Akses ditolak.');
    }

    $d = get_input();
    $id = (int)($d['id'] ?? 0);
    $nama = sanitize($d['nama'] ?? '');
    $deskripsi = sanitize($d['deskripsi'] ?? '');
    $permissions = $d['permissions'] ?? [];

    if (!is_array($permissions)) {
        $permissions = explode(',', (string)$permissions);
    }
    $permissions = array_values(array_filter(array_map('trim', $permissions)));

    if (empty($nama)) {
        json_response(400, false, 'Nama role wajib diisi.');
    }

    try {
        db()->beginTransaction();

        if ($id > 0) {
            $stmtChk = db()->prepare("SELECT is_locked FROM portal_roles_def WHERE id = ?");
            $stmtChk->execute([$id]);
            $isLocked = (int)$stmtChk->fetchColumn();

            if ($isLocked) {
                // If locked, update description only (or keep locked name)
                $stmt = db()->prepare("UPDATE portal_roles_def SET deskripsi = ? WHERE id = ?");
                $stmt->execute([$deskripsi, $id]);
            } else {
                $stmt = db()->prepare("UPDATE portal_roles_def SET nama = ?, deskripsi = ? WHERE id = ?");
                $stmt->execute([$nama, $deskripsi, $id]);
            }
        } else {
            $stmt = db()->prepare("INSERT INTO portal_roles_def (nama, deskripsi, is_locked) VALUES (?, ?, 0)");
            $stmt->execute([$nama, $deskripsi]);
            $id = (int)db()->lastInsertId();
        }

        // Sync permissions
        db()->prepare("DELETE FROM portal_role_permissions WHERE role_id = ?")->execute([$id]);

        if (!empty($permissions)) {
            $stmtP = db()->prepare("INSERT IGNORE INTO portal_role_permissions (role_id, permission_key) VALUES (?, ?)");
            foreach ($permissions as $p) {
                $pClean = sanitize($p);
                if ($pClean) {
                    $stmtP->execute([$id, $pClean]);
                }
            }
        }

        db()->commit();
        json_response(200, true, 'Role dan hak akses berhasil disimpan.', ['id' => $id]);
    } catch (Exception $e) {
        if (db()->inTransaction()) db()->rollBack();
        json_response(500, false, 'Gagal menyimpan role: ' . $e->getMessage());
    }
}

/**
 * Delete custom role
 */
function deleteRole() {
    $user = auth_check();
    if (!portal_has_permission($user, 'roles_manage')) {
        json_response(403, false, 'Akses ditolak.');
    }

    $d = get_input();
    $id = (int)($d['id'] ?? ($_GET['id'] ?? 0));

    if ($id <= 0) {
        json_response(400, false, 'ID role tidak valid.');
    }

    try {
        $stmtChk = db()->prepare("SELECT is_locked, nama FROM portal_roles_def WHERE id = ?");
        $stmtChk->execute([$id]);
        $role = $stmtChk->fetch();

        if (!$role) {
            json_response(404, false, 'Role tidak ditemukan.');
        }

        if (!empty($role['is_locked'])) {
            json_response(400, false, 'Role sistem terlindungi dan tidak dapat dihapus.');
        }

        db()->prepare("DELETE FROM portal_roles_def WHERE id = ?")->execute([$id]);

        json_response(200, true, 'Role "' . $role['nama'] . '" berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * List accounts with their assigned portal role
 */
function accountsList() {
    $user = auth_check();
    if (!portal_has_permission($user, 'roles_manage')) {
        json_response(403, false, 'Akses ditolak.');
    }

    try {
        $stmt = db()->query("
            SELECT u.id, u.username, u.nama_lengkap, u.nik, u.email, u.role as system_role, u.tupoksi,
                   pur.role_id as portal_role_id, prd.nama as portal_role_nama,
                   (SELECT COUNT(*) FROM portal_role_permissions WHERE role_id = pur.role_id) as perms_count
            FROM users u
            LEFT JOIN portal_user_roles pur ON pur.user_id = u.id
            LEFT JOIN portal_roles_def prd ON prd.id = pur.role_id
            WHERE u.status = 1
            ORDER BY (pur.role_id IS NOT NULL) DESC, u.nama_lengkap ASC
        ");
        $accounts = $stmt->fetchAll();

        json_response(200, true, 'Data akun berhasil dimuat.', $accounts);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Assign role to an account (or revoke)
 */
function assignAccount() {
    $user = auth_check();
    if (!portal_has_permission($user, 'roles_manage')) {
        json_response(403, false, 'Akses ditolak.');
    }

    $d = get_input();
    $userId = (int)($d['user_id'] ?? 0);
    $roleId = (int)($d['role_id'] ?? 0);

    if ($userId <= 0) {
        json_response(400, false, 'User tidak valid.');
    }

    try {
        if ($roleId <= 0) {
            db()->prepare("DELETE FROM portal_user_roles WHERE user_id = ?")->execute([$userId]);
            json_response(200, true, 'Role akses pengguna berhasil dicabut.');
        }

        // Verify role exists
        $stmtChk = db()->prepare("SELECT nama FROM portal_roles_def WHERE id = ?");
        $stmtChk->execute([$roleId]);
        $roleName = $stmtChk->fetchColumn();

        if (!$roleName) {
            json_response(404, false, 'Role tujuan tidak ditemukan.');
        }

        $stmt = db()->prepare("
            INSERT INTO portal_user_roles (user_id, role_id)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)
        ");
        $stmt->execute([$userId, $roleId]);

        json_response(200, true, 'Akses pengguna berhasil diperbarui ke role "' . $roleName . '".');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Return available permission definitions (Modules + Admin Features)
 */
function getPermissionsDef() {
    $user = auth_check();
    if (!portal_has_permission($user, 'roles_manage')) {
        json_response(403, false, 'Akses ditolak.');
    }

    try {
        // 1. Dynamic Modules from modules table
        $stmtMod = db()->query("SELECT id, nama_modul, slug, color, url_path FROM modules WHERE status = 1 ORDER BY urutan ASC, nama_modul ASC");
        $modules = $stmtMod->fetchAll();

        $modulePerms = [];
        foreach ($modules as $m) {
            $modulePerms[] = [
                'key' => 'module_' . $m['slug'],
                'label' => $m['nama_modul'],
                'color' => $m['color'] ?: '#1565C0',
                'slug' => $m['slug'],
                'url' => $m['url_path']
            ];
        }

        // 2. Admin Panel Features
        $adminPerms = [
            ['key' => 'admin_panel', 'label' => 'Akses Masuk ke Admin Panel', 'desc' => 'Dapat membuka menu Admin Panel E-Portal'],
            ['key' => 'admin_dashboard', 'label' => 'Lihat Dashboard Ringkasan Admin', 'desc' => 'Statistik dan ringkasan portal'],
            ['key' => 'academic_years_manage', 'label' => 'Kelola Tahun Ajaran', 'desc' => 'Tambah, ubah, dan aktifkan tahun ajaran'],
            ['key' => 'students_manage', 'label' => 'Kelola Data Siswa', 'desc' => 'CRUD data siswa, naik kelas, import'],
            ['key' => 'siswa_lulus_manage', 'label' => 'Kelola Siswa Lulus (Alumni)', 'desc' => 'Kelola data siswa yang telah lulus'],
            ['key' => 'foto_siswa_manage', 'label' => 'Kelola Foto Siswa', 'desc' => 'Upload dan sinkronisasi foto siswa'],
            ['key' => 'gurus_manage', 'label' => 'Kelola Data Guru', 'desc' => 'Data guru, kode guru, dan status'],
            ['key' => 'referensi_manage', 'label' => 'Kelola Data Referensi', 'desc' => 'Data kelas, mapel, dan referensi akademik'],
            ['key' => 'users_manage', 'label' => 'Kelola User & Akun', 'desc' => 'Manajemen user login dan password'],
            ['key' => 'modules_manage', 'label' => 'Kelola Modul Portal', 'desc' => 'Tambah modul baru dan atur urutan modul'],
            ['key' => 'roles_manage', 'label' => 'Kelola Role & Akses Modul', 'desc' => 'Atur hak akses akun dan modul (halaman ini)'],
            ['key' => 'settings_manage', 'label' => 'Ubah Pengaturan Sekolah', 'desc' => 'Identitas sekolah, logo, dan pengaturan PWA'],
            ['key' => 'reset_data_manage', 'label' => 'Akses Reset Data', 'desc' => 'Akses fitur reset data sistem']
        ];

        json_response(200, true, 'Daftar izin hak akses.', [
            'modules' => $modulePerms,
            'admin' => $adminPerms
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
