<?php
/**
 * API for managing Hari Libur (Holidays) and calculating effective working days
 */
require_once __DIR__ . '/auth_helper.php';

if (basename($_SERVER['SCRIPT_FILENAME']) === 'hari_libur.php') {
    $user = acad_auth();
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    switch ($action) {
        case 'list':
            listHariLibur();
            break;
        case 'save':
            saveHariLibur($user);
            break;
        case 'delete':
            deleteHariLibur($user);
            break;
        default:
            json_response(400, false, 'Action tidak valid.');
            break;
    }
}

function listHariLibur() {
    try {
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        $stmt = db()->prepare("SELECT * FROM acad_hari_libur WHERE academic_year_id = ? ORDER BY tanggal ASC");
        $stmt->execute([$year_id]);
        $data = $stmt->fetchAll();

        json_response(200, true, 'Data hari libur dimuat.', $data);
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function saveHariLibur($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $tanggal_mulai = $input['tanggal_mulai'] ?? ($input['tanggal'] ?? '');
    $tanggal_akhir = $input['tanggal_akhir'] ?? $tanggal_mulai;
    $keterangan = $input['keterangan'] ?? '';
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if (empty($tanggal_mulai) || empty($keterangan)) {
        json_response(400, false, 'Tanggal dan keterangan wajib diisi.');
    }

    $active_year = get_active_academic_year();
    $year_id = $active_year['id'] ?? 0;

    try {
        if ($id > 0) {
            $stmt = db()->prepare("UPDATE acad_hari_libur SET tanggal = ?, keterangan = ? WHERE id = ? AND academic_year_id = ?");
            $stmt->execute([$tanggal_mulai, $keterangan, $id, $year_id]);
            json_response(200, true, 'Hari libur berhasil diupdate.');
        } else {
            $start_ts = strtotime($tanggal_mulai);
            $end_ts = strtotime($tanggal_akhir);
            if ($start_ts > $end_ts) {
                json_response(400, false, 'Tanggal mulai tidak boleh lebih besar dari tanggal selesai.');
            }

            $stmt = db()->prepare("INSERT IGNORE INTO acad_hari_libur (academic_year_id, tanggal, keterangan) VALUES (?, ?, ?)");
            $current_ts = $start_ts;
            while ($current_ts <= $end_ts) {
                $tgl = date('Y-m-d', $current_ts);
                $stmt->execute([$year_id, $tgl, $keterangan]);
                $current_ts = strtotime('+1 day', $current_ts);
            }
            json_response(200, true, 'Hari libur berhasil ditambahkan.');
        }
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

function deleteHariLibur($user) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(405, false, 'Method not allowed.');

    $input = get_input();
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    if ($id <= 0) json_response(400, false, 'ID tidak valid.');

    try {
        $stmt = db()->prepare("DELETE FROM acad_hari_libur WHERE id = ?");
        $stmt->execute([$id]);
        json_response(200, true, 'Hari libur berhasil dihapus.');
    } catch (PDOException $e) {
        json_response(500, false, 'Server error: ' . $e->getMessage());
    }
}

/**
 * Helper function to calculate effective working days between two dates.
 * Skips Saturdays, Sundays, and dates listed in acad_hari_libur for the current academic year.
 */
function get_hari_efektif($start_date, $end_date) {
    if (empty($start_date) || empty($end_date)) return 0;
    $current = strtotime($start_date);
    $end = strtotime($end_date);
    if (!$current || !$end || $current > $end) return 0;

    $holidays = [];
    try {
        $active_year = get_active_academic_year();
        $year_id = $active_year['id'] ?? 0;

        // Get all holidays in the range
        $stmt = db()->prepare("
            SELECT tanggal FROM acad_hari_libur 
            WHERE academic_year_id = ? 
              AND tanggal BETWEEN ? AND ?
        ");
        $stmt->execute([$year_id, $start_date, $end_date]);
        $holidays = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception $e) {
        // Fallback gracefully if table not yet migrated or query fails
        $holidays = [];
    }

    $effective_days = 0;
    while ($current <= $end) {
        $dayOfWeek = date('N', $current); // 1 (Mon) - 7 (Sun)
        $dateStr = date('Y-m-d', $current);

        // If not weekend (Sat=6, Sun=7) and not in holidays list
        if ($dayOfWeek < 6 && !in_array($dateStr, $holidays)) {
            $effective_days++;
        }

        $current = strtotime('+1 day', $current);
    }

    return $effective_days;
}

