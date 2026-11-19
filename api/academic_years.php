<?php
/**
 * E-Portal Academic Years API
 * Shared active academic year for all modules.
 */
require_once __DIR__ . '/config.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'active':
        activeAcademicYear();
        break;
    case 'list':
        listAcademicYears();
        break;
    case 'get':
        getAcademicYear();
        break;
    case 'create':
        saveAcademicYear(false);
        break;
    case 'update':
        saveAcademicYear(true);
        break;
    case 'activate':
        activateAcademicYear();
        break;
    case 'delete':
        deleteAcademicYear();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function activeAcademicYear()
{
    json_response(200, true, 'Tahun ajaran aktif.', get_active_academic_year());
}

function listAcademicYears()
{
    portal_require_permission('academic_years_manage');

    try {
        $stmt = db()->query("SELECT * FROM academic_years ORDER BY is_active DESC, tahun_ajaran DESC, semester DESC");
        json_response(200, true, 'Data tahun ajaran berhasil dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function getAcademicYear()
{
    portal_require_permission('academic_years_manage');

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        json_response(400, false, 'ID tahun ajaran tidak valid.');
    }

    try {
        $stmt = db()->prepare("SELECT * FROM academic_years WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            json_response(404, false, 'Tahun ajaran tidak ditemukan.');
        }
        json_response(200, true, 'Data tahun ajaran.', $row);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function saveAcademicYear($isUpdate)
{
    portal_require_permission('academic_years_manage');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    $tahun = sanitize($input['tahun_ajaran'] ?? '');
    $semester = sanitize($input['semester'] ?? '1');
    $tanggalMulai = sanitize($input['tanggal_mulai'] ?? '');
    $tanggalSelesai = sanitize($input['tanggal_selesai'] ?? '');
    $isActive = isset($input['is_active']) ? (int) $input['is_active'] : 0;

    if ($isUpdate && $id <= 0) {
        json_response(400, false, 'ID tahun ajaran tidak valid.');
    }
    if (empty($tahun) || !preg_match('/^\d{4}\/\d{4}$/', $tahun)) {
        json_response(400, false, 'Format tahun ajaran harus seperti 2025/2026.');
    }
    if (!in_array($semester, ['1', '2'])) {
        json_response(400, false, 'Semester tidak valid.');
    }

    $tanggalMulai = $tanggalMulai ?: null;
    $tanggalSelesai = $tanggalSelesai ?: null;

    try {
        db()->beginTransaction();

        if ($isActive === 1) {
            db()->exec("UPDATE academic_years SET is_active = 0");
        }

        if ($isUpdate) {
            $stmt = db()->prepare("
                UPDATE academic_years
                SET tahun_ajaran = ?, semester = ?, tanggal_mulai = ?, tanggal_selesai = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$tahun, $semester, $tanggalMulai, $tanggalSelesai, $isActive, $id]);
        } else {
            $stmt = db()->prepare("
                INSERT INTO academic_years (tahun_ajaran, semester, tanggal_mulai, tanggal_selesai, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tahun, $semester, $tanggalMulai, $tanggalSelesai, $isActive]);
            $id = (int) db()->lastInsertId();
        }

        if ($isActive === 1) {
            syncActiveAcademicYearSettings($tahun, $semester, $id);
        }

        db()->commit();
        json_response($isUpdate ? 200 : 201, true, $isUpdate ? 'Tahun ajaran berhasil diperbarui.' : 'Tahun ajaran berhasil ditambahkan.', ['id' => $id]);
    } catch (PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        if (strpos($e->getMessage(), 'uk_academic_year_semester') !== false) {
            json_response(400, false, 'Tahun ajaran dan semester tersebut sudah ada.');
        }
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function activateAcademicYear()
{
    portal_require_permission('academic_years_manage');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    if ($id <= 0) {
        json_response(400, false, 'ID tahun ajaran tidak valid.');
    }

    try {
        $stmt = db()->prepare("SELECT * FROM academic_years WHERE id = ?");
        $stmt->execute([$id]);
        $year = $stmt->fetch();
        if (!$year) {
            json_response(404, false, 'Tahun ajaran tidak ditemukan.');
        }

        db()->beginTransaction();
        db()->exec("UPDATE academic_years SET is_active = 0");
        $stmt = db()->prepare("UPDATE academic_years SET is_active = 1 WHERE id = ?");
        $stmt->execute([$id]);
        syncActiveAcademicYearSettings($year['tahun_ajaran'], $year['semester'], $id);

        // Hanya isi academic_year_id untuk siswa aktif yang belum punya tahun ajaran (jangan timpa tahun ajaran historis siswa lain)
        $stmtSync = db()->prepare("
            UPDATE students 
            SET academic_year_id = ? 
            WHERE status = 1
              AND (status_siswa = 'Aktif' OR status_siswa IS NULL OR status_siswa = '')
              AND (academic_year_id IS NULL OR academic_year_id = 0)
        ");
        $stmtSync->execute([$id]);

        db()->commit();

        json_response(200, true, 'Tahun ajaran aktif berhasil diperbarui.', $year);
    } catch (PDOException $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteAcademicYear()
{
    portal_require_permission('academic_years_manage');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    if ($id <= 0) {
        json_response(400, false, 'ID tahun ajaran tidak valid.');
    }

    try {
        $stmt = db()->prepare("SELECT is_active FROM academic_years WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            json_response(404, false, 'Tahun ajaran tidak ditemukan.');
        }
        if ((int) $row['is_active'] === 1) {
            json_response(400, false, 'Tahun ajaran aktif tidak dapat dihapus.');
        }

        $stmt = db()->prepare("SELECT COUNT(*) FROM students WHERE academic_year_id = ?");
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            json_response(400, false, 'Tahun ajaran masih digunakan oleh data siswa.');
        }

        $stmt = db()->prepare("DELETE FROM academic_years WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Tahun ajaran berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function syncActiveAcademicYearSettings($tahun, $semester, $id)
{
    upsert_setting('tahun_ajaran_aktif', $tahun, 'text', 'Tahun ajaran aktif global');
    upsert_setting('semester_aktif', $semester, 'text', 'Semester aktif global');
    upsert_setting('academic_year_id_aktif', (string) $id, 'number', 'ID tahun ajaran aktif global');
    reset_active_academic_year_cache();
}
