<?php
/**
 * Kelas API for E-Schedule
 */
require_once __DIR__ . '/../../../api/config.php';

$user = auth_check();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $stmt = db()->query("SELECT * FROM sch_kelas ORDER BY rombel ASC, nama_kelas ASC");
        json_response(200, true, 'Sukses', $stmt->fetchAll());
        break;

    case 'create':
        $input = get_input();
        try {
            $stmt = db()->prepare("INSERT INTO sch_kelas (rombel, nama_kelas) VALUES (?, ?)");
            $stmt->execute([$input['rombel'], $input['nama_kelas']]);
            json_response(200, true, 'Data kelas berhasil ditambahkan', ['id' => db()->lastInsertId()]);
        } catch (PDOException $e) {
            json_response(500, false, 'Gagal: ' . $e->getMessage());
        }
        break;

    case 'update':
        $input = get_input();
        try {
            $stmt = db()->prepare("UPDATE sch_kelas SET rombel=?, nama_kelas=? WHERE id=?");
            $stmt->execute([$input['rombel'], $input['nama_kelas'], $input['id']]);
            json_response(200, true, 'Data kelas berhasil diupdate');
        } catch (PDOException $e) {
            json_response(500, false, 'Gagal: ' . $e->getMessage());
        }
        break;

    case 'delete':
        $input = get_input();
        try {
            $stmt = db()->prepare("DELETE FROM sch_kelas WHERE id=?");
            $stmt->execute([$input['id']]);
            json_response(200, true, 'Data kelas berhasil dihapus');
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

    default:
        json_response(400, false, 'Invalid action');
}
