<?php
/**
 * E-Xam Card teacher access API.
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/xam_helper.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        xam_require_manage();
        listAccess();
        break;
    case 'save':
        xam_require_manage();
        saveAccess();
        break;
    case 'delete':
        xam_require_manage();
        deleteAccess();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function listAccess()
{
    try {
        $yearId = xam_active_year_id();
        $accesses = db()->query("
            SELECT a.user_id, u.username, u.nama_lengkap, u.jabatan,
                   a.id as access_id, a.access_role, a.kelas, a.status as access_status
            FROM xam_teacher_access a
            JOIN users u ON u.id = a.user_id
            WHERE u.role = 'guru' AND u.status = 1
            ORDER BY u.nama_lengkap ASC
        ")->fetchAll();

        $teachers = db()->query("
            SELECT u.id, u.username, u.nama_lengkap, u.jabatan
            FROM users u
            WHERE u.role = 'guru' AND u.status = 1
            ORDER BY u.nama_lengkap ASC
        ")->fetchAll();

        $classes = xam_all_classes($yearId);

        json_response(200, true, 'Data akses berhasil dimuat.', [
            'accesses' => $accesses,
            'teachers' => $teachers,
            'classes' => $classes
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function saveAccess()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $userId = isset($input['user_id']) ? (int) $input['user_id'] : 0;
    $kelas = sanitize($input['kelas'] ?? '');
    $status = isset($input['status']) ? (int) $input['status'] : 1;

    if ($userId <= 0 || $kelas === '') {
        json_response(400, false, 'Guru dan kelas wajib diisi.');
    }

    try {
        $yearId = xam_active_year_id();
        $stmt = db()->prepare("SELECT id FROM users WHERE id = ? AND role = 'guru' AND status = 1");
        $stmt->execute([$userId]);
        if (!$stmt->fetchColumn()) {
            json_response(404, false, 'Data guru tidak ditemukan atau tidak aktif.');
        }

        $allowedClasses = array_flip(array_map(function ($row) {
            return (string) ($row['kelas'] ?? '');
        }, xam_all_classes($yearId)));
        
        if (!isset($allowedClasses[$kelas])) {
            json_response(400, false, 'Kelas wali tidak valid. Pilih kelas dari master siswa.');
        }

        $stmt = db()->prepare("
            INSERT INTO xam_teacher_access (user_id, access_role, kelas, status)
            VALUES (?, 'wali_kelas', ?, ?)
            ON DUPLICATE KEY UPDATE
                access_role = VALUES(access_role),
                kelas = VALUES(kelas),
                status = VALUES(status)
        ");
        $stmt->execute([$userId, $kelas, $status ? 1 : 0]);

        json_response(200, true, 'Akses guru berhasil disimpan.');
    } catch (PDOException $e) {
        // If the table doesn't exist, provide a helpful error message
        if (strpos($e->getMessage(), "Table") !== false && strpos($e->getMessage(), "doesn't exist") !== false) {
            json_response(500, false, 'Tabel xam_teacher_access tidak ditemukan. Silakan jalankan migratev4.php terlebih dahulu.');
        }
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteAccess()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $userId = isset($input['user_id']) ? (int) $input['user_id'] : 0;
    if ($userId <= 0) {
        json_response(400, false, 'ID guru tidak valid.');
    }

    try {
        $stmt = db()->prepare("DELETE FROM xam_teacher_access WHERE user_id = ?");
        $stmt->execute([$userId]);
        json_response(200, true, 'Akses guru berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
