<?php
/**
 * Jadwal API for E-Schedule
 * Engine Pembuat Jadwal Pelajaran (Scheduler)
 */
require_once __DIR__ . '/../../../api/config.php';

$user = auth_check();
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

switch ($action) {
    case 'list':
        $kelas_id = $_GET['kelas_id'] ?? null;
        
        $where = "";
        if ($kelas_id) $where = "WHERE j.kelas_id = " . (int)$kelas_id;
        
        $query = "
            SELECT j.*, 
                   k.nama_kelas,
                   jb.hari, jb.jam_ke, jb.tipe, jb.nama_jam,
                   d.guru_id, d.mapel_id,
                   g.nama_guru, g.kode_guru, g.singkatan,
                   m.nama_mapel, m.kode_mapel
            FROM sch_jadwal j
            JOIN sch_kelas k ON j.kelas_id = k.id
            JOIN sch_jam_belajar jb ON j.jam_belajar_id = jb.id
            JOIN sch_distribusi d ON j.distribusi_id = d.id
            JOIN sch_guru g ON d.guru_id = g.id
            JOIN sch_mapel m ON d.mapel_id = m.id
            $where
            ORDER BY k.rombel, k.nama_kelas, 
            CASE jb.hari
                WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3
                WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 ELSE 7 END, 
            jb.jam_ke ASC
        ";
        $stmt = db()->query($query);
        json_response(200, true, 'Sukses', $stmt->fetchAll());
        break;

    case 'stats':
        // Cek kesiapan data
        $tKelas = db()->query("SELECT COUNT(*) FROM sch_kelas")->fetchColumn();
        $tGuru = db()->query("SELECT COUNT(*) FROM sch_guru")->fetchColumn();
        $tDist = db()->query("SELECT COUNT(*) FROM sch_distribusi")->fetchColumn();
        $tJam = db()->query("SELECT COUNT(*) FROM sch_jam_belajar WHERE tipe='Pembelajaran'")->fetchColumn();
        
        $dist = db()->query("SELECT SUM(jp) as total_jp FROM sch_distribusi")->fetchColumn();
        
        json_response(200, true, 'Info Stats', [
            'total_kelas' => $tKelas,
            'total_guru' => $tGuru,
            'total_distribusi' => $tDist,
            'slot_belajar_minimum_per_kelas' => $tJam,
            'total_kebutuhan_jp_sekolah' => $dist ?: 0
        ]);
        break;

    case 'generate':
        set_time_limit(0); // Prevent timeout for long algorithm
        ignore_user_abort(true); // Don't stop if browser disconnects
        
        // The Engine
        try {
            // 1. Bersihkan jadwal lama
            db()->query("DELETE FROM sch_jadwal");
            db()->query("ALTER TABLE sch_jadwal AUTO_INCREMENT = 1");

            // Mulai transaksi setelah ALTER TABLE karena ALTER TABLE menyebabkan implicit commit
            db()->beginTransaction();

            // 2. Load Environment
            $jamRows = db()->query("SELECT * FROM sch_jam_belajar ORDER BY CASE hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 WHEN 'Sabtu' THEN 6 ELSE 7 END, jam_ke ASC")->fetchAll();
            $kelasRows = db()->query("SELECT id FROM sch_kelas")->fetchAll();
            $guruRows = db()->query("SELECT id FROM sch_guru")->fetchAll();
            
            $distribusiRows = db()->query("
                SELECT d.*, m.nama_mapel 
                FROM sch_distribusi d 
                JOIN sch_mapel m ON d.mapel_id = m.id 
                ORDER BY d.jp DESC
            ")->fetchAll();
            
            $kesediaanRows = db()->query("SELECT guru_id, jam_belajar_id FROM sch_kesediaan")->fetchAll();
            
            // Map Kesediaan (guru -> jam_ids)
            $kesediaanMap = [];
            foreach($kesediaanRows as $kr) {
                $kesediaanMap[$kr['guru_id']][$kr['jam_belajar_id']] = true;
            }

            // Prepare memory structures
            $schedule = []; // schedule[kelas_id][jam_id] = distribusi_id
            $guruBusy = []; // guruBusy[guru_id][jam_id] = true

            // Group jam by hari for block searching rules
            $jamByHari = [];
            $jamList = []; // flat ordered list
            foreach($jamRows as $j) {
                $jamByHari[$j['hari']][] = $j;
                $jamList[$j['id']] = $j;
            }

            // Placing blocks solver - Heuristic Iteration
            // Since this is a simple heuristic, we loop through assignments and try to place them
            $unplaced = [];
            $stmtInsert = db()->prepare("INSERT INTO sch_jadwal (kelas_id, jam_belajar_id, distribusi_id) VALUES (?, ?, ?)");

            foreach ($distribusiRows as $dist) {
                $kId = $dist['kelas_id'];
                $gId = $dist['guru_id'];
                $dId = $dist['id'];
                $jp = (int)$dist['jp'];
                $mapelStr = strtolower($dist['nama_mapel']);

                // Rule Breakdown
                $blocks = [];
                if ($jp == 1) $blocks = [1];
                elseif ($jp == 2) $blocks = [2];
                elseif ($jp == 3) $blocks = [3]; // Prefer 3, fallback 2+1 (simplified to 3 for heuristic)
                elseif ($jp == 4) $blocks = [2, 2];
                elseif ($jp == 5) $blocks = [3, 2];
                else {
                    // For anything > 5, split into 2s and 1s
                    $rem = $jp;
                    while($rem > 0) {
                        if ($rem >= 2) { $blocks[] = 2; $rem -= 2; }
                        else { $blocks[] = 1; $rem -= 1; }
                    }
                }

                // Place each block
                foreach ($blocks as $blockSize) {
                    $placed = false;
                    
                    // Shuffle hari to ensure randomness in schedule
                    $haris = array_keys($jamByHari);
                    shuffle($haris);

                    foreach ($haris as $hari) {
                        if ($placed) break;
                        
                        $hariJams = $jamByHari[$hari];
                        
                        // Find consecutive slots of length $blockSize
                        for ($i = 0; $i <= count($hariJams) - $blockSize; $i++) {
                            $canPlace = true;
                            $candidateSlots = [];

                            for ($step = 0; $step < $blockSize; $step++) {
                                $slot = $hariJams[$i + $step];
                                
                                // Check tipe
                                if ($slot['tipe'] !== 'Pembelajaran') { $canPlace = false; break; }
                                
                                // Check overlap kelas
                                if (isset($schedule[$kId][$slot['id']])) { $canPlace = false; break; }
                                
                                // Check guru busy
                                if (isset($guruBusy[$gId][$slot['id']])) { $canPlace = false; break; }
                                
                                // Check guru kesediaan (if kesediaan is strict)
                                // If Kesediaan is empty, assume they are free. Else must exist:
                                if (!empty($kesediaanMap[$gId])) {
                                    if (!isset($kesediaanMap[$gId][$slot['id']])) { $canPlace = false; break; }
                                }

                                // PJOK Rule: Check if previous slot or next slot across this block is Istirahat.
                                // Simplification: Ensure no 'Istirahat' divides the block (already checked by tipe='Pembelajaran').
                                
                                $candidateSlots[] = $slot['id'];
                            }

                            if ($canPlace) {
                                // Place it!
                                foreach ($candidateSlots as $cSlotId) {
                                    $schedule[$kId][$cSlotId] = $dId;
                                    $guruBusy[$gId][$cSlotId] = true;
                                    $stmtInsert->execute([$kId, $cSlotId, $dId]);
                                }
                                $placed = true;
                                break;
                            }
                        }
                    }
                    if (!$placed) {
                        $unplaced[] = ['distribusi_id' => $dId, 'block' => $blockSize];
                    }
                }
            }
            
            db()->commit();
            
            if (count($unplaced) > 0) {
                json_response(200, true, 'Jadwal di-generate sebagian, ada konflik / kekurangan slot jam.', [
                    'unplaced_blocks' => count($unplaced)
                ]);
            } else {
                json_response(200, true, 'Jadwal berhasil di-generate secara utuh 100%.');
            }

        } catch (Exception $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            json_response(500, false, 'Gagal Generate: ' . $e->getMessage());
        }
        break;

    default:
        json_response(400, false, 'Invalid action');
}
