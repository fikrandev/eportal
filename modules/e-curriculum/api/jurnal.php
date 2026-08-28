<?php
/**
 * E-Curriculum Jurnal Mengajar API
 * Manages teaching journal entries
 */
require_once __DIR__ . '/auth_helper.php';

$user = acad_auth();
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        listJurnal($user);
        break;
    case 'get':
        getJurnal($user);
        break;
    case 'create':
        createJurnal($user);
        break;
    case 'update':
        updateJurnal($user);
        break;
    case 'delete':
        deleteJurnal($user);
        break;
    case 'report':
        reportJurnal($user);
        break;
    case 'meta':
        getJurnalMeta($user);
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

/**
 * List jurnal entries for a teacher on given date/range
 */
function listJurnal($user) {
    try {
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : '';

        $isAdmin = $user['role'] === 'superadmin';
        $guru_id = $isAdmin ? (isset($_GET['guru_id']) ? (int)$_GET['guru_id'] : 0) : $user['user_id'];

        $where = "j.academic_year_id = ?";
        $params = [$year_id];

        if ($guru_id > 0) {
            $where .= " AND j.guru_id = ?";
            $params[] = $guru_id;
        }
        if ($kelas_id > 0) {
            $where .= " AND j.kelas_id = ?";
            $params[] = $kelas_id;
        }
        if (!empty($tanggal_akhir)) {
            $where .= " AND j.tanggal BETWEEN ? AND ?";
            $params[] = $tanggal;
            $params[] = $tanggal_akhir;
        } else {
            $where .= " AND j.tanggal = ?";
            $params[] = $tanggal;
        }

        $stmt = db()->prepare("
            SELECT j.*, u.nama_lengkap as guru_nama, k.nama_kelas, m.nama_mapel, m.nama_mapel as kode_mapel
            FROM acad_jurnal j
            JOIN users u ON j.guru_id = u.id
            LEFT JOIN sch_kelas k ON j.kelas_id = k.id
            LEFT JOIN sch_mapel m ON j.mapel_id = m.id
            WHERE $where
            ORDER BY j.tanggal DESC, j.jam_ke ASC
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        json_response(200, true, 'Data jurnal dimuat.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function getJurnal($user) {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        $stmt = db()->prepare("
            SELECT j.*, u.nama_lengkap as guru_nama, k.nama_kelas, m.nama_mapel
            FROM acad_jurnal j
            JOIN users u ON j.guru_id = u.id
            LEFT JOIN sch_kelas k ON j.kelas_id = k.id
            LEFT JOIN sch_mapel m ON j.mapel_id = m.id
            WHERE j.id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        if (!$data) json_response(404, false, 'Jurnal tidak ditemukan.');
        json_response(200, true, 'Data jurnal.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function createJurnal($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $guru_id = ($user['role'] === 'superadmin' && isset($input['guru_id'])) ? (int)$input['guru_id'] : $user['user_id'];
    $kelas_id = isset($input['kelas_id']) ? (int)$input['kelas_id'] : 0;
    $mapel_id = isset($input['mapel_id']) ? (int)$input['mapel_id'] : 0;
    $tanggal = isset($input['tanggal']) ? $input['tanggal'] : date('Y-m-d');
    $jam_ke = isset($input['jam_ke']) ? trim($input['jam_ke']) : '';
    $tp = isset($input['tujuan_pembelajaran']) ? trim($input['tujuan_pembelajaran']) : '';
    $iptp = isset($input['indikator_tp']) ? trim($input['indikator_tp']) : '';
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';
    $siswa_tidak_hadir = isset($input['siswa_tidak_hadir']) ? $input['siswa_tidak_hadir'] : '';

    $active_year = get_active_academic_year();
    $year_id = $active_year['id'] ?? 0;

    if ($kelas_id <= 0 || $mapel_id <= 0 || empty($jam_ke)) {
        json_response(400, false, 'Kelas, Mata Pelajaran, dan Jam wajib diisi.');
    }

    try {
        $stmt = db()->prepare("
            INSERT INTO acad_jurnal (guru_id, kelas_id, mapel_id, academic_year_id, tanggal, jam_ke, tujuan_pembelajaran, indikator_tp, catatan, siswa_tidak_hadir)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$guru_id, $kelas_id, $mapel_id, $year_id, $tanggal, $jam_ke, $tp, $iptp, $catatan,
            is_array($siswa_tidak_hadir) ? json_encode($siswa_tidak_hadir) : $siswa_tidak_hadir]);

        json_response(201, true, 'Jurnal mengajar berhasil disimpan.', ['id' => db()->lastInsertId()]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function updateJurnal($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    $tp = isset($input['tujuan_pembelajaran']) ? trim($input['tujuan_pembelajaran']) : '';
    $iptp = isset($input['indikator_tp']) ? trim($input['indikator_tp']) : '';
    $catatan = isset($input['catatan']) ? trim($input['catatan']) : '';
    $siswa_tidak_hadir = isset($input['siswa_tidak_hadir']) ? $input['siswa_tidak_hadir'] : '';

    try {
        $stmt = db()->prepare("
            UPDATE acad_jurnal SET tujuan_pembelajaran = ?, indikator_tp = ?, catatan = ?, siswa_tidak_hadir = ? WHERE id = ?
        ");
        $stmt->execute([$tp, $iptp, $catatan,
            is_array($siswa_tidak_hadir) ? json_encode($siswa_tidak_hadir) : $siswa_tidak_hadir, $id]);

        json_response(200, true, 'Jurnal berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteJurnal($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        $stmt = db()->prepare("DELETE FROM acad_jurnal WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Jurnal berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Report: Jurnal Kelas / Pimpinan / Wali
 */
function reportJurnal($user) {
    try {
        $type = isset($_GET['type']) ? $_GET['type'] : 'kelas';
        $tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
        $tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : $tanggal;
        $kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : 0;
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        $where = "j.academic_year_id = ? AND j.tanggal BETWEEN ? AND ?";
        $params = [$year_id, $tanggal, $tanggal_akhir];

        if ($kelas_id > 0) {
            $where .= " AND j.kelas_id = ?";
            $params[] = $kelas_id;
        }

        if ($type === 'guru' && $user['role'] !== 'superadmin') {
            $where .= " AND j.guru_id = ?";
            $params[] = $user['user_id'];
        }

        $stmt = db()->prepare("
            SELECT j.*, u.nama_lengkap as guru_nama, k.nama_kelas, m.nama_mapel, m.nama_mapel as kode_mapel
            FROM acad_jurnal j
            JOIN users u ON j.guru_id = u.id
            LEFT JOIN sch_kelas k ON j.kelas_id = k.id
            LEFT JOIN sch_mapel m ON j.mapel_id = m.id
            WHERE $where
            ORDER BY j.tanggal ASC, k.nama_kelas ASC, j.jam_ke ASC
        ");
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        json_response(200, true, 'Laporan jurnal dimuat.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function getJurnalMeta($user) {
    try {
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;
        $isAdmin = $user['role'] === 'superadmin';
        $guru_id = $isAdmin ? 0 : $user['user_id'];

        // Get classes this teacher teaches (or all for admin)
        if ($isAdmin) {
            $stmt = db()->query("SELECT id, nama_kelas, rombel as tingkat FROM sch_kelas ORDER BY rombel, nama_kelas");
        } else {
            $stmt = db()->prepare("
                SELECT DISTINCT k.id, k.nama_kelas, k.tingkat
                FROM sch_distribusi m
                JOIN sch_kelas k ON m.kelas_id = k.id
                WHERE m.guru_id = ? AND m.academic_year_id = ?
                ORDER BY k.tingkat, k.nama_kelas
            ");
            $stmt->execute([$guru_id, $year_id]);
        }
        $classes = $stmt->fetchAll();

        // Get subjects this teacher teaches (or all for admin)
        if ($isAdmin) {
            $stmt = db()->query("SELECT id, nama_mapel as kode_mapel, nama_mapel FROM sch_mapel WHERE status = 1 ORDER BY nama_mapel");
        } else {
            $stmt = db()->prepare("
                SELECT DISTINCT mp.id, mp.kode_mapel, mp.nama_mapel
                FROM sch_distribusi m
                JOIN sch_mapel mp ON m.mapel_id = mp.id
                WHERE m.guru_id = ? AND m.academic_year_id = ?
                ORDER BY mp.nama_mapel
            ");
            $stmt->execute([$guru_id, $year_id]);
        }
        $subjects = $stmt->fetchAll();

        // Get students for attendance
        $students = [];
        if (count($classes) > 0) {
            $classIds = array_column($classes, 'id');
            $classNames = [];
            foreach ($classes as $c) $classNames[$c['id']] = $c['nama_kelas'];

            // Map sch_kelas nama to students.kelas
            $placeholders = implode(',', array_fill(0, count($classNames), '?'));
            $stmt = db()->prepare("SELECT id, nis, nama, kelas FROM students WHERE kelas IN ($placeholders) AND status = 1 ORDER BY kelas, nama");
            $stmt->execute(array_values($classNames));
            $students = $stmt->fetchAll();
        }

        json_response(200, true, 'Metadata jurnal.', [
            'classes' => $classes,
            'subjects' => $subjects,
            'students' => $students
        ]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}
