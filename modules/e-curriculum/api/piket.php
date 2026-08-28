<?php
/**
 * E-Curriculum Piket Guru API
 */
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        listPiket($user);
        break;
    case 'save':
        savePiket($user);
        break;
    case 'delete':
        deletePiket($user);
        break;
    case 'rekap':
        rekapPiket($user);
        break;
    case 'available_guru':
        availableGuru($user);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function listPiket($user) {
    try {
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';

        $where = "1=1";
        $params = [];

        if (!empty($tanggal_akhir)) {
            $where .= " AND p.tanggal BETWEEN ? AND ?";
            $params[] = $tanggal;
            $params[] = $tanggal_akhir;
        } else {
            $where .= " AND p.tanggal = ?";
            $params[] = $tanggal;
        }

        $stmt = db()->prepare("
            SELECT p.*, 
                   u1.nama_lengkap as guru_piket_nama,
                   u2.nama_lengkap as guru_diganti_nama,
                   k.nama_kelas
            FROM acad_piket p
            JOIN users u1 ON p.guru_id = u1.id
            LEFT JOIN users u2 ON p.guru_diganti_id = u2.id
            LEFT JOIN sch_kelas k ON p.kelas_id = k.id
            WHERE $where
            ORDER BY p.tanggal DESC, u1.nama_lengkap ASC
        ");
        $stmt->execute($params);
        json_response(200, true, 'Data piket dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function savePiket($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    acad_require_admin($user);

    $input = get_input();
    $tanggal = isset($input['tanggal']) ? $input['tanggal'] : date('Y-m-d');
    $guru_id = isset($input['guru_id']) ? (int)$input['guru_id'] : 0;
    $guru_diganti_id = isset($input['guru_diganti_id']) ? (int)$input['guru_diganti_id'] : null;
    $kelas_id = isset($input['kelas_id']) ? (int)$input['kelas_id'] : null;
    $jam_ke = isset($input['jam_ke']) ? trim($input['jam_ke']) : null;
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';

    if ($guru_id <= 0) json_response(400, false, 'Guru piket wajib dipilih.');

    try {
        $stmt = db()->prepare("
            INSERT INTO acad_piket (tanggal, guru_id, guru_diganti_id, kelas_id, jam_ke, catatan)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tanggal, $guru_id, $guru_diganti_id ?: null, $kelas_id ?: null, $jam_ke, $catatan]);
        json_response(201, true, 'Piket berhasil ditambahkan.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deletePiket($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    acad_require_admin($user);

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        $stmt = db()->prepare("DELETE FROM acad_piket WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Piket berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function rekapPiket($user) {
    try {
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-01');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');

        $stmt = db()->prepare("
            SELECT u.nama_lengkap as guru_nama,
                   COUNT(*) as total_piket
            FROM acad_piket p
            JOIN users u ON p.guru_id = u.id
            WHERE p.tanggal BETWEEN ? AND ?
            GROUP BY u.id, u.nama_lengkap
            ORDER BY total_piket DESC
        ");
        $stmt->execute([$tanggal, $tanggal_akhir]);
        json_response(200, true, 'Rekap piket dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function availableGuru($user) {
    $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

    try {
        // Get all teachers
        $allGuru = db()->query("SELECT id, nama_lengkap, username FROM users WHERE role = 'guru' AND status = 1 ORDER BY nama_lengkap")->fetchAll();

        // Get absent teachers on this date
        $stmt = db()->prepare("SELECT guru_id FROM acad_ketidakhadiran WHERE tanggal = ?");
        $stmt->execute([$tanggal]);
        $absentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Filter out absent teachers (they're the ones needing replacement)
        $available = [];
        $absent = [];
        foreach ($allGuru as $g) {
            if (in_array($g['id'], $absentIds)) {
                $absent[] = $g;
            } else {
                $available[] = $g;
            }
        }

        json_response(200, true, 'Data guru dimuat.', [
            'available' => $available,
            'absent' => $absent
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
