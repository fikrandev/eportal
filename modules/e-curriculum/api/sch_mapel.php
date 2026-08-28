<?php
/**
 * Mapel API for E-Schedule
 */
require_once __DIR__ . '/../../../api/config.php';

$user = auth_check();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $stmt = db()->query("SELECT * FROM sch_mapel ORDER BY nama_mapel ASC");
        json_response(200, true, 'Sukses', $stmt->fetchAll());
        break;

    case 'create':
        $input = get_input();
        try {
            $stmt = db()->prepare("INSERT INTO sch_mapel (kode_mapel, nama_mapel) VALUES (?, ?)");
            $stmt->execute([$input['kode_mapel'], $input['nama_mapel']]);
            json_response(200, true, 'Data mapel berhasil ditambahkan', ['id' => db()->lastInsertId()]);
        } catch (PDOException $e) {
            json_response(500, false, 'Gagal: ' . $e->getMessage());
        }
        break;

    case 'update':
        $input = get_input();
        try {
            $stmt = db()->prepare("UPDATE sch_mapel SET kode_mapel=?, nama_mapel=? WHERE id=?");
            $stmt->execute([$input['kode_mapel'], $input['nama_mapel'], $input['id']]);
            json_response(200, true, 'Data mapel berhasil diupdate');
        } catch (PDOException $e) {
            json_response(500, false, 'Gagal: ' . $e->getMessage());
        }
        break;

    case 'delete':
        $input = get_input();
        try {
            $stmt = db()->prepare("DELETE FROM sch_mapel WHERE id=?");
            $stmt->execute([$input['id']]);
            json_response(200, true, 'Data mapel berhasil dihapus');
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
            db()->query("DELETE FROM sch_mapel");
            db()->query("ALTER TABLE sch_mapel AUTO_INCREMENT = 1");

            $stmt = db()->prepare("INSERT INTO sch_mapel (kode_mapel, nama_mapel) VALUES (?, ?)");
            foreach ($data as $row) {
                if(!empty($row['Kode Mapel']) && !empty($row['Nama Mapel'])){
                    $stmt->execute([$row['Kode Mapel'], $row['Nama Mapel']]);
                }
            }
            db()->commit();
            json_response(200, true, 'Import data mapel berhasil. Data sebelumnya dihapus.');
        } catch(PDOException $e) {
            db()->rollBack();
            json_response(500, false, 'Gagal import: ' . $e->getMessage());
        }
        break;

    default:
        json_response(400, false, 'Invalid action');
}
