<?php
/**
 * E-Curriculum — Manajemen Pengguna & Hak Akses
 */
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
acad_require_admin($user); // Only admin_kurikulum or superadmin

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

switch ($action) {
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
