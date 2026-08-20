<?php
/**
 * Jam Belajar API for E-Schedule
 */
require_once __DIR__ . '/../../../api/config.php';

$user = auth_check();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        // Custom order so Hari is sorted logically
        $stmt = db()->query("
            SELECT * FROM sch_jam_belajar 
            ORDER BY 
            CASE hari
                WHEN 'Senin' THEN 1
                WHEN 'Selasa' THEN 2
                WHEN 'Rabu' THEN 3
                WHEN 'Kamis' THEN 4
                WHEN 'Jumat' THEN 5
                WHEN 'Sabtu' THEN 6
                ELSE 7
            END ASC, jam_ke ASC
        ");
        json_response(200, true, 'Sukses', $stmt->fetchAll());
        break;

    case 'create':
        $input = get_input();
        try {
            $stmt = db()->prepare("INSERT INTO sch_jam_belajar (hari, jam_ke, tipe, nama_jam) VALUES (?, ?, ?, ?)");
            $stmt->execute([$input['hari'], $input['jam_ke'], $input['tipe'], $input['nama_jam']]);
            json_response(200, true, 'Data jam belajar berhasil ditambahkan', ['id' => db()->lastInsertId()]);
        } catch (PDOException $e) {
            json_response(500, false, 'Gagal: ' . $e->getMessage());
        }
        break;

    case 'update':
        $input = get_input();
        try {
            $stmt = db()->prepare("UPDATE sch_jam_belajar SET hari=?, jam_ke=?, tipe=?, nama_jam=? WHERE id=?");
            $stmt->execute([$input['hari'], $input['jam_ke'], $input['tipe'], $input['nama_jam'], $input['id']]);
            json_response(200, true, 'Data jam belajar berhasil diupdate');
        } catch (PDOException $e) {
            json_response(500, false, 'Gagal: ' . $e->getMessage());
        }
        break;

    case 'delete':
        $input = get_input();
        try {
            $stmt = db()->prepare("DELETE FROM sch_jam_belajar WHERE id=?");
            $stmt->execute([$input['id']]);
            json_response(200, true, 'Data jam belajar berhasil dihapus');
        } catch (PDOException $e) {
            json_response(500, false, 'Gagal: ' . $e->getMessage());
        }
        break;
        
    case 'copy':
        $input = get_input();
        $source = $input['source_hari'] ?? '';
        $target = $input['target_hari'] ?? '';
        
        if (empty($source) || empty($target)) json_response(400, false, 'Parameter tidak lengkap');
        
        try {
            db()->beginTransaction();
            // Delete target explicitly first before overriding
            $stmt = db()->prepare("DELETE FROM sch_jam_belajar WHERE hari=?");
            $stmt->execute([$target]);
            
            // Get source data
            $stmt = db()->prepare("SELECT jam_ke, tipe, nama_jam FROM sch_jam_belajar WHERE hari=?");
            $stmt->execute([$source]);
            $sources = $stmt->fetchAll();
            
            $stmt = db()->prepare("INSERT INTO sch_jam_belajar (hari, jam_ke, tipe, nama_jam) VALUES (?, ?, ?, ?)");
            foreach($sources as $s) {
                $stmt->execute([$target, $s['jam_ke'], $s['tipe'], $s['nama_jam']]);
            }
            db()->commit();
            json_response(200, true, "Berhasil disalin dari {$source} ke {$target}");
        } catch(PDOException $e) {
            db()->rollBack();
            json_response(500, false, 'Gagal copy: ' . $e->getMessage());
        }
        break;

    case 'import':
        $input = get_input();
        $data = $input['data'] ?? [];
        if (!is_array($data) || empty($data)) json_response(400, false, 'Data kosong');
        
        try {
            db()->beginTransaction();
            db()->query("DELETE FROM sch_jam_belajar");
            db()->query("ALTER TABLE sch_jam_belajar AUTO_INCREMENT = 1");

            $stmt = db()->prepare("INSERT INTO sch_jam_belajar (hari, jam_ke, tipe, nama_jam) VALUES (?, ?, ?, ?)");
            foreach ($data as $row) {
                if(!empty($row['Hari']) && isset($row['Jam'])){
                    $tipe = $row['Tipe'] ?? 'Pembelajaran';
                    $nama = $row['Nama Jam'] ?? $tipe;
                    $stmt->execute([$row['Hari'], $row['Jam'], $tipe, $nama]);
                }
            }
            db()->commit();
            json_response(200, true, 'Import data jam belajar berhasil. Data sebelumnya dihapus.');
        } catch(PDOException $e) {
            db()->rollBack();
            json_response(500, false, 'Gagal import: ' . $e->getMessage());
        }
        break;

    default:
        json_response(400, false, 'Invalid action');
}
