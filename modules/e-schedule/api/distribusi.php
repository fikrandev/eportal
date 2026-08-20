<?php
/**
 * Distribusi API for E-Schedule
 */
require_once __DIR__ . '/../../../api/config.php';

$user = auth_check();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $query = "
            SELECT d.*, 
                   g.nama_guru, g.kode_guru,
                   k.rombel, k.nama_kelas,
                   m.nama_mapel, m.kode_mapel
            FROM sch_distribusi d
            JOIN sch_guru g ON d.guru_id = g.id
            JOIN sch_kelas k ON d.kelas_id = k.id
            JOIN sch_mapel m ON d.mapel_id = m.id
            ORDER BY k.rombel ASC, k.nama_kelas ASC, g.nama_guru ASC
        ";
        $stmt = db()->query($query);
        json_response(200, true, 'Sukses', $stmt->fetchAll());
        break;

    case 'create':
        $input = get_input();
        try {
            $stmt = db()->prepare("INSERT INTO sch_distribusi (guru_id, kelas_id, mapel_id, jp) VALUES (?, ?, ?, ?)");
            $stmt->execute([$input['guru_id'], $input['kelas_id'], $input['mapel_id'], $input['jp']]);
            json_response(200, true, 'Data distribusi berhasil ditambahkan', ['id' => db()->lastInsertId()]);
        } catch (PDOException $e) {
            json_response(500, false, 'Gagal: ' . $e->getMessage());
        }
        break;

    case 'update':
        $input = get_input();
        try {
            $stmt = db()->prepare("UPDATE sch_distribusi SET guru_id=?, kelas_id=?, mapel_id=?, jp=? WHERE id=?");
            $stmt->execute([$input['guru_id'], $input['kelas_id'], $input['mapel_id'], $input['jp'], $input['id']]);
            json_response(200, true, 'Data distribusi berhasil diupdate');
        } catch (PDOException $e) {
            json_response(500, false, 'Gagal: ' . $e->getMessage());
        }
        break;

    case 'delete':
        $input = get_input();
        try {
            $stmt = db()->prepare("DELETE FROM sch_distribusi WHERE id=?");
            $stmt->execute([$input['id']]);
            json_response(200, true, 'Data distribusi berhasil dihapus');
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
            db()->query("DELETE FROM sch_distribusi");
            db()->query("ALTER TABLE sch_distribusi AUTO_INCREMENT = 1");

            // Build lookup maps for faster relation finding
            $guruMap = [];
            foreach(db()->query("SELECT id, kode_guru FROM sch_guru")->fetchAll() as $g) { $guruMap[$g['kode_guru']] = $g['id']; }
            
            $kelasMap = [];
            foreach(db()->query("SELECT id, nama_kelas FROM sch_kelas")->fetchAll() as $k) { $kelasMap[$k['nama_kelas']] = $k['id']; }
            
            $mapelMap = [];
            foreach(db()->query("SELECT id, kode_mapel FROM sch_mapel")->fetchAll() as $m) { $mapelMap[$m['kode_mapel']] = $m['id']; }

            $stmt = db()->prepare("INSERT INTO sch_distribusi (guru_id, kelas_id, mapel_id, jp) VALUES (?, ?, ?, ?)");
            foreach ($data as $row) {
                // Expected header: Kode Guru, Nama Kelas, Kode Mapel, JP
                $gId = $guruMap[$row['Kode Guru']] ?? null;
                $kId = $kelasMap[$row['Nama Kelas']] ?? null;
                $mId = $mapelMap[$row['Kode Mapel']] ?? null;
                $jp = (int)($row['JP'] ?? 0);
                
                if($gId && $kId && $mId && $jp > 0) {
                    $stmt->execute([$gId, $kId, $mId, $jp]);
                }
            }
            db()->commit();
            json_response(200, true, 'Import data distribusi berhasil. Data lama dihapus.');
        } catch(PDOException $e) {
            db()->rollBack();
            json_response(500, false, 'Gagal import: ' . $e->getMessage());
        }
        break;

    default:
        json_response(400, false, 'Invalid action');
}
