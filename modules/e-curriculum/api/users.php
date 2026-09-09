<?php
/**
 * E-Curriculum — Manajemen Pengguna & Akses Modul (RBAC)
 */
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
acad_require_admin($user); // Only admin_kurikulum or superadmin

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

switch ($action) {
    // ============ NEW RBAC ACTIONS ============
    case 'accounts_list':
        accountsList();
        break;
    case 'list_roles':
        listRolesDef();
        break;
    case 'save_role':
        saveRole();
        break;
    case 'delete_role':
        deleteRole_rbac();
        break;
    case 'assign_account':
        assignAccount();
        break;

    // ============ LEGACY ACTIONS (backward compat) ============
    case 'list':
        listUsers();
        break;
    case 'create':
        createUser();
        break;
    case 'delete':
        deleteUser();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

// ==================== RBAC: ACCOUNTS LIST ====================
function accountsList() {
    try {
        $stmt = db()->query("
            SELECT u.id, u.username, u.nama_lengkap, u.nik, u.role as portal_role,
                   u.tupoksi, u.jabatan, u.mapel,
                   ar.custom_role_id, rd.nama as custom_role_nama
            FROM users u
            LEFT JOIN acad_roles ar ON u.id = ar.user_id
            LEFT JOIN acad_roles_def rd ON ar.custom_role_id = rd.id
            WHERE u.status = 1 AND u.role NOT IN ('siswa', 'orangtua')
            ORDER BY u.nama_lengkap ASC
        ");
        json_response(200, true, 'OK', $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

// ==================== RBAC: LIST ROLES ====================
function listRolesDef() {
    try {
        $stmt = db()->query("
            SELECT r.*, 
                   (SELECT GROUP_CONCAT(permission_key) FROM acad_role_permissions WHERE role_id=r.id) as permissions 
            FROM acad_roles_def r 
            ORDER BY r.is_locked DESC, r.nama ASC
        ");
        json_response(200, true, 'OK', $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

// ==================== RBAC: SAVE ROLE ====================
function saveRole() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');

    $d = get_input();
    $id = (int)($d['id'] ?? 0);
    $nama = trim($d['nama'] ?? '');
    $deskripsi = trim($d['deskripsi'] ?? '');
    $perms = isset($d['permissions']) && is_array($d['permissions']) ? $d['permissions'] : [];

    if (empty($nama)) json_response(400, false, 'Nama role wajib diisi');

    try {
        db()->beginTransaction();

        if ($id > 0) {
            // Verify existing role
            $check = db()->prepare("SELECT id, nama, is_locked FROM acad_roles_def WHERE id=?");
            $check->execute([$id]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                db()->rollBack();
                json_response(404, false, 'Role tidak ditemukan');
            }

            if (!empty($existing['is_locked'])) {
                // Locked role: update description only, retain locked system name
                $stmt = db()->prepare("UPDATE acad_roles_def SET deskripsi=? WHERE id=?");
                $stmt->execute([$deskripsi, $id]);
            } else {
                // Custom role: update name and description
                $stmt = db()->prepare("UPDATE acad_roles_def SET nama=?, deskripsi=? WHERE id=?");
                $stmt->execute([$nama, $deskripsi, $id]);
            }
        } else {
            $stmt = db()->prepare("INSERT INTO acad_roles_def (nama, deskripsi) VALUES (?,?)");
            $stmt->execute([$nama, $deskripsi]);
            $id = (int)db()->lastInsertId();
        }

        // Sync permissions
        db()->prepare("DELETE FROM acad_role_permissions WHERE role_id=?")->execute([$id]);
        if (!empty($perms)) {
            $stmtP = db()->prepare("INSERT INTO acad_role_permissions (role_id, permission_key) VALUES (?,?)");
            foreach ($perms as $p) {
                $pClean = trim((string)$p);
                if ($pClean !== '') {
                    $stmtP->execute([$id, $pClean]);
                }
            }
        }

        db()->commit();
        json_response(200, true, 'Role berhasil disimpan');
    } catch (Exception $e) {
        if (db()->inTransaction()) db()->rollBack();
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

// ==================== RBAC: DELETE ROLE ====================
function deleteRole_rbac() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');

    $d = get_input();
    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) json_response(400, false, 'ID tidak valid');

    try {
        // Check if locked
        $check = db()->prepare("SELECT is_locked FROM acad_roles_def WHERE id=?");
        $check->execute([$id]);
        if ($check->fetchColumn()) {
            json_response(400, false, 'Role sistem tidak dapat dihapus');
        }

        // Remove role assignments using this role
        db()->prepare("DELETE FROM acad_roles WHERE custom_role_id=?")->execute([$id]);
        // Remove permissions
        db()->prepare("DELETE FROM acad_role_permissions WHERE role_id=?")->execute([$id]);
        // Remove role def
        db()->prepare("DELETE FROM acad_roles_def WHERE id=?")->execute([$id]);

        json_response(200, true, 'Role dihapus');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

// ==================== RBAC: ASSIGN ACCOUNT ====================
function assignAccount() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');

    $d = get_input();
    $uid = (int)($d['user_id'] ?? 0);
    $rid = (int)($d['role_id'] ?? 0);

    if ($uid <= 0) json_response(400, false, 'User tidak valid');

    try {
        if ($rid <= 0) {
            // Revoke access
            db()->prepare("DELETE FROM acad_roles WHERE user_id=?")->execute([$uid]);
            // Also clean up legacy acad_users
            db()->prepare("DELETE FROM acad_users WHERE user_id=?")->execute([$uid]);
            json_response(200, true, 'Akses user berhasil dicabut');
        }

        // Get role info to determine base role
        $stmtR = db()->prepare("
            SELECT rd.nama, GROUP_CONCAT(rp.permission_key) as permissions
            FROM acad_roles_def rd
            LEFT JOIN acad_role_permissions rp ON rp.role_id = rd.id
            WHERE rd.id=?
            GROUP BY rd.id, rd.nama
        ");
        $stmtR->execute([$rid]);
        $roleRow = $stmtR->fetch(PDO::FETCH_ASSOC);
        if (!$roleRow) json_response(404, false, 'Role tidak ditemukan');

        $perms = array_filter(explode(',', (string)($roleRow['permissions'] ?? '')));
        $base_role = in_array('roles_manage', $perms) ? 'admin_kurikulum' : 'operator_kurikulum';

        // Upsert acad_roles
        db()->prepare("INSERT INTO acad_roles (user_id, custom_role_id, role) VALUES (?,?,?) ON DUPLICATE KEY UPDATE custom_role_id=?, role=?")
            ->execute([$uid, $rid, $base_role, $rid, $base_role]);

        // Also sync to legacy acad_users for backward compat
        db()->prepare("INSERT INTO acad_users (user_id, role) VALUES (?,?) ON DUPLICATE KEY UPDATE role=?")
            ->execute([$uid, $base_role, $base_role]);

        json_response(200, true, 'Akses user berhasil diperbarui');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

// ==================== LEGACY FUNCTIONS ====================

/**
 * List all users with access to E-Curriculum
 */
function listUsers() {
    try {
        $stmt = db()->query("
            SELECT a.id, a.user_id, a.role as acad_role, u.username, u.nama_lengkap, u.role as global_role, a.created_at
            FROM acad_users a
            JOIN users u ON a.user_id = u.id
            ORDER BY a.created_at DESC
        ");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        json_response(200, true, 'Daftar pengguna kurikulum', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Create or assign a user to E-Curriculum
 */
function createUser() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    
    $input = get_input();
    $username = isset($input['username']) ? trim($input['username']) : '';
    $password = isset($input['password']) ? $input['password'] : '';
    $nama_lengkap = isset($input['nama_lengkap']) ? trim($input['nama_lengkap']) : '';
    $acad_role = isset($input['acad_role']) ? trim($input['acad_role']) : 'operator_kurikulum';
    
    if (empty($username) || empty($password) || empty($nama_lengkap) || empty($acad_role)) {
        json_response(400, false, 'Semua field wajib diisi.');
    }
    
    if (!in_array($acad_role, ['admin_kurikulum', 'operator_kurikulum'])) {
        json_response(400, false, 'Role kurikulum tidak valid.');
    }
    
    try {
        db()->beginTransaction();
        
        // 1. Check if username already exists in `users`
        $stmtCheck = db()->prepare("SELECT id FROM users WHERE username = ?");
        $stmtCheck->execute([$username]);
        $existingUserId = $stmtCheck->fetchColumn();
        
        if ($existingUserId) {
            $userId = $existingUserId;
        } else {
            // Create new user
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmtInsert = db()->prepare("
                INSERT INTO users (username, password, nama_lengkap, role, status)
                VALUES (?, ?, ?, 'user', 1)
            ");
            $stmtInsert->execute([$username, $hashed, $nama_lengkap]);
            $userId = db()->lastInsertId();
        }
        
        // 2. Assign to acad_users
        // Check if already assigned
        $stmtCheckAcad = db()->prepare("SELECT id FROM acad_users WHERE user_id = ?");
        $stmtCheckAcad->execute([$userId]);
        if ($stmtCheckAcad->fetchColumn()) {
            db()->rollBack();
            json_response(400, false, 'Pengguna ini sudah memiliki akses di E-Curriculum.');
        }
        
        $stmtAssign = db()->prepare("INSERT INTO acad_users (user_id, role) VALUES (?, ?)");
        $stmtAssign->execute([$userId, $acad_role]);
        
        db()->commit();
        json_response(201, true, 'Pengguna berhasil ditambahkan.');
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Remove user access from E-Curriculum
 */
function deleteUser() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed');
    
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');
    
    try {
        $stmt = db()->prepare("DELETE FROM acad_users WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Akses pengguna berhasil dicabut.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
