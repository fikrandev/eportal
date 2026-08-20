<?php
/**
 * E-Performance — PTK API
 * CRUD for Pendidik & Tenaga Kependidikan
 */
require_once __DIR__ . '/config_perf.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':
        listPtk();
        break;
    case 'get':
        getPtk();
        break;
    case 'create':
        createPtk();
        break;
    case 'update':
        updatePtk();
        break;
    case 'delete':
        deletePtk();
        break;
    case 'bulk_delete':
        bulkDeletePtk();
        break;
    case 'import':
        importPtk();
        break;
    case 'get_portal_ptk':
        getPortalPtk();
        break;
    case 'import_portal_ptk':
        importPortalPtk();
        break;
    case 'stats':
        statsPtk();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function listPtk()
{
    perf_auth_check();
    try {
        $jenis = isset($_GET['jenis']) ? $_GET['jenis'] : '';
        $sql = "SELECT * FROM perf_ptk WHERE status = 1";
        $params = [];
        if ($jenis) {
            $sql .= " AND jenis_ptk = ?";
            $params[] = $jenis;
        }
        $sql .= " ORDER BY jenis_ptk, nama ASC";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        json_response(200, true, 'Data PTK.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function getPtk()
{
    perf_auth_check();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    try {
        $stmt = db()->prepare("SELECT * FROM perf_ptk WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        if (!$data)
            json_response(404, false, 'Data tidak ditemukan.');
        json_response(200, true, 'Detail PTK.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function createPtk()
{
    $auth = perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $niy = sanitize($input['niy'] ?? '');
    $nama = sanitize($input['nama'] ?? '');
    $jenis = sanitize($input['jenis_ptk'] ?? 'guru');

    if (empty($niy) || empty($nama)) {
        json_response(400, false, 'NIY dan Nama wajib diisi.');
    }

    try {
        // Check duplicate NIY
        $stmt = db()->prepare("SELECT id FROM perf_ptk WHERE niy = ?");
        $stmt->execute([$niy]);
        if ($stmt->fetch())
            json_response(400, false, 'NIY sudah terdaftar.');

        $stmt = db()->prepare("
            INSERT INTO perf_ptk (niy, nama, tmt, tempat_lahir, tgl_lahir, jabatan, mata_pelajaran, jenis_ptk)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $niy,
            $nama,
            $input['tmt'] ?? null,
            sanitize($input['tempat_lahir'] ?? ''),
            $input['tgl_lahir'] ?? null,
            sanitize($input['jabatan'] ?? ''),
            sanitize($input['mata_pelajaran'] ?? ''),
            $jenis
        ]);

        $ptkId = db()->lastInsertId();

        // Auto-create login user with NIY as username & password
        $hashedPw = password_hash($niy, PASSWORD_DEFAULT);
        $stmt = db()->prepare("
            INSERT INTO perf_users (username, password, perf_ptk_id, nama_lengkap, role)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$niy, $hashedPw, $ptkId, $nama, $jenis]);

        json_response(201, true, 'PTK berhasil ditambahkan.', ['id' => $ptkId]);
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function updatePtk()
{
    $auth = perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $id = (int) ($input['id'] ?? 0);
    if ($id <= 0)
        json_response(400, false, 'ID tidak valid.');

    try {
        $stmt = db()->prepare("
            UPDATE perf_ptk SET niy=?, nama=?, tmt=?, tempat_lahir=?, tgl_lahir=?, 
            jabatan=?, mata_pelajaran=?, jenis_ptk=? WHERE id=?
        ");
        $stmt->execute([
            sanitize($input['niy'] ?? ''),
            sanitize($input['nama'] ?? ''),
            $input['tmt'] ?? null,
            sanitize($input['tempat_lahir'] ?? ''),
            $input['tgl_lahir'] ?? null,
            sanitize($input['jabatan'] ?? ''),
            sanitize($input['mata_pelajaran'] ?? ''),
            sanitize($input['jenis_ptk'] ?? 'guru'),
            $id
        ]);

        // Update the corresponding perf_users record
        $stmt = db()->prepare("UPDATE perf_users SET nama_lengkap=?, role=? WHERE perf_ptk_id=?");
        $stmt->execute([sanitize($input['nama'] ?? ''), sanitize($input['jenis_ptk'] ?? 'guru'), $id]);

        json_response(200, true, 'PTK berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function deletePtk()
{
    $auth = perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $id = (int) ($input['id'] ?? 0);

    try {
        // Delete related perf_user
        $stmt = db()->prepare("DELETE FROM perf_users WHERE perf_ptk_id = ?");
        $stmt->execute([$id]);
        // Delete PTK
        $stmt = db()->prepare("DELETE FROM perf_ptk WHERE id = ?");
        $stmt->execute([$id]);

        json_response(200, true, 'PTK berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function bulkDeletePtk()
{
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $ids = $input['ids'] ?? [];
    if (empty($ids))
        json_response(400, false, 'Tidak ada data terpilih.');

    try {
        db()->beginTransaction();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Delete related users
        db()->prepare("DELETE FROM perf_users WHERE perf_ptk_id IN ($placeholders)")->execute($ids);

        // Delete PTK
        db()->prepare("DELETE FROM perf_ptk WHERE id IN ($placeholders)")->execute($ids);

        db()->commit();
        json_response(200, true, count($ids) . ' PTK berhasil dihapus.');
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}


function importPtk()
{
    $auth = perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $rows = $input['data'] ?? [];
    if (empty($rows))
        json_response(400, false, 'Data import kosong.');

    $inserted = 0;
    $skipped = 0;

    try {
        db()->beginTransaction();

        foreach ($rows as $row) {
            $niy = trim($row['NIY'] ?? $row['niy'] ?? '');
            $nama = trim($row['Nama'] ?? $row['nama'] ?? '');
            $jenis = strtolower(trim($row['Jabatan PTK'] ?? $row['jenis_ptk'] ?? 'guru'));

            if (empty($niy) || empty($nama)) {
                $skipped++;
                continue;
            }

            // Map jabatan to jenis_ptk
            if (strpos($jenis, 'kepala') !== false)
                $jenis = 'kepsek';
            elseif (strpos($jenis, 'tu') !== false || strpos($jenis, 'tata') !== false)
                $jenis = 'tu';
            elseif (strpos($jenis, 'it') !== false || strpos($jenis, 'teknologi') !== false)
                $jenis = 'it';
            elseif (strpos($jenis, 'pustaka') !== false)
                $jenis = 'pustakawan';
            elseif (!in_array($jenis, ['guru', 'tu', 'it', 'pustakawan', 'kepsek']))
                $jenis = 'guru';

            // Skip duplicate NIY
            $check = db()->prepare("SELECT id FROM perf_ptk WHERE niy = ?");
            $check->execute([$niy]);
            if ($check->fetch()) {
                $skipped++;
                continue;
            }

            $stmt = db()->prepare("
                INSERT INTO perf_ptk (niy, nama, tmt, tempat_lahir, tgl_lahir, jabatan, mata_pelajaran, jenis_ptk)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $niy,
                $nama,
                $row['TMT'] ?? $row['tmt'] ?? null,
                $row['Tempat Lahir'] ?? $row['tempat_lahir'] ?? '',
                $row['Tgl Lahir'] ?? $row['tgl_lahir'] ?? null,
                $row['Jabatan'] ?? $row['jabatan'] ?? '',
                $row['Mata Pelajaran'] ?? $row['mata_pelajaran'] ?? '',
                $jenis
            ]);

            $ptkId = db()->lastInsertId();

            // Auto-create login
            $hashedPw = password_hash($niy, PASSWORD_DEFAULT);
            $stmt2 = db()->prepare("INSERT INTO perf_users (username, password, perf_ptk_id, nama_lengkap, role) VALUES (?, ?, ?, ?, ?)");
            $stmt2->execute([$niy, $hashedPw, $ptkId, $nama, $jenis]);

            $inserted++;
        }

        db()->commit();
        json_response(200, true, "Import selesai. $inserted data ditambahkan, $skipped dilewati.");
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Error import: ' . $e->getMessage());
    }
}

function statsPtk()
{
    perf_auth_check();
    try {
        $stats = [];
        $stmt = db()->query("SELECT jenis_ptk, COUNT(*) as total FROM perf_ptk WHERE status=1 GROUP BY jenis_ptk");
        $byJenis = $stmt->fetchAll();
        $stats['by_jenis'] = $byJenis;

        $stmt = db()->query("SELECT COUNT(*) as total FROM perf_ptk WHERE status=1");
        $stats['total_ptk'] = $stmt->fetch()['total'];

        $stmt = db()->query("SELECT COUNT(*) as total FROM perf_siswa WHERE status=1");
        $stats['total_siswa'] = $stmt->fetch()['total'];

        $stmt = db()->query("SELECT COUNT(*) as total FROM perf_periode WHERE status='aktif'");
        $stats['periode_aktif'] = $stmt->fetch()['total'];

        $stmt = db()->query("SELECT COUNT(*) as total FROM perf_users WHERE status=1");
        $stats['total_users'] = $stmt->fetch()['total'];

        json_response(200, true, 'Statistik.', $stats);
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function getPortalPtk()
{
    perf_require_admin();
    try {
        // Fetch users from portal with role guru
        $stmt = db()->query("SELECT id, username, nama_lengkap, tupoksi FROM users WHERE role = 'guru' AND status = 1 ORDER BY nama_lengkap ASC");
        json_response(200, true, 'Portal PTK.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function importPortalPtk()
{
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $ids = $input['ids'] ?? [];

    if (empty($ids))
        json_response(400, false, 'Tidak ada guru yang dipilih.');

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare("SELECT username, nama_lengkap, tupoksi, tmt, tempat_lahir, tgl_lahir, jabatan FROM users WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $teachers = $stmt->fetchAll();

        $inserted = 0;
        $skipped = 0;

        db()->beginTransaction();
        foreach ($teachers as $t) {
            // Check if already exists in perf_ptk (by niy/username)
            $check = db()->prepare("SELECT id FROM perf_ptk WHERE niy = ?");
            $check->execute([$t['username']]);
            if ($check->fetch()) {
                $skipped++;
                continue;
            }

            $tupoksi = trim($t['tupoksi'] ?? '');
            if ($tupoksi === '-' || $tupoksi === '') {
                $jenisPtk = 'guru';
            } else {
                $jenisPtk = $tupoksi;
            }

            // Insert into perf_ptk
            $stmtIns = db()->prepare("INSERT INTO perf_ptk (niy, nama, jenis_ptk, tmt, tempat_lahir, tgl_lahir, jabatan) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtIns->execute([
                $t['username'],
                $t['nama_lengkap'],
                $jenisPtk,
                $t['tmt'],
                $t['tempat_lahir'],
                $t['tgl_lahir'],
                $t['jabatan']
            ]);
            $ptkId = db()->lastInsertId();

            // Determine valid enum role for perf_users login
            $userRole = strtolower($jenisPtk);
            if (strpos($userRole, 'kepala') !== false)
                $userRole = 'kepsek';
            elseif (strpos($userRole, 'tu') !== false || strpos($userRole, 'tata') !== false)
                $userRole = 'tu';
            elseif (strpos($userRole, 'it') !== false || strpos($userRole, 'teknologi') !== false)
                $userRole = 'it';
            elseif (strpos($userRole, 'pustaka') !== false)
                $userRole = 'pustakawan';
            elseif (!in_array($userRole, ['guru', 'tu', 'it', 'pustakawan', 'kepsek']))
                $userRole = 'guru';

            // Auto-create perf_users login (password same as username/niy)
            $hashedPw = password_hash($t['username'], PASSWORD_DEFAULT);
            $stmt2 = db()->prepare("INSERT INTO perf_users (username, password, perf_ptk_id, nama_lengkap, role) VALUES (?, ?, ?, ?, ?)");
            $stmt2->execute([$t['username'], $hashedPw, $ptkId, $t['nama_lengkap'], $userRole]);

            $inserted++;
        }
        db()->commit();

        json_response(200, true, "$inserted guru berhasil diimport. $skipped data dilewati karena sudah ada.");
    } catch (PDOException $e) {
        db()->rollBack();
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

