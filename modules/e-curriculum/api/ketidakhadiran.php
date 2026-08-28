<?php
/**
 * E-Curriculum Ketidakhadiran Guru API
 */
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        listKetidakhadiran($user);
        break;
    case 'create':
        createKetidakhadiran($user);
        break;
    case 'delete':
        deleteKetidakhadiran($user);
        break;
    case 'rekap':
        rekapKetidakhadiran($user);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function listKetidakhadiran($user) {
    try {
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';
        $isAdmin = $user['role'] === 'superadmin';

        $where = "1=1";
        $params = [];

        if (!$isAdmin) {
            $where .= " AND k.guru_id = ?";
            $params[] = $user['user_id'];
        }

        if (!empty($tanggal_akhir)) {
            $where .= " AND k.tanggal BETWEEN ? AND ?";
            $params[] = $tanggal;
            $params[] = $tanggal_akhir;
        } else {
            $where .= " AND k.tanggal = ?";
            $params[] = $tanggal;
        }

        $stmt = db()->prepare("
            SELECT k.*, u.nama_lengkap as guru_nama
            FROM acad_ketidakhadiran k
            JOIN users u ON k.guru_id = u.id
            WHERE $where
            ORDER BY k.tanggal DESC, u.nama_lengkap ASC
        ");
        $stmt->execute($params);
        json_response(200, true, 'Data ketidakhadiran dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function createKetidakhadiran($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $guru_id = ($user['role'] === 'superadmin' && isset($input['guru_id'])) ? (int)$input['guru_id'] : $user['user_id'];
    $tanggal = isset($input['tanggal']) ? $input['tanggal'] : date('Y-m-d');
    $jenis = isset($input['jenis']) && in_array($input['jenis'], ['Izin', 'Sakit']) ? $input['jenis'] : 'Izin';
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';

    try {
        $stmt = db()->prepare("
            INSERT INTO acad_ketidakhadiran (guru_id, tanggal, jenis, catatan)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE jenis = VALUES(jenis), catatan = VALUES(catatan)
        ");
        $stmt->execute([$guru_id, $tanggal, $jenis, $catatan]);
        json_response(201, true, 'Ketidakhadiran berhasil dicatat.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteKetidakhadiran($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        $stmt = db()->prepare("DELETE FROM acad_ketidakhadiran WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Data berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function rekapKetidakhadiran($user) {
    try {
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-01');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');

        $stmt = db()->prepare("
            SELECT u.nama_lengkap as guru_nama, u.username as nik,
                   SUM(CASE WHEN k.jenis = 'Izin' THEN 1 ELSE 0 END) as izin,
                   SUM(CASE WHEN k.jenis = 'Sakit' THEN 1 ELSE 0 END) as sakit,
                   COUNT(*) as total
            FROM acad_ketidakhadiran k
            JOIN users u ON k.guru_id = u.id
            WHERE k.tanggal BETWEEN ? AND ?
            GROUP BY u.id, u.nama_lengkap, u.username
            ORDER BY u.nama_lengkap
        ");
        $stmt->execute([$tanggal, $tanggal_akhir]);
        json_response(200, true, 'Rekap ketidakhadiran dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
