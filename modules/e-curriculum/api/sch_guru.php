<?php
/**
 * Guru API for E-Schedule
 */
require_once __DIR__ . '/../../../api/config.php';

$user = auth_check();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

function generateKodeGuru($nama) {
    // Hilangkan huruf vokal dan spasi, ambil maksimal 4 karakter pertama
    $consonants = preg_replace('/[AEIOUaeiou\s]/', '', strtoupper(trim($nama)));
    $kode = substr($consonants, 0, 4);
    return empty($kode) ? substr(strtoupper(trim($nama)), 0, 4) : $kode;
}

switch ($action) {
    case 'list':
        $stmt = db()->query("
            SELECT g.*, u.tupoksi 
            FROM sch_guru g
            LEFT JOIN users u ON g.kode_guru = u.username
            ORDER BY g.nama_guru ASC
        ");
        json_response(200, true, 'Sukses', $stmt->fetchAll());
        break;

    case 'list_tupoksi_guru':
        $stmt = db()->query("SELECT DISTINCT tupoksi FROM users WHERE role = 'guru' AND status = 1 AND tupoksi IS NOT NULL AND tupoksi != ''");
        json_response(200, true, 'Sukses', $stmt->fetchAll(PDO::FETCH_COLUMN));
        break;

    case 'create':
        $input = get_input();
        try {
            $stmt = db()->prepare("INSERT INTO sch_guru (kode_guru, nama_guru) VALUES (?, ?)");
            $stmt->execute([$input['kode_guru'], $input['nama_guru']]);
            json_response(200, true, 'Data guru berhasil ditambahkan', ['id' => db()->lastInsertId()]);
        } catch (PDOException $e) {
            json_response(500, false, 'Gagal: ' . $e->getMessage());
        }
        break;

    case 'update':
        $input = get_input();
        try {
            $stmt = db()->prepare("UPDATE sch_guru SET kode_guru=?, nama_guru=? WHERE id=?");
            $stmt->execute([strtoupper($input['kode_guru']), $input['nama_guru'], $input['id']]);
            json_response(200, true, 'Data guru berhasil diupdate');
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
                $stmt = db()->prepare("DELETE FROM sch_guru WHERE id IN ($inQuery)");
                $stmt->execute($ids);
                json_response(200, true, count($ids) . ' guru berhasil dihapus.');
            } else {
                $stmt = db()->prepare("DELETE FROM sch_guru WHERE id=?");
                $stmt->execute([$input['id']]);
                json_response(200, true, 'Data guru berhasil dihapus');
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
            // Menghapus data sebelumnya sesuai aturan: "Setiap dilakukan import, akan menghapus data yang telah ada sebelumnya"
            db()->query("DELETE FROM sch_guru");
            // Reset AUTO_INCREMENT (optional but good for clean imports)
            db()->query("ALTER TABLE sch_guru AUTO_INCREMENT = 1");

            $stmt = db()->prepare("INSERT INTO sch_guru (kode_guru, nama_guru) VALUES (?, ?)");
            foreach ($data as $row) {
                // Asumsi kolom: Kode Guru, Nama Guru
                if(!empty($row['Kode Guru']) && !empty($row['Nama Guru'])){
                    $stmt->execute([$row['Kode Guru'], $row['Nama Guru']]);
                }
            }
            db()->commit();
            json_response(200, true, 'Import data guru berhasil. Data sebelumnya telah dihapus.');
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
            // Get existing kode_guru to avoid duplicates
            $stmtExist = db()->query("SELECT kode_guru FROM sch_guru");
            $existing = $stmtExist->fetchAll(PDO::FETCH_COLUMN);
            $existingLower = array_map('strtolower', $existing);

            $tupoksi = $_GET['tupoksi'] ?? '';
            $whereTupoksi = "";
            $params = [];
            if ($tupoksi) {
                $tupoksiArray = explode(',', $tupoksi);
                $placeholders = implode(',', array_fill(0, count($tupoksiArray), '?'));
                $whereTupoksi = " AND tupoksi IN ($placeholders)";
                $params = $tupoksiArray;
            }

            // Fetch users with role = 'guru'
            $stmtPortal = db()->prepare("SELECT username, kode_guru AS singkatan, nama_lengkap FROM users WHERE role = 'guru' AND status = 1 $whereTupoksi");
            $stmtPortal->execute($params);
            $portalGurus = $stmtPortal->fetchAll();

            $insertedCount = 0;
            $updatedCount = 0;
            
            $stmtInsert = db()->prepare("
                INSERT INTO sch_guru (kode_guru, singkatan, nama_guru) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE singkatan = VALUES(singkatan), nama_guru = VALUES(nama_guru)
            ");

            foreach ($portalGurus as $g) {
                $singkatan = $g['singkatan'];
                if (empty($singkatan)) {
                    $singkatan = generateKodeGuru($g['nama_lengkap']);
                }
                
                $isNew = !in_array(strtolower($g['username']), $existingLower);
                
                $stmtInsert->execute([$g['username'], $singkatan, $g['nama_lengkap']]);
                
                if ($isNew) $insertedCount++;
                else $updatedCount++;
            }

            if ($insertedCount > 0 || $updatedCount > 0) {
                json_response(200, true, "Sinkronisasi selesai. $insertedCount data baru ditambahkan, $updatedCount data diupdate.");
            } else {
                json_response(200, true, 'Tidak ada data guru untuk disinkronkan.');
            }
        } catch (PDOException $e) {
            json_response(500, false, 'Server error: ' . $e->getMessage());
        }
        break;

    default:
        json_response(400, false, 'Invalid action');
}
