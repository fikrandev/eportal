<?php
/**
 * E-Graduation subject groups and subjects API.
 */
require_once __DIR__ . '/../../../api/config.php';
require_once __DIR__ . '/auth_helper.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'groups':
        grad_auth();
        listGroups();
        break;
    case 'group-get':
        grad_auth();
        getGroup();
        break;
    case 'group-save':
        grad_require_manage();
        saveGroup();
        break;
    case 'group-delete':
        grad_require_manage();
        deleteGroup();
        break;
    case 'subjects':
        grad_auth();
        listSubjects();
        break;
    case 'subject-get':
        grad_auth();
        getSubject();
        break;
    case 'subject-save':
        grad_require_manage();
        saveSubject();
        break;
    case 'subject-delete':
        grad_require_manage();
        deleteSubject();
        break;
    case 'classes':
        grad_auth();
        listClasses();
        break;
    default:
        json_response(400, false, 'Action tidak valid.');
}

function listGroups()
{
    try {
        $academicYearId = grad_active_year_id();
        $stmt = db()->prepare("
            SELECT g.*, COUNT(s.id) as total_mapel
            FROM grad_subject_groups g
            LEFT JOIN grad_subjects s ON s.group_id = g.id
            WHERE g.academic_year_id = ?
            GROUP BY g.id
            ORDER BY g.urutan ASC, g.kode ASC, g.nama ASC
        ");
        $stmt->execute([$academicYearId]);
        json_response(200, true, 'Data kelompok mapel berhasil dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function getGroup()
{
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        json_response(400, false, 'ID kelompok tidak valid.');
    }

    try {
        $stmt = db()->prepare("SELECT * FROM grad_subject_groups WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            json_response(404, false, 'Kelompok mapel tidak ditemukan.');
        }
        json_response(200, true, 'Data kelompok mapel.', $row);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function saveGroup()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    $academicYearId = grad_active_year_id();
    $kode = strtoupper(sanitize($input['kode'] ?? ''));
    $nama = sanitize($input['nama'] ?? '');
    $tipe = sanitize($input['tipe'] ?? 'wajib');
    $deskripsi = sanitize($input['deskripsi'] ?? '');
    $urutan = isset($input['urutan']) ? (int) $input['urutan'] : 0;

    if ($kode === '' || $nama === '') {
        json_response(400, false, 'Kode dan nama kelompok wajib diisi.');
    }
    if (!in_array($tipe, ['wajib', 'pilihan', 'lainnya'], true)) {
        $tipe = 'wajib';
    }

    try {
        if ($id > 0) {
            $stmt = db()->prepare("
                UPDATE grad_subject_groups
                SET kode = ?, nama = ?, tipe = ?, deskripsi = ?, urutan = ?
                WHERE id = ? AND academic_year_id = ?
            ");
            $stmt->execute([$kode, $nama, $tipe, $deskripsi, $urutan, $id, $academicYearId]);
            json_response(200, true, 'Kelompok mapel berhasil diperbarui.');
        }

        $stmt = db()->prepare("
            INSERT INTO grad_subject_groups (academic_year_id, kode, nama, tipe, deskripsi, urutan)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$academicYearId, $kode, $nama, $tipe, $deskripsi, $urutan]);
        json_response(201, true, 'Kelompok mapel berhasil ditambahkan.', ['id' => db()->lastInsertId()]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'uk_grad_group_year_code') !== false) {
            json_response(400, false, 'Kode kelompok sudah digunakan pada tahun ajaran aktif.');
        }
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteGroup()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    if ($id <= 0) {
        json_response(400, false, 'ID kelompok tidak valid.');
    }

    try {
        $stmt = db()->prepare("DELETE FROM grad_subject_groups WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Kelompok mapel berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function listSubjects()
{
    try {
        $academicYearId = grad_active_year_id();
        $groupId = isset($_GET['group_id']) ? (int) $_GET['group_id'] : 0;
        $search = sanitize($_GET['search'] ?? '');

        $where = 'WHERE s.academic_year_id = ?';
        $params = [$academicYearId];
        if ($groupId > 0) {
            $where .= ' AND s.group_id = ?';
            $params[] = $groupId;
        }
        if ($search !== '') {
            $where .= ' AND (s.kode_mapel LIKE ? OR s.nama_mapel LIKE ? OR s.kelas LIKE ? OR g.nama LIKE ?)';
            $like = "%{$search}%";
            array_push($params, $like, $like, $like, $like);
        }

        $stmt = db()->prepare("
            SELECT s.*, g.kode as group_kode, g.nama as group_nama, g.tipe as group_tipe
            FROM grad_subjects s
            JOIN grad_subject_groups g ON g.id = s.group_id
            {$where}
            ORDER BY g.urutan ASC, g.kode ASC, s.urutan ASC, s.nama_mapel ASC
        ");
        $stmt->execute($params);
        json_response(200, true, 'Data mata pelajaran berhasil dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function getSubject()
{
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        json_response(400, false, 'ID mata pelajaran tidak valid.');
    }

    try {
        $stmt = db()->prepare("
            SELECT s.*, g.tipe as group_tipe
            FROM grad_subjects s
            JOIN grad_subject_groups g ON g.id = s.group_id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            json_response(404, false, 'Mata pelajaran tidak ditemukan.');
        }
        json_response(200, true, 'Data mata pelajaran.', $row);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function saveSubject()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    $academicYearId = grad_active_year_id();
    $groupId = isset($input['group_id']) ? (int) $input['group_id'] : 0;
    $kodeMapel = sanitize($input['kode_mapel'] ?? '');
    $namaMapel = sanitize($input['nama_mapel'] ?? '');
    $kelas = normalizeKelasInput($input['kelas'] ?? '');
    $urutan = isset($input['urutan']) ? (int) $input['urutan'] : 0;

    if ($groupId <= 0 || $namaMapel === '') {
        json_response(400, false, 'Kelompok dan nama mata pelajaran wajib diisi.');
    }

    try {
        $stmt = db()->prepare("SELECT * FROM grad_subject_groups WHERE id = ? AND academic_year_id = ?");
        $stmt->execute([$groupId, $academicYearId]);
        $group = $stmt->fetch();
        if (!$group) {
            json_response(404, false, 'Kelompok mapel tidak ditemukan pada tahun ajaran aktif.');
        }

        if ($group['tipe'] === 'pilihan') {
            if ($kelas === '') {
                json_response(400, false, 'Kelas wajib dipilih untuk kelompok mapel pilihan.');
            }
        } else {
            $kelas = null;
        }

        $dupParams = [$academicYearId, $groupId, $namaMapel];
        $dupSql = "SELECT id FROM grad_subjects WHERE academic_year_id = ? AND group_id = ? AND nama_mapel = ?";
        if ($kelas === null) {
            $dupSql .= " AND kelas IS NULL";
        } else {
            $dupSql .= " AND kelas = ?";
            $dupParams[] = $kelas;
        }
        if ($id > 0) {
            $dupSql .= " AND id <> ?";
            $dupParams[] = $id;
        }
        $stmt = db()->prepare($dupSql);
        $stmt->execute($dupParams);
        if ($stmt->fetchColumn()) {
            json_response(400, false, 'Mata pelajaran sudah ada pada kelompok dan kelas tersebut.');
        }

        if ($id > 0) {
            $stmt = db()->prepare("
                UPDATE grad_subjects
                SET group_id = ?, kode_mapel = ?, nama_mapel = ?, kelas = ?, urutan = ?
                WHERE id = ? AND academic_year_id = ?
            ");
            $stmt->execute([$groupId, $kodeMapel, $namaMapel, $kelas, $urutan, $id, $academicYearId]);
            json_response(200, true, 'Mata pelajaran berhasil diperbarui.');
        }

        $stmt = db()->prepare("
            INSERT INTO grad_subjects (academic_year_id, group_id, kode_mapel, nama_mapel, kelas, urutan)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$academicYearId, $groupId, $kodeMapel, $namaMapel, $kelas, $urutan]);
        json_response(201, true, 'Mata pelajaran berhasil ditambahkan.', ['id' => db()->lastInsertId()]);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteSubject()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(405, false, 'Method not allowed.');
    }

    $input = get_input();
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    if ($id <= 0) {
        json_response(400, false, 'ID mata pelajaran tidak valid.');
    }

    try {
        $stmt = db()->prepare("DELETE FROM grad_subjects WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Mata pelajaran berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function listClasses()
{
    try {
        $academicYearId = grad_active_year_id();
        $stmt = db()->prepare("
            SELECT kelas, COUNT(*) as total_siswa
            FROM students
            WHERE academic_year_id = ? AND kelas <> ''
            GROUP BY kelas
            ORDER BY kelas ASC
        ");
        $stmt->execute([$academicYearId]);
        json_response(200, true, 'Data kelas berhasil dimuat.', $stmt->fetchAll());
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function normalizeKelasInput($value)
{
    if (is_array($value)) {
        $items = $value;
    } else {
        $items = explode(',', (string) $value);
    }

    $clean = [];
    foreach ($items as $item) {
        $item = sanitize($item);
        if ($item !== '') {
            $clean[] = $item;
        }
    }

    return implode(',', array_values(array_unique($clean)));
}
