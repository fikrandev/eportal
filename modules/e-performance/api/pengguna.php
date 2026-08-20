<?php
/**
 * E-Performance Pengguna Manual API
 */
require_once __DIR__ . '/config_perf.php';
$auth = perf_require_permission('akses_modul'); // requires 'akses_modul' permission

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list': listUsers(); break;
    case 'save': saveUser(); break;
    case 'delete': deleteUser(); break;
    case 'change_password': changePassword(); break;
    default: json_response(400, false, 'Action tidak valid.');
}

function listUsers() {
    $stmt = db()->query("
        SELECT u.id, u.username, u.nama_lengkap, u.role, u.perf_ptk_id, r.role_name,
               (SELECT MAX(created_at) FROM perf_sessions WHERE perf_user_id = u.id) as last_login
        FROM perf_users u
        LEFT JOIN perf_roles r ON u.role = r.role_slug
        WHERE u.perf_ptk_id IS NULL
        ORDER BY u.id DESC
    ");
    $data = $stmt->fetchAll();
    json_response(200, true, 'Data pengguna', $data);
}

function saveUser() {
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $username = isset($input['username']) ? sanitize($input['username']) : '';
    $nama = isset($input['nama_lengkap']) ? sanitize($input['nama_lengkap']) : '';
    $role = isset($input['role']) ? sanitize($input['role']) : '';
    
    if(empty($username) || empty($nama) || empty($role)) {
        json_response(400, false, 'Data wajib tidak boleh kosong');
    }

    if ($id > 0) {
        $stmt = db()->prepare("UPDATE perf_users SET username=?, nama_lengkap=?, role=? WHERE id=?");
        $stmt->execute([$username, $nama, $role, $id]);
        json_response(200, true, 'Pengguna berhasil diupdate');
    } else {
        $password = isset($input['password']) && !empty($input['password']) ? $input['password'] : $username;
        $hash = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = db()->prepare("SELECT id FROM perf_users WHERE username=?");
        $stmt->execute([$username]);
        if($stmt->fetch()) {
            json_response(400, false, 'Username sudah digunakan');
        }
        
        $stmt = db()->prepare("INSERT INTO perf_users (username, password, nama_lengkap, role, status) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$username, $hash, $nama, $role]);
        json_response(200, true, 'Pengguna berhasil ditambahkan');
    }
}

function changePassword() {
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $password = isset($input['password']) ? $input['password'] : '';
    
    if(empty($password)) {
        json_response(400, false, 'Password baru tidak boleh kosong');
    }
    
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = db()->prepare("UPDATE perf_users SET password=? WHERE id=?");
    $stmt->execute([$hash, $id]);
    
    // Sinkronisasi dengan tabel users (eportal) jika ada
    $stmtUser = db()->prepare("SELECT username FROM perf_users WHERE id = ?");
    $stmtUser->execute([$id]);
    $u = $stmtUser->fetch();
    if ($u) {
        $stmtUpdatePortal = db()->prepare("UPDATE users SET password = ? WHERE username = ?");
        $stmtUpdatePortal->execute([$hash, $u['username']]);
    }
    
    json_response(200, true, 'Password berhasil diubah');
}

function deleteUser() {
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    
    $stmt = db()->prepare("SELECT * FROM perf_users WHERE id=?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if(!$user) {
        json_response(404, false, 'Pengguna tidak ditemukan');
    }
    if(!empty($user['perf_ptk_id'])) {
        json_response(400, false, 'Gagal. Akun ini terkait dengan PTK. Silakan hapus melalui menu Data PTK.');
    }
    
    // Also delete sessions
    db()->prepare("DELETE FROM perf_sessions WHERE perf_user_id=?")->execute([$id]);
    
    $stmt = db()->prepare("DELETE FROM perf_users WHERE id=?");
    $stmt->execute([$id]);
    json_response(200, true, 'Pengguna berhasil dihapus');
}
