<?php
/**
 * Guru API for E-Schedule
 */
require_once __DIR__ . '/../../../api/config.php';

$user = auth_check();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $stmt = db()->query("SELECT * FROM sch_guru ORDER BY nama_guru ASC");
        json_response(200, true, 'Sukses', $stmt->fetchAll());
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
            $stmt->execute([$input['kode_guru'], $input['nama_guru'], $input['id']]);
            json_response(200, true, 'Data guru berhasil diupdate');
        } catch (PDOException $e) {
            json_response(500, false, 'Gagal: ' . $e->getMessage());
        }
        break;

    case 'delete':
        $input = get_input();
        try {
            $stmt = db()->prepare("DELETE FROM sch_guru WHERE id=?");
            $stmt->execute([$input['id']]);
            json_response(200, true, 'Data guru berhasil dihapus');
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

    default:
        json_response(400, false, 'Invalid action');
}
