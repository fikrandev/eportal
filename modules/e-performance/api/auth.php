<?php
/**
 * E-Performance — Auth API
 * Login for kepsek/guru/siswa/tu/it/pustakawan via NIY
 */
require_once __DIR__ . '/config_perf.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'check':
        handleCheck();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'change_password':
        handleChangePassword();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $username = isset($input['username']) ? trim($input['username']) : '';
    $password = isset($input['password']) ? trim($input['password']) : '';

    if (empty($username) || empty($password)) {
        json_response(400, false, 'Username dan password harus diisi.');
    }

    try {
        // Check perf_users table
        $stmt = db()->prepare("
            SELECT pu.*, pp.nama as ptk_nama, pp.jenis_ptk, pp.niy
            FROM perf_users pu
            LEFT JOIN perf_ptk pp ON pu.perf_ptk_id = pp.id
            WHERE pu.username = ? AND pu.status = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            json_response(401, false, 'Username tidak ditemukan.');
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            // Also check plain text for siswa (generated passwords)
            if ($user['role'] === 'siswa') {
                $stmtSiswa = db()->prepare("SELECT password_plain FROM perf_siswa WHERE perf_user_id = ?");
                $stmtSiswa->execute([$user['id']]);
                $siswa = $stmtSiswa->fetch();
                if (!$siswa || $siswa['password_plain'] !== $password) {
                    json_response(401, false, 'Password salah.');
                }
            } else {
                // Fallback: cek password dari tabel users eportal (berdasarkan username/NIY yang sama)
                $stmtPortal = db()->prepare("SELECT password FROM users WHERE username = ? AND status = 1");
                $stmtPortal->execute([$username]);
                $portalUser = $stmtPortal->fetch();
                if (!$portalUser || !password_verify($password, $portalUser['password'])) {
                    json_response(401, false, 'Password salah.');
                }
                // Password eportal cocok, update perf_users password agar sinkron
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                db()->prepare("UPDATE perf_users SET password = ? WHERE id = ?")->execute([$newHash, $user['id']]);
            }
        }

        // Generate token
        $token = perf_generate_token($user['id']);

        // Update last login
        $stmt = db()->prepare("UPDATE perf_users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        json_response(200, true, 'Login berhasil.', [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'nama_lengkap' => $user['nama_lengkap'],
                'role' => $user['role'],
                'ptk_id' => $user['perf_ptk_id'],
                'ptk_nama' => $user['ptk_nama'],
                'jenis_ptk' => $user['jenis_ptk']
            ]
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function handleCheck() {
    $auth = perf_auth_check();
    json_response(200, true, 'Session valid.', $auth);
}

function handleLogout() {
    $token = '';
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    }
    // Also check query parameter (sent by doLogout as fallback)
    if (empty($token) && isset($_GET['token'])) {
        $token = $_GET['token'];
    }

    if (!empty($token)) {
        try {
            // Delete from perf_sessions (for PTK/siswa login)
            $stmt = db()->prepare("DELETE FROM perf_sessions WHERE token = ?");
            $stmt->execute([$token]);
        } catch (PDOException $e) {}

        try {
            // Also delete from eportal sessions (for admin login)
            $stmt = db()->prepare("DELETE FROM sessions WHERE token = ?");
            $stmt->execute([$token]);
        } catch (PDOException $e) {}
    }

    json_response(200, true, 'Logout berhasil.');
}

function handleChangePassword() {
    $auth = perf_auth_check();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $old_password = isset($input['old_password']) ? trim($input['old_password']) : '';
    $new_password = isset($input['new_password']) ? trim($input['new_password']) : '';

    if (empty($old_password) || empty($new_password)) {
        json_response(400, false, 'Password lama dan baru harus diisi.');
    }

    try {
        $user_id = $auth['user_id'];
        
        $stmt = db()->prepare("SELECT * FROM perf_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            json_response(404, false, 'User tidak ditemukan.');
        }

        // Verify old password
        $valid = false;
        if (password_verify($old_password, $user['password'])) {
            $valid = true;
        } else if ($user['role'] === 'siswa') {
            $stmtSiswa = db()->prepare("SELECT password_plain FROM perf_siswa WHERE perf_user_id = ?");
            $stmtSiswa->execute([$user_id]);
            $siswa = $stmtSiswa->fetch();
            if ($siswa && $siswa['password_plain'] === $old_password) {
                $valid = true;
            }
        } else {
            // Check eportal password fallback
            $stmtPortal = db()->prepare("SELECT password FROM users WHERE username = ?");
            $stmtPortal->execute([$user['username']]);
            $portalUser = $stmtPortal->fetch();
            if ($portalUser && password_verify($old_password, $portalUser['password'])) {
                $valid = true;
            }
        }

        if (!$valid) {
            json_response(400, false, 'Password lama salah.');
        }

        // Hash new password
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

        // Update perf_users
        $stmtUpdatePerf = db()->prepare("UPDATE perf_users SET password = ? WHERE id = ?");
        $stmtUpdatePerf->execute([$new_hash, $user_id]);

        // Update eportal users if exists
        $stmtUpdatePortal = db()->prepare("UPDATE users SET password = ? WHERE username = ?");
        $stmtUpdatePortal->execute([$new_hash, $user['username']]);

        // Update perf_siswa if role siswa
        if ($user['role'] === 'siswa') {
            $stmtUpdateSiswa = db()->prepare("UPDATE perf_siswa SET password_plain = ? WHERE perf_user_id = ?");
            $stmtUpdateSiswa->execute([$new_password, $user_id]);
        }

        json_response(200, true, 'Password berhasil diubah.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
