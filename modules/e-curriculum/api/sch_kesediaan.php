<?php
/**
 * Kesediaan API for E-Schedule
 */
require_once __DIR__ . '/../../../api/config.php';

$user = auth_check();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        // Get all Guru and their checked Jam IDs
        $guruList = db()->query("SELECT id, kode_guru, nama_guru FROM sch_guru ORDER BY nama_guru ASC")->fetchAll();
        $kesediaan = db()->query("SELECT guru_id, jam_belajar_id FROM sch_kesediaan")->fetchAll();
        
        // Group by guru_id
        $map = [];
        foreach($kesediaan as $k) {
            $map[$k['guru_id']][] = $k['jam_belajar_id'];
        }
        
        $result = [];
        foreach($guruList as $g) {
            $result[] = [
                'id' => $g['id'],
                'kode_guru' => $g['kode_guru'],
                'nama_guru' => $g['nama_guru'],
                'jam_ids' => isset($map[$g['id']]) ? $map[$g['id']] : []
            ];
        }
        json_response(200, true, 'Sukses', $result);
        break;

    case 'save':
        $input = get_input();
        $guru_id = $input['guru_id'] ?? null;
        $jam_ids = $input['jam_ids'] ?? []; // Array of jam_belajar_id
        
        if (!$guru_id) json_response(400, false, 'ID Guru diperlukan');
        
        try {
            db()->beginTransaction();
            // Clear current kesediaan for this guru
            $stmt = db()->prepare("DELETE FROM sch_kesediaan WHERE guru_id = ?");
            $stmt->execute([$guru_id]);
            
            // Insert new ones
            if (!empty($jam_ids)) {
                $stmt = db()->prepare("INSERT INTO sch_kesediaan (guru_id, jam_belajar_id) VALUES (?, ?)");
                foreach($jam_ids as $jid) {
                    $stmt->execute([$guru_id, $jid]);
                }
            }
            db()->commit();
            json_response(200, true, 'Kesediaan guru berhasil disimpan');
        } catch (PDOException $e) {
            db()->rollBack();
            json_response(500, false, 'Gagal: ' . $e->getMessage());
        }
        break;

    case 'import':
        $input = get_input();
        $data = $input['data'] ?? [];
        if (!is_array($data) || empty($data)) json_response(400, false, 'Data kosong');
        
        try {
            db()->beginTransaction();
            db()->query("DELETE FROM sch_kesediaan"); // Clear all previous kesediaan data as requested
            db()->query("ALTER TABLE sch_kesediaan AUTO_INCREMENT = 1");

            // Lookups
            $guruMap = [];
            foreach(db()->query("SELECT id, kode_guru FROM sch_guru")->fetchAll() as $g) { $guruMap[$g['kode_guru']] = $g['id']; }
            
            // Get all Jam Pembelajaran mapped by hari
            // The instruction says "artinya guru dengan kode SAY, memilih hari senin s.d kamis dan akan ter-checkbox otomatis semua jam" (only Tipe Pembelajaran usually?)
            // We'll map all Jam Belajar
            $jamMap = [];
            foreach(db()->query("SELECT id, LOWER(hari) as hari FROM sch_jam_belajar WHERE tipe = 'Pembelajaran'")->fetchAll() as $j) { 
                $jamMap[$j['hari']][] = $j['id']; 
            }

            $stmt = db()->prepare("INSERT IGNORE INTO sch_kesediaan (guru_id, jam_belajar_id) VALUES (?, ?)");
            foreach ($data as $row) {
                // Example column: Kode Guru, Hari
                $gId = $guruMap[$row['Kode Guru']] ?? null;
                if ($gId && !empty($row['Hari'])) {
                    // Hari can be a single word or comma-separated if we want to support flexible imports or just specific fields 'senin', 'selasa' etc.
                    // Based on instruction: "SAY|senin|selasa|rabu|kamis|" -> maybe the row has multiple hari columns or a comma separated string
                    // Let's iterate all keys in the row looking for day names
                    $daysProvided = [];
                    foreach($row as $key => $val) {
                        if(strtolower($key) !== 'kode guru' && !empty($val)) {
                            // The cell might contain a 'v' or 'yes' or be the day name itself. For safety, if column matches a day name conceptually:
                            $dayRaw = strtolower(trim($val));
                            if(isset($jamMap[$dayRaw])) {
                                $daysProvided[] = $dayRaw;
                            } else if (isset($jamMap[strtolower($key)])) {
                                // if Header is the day name and val is 'yes' or something
                                $daysProvided[] = strtolower($key);
                            }
                        }
                    }
                    
                    // Insert for each identified day
                    foreach($daysProvided as $d) {
                        foreach($jamMap[$d] as $jId) {
                            $stmt->execute([$gId, $jId]);
                        }
                    }
                }
            }
            db()->commit();
            json_response(200, true, 'Import kesediaan berhasil. Data lama dihapus.');
        } catch(PDOException $e) {
            db()->rollBack();
            json_response(500, false, 'Gagal import: ' . $e->getMessage());
        }
        break;

    default:
        json_response(400, false, 'Invalid action');
}
