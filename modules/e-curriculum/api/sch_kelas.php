<?php
/**
 * Kelas API for E-Schedule
 */
require_once __DIR__ . '/../../../api/config.php';

$user = auth_check();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $stmt = db()->query("
            SELECT k.*, u.nama_lengkap as wali_nama 
            FROM sch_kelas k 
            LEFT JOIN users u ON k.wali_id = u.id 
            ORDER BY k.rombel ASC, k.nama_kelas ASC
        ");
        json_response(200, true, 'Sukses', $stmt->fetchAll());
        break;

    case 'create':
        $input = get_input();
        try {
            $wali_id = isset($input['wali_id']) && $input['wali_id'] !== '' ? (int)$input['wali_id'] : null;
            $stmt = db()->prepare("INSERT INTO sch_kelas (rombel, nama_kelas, wali_id) VALUES (?, ?, ?)");
            $stmt->execute([$input['rombel'], $input['nama_kelas'], $wali_id]);
            json_response(200, true, 'Data kelas berhasil ditambahkan', ['id' => db()->lastInsertId()]);
        } catch (PDOException $e) {
            json_response(500, false, 'Gagal: ' . $e->getMessage());
        }
        break;

    case 'update':
        $input = get_input();
        try {
            $wali_id = isset($input['wali_id']) && $input['wali_id'] !== '' ? (int)$input['wali_id'] : null;
            $stmt = db()->prepare("UPDATE sch_kelas SET rombel=?, nama_kelas=?, wali_id=? WHERE id=?");
            $stmt->execute([$input['rombel'], $input['nama_kelas'], $wali_id, $input['id']]);
            json_response(200, true, 'Data kelas berhasil diupdate');
        } catch (PDOException $e) {
            json_response(500, false, 'Gagal: ' . $e->getMessage());
        }
        break;

    case 'delete':
        $input = get_input();
        try {
            if (isset($input['ids']) && is_array($input['ids'])) {
                $ids = array_map('intval', $input['ids']);
                $ids = array_filter($ids, function($val) { return $val > 0; });
                if (empty($ids)) json_response(400, false, 'ID tidak valid.');
                $inQuery = implode(',', array_fill(0, count($ids), '?'));
                $stmt = db()->prepare("DELETE FROM sch_kelas WHERE id IN ($inQuery)");
                $stmt->execute($ids);
                json_response(200, true, count($ids) . ' kelas berhasil dihapus.');
            } else {
                $stmt = db()->prepare("DELETE FROM sch_kelas WHERE id=?");
                $stmt->execute([$input['id']]);
                json_response(200, true, 'Data kelas berhasil dihapus');
            }
        } catch (PDOException $e) {
            json_response(500, false, 'Gagal: ' . $e->getMessage());
        }
        break;

    case 'import':
        $input = get_input();
        $data = $input['data'] ?? [];
        if (!is_array($data) || empty($data)) json_response(400, false, 'Data kosong');
        
        try {
            db()->beginTransaction();
            db()->query("DELETE FROM sch_kelas");
            db()->query("ALTER TABLE sch_kelas AUTO_INCREMENT = 1");

            $stmt = db()->prepare("INSERT INTO sch_kelas (rombel, nama_kelas) VALUES (?, ?)");
            foreach ($data as $row) {
                if(!empty($row['Rombel']) && !empty($row['Nama Kelas'])){
                    $stmt->execute([$row['Rombel'], $row['Nama Kelas']]);
                }
            }
            db()->commit();
            json_response(200, true, 'Import data kelas berhasil. Data sebelumnya dihapus.');
        } catch(PDOException $e) {
            db()->rollBack();
            json_response(500, false, 'Gagal import: ' . $e->getMessage());
        }
        break;
    case 'import_portal':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(405, false, 'Method not allowed.');
        }

        try {
            // Get current existing classes to avoid duplicates
            $stmtExist = db()->query("SELECT nama_kelas FROM sch_kelas");
            $existing = $stmtExist->fetchAll(PDO::FETCH_COLUMN);
            $existingLower = array_map('strtolower', $existing);

            // Fetch distinct classes from students table
            $stmtPortal = db()->query("SELECT DISTINCT kelas FROM students WHERE kelas != '' AND kelas IS NOT NULL");
            $portalClasses = $stmtPortal->fetchAll(PDO::FETCH_COLUMN);

            $insertedCount = 0;
            $stmtInsert = db()->prepare("INSERT INTO sch_kelas (rombel, nama_kelas) VALUES (?, ?)");

            foreach ($portalClasses as $kelas) {
                $kelasName = trim($kelas);
                if (in_array(strtolower($kelasName), $existingLower)) continue;

                // Simple parser to extract Tingkat (Rombel)
                $parts = explode(' ', $kelasName);
                $tingkat = isset($parts[0]) ? (int)$parts[0] : 0;
                if ($tingkat == 0) $tingkat = 1; // Default rombel if unable to parse

                $stmtInsert->execute([$tingkat, $kelasName]);
                $insertedCount++;
            }

            if ($insertedCount > 0) {
                json_response(200, true, "$insertedCount kelas baru berhasil diimpor dari E-Portal.");
            } else {
                json_response(200, true, 'Tidak ada kelas baru untuk diimpor. Semua kelas sudah sinkron.');
            }
        } catch (PDOException $e) {
            json_response(500, false, 'Server error: ' . $e->getMessage());
        }
        break;

    default:
        json_response(400, false, 'Invalid action');
}
