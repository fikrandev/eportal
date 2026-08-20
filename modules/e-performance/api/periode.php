<?php
/**
 * E-Performance — Periode Penilaian API
 */
require_once __DIR__ . '/config_perf.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'list':   listPeriode(); break;
    case 'create': createPeriode(); break;
    case 'update': updatePeriode(); break;
    case 'delete': deletePeriode(); break;
    case 'activate': activatePeriode(); break;
    case 'toggle_release': toggleRelease(); break;
    case 'get_active_year': getActiveYear(); break;
    case 'get_all_years': getAllYears(); break;
    default: json_response(400, false, 'Action tidak valid.');
}

function toggleRelease() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $id = (int)($input['id'] ?? 0);
    $status = (int)($input['is_released'] ?? 0);

    try {
        $stmt = db()->prepare("UPDATE perf_periode SET is_released = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        json_response(200, true, 'Status release hasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function listPeriode() {
    perf_auth_check();
    try {
        $stmt = db()->query("SELECT * FROM perf_periode ORDER BY created_at DESC");
        json_response(200, true, 'Daftar Periode.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function createPeriode() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();

    try {
        $stmt = db()->prepare("
            INSERT INTO perf_periode (nama_periode, tahun_ajaran, semester, tgl_mulai, tgl_selesai, status)
            VALUES (?, ?, ?, ?, ?, 'draft')
        ");
        $stmt->execute([
            sanitize($input['nama_periode'] ?? ''),
            sanitize($input['tahun_ajaran'] ?? ''),
            $input['semester'] ?? '1',
            $input['tgl_mulai'] ?? null,
            $input['tgl_selesai'] ?? null
        ]);
        json_response(201, true, 'Periode berhasil dibuat.', ['id' => db()->lastInsertId()]);
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function updatePeriode() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $id = (int)($input['id'] ?? 0);

    try {
        $stmt = db()->prepare("
            UPDATE perf_periode SET nama_periode=?, tahun_ajaran=?, semester=?, tgl_mulai=?, tgl_selesai=?
            WHERE id=?
        ");
        $stmt->execute([
            sanitize($input['nama_periode'] ?? ''),
            sanitize($input['tahun_ajaran'] ?? ''),
            $input['semester'] ?? '1',
            $input['tgl_mulai'] ?? null,
            $input['tgl_selesai'] ?? null,
            $id
        ]);
        json_response(200, true, 'Periode berhasil diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function deletePeriode() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $id = (int)($input['id'] ?? 0);

    try {
        // Clean related data
        db()->prepare("DELETE FROM perf_sampling WHERE periode_id = ?")->execute([$id]);
        db()->prepare("DELETE FROM perf_penugasan_sejawat WHERE periode_id = ?")->execute([$id]);
        db()->prepare("DELETE FROM perf_penilaian WHERE periode_id = ?")->execute([$id]);
        db()->prepare("DELETE FROM perf_progress WHERE periode_id = ?")->execute([$id]);
        db()->prepare("DELETE FROM perf_periode WHERE id = ?")->execute([$id]);

        json_response(200, true, 'Periode dan semua data terkait berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function activatePeriode() {
    perf_require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');
    $input = get_input();
    $id = (int)($input['id'] ?? 0);
    $status = sanitize($input['status'] ?? 'aktif');

    try {
        // If activating, deactivate others
        if ($status === 'aktif') {
            db()->exec("UPDATE perf_periode SET status = 'draft' WHERE status = 'aktif'");
        }
        $stmt = db()->prepare("UPDATE perf_periode SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        json_response(200, true, 'Status periode diperbarui.');
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function getActiveYear() {
    perf_auth_check();
    try {
        $active = get_active_academic_year();
        json_response(200, true, 'Active year.', $active);
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}

function getAllYears() {
    perf_auth_check();
    try {
        $stmt = db()->query("SELECT id, tahun_ajaran, semester, is_active FROM academic_years ORDER BY tahun_ajaran DESC, semester DESC");
        json_response(200, true, 'All academic years.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Error: ' . $e->getMessage());
    }
}


